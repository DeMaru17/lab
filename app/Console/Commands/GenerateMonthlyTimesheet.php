<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\User;
// use App\Models\Vendor; // Tidak terpakai langsung di sini tapi mungkin di helper getUserPeriod
use App\Models\Holiday;
use App\Models\MonthlyTimesheet;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyTimesheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timesheet:generate-monthly
                            {--month= : Bulan (1-12) untuk diproses. Jika kosong, akan memproses bulan lalu.}
                            {--year= : Tahun (YYYY) untuk diproses. Jika kosong, akan memproses bulan lalu.}
                            {--group=* : Proses hanya untuk grup user tertentu (internal, csi, tdp). Wajib jika --user_id tidak diisi.}
                            {--user_id=* : Proses hanya untuk User ID tertentu. Jika diisi, --group diabaikan untuk filter user.}
                            {--force : Paksa proses ulang semua timesheet yang cocok (termasuk yang bukan rejected) dan reset status ke generated.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate or re-evaluate monthly timesheet summary for employees. Re-evaluates rejected timesheets by default.';

    /**
     * Map nama grup ke nama vendor di database.
     * Sesuaikan nama vendor ini dengan yang ada di tabel `vendors` Anda.
     */
    protected $vendorGroupMap = [
        'internal' => null, // Untuk karyawan internal (vendor_id IS NULL)
        'csi' => 'PT Cakra Satya Internusa',
        'tdp' => 'PT Trans Dana Profitri',
        // Tambahkan vendor lain jika perlu
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $monthInput = $this->option('month');
        $yearInput = $this->option('year');
        $forceGenerate = $this->option('force');
        $groupIdsInput = $this->option('group');
        $userIdsInput = $this->option('user_id');

        // --- Tentukan Periode ---
        if ($monthInput && $yearInput) {
            if ($monthInput < 1 || $monthInput > 12 || !ctype_digit((string)$yearInput) || $yearInput < 2000 || $yearInput > Carbon::now()->year + 5) {
                $this->error('Input bulan (1-12) atau tahun (YYYY) tidak valid.');
                Log::error("GenerateMonthlyTimesheet: Invalid month/year input. Month: {$monthInput}, Year: {$yearInput}");
                return Command::FAILURE;
            }
            $targetDate = Carbon::createFromDate($yearInput, $monthInput, 1)->startOfMonth();
            $this->info("Menargetkan periode berdasarkan input: " . $targetDate->format('F Y'));
        } else {
            $targetDate = Carbon::now()->subMonthNoOverflow()->startOfMonth(); // Default ke bulan lalu
            $this->warn("PERINGATAN: Menjalankan tanpa --month/--year, menggunakan default bulan lalu: " . $targetDate->format('F Y'));
        }
        Log::info("GenerateMonthlyTimesheet: Processing for target month: " . $targetDate->format('F Y'));


        // --- Ambil User yang Akan Diproses ---
        $queryUsers = User::whereIn('role', ['personil', 'admin']) // Hanya proses role yg relevan
            ->whereNotNull('tanggal_mulai_bekerja')
            ->with('vendor:id,name'); // Eager load vendor untuk efisiensi

        if (!empty($userIdsInput)) {
            $queryUsers->whereIn('id', $userIdsInput);
            $this->info("Memproses HANYA untuk User ID: " . implode(', ', $userIdsInput));
            Log::info("GenerateMonthlyTimesheet: Filtering by specific User IDs: " . implode(', ', $userIdsInput));
        } elseif (!empty($groupIdsInput)) {
            $this->info("Memproses untuk grup: " . implode(', ', $groupIdsInput));
            Log::info("GenerateMonthlyTimesheet: Filtering by groups: " . implode(', ', $groupIdsInput));
            $queryUsers->where(function ($query) use ($groupIdsInput) {
                $hasInternal = in_array('internal', $groupIdsInput);
                $vendorNamesToInclude = [];
                foreach ($groupIdsInput as $group) {
                    if ($group !== 'internal' && isset($this->vendorGroupMap[$group])) {
                        $vendorNamesToInclude[] = $this->vendorGroupMap[$group];
                    }
                }

                if ($hasInternal) {
                    $query->whereNull('vendor_id');
                }
                if (!empty($vendorNamesToInclude)) {
                    $query->orWhereHas('vendor', function ($vendorQuery) use ($vendorNamesToInclude) {
                        $vendorQuery->whereIn('name', $vendorNamesToInclude);
                    });
                }
                // Jika tidak ada grup valid yang cocok, query ini mungkin tidak menghasilkan apa-apa (yang diharapkan)
                if (!$hasInternal && empty($vendorNamesToInclude)) {
                    $this->warn("Tidak ada pemetaan vendor yang valid untuk grup yang diberikan (selain 'internal'). Tidak ada user vendor yang akan diproses.");
                    $query->whereRaw('1 = 0'); // Pastikan tidak ada user terpilih jika grup tidak valid
                }
            });
        } else {
            $this->error('Kesalahan: Opsi --group atau --user_id wajib diisi.');
            Log::error("GenerateMonthlyTimesheet: Neither --group nor --user_id provided.");
            return Command::FAILURE;
        }

        $usersToProcess = $queryUsers->get();

        if ($usersToProcess->isEmpty()) {
            $this->warn('Tidak ada user yang memenuhi kriteria untuk diproses.');
            Log::warning("GenerateMonthlyTimesheet: No eligible users found for the given criteria.");
            return Command::SUCCESS;
        }
        $this->info("Ditemukan " . $usersToProcess->count() . " user yang akan diproses.");

        // --- Ambil Data Hari Libur untuk Periode Target ---
        // Ambil libur untuk bulan target dan bulan berikutnya/sebelumnya jika periode melintasi bulan
        $minDateForHolidays = $targetDate->copy()->subMonth(); // Ambil dari sebulan sebelum target
        $maxDateForHolidays = $targetDate->copy()->addMonth(); // Ambil hingga sebulan setelah target
        // Ini untuk mengakomodasi periode CSI 16-15

        $periodHolidays = Holiday::whereBetween('tanggal', [$minDateForHolidays, $maxDateForHolidays])
            ->pluck('tanggal')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $generatedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        // --- Loop Utama Per User ---
        foreach ($usersToProcess as $user) {
            $this->line("Memproses User ID: {$user->id} - {$user->name}");

            // Tentukan periode spesifik user berdasarkan vendor dan tanggal target
            list($periodStartDate, $periodEndDate) = $this->getUserPeriod($user, $targetDate);
            $this->info("  -> Periode User: {$periodStartDate->format('Y-m-d')} s/d {$periodEndDate->format('Y-m-d')}");


            // Validasi apakah user sudah bekerja pada periode tersebut
            if ($user->tanggal_mulai_bekerja && Carbon::parse($user->tanggal_mulai_bekerja)->gt($periodEndDate)) {
                $this->line("  -> Dilewati: User belum bekerja pada periode ini.");
                Log::info("  Skipping User ID: {$user->id} - {$user->name} (Start date {$user->tanggal_mulai_bekerja->format('Y-m-d')} is after period end {$periodEndDate->format('Y-m-d')})");
                $skippedCount++;
                continue;
            }

            $existingTimesheet = MonthlyTimesheet::where('user_id', $user->id)
                ->where('period_start_date', $periodStartDate->toDateString())
                ->where('period_end_date', $periodEndDate->toDateString())
                ->first();

            // Tentukan apakah timesheet ini harus diproses
            $shouldProcess = false;
            $processReason = "";

            if (!$existingTimesheet) {
                $shouldProcess = true;
                $processReason = "Akan membuat rekap baru.";
            } elseif ($forceGenerate) {
                $shouldProcess = true;
                $processReason = "Akan me-regenerasi rekap (force flag).";
            } elseif ($existingTimesheet->status === 'rejected') {
                $shouldProcess = true;
                $processReason = "Akan me-regenerasi rekap yang sebelumnya ditolak.";
            } else {
                $processReason = "Dilewati: Rekap sudah ada dan statusnya '{$existingTimesheet->status}' (bukan 'rejected' dan tidak ada force).";
                $skippedCount++;
            }
            $this->line("  -> Status Proses: " . ($shouldProcess ? "YA" : "TIDAK") . ". Alasan: " . $processReason);
            Log::info("GenerateMonthlyTimesheet: User {$user->id} - {$user->name}. Should Process: " . ($shouldProcess ? "Yes" : "No") . ". Reason: " . $processReason);


            if (!$shouldProcess) {
                continue;
            }

            DB::beginTransaction();
            try {
                // Hitung hari kerja efektif dalam periode user
                $totalWorkDays = $this->calculateWorkdays($periodStartDate, $periodEndDate, $periodHolidays);
                $this->info("  -> Total hari kerja efektif: {$totalWorkDays}");

                // Ambil summary absensi dari DB
                $attendanceSummary = DB::table('attendances')
                    ->where('user_id', $user->id)
                    ->whereBetween('attendance_date', [$periodStartDate->toDateString(), $periodEndDate->toDateString()])
                    ->selectRaw("
                        SUM(CASE WHEN attendance_status IN ('Hadir', 'Terlambat', 'Pulang Cepat', 'Terlambat & Pulang Cepat') THEN 1 ELSE 0 END) as total_present_days,
                        SUM(CASE WHEN attendance_status IN ('Terlambat', 'Terlambat & Pulang Cepat') THEN 1 ELSE 0 END) as total_late_days,
                        SUM(CASE WHEN attendance_status IN ('Pulang Cepat', 'Terlambat & Pulang Cepat') THEN 1 ELSE 0 END) as total_early_leave_days,
                        SUM(CASE WHEN attendance_status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha_days,
                        SUM(CASE WHEN attendance_status IN ('Cuti', 'Sakit') THEN 1 ELSE 0 END) as total_leave_days,
                        SUM(CASE WHEN attendance_status = 'Dinas Luar' THEN 1 ELSE 0 END) as total_duty_days,
                        SUM(CASE WHEN attendance_status = 'Lembur' THEN 1 ELSE 0 END) as total_holiday_duty_days
                    ")
                    ->first();

                // Ambil summary lembur yang sudah diapprove
                $overtimeSummary = Overtime::where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereBetween('tanggal_lembur', [$periodStartDate->toDateString(), $periodEndDate->toDateString()])
                    ->selectRaw('COUNT(id) as occurrences, SUM(durasi_menit) as total_minutes')
                    ->first();

                // Siapkan data untuk disimpan atau diupdate
                $timesheetData = [
                    'user_id' => $user->id,
                    'vendor_id' => $user->vendor_id, // Diambil dari eager load User
                    'period_start_date' => $periodStartDate->toDateString(),
                    'period_end_date' => $periodEndDate->toDateString(),
                    'total_work_days' => $totalWorkDays,
                    'total_present_days' => (int) ($attendanceSummary->total_present_days ?? 0),
                    'total_late_days' => (int) ($attendanceSummary->total_late_days ?? 0),
                    'total_early_leave_days' => (int) ($attendanceSummary->total_early_leave_days ?? 0),
                    'total_alpha_days' => (int) ($attendanceSummary->total_alpha_days ?? 0),
                    'total_leave_days' => (int) ($attendanceSummary->total_leave_days ?? 0),
                    'total_duty_days' => (int) ($attendanceSummary->total_duty_days ?? 0),
                    'total_holiday_duty_days' => (int) ($attendanceSummary->total_holiday_duty_days ?? 0),
                    'total_overtime_minutes' => (int) ($overtimeSummary->total_minutes ?? 0),
                    'total_overtime_occurrences' => (int) ($overtimeSummary->occurrences ?? 0),
                    'status' => 'generated', // Selalu reset ke 'generated' saat diproses/diproses ulang
                    'generated_at' => now(),
                    // Reset semua field approval dan rejection
                    'approved_by_asisten_id' => null,
                    'approved_at_asisten' => null,
                    'approved_by_manager_id' => null,
                    'approved_at_manager' => null,
                    'rejected_by_id' => null,
                    'rejected_at' => null,
                    'notes' => ($existingTimesheet && $existingTimesheet->status === 'rejected') ? 'Re-evaluated after previous rejection on ' . now()->format('Y-m-d H:i:s') : ($forceGenerate ? 'Re-generated (forced) on ' . now()->format('Y-m-d H:i:s') : null),
                ];

                // Gunakan updateOrCreate untuk membuat baru atau memperbarui yang sudah ada
                MonthlyTimesheet::updateOrCreate(
                    [ // Kriteria pencarian record yang ada
                        'user_id' => $user->id,
                        'period_start_date' => $periodStartDate->toDateString(),
                        'period_end_date' => $periodEndDate->toDateString()
                    ],
                    $timesheetData // Data untuk diinsert atau diupdate
                );

                DB::commit();

                if ($existingTimesheet) {
                    $this->info("  -> Rekap berhasil di-update/di-regenerasi.");
                    Log::info("GenerateMonthlyTimesheet: Timesheet updated/re-evaluated for User ID {$user->id}, Period: {$periodStartDate->format('Y-m-d')}-{$periodEndDate->format('Y-m-d')}.");
                    $updatedCount++;
                } else {
                    $this->info("  -> Rekap berhasil di-generate.");
                    Log::info("GenerateMonthlyTimesheet: New timesheet generated for User ID {$user->id}, Period: {$periodStartDate->format('Y-m-d')}-{$periodEndDate->format('Y-m-d')}.");
                    $generatedCount++;
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  -> Gagal memproses User ID: {$user->id}. Error: " . $e->getMessage());
                Log::error("GenerateMonthlyTimesheet: Error processing User ID {$user->id}, Period: {$periodStartDate->format('Y-m-d')}-{$periodEndDate->format('Y-m-d')}. Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                $errorCount++;
            }
        } // End loop user

        $this->info("-----------------------------------------");
        $this->info("Proses generate timesheet bulanan selesai.");
        $this->info("Generated: {$generatedCount}, Updated/Re-evaluated: {$updatedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}");
        Log::info("GenerateMonthlyTimesheet: Finished. Target month: {$targetDate->format('F Y')}. Generated: {$generatedCount}, Updated/Re-evaluated: {$updatedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}.");

        return Command::SUCCESS;
    }

    /**
     * Menentukan periode start dan end date untuk seorang user berdasarkan vendor dan tanggal target.
     *
     * @param User $user
     * @param Carbon $targetDate Tanggal acuan untuk menentukan bulan/tahun periode
     * @return array [Carbon $periodStartDate, Carbon $periodEndDate]
     */
    private function getUserPeriod(User $user, Carbon $targetDate): array
    {
        $vendorName = $user->vendor?->name; // Ambil dari relasi yg sudah di-load

        // Logika default (Internal atau vendor lain selain CSI)
        // Periode adalah dari tanggal 1 hingga akhir bulan targetDate
        $startDate = $targetDate->copy()->startOfMonth();
        $endDate = $targetDate->copy()->endOfMonth();

        if ($vendorName === 'PT Cakra Satya Internusa') { // Sesuaikan dengan nama vendor Anda
            // Periode CSI: Tgl 16 bulan (targetDate - 1 jika tgl < 16) s/d Tgl 15 bulan (targetDate)
            if ($targetDate->day >= 16) {
                // Jika hari ini >= tgl 16, periode dimulai tgl 16 bulan ini
                // dan berakhir tgl 15 bulan depan
                $startDate = $targetDate->copy()->day(16);
                $endDate = $targetDate->copy()->addMonthNoOverflow()->day(15);
            } else {
                // Jika hari ini < tgl 16, periode dimulai tgl 16 bulan lalu
                // dan berakhir tgl 15 bulan ini
                $startDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
                $endDate = $targetDate->copy()->day(15);
            }
        }
        // Untuk vendor lain atau internal, periode tetap 1 s/d akhir bulan targetDate

        return [$startDate, $endDate];
    }

    /**
     * Menghitung jumlah hari kerja efektif antara dua tanggal,
     * tidak termasuk weekend dan hari libur nasional dari tabel holidays.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $holidaysArray Array berisi tanggal libur format 'Y-m-d'
     * @return int
     */
    private function calculateWorkdays(Carbon $startDate, Carbon $endDate, array $holidaysArray): int
    {
        $workDays = 0;
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidaysArray)) {
                $workDays++;
            }
        }
        return $workDays;
    }
}
