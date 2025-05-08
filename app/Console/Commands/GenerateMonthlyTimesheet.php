<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Holiday;
use App\Models\MonthlyTimesheet;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB; // Pastikan DB facade di-import
use Illuminate\Support\Facades\Log;

class GenerateMonthlyTimesheet extends Command
{
    /**
     * Signature diperbarui dengan opsi --group
     * {--month=} : Bulan (1-12)
     * {--year=} : Tahun (YYYY)
     * {--group=*} : Grup user yang akan diproses ('internal', 'csi', 'tdp'). Bisa multiple.
     * {--force} : Paksa generate ulang.
     *
     * @var string
     */
    protected $signature = 'timesheet:generate-monthly
                            {--month= : Bulan (1-12) untuk diproses}
                            {--year= : Tahun (YYYY) untuk diproses}
                            {--group=* : Proses hanya untuk grup user tertentu (internal, csi, tdp)}
                            {--force : Generate ulang meskipun data sudah ada}';

    protected $description = 'Generate monthly timesheet summary for specific employee groups based on attendance and overtime data.';

    // Map nama grup ke nama vendor (atau null untuk internal)
    // Sesuaikan 'PT Cakra Satya Internusa' dan 'PT Trans Dana Profitri' dengan nama di DB Anda
    protected $vendorGroupMap = [
        'internal' => null,
        'csi' => 'PT Cakra Satya Internusa',
        'tdp' => 'PT Trans Dana Profitri',
        // Tambahkan vendor lain jika perlu
    ];

    public function handle()
    {
        // --- Tentukan Periode (Logika tetap sama) ---
        $month = $this->option('month');
        $year = $this->option('year');
        $targetDate = Carbon::now();

        if ($month && $year) {
            if ($month < 1 || $month > 12 || !ctype_digit((string)$year) || $year < 2000 || $year > 2100) {
                $this->error('Input bulan (1-12) atau tahun (YYYY) tidak valid.');
                return 1;
            }
            $targetDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $this->info("Menargetkan periode berdasarkan: " . $targetDate->format('F Y'));
        } else {
            $targetDate = Carbon::now()->subMonth()->startOfMonth();
            $this->warn("PERINGATAN: Menjalankan tanpa --month/--year, menggunakan default bulan lalu: " . $targetDate->format('F Y'));
            $this->warn("Sebaiknya panggil command ini dari scheduler dengan --month dan --year.");
        }

        // --- Ambil Grup User dari Opsi (Logika tetap sama) ---
        $groups = $this->option('group');
        if (empty($groups)) {
            $this->error('Opsi --group wajib diisi (contoh: --group=internal --group=csi).');
            return 1;
        }
        $this->info("Memproses untuk grup: " . implode(', ', $groups));

        // --- Ambil User Berdasarkan Grup (Logika tetap sama) ---
        $queryUsers = User::whereIn('role', ['personil', 'admin'])
            ->whereNotNull('tanggal_mulai_bekerja')
            ->with('vendor:id,name'); // Eager load vendor

        $queryUsers->where(function ($query) use ($groups) {
            $hasInternal = in_array('internal', $groups);
            $vendorNamesToInclude = [];
            foreach ($groups as $group) {
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
        });
        $usersToProcess = $queryUsers->get();

        // --- Sisa logika (Ambil Libur, Loop User, Hitung, Simpan) ---
        if ($usersToProcess->isEmpty()) {
            $this->warn('Tidak ada user yang memenuhi kriteria grup untuk diproses.');
            return 0;
        }
        $this->info("Ditemukan " . $usersToProcess->count() . " user dalam grup yang akan diproses.");

        // --- Ambil Libur (Logika tetap sama) ---
        $periodHolidays = Holiday::whereMonth('tanggal', $targetDate->month)
            ->whereYear('tanggal', $targetDate->year)
            ->pluck('tanggal')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $forceGenerate = $this->option('force');
        $generatedCount = 0; $updatedCount = 0; $skippedCount = 0; $errorCount = 0;

        // --- Loop Utama Per User ---
        foreach ($usersToProcess as $user) {
            $this->line("Memproses User ID: {$user->id} - {$user->name}");

            // --- Tentukan Periode Spesifik User ---
            list($periodStartDate, $periodEndDate) = $this->getUserPeriod($user, $targetDate);

            // --- Validasi & Skip User (Logika tetap sama) ---
            if ($user->tanggal_mulai_bekerja->gt($periodEndDate)) {
                $this->line("  -> Dilewati: User belum bekerja pada periode ({$periodStartDate->format('Y-m-d')} - {$periodEndDate->format('Y-m-d')}).");
                $skippedCount++;
                continue;
            }
            $existingTimesheet = MonthlyTimesheet::where('user_id', $user->id)
                ->where('period_start_date', $periodStartDate->toDateString())
                ->where('period_end_date', $periodEndDate->toDateString())
                ->first();
            if ($existingTimesheet && !$forceGenerate) {
                $this->line("  -> Dilewati: Rekap untuk periode ini sudah ada.");
                $skippedCount++;
                continue;
            }

            // --- Mulai Transaksi Per User ---
            DB::beginTransaction();
            try {
                // --- HITUNG HARI KERJA EFEKTIF (Logika tetap sama) ---
                $totalWorkDays = $this->calculateWorkdays($periodStartDate, $periodEndDate, $periodHolidays);

                // --- OPTIMASI: Ambil Summary Absensi via Query Agregasi DB ---
                $attendanceSummary = DB::table('attendances') // Gunakan Query Builder
                    ->where('user_id', $user->id)
                    ->whereBetween('attendance_date', [$periodStartDate->toDateString(), $periodEndDate->toDateString()])
                    ->selectRaw("
                        COUNT(*) as total_records, /* Hitung total record untuk cek apakah ada data */
                        SUM(CASE WHEN attendance_status IN ('Hadir', 'Terlambat', 'Pulang Cepat', 'Terlambat & Pulang Cepat') THEN 1 ELSE 0 END) as total_present_days,
                        SUM(CASE WHEN attendance_status IN ('Terlambat', 'Terlambat & Pulang Cepat') THEN 1 ELSE 0 END) as total_late_days,
                        SUM(CASE WHEN attendance_status IN ('Pulang Cepat', 'Terlambat & Pulang Cepat') THEN 1 ELSE 0 END) as total_early_leave_days,
                        SUM(CASE WHEN attendance_status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha_days,
                        SUM(CASE WHEN attendance_status IN ('Cuti', 'Sakit') THEN 1 ELSE 0 END) as total_leave_days,
                        SUM(CASE WHEN attendance_status = 'Dinas Luar' THEN 1 ELSE 0 END) as total_duty_days,
                        SUM(CASE WHEN attendance_status = 'Lembur' THEN 1 ELSE 0 END) as total_holiday_duty_days
                    ")
                    ->first(); // Hanya butuh 1 baris hasil agregasi

                // Inisialisasi summary (jika tidak ada record absensi sama sekali)
                $summary = [
                    'total_present_days' => 0, 'total_late_days' => 0, 'total_early_leave_days' => 0,
                    'total_alpha_days' => 0, 'total_leave_days' => 0, 'total_duty_days' => 0,
                    'total_holiday_duty_days' => 0,
                ];

                // Jika ada hasil dari query agregasi, gunakan nilainya
                if ($attendanceSummary && $attendanceSummary->total_records > 0) {
                    $summary['total_present_days'] = (int) $attendanceSummary->total_present_days;
                    $summary['total_late_days'] = (int) $attendanceSummary->total_late_days;
                    $summary['total_early_leave_days'] = (int) $attendanceSummary->total_early_leave_days;
                    $summary['total_alpha_days'] = (int) $attendanceSummary->total_alpha_days;
                    $summary['total_leave_days'] = (int) $attendanceSummary->total_leave_days;
                    $summary['total_duty_days'] = (int) $attendanceSummary->total_duty_days;
                    $summary['total_holiday_duty_days'] = (int) $attendanceSummary->total_holiday_duty_days;
                }
                // --- Akhir Optimasi Agregasi Absensi ---

                // --- AMBIL DATA LEMBUR (Tetap sama, sudah efisien) ---
                $overtimeSummary = Overtime::where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereBetween('tanggal_lembur', [$periodStartDate->toDateString(), $periodEndDate->toDateString()])
                    ->selectRaw('COUNT(id) as occurrences, SUM(durasi_menit) as total_minutes')
                    ->first();

                // --- SIAPKAN DATA UNTUK DISIMPAN ---
                $timesheetData = [
                    'user_id' => $user->id,
                    'vendor_id' => $user->vendor_id, // Ambil dari eager load
                    'period_start_date' => $periodStartDate->toDateString(),
                    'period_end_date' => $periodEndDate->toDateString(),
                    'total_work_days' => $totalWorkDays,
                    'total_present_days' => $summary['total_present_days'],
                    'total_late_days' => $summary['total_late_days'],
                    'total_early_leave_days' => $summary['total_early_leave_days'],
                    'total_alpha_days' => $summary['total_alpha_days'],
                    'total_leave_days' => $summary['total_leave_days'],
                    'total_duty_days' => $summary['total_duty_days'],
                    'total_holiday_duty_days' => $summary['total_holiday_duty_days'],
                    'total_overtime_minutes' => $overtimeSummary->total_minutes ?? 0,
                    'total_overtime_occurrences' => $overtimeSummary->occurrences ?? 0,
                    'status' => 'generated', // Status awal
                    'generated_at' => now(),
                    // Reset kolom approval (penting jika --force)
                    'approved_by_asisten_id' => null,
                    'approved_at_asisten' => null,
                    'approved_by_manager_id' => null,
                    'approved_at_manager' => null,
                    'rejected_by_id' => null,
                    'rejected_at' => null,
                    'notes' => $forceGenerate ? 'Generated ulang pada ' . now()->format('Y-m-d H:i') : null,
                ];

                // --- SIMPAN ATAU UPDATE (Logika tetap sama) ---
                MonthlyTimesheet::updateOrCreate(
                    ['user_id' => $user->id, 'period_start_date' => $periodStartDate->toDateString(), 'period_end_date' => $periodEndDate->toDateString()],
                    $timesheetData
                );

                DB::commit(); // Commit transaksi per user

                if ($existingTimesheet && $forceGenerate) {
                    $this->info("  -> Rekap berhasil di-generate ulang.");
                    $updatedCount++;
                } else if (!$existingTimesheet) {
                     $this->info("  -> Rekap berhasil di-generate.");
                     $generatedCount++;
                }
                // Tidak ada output jika skip karena sudah ada dan tidak force

            } catch (\Exception $e) {
                DB::rollBack(); // Rollback jika ada error pada user ini
                $this->error("  -> Gagal memproses User ID: {$user->id}. Error: " . $e->getMessage());
                Log::error("Error generating timesheet for User ID {$user->id} Period: {$periodStartDate->format('Y-m-d')}-{$periodEndDate->format('Y-m-d')}: " . $e->getMessage());
                $errorCount++;
            }
        } // End loop user

        // --- Output Ringkasan (Logika tetap sama) ---
        $this->info("-----------------------------------------");
        $this->info("Proses generate timesheet bulanan selesai.");
        $this->info("Generated: {$generatedCount}, Updated: {$updatedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}");
        Log::info("Finished monthly timesheet generation for groups: " . implode(', ', $groups) . ". Period target: {$targetDate->format('F Y')}. Generated: {$generatedCount}, Updated: {$updatedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}");

        return Command::SUCCESS;
    }

    // --- Helper Methods (Tetap sama) ---
    private function getUserPeriod(User $user, Carbon $targetDate): array
    {
        $vendorName = $user->vendor?->name; // Ambil dari relasi yg sudah di-load
        if ($vendorName === 'PT Cakra Satya Internusa') { // Sesuaikan nama vendor
            if ($targetDate->day >= 16) {
                $startDate = $targetDate->copy()->day(16);
                $endDate = $targetDate->copy()->addMonthNoOverflow()->day(15);
            } else {
                $startDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
                $endDate = $targetDate->copy()->day(15);
            }
        } else {
            // Default/Internal/Vendor Lain: Tgl 1 s/d akhir bulan target
            $startDate = $targetDate->copy()->startOfMonth();
            $endDate = $targetDate->copy()->endOfMonth();
        }
        return [$startDate, $endDate];
    }

    private function calculateWorkdays(Carbon $startDate, Carbon $endDate, array $holidays): int
    {
        $workDays = 0;
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidays)) {
                $workDays++;
            }
        }
        return $workDays;
    }
}
