<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\User;
// use App\Models\Vendor; // Tidak digunakan secara langsung di sini, tapi $user->vendor diakses
use App\Models\Holiday;
use App\Models\MonthlyTimesheet;
use Carbon\Carbon;
use Carbon\CarbonPeriod; // Digunakan di method calculateWorkdays
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class GenerateMonthlyTimesheet
 *
 * Command Artisan untuk membuat atau mengevaluasi ulang rekapitulasi timesheet bulanan karyawan.
 * Command ini akan mengagregasi data dari absensi, lembur, cuti, dan perjalanan dinas
 * untuk menghasilkan ringkasan kehadiran bulanan.
 * Secara default, command ini juga akan memproses ulang timesheet yang sebelumnya ditolak.
 *
 * @package App\Console\Commands
 */
class GenerateMonthlyTimesheet extends Command
{
    /**
     * Nama dan signature dari command Artisan.
     * Mendefinisikan bagaimana command ini dipanggil dan opsi-opsi yang tersedia.
     * - `--month`: Bulan spesifik (1-12) yang akan diproses. Default ke bulan lalu.
     * - `--year`: Tahun spesifik (YYYY) yang akan diproses. Default ke tahun dari bulan lalu jika --month kosong, atau tahun berjalan jika --month diisi.
     * - `--group`: Memproses hanya untuk grup user tertentu (internal, csi, tdp). Wajib jika --user_id tidak diisi.
     * - `--user_id`: Memproses hanya untuk User ID tertentu. Jika diisi, --group diabaikan.
     * - `--force`: Memaksa proses ulang semua timesheet yang cocok (termasuk yang bukan rejected) dan mereset status ke 'generated'.
     *
     * @var string
     */
    protected $signature = 'timesheet:generate-monthly
                            {--month= : Bulan (1-12) untuk diproses. Jika kosong, akan memproses bulan lalu.}
                            {--year= : Tahun (YYYY) untuk diproses. Jika kosong, akan memproses tahun dari bulan lalu atau tahun berjalan.}
                            {--group=* : Proses hanya untuk grup user tertentu (internal, csi, tdp). Wajib jika --user_id tidak diisi.}
                            {--user_id=* : Proses hanya untuk User ID tertentu. Jika diisi, --group diabaikan untuk filter user.}
                            {--force : Paksa proses ulang semua timesheet yang cocok (termasuk yang bukan rejected) dan reset status ke generated.}';

    /**
     * Deskripsi command Artisan.
     * Deskripsi ini akan muncul saat menjalankan 'php artisan list'.
     *
     * @var string
     */
    protected $description = 'Membuat atau mengevaluasi ulang ringkasan timesheet bulanan karyawan. Mengevaluasi ulang timesheet yang ditolak secara default.';

    /**
     * Pemetaan nama grup pengguna ke nama vendor aktual di database.
     * Digunakan untuk memfilter pengguna berdasarkan grup vendor mereka.
     * 'internal' merepresentasikan pengguna yang tidak memiliki vendor_id (vendor_id IS NULL).
     * Sesuaikan nama vendor ini agar cocok dengan data di tabel 'vendors' Anda.
     *
     * @var array<string, string|null>
     */
    protected $vendorGroupMap = [
        'internal' => null, // Untuk karyawan internal (vendor_id IS NULL)
        'csi' => 'PT Cakra Satya Internusa',
        'tdp' => 'PT Trans Dana Profitri',
        // Tambahkan pemetaan vendor lain jika diperlukan
    ];

    /**
     * Menjalankan logika utama command Artisan.
     * Method ini akan dipanggil saat command dieksekusi.
     *
     * @return int Kode status eksekusi (Command::SUCCESS atau Command::FAILURE).
     */
    public function handle()
    {
        // Mengambil opsi dari input command
        $monthInput = $this->option('month');
        $yearInput = $this->option('year');
        $forceGenerate = $this->option('force');
        $groupIdsInput = $this->option('group');
        $userIdsInput = $this->option('user_id');

        // --- Menentukan Periode Target untuk Pemrosesan ---
        $targetDate = null; // Inisialisasi variabel
        $currentCarbon = Carbon::now(config('app.timezone', 'Asia/Jakarta'));

        if ($monthInput) { // Jika opsi --month diberikan
            $yearToUse = $yearInput ?: $currentCarbon->year; // Jika --year tidak ada, gunakan tahun saat ini

            // Validasi input bulan dan tahun
            if (
                !ctype_digit((string)$monthInput) || $monthInput < 1 || $monthInput > 12 ||
                !ctype_digit((string)$yearToUse) || $yearToUse < 2000 || $yearToUse > $currentCarbon->year + 5 // Batas tahun wajar
            ) {
                $this->error("Input bulan (1-12) atau tahun (YYYY) tidak valid: Bulan={$monthInput}, Tahun={$yearToUse}.");
                Log::error("GenerateMonthlyTimesheet: Invalid month/year input. Month: {$monthInput}, Year: {$yearToUse}");
                return Command::FAILURE;
            }
            // Membuat objek Carbon dari input bulan dan tahun, dimulai dari awal bulan
            $targetDate = Carbon::createFromDate($yearToUse, $monthInput, 1, config('app.timezone', 'Asia/Jakarta'))->startOfMonth();
            $this->info("Menargetkan periode berdasarkan input: " . $targetDate->translatedFormat('F Y'));
        } elseif ($yearInput && !$monthInput) { // Jika hanya --year yang diberikan tanpa --month
            $this->error("Opsi --month wajib diisi jika Anda memberikan opsi --year.");
            Log::error("GenerateMonthlyTimesheet: --year option provided without --month.");
            return Command::FAILURE;
        } else {
            // Jika tidak ada input --month (dan mungkin juga --year), default ke bulan lalu
            $targetDate = $currentCarbon->copy()->subMonthNoOverflow()->startOfMonth();
            $this->warn("PERINGATAN: Menjalankan tanpa --month, menggunakan default bulan lalu: " . $targetDate->translatedFormat('F Y'));
        }
        Log::info("GenerateMonthlyTimesheet: Processing for target month: " . $targetDate->translatedFormat('F Y'));


        // --- Mengambil Daftar Pengguna yang Akan Diproses ---
        $queryUsers = User::whereIn('role', ['personil', 'admin']) // Hanya proses pengguna dengan peran 'personil' atau 'admin'
            ->whereNotNull('tanggal_mulai_bekerja') // Pastikan pengguna sudah memiliki tanggal mulai bekerja
            ->with('vendor:id,name'); // Eager load data vendor untuk efisiensi

        if (!empty($userIdsInput)) {
            // Jika opsi --user_id diberikan, proses hanya untuk user ID tersebut
            $queryUsers->whereIn('id', $userIdsInput);
            $this->info("Memproses HANYA untuk User ID: " . implode(', ', $userIdsInput));
            Log::info("GenerateMonthlyTimesheet: Filtering by specific User IDs: " . implode(', ', $userIdsInput));
            if (!empty($groupIdsInput)) {
                $this->warn("Opsi --user_id digunakan, opsi --group akan diabaikan untuk filter user utama.");
            }
        } elseif (!empty($groupIdsInput)) {
            // Jika opsi --group diberikan, filter berdasarkan grup vendor
            $this->info("Memproses untuk grup: " . implode(', ', $groupIdsInput));
            Log::info("GenerateMonthlyTimesheet: Filtering by groups: " . implode(', ', $groupIdsInput));
            $queryUsers->where(function ($query) use ($groupIdsInput) {
                $hasInternal = in_array('internal', array_map('strtolower', $groupIdsInput)); // case-insensitive check for 'internal'
                $vendorNamesToInclude = [];
                // Kumpulkan nama vendor yang valid dari input grup
                foreach ($groupIdsInput as $group) {
                    $groupLower = strtolower($group); // case-insensitive group name
                    if ($groupLower !== 'internal' && isset($this->vendorGroupMap[$groupLower])) {
                        $vendorNamesToInclude[] = $this->vendorGroupMap[$groupLower];
                    }
                }

                $conditionsApplied = false;
                if ($hasInternal) {
                    $query->whereNull('vendor_id'); // Pengguna internal tidak memiliki vendor_id
                    $conditionsApplied = true;
                }
                if (!empty($vendorNamesToInclude)) {
                    // Menggunakan orWhereHas jika 'internal' juga dipilih, atau whereHas jika hanya vendor
                    if ($hasInternal) {
                        $query->orWhereHas('vendor', function ($vendorQuery) use ($vendorNamesToInclude) {
                            $vendorQuery->whereIn('name', $vendorNamesToInclude);
                        });
                    } else {
                        $query->whereHas('vendor', function ($vendorQuery) use ($vendorNamesToInclude) {
                            $vendorQuery->whereIn('name', $vendorNamesToInclude);
                        });
                    }
                    $conditionsApplied = true;
                }
                // Jika tidak ada grup valid yang cocok (selain 'internal'), pastikan tidak ada user vendor yang terpilih
                if (!$conditionsApplied && !empty($groupIdsInput)) {
                    $this->warn("Tidak ada pemetaan vendor yang valid untuk grup yang diberikan. Tidak ada user yang akan diproses dari grup tersebut.");
                    $query->whereRaw('1 = 0'); // Trik untuk memastikan query tidak mengembalikan hasil jika kondisi tidak terpenuhi
                }
            });
        } else {
            // Jika tidak ada --user_id maupun --group, berikan error karena salah satu wajib
            $this->error('Kesalahan: Opsi --group atau --user_id wajib diisi untuk menentukan target pengguna.');
            Log::error("GenerateMonthlyTimesheet: Neither --group nor --user_id provided, which is required.");
            return Command::FAILURE;
        }

        $usersToProcess = $queryUsers->get();

        if ($usersToProcess->isEmpty()) {
            $this->warn('Tidak ada user yang memenuhi kriteria untuk diproses.');
            Log::warning("GenerateMonthlyTimesheet: No eligible users found for the given criteria.");
            return Command::SUCCESS; // Selesai dengan sukses jika tidak ada user
        }
        $this->info("Ditemukan " . $usersToProcess->count() . " user yang akan diproses.");

        // --- Mengambil Data Hari Libur untuk Periode yang Relevan ---
        // Ambil data hari libur dari bulan sebelum, bulan target, dan bulan sesudah target
        // untuk mengakomodasi periode timesheet yang mungkin melintasi bulan (seperti kasus vendor CSI 16-15).
        $minDateForHolidays = $targetDate->copy()->subMonth()->startOfMonth();
        $maxDateForHolidays = $targetDate->copy()->addMonth()->endOfMonth();

        $periodHolidays = Holiday::whereBetween('tanggal', [$minDateForHolidays->toDateString(), $maxDateForHolidays->toDateString()])
            ->pluck('tanggal')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d')) // Format ke Y-m-d untuk pencocokan
            ->toArray();

        // Inisialisasi counter untuk ringkasan hasil
        $generatedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        // --- Loop Utama: Memproses Timesheet per Pengguna ---
        foreach ($usersToProcess as $user) {
            // PERBAIKAN: Menggunakan variabel sementara untuk nama vendor agar lebih aman dan kompatibel dengan PHP 7.x
            $vendorDisplayName = 'Internal'; // Default jika user tidak punya vendor atau nama vendor null
            if (isset($user->vendor) && $user->vendor->name !== null) {
                $vendorDisplayName = $user->vendor->name;
            }
            $this->line("Memproses User ID: {$user->id} - {$user->name} (Vendor: {$vendorDisplayName})");

            // Tentukan periode (tanggal mulai dan selesai) spesifik untuk pengguna ini,
            // berdasarkan vendornya dan bulan target yang sedang diproses.
            list($periodStartDate, $periodEndDate) = $this->getUserPeriod($user, $targetDate);
            $this->info("  -> Periode Timesheet User: {$periodStartDate->format('Y-m-d')} s/d {$periodEndDate->format('Y-m-d')}");

            // Validasi apakah pengguna sudah mulai bekerja pada periode timesheet ini
            if ($user->tanggal_mulai_bekerja && Carbon::parse($user->tanggal_mulai_bekerja)->gt($periodEndDate)) {
                $this->line("  -> Dilewati: Pengguna belum mulai bekerja pada periode ini ({$user->tanggal_mulai_bekerja->format('Y-m-d')} > {$periodEndDate->format('Y-m-d')}).");
                Log::info("GenerateMonthlyTimesheet: Skipping User ID: {$user->id} - {$user->name} (Start date {$user->tanggal_mulai_bekerja->format('Y-m-d')} is after period end {$periodEndDate->format('Y-m-d')})");
                $skippedCount++;
                continue; // Lanjut ke pengguna berikutnya
            }

            // Cek apakah sudah ada timesheet untuk pengguna dan periode ini
            $existingTimesheet = MonthlyTimesheet::where('user_id', $user->id)
                ->where('period_start_date', $periodStartDate->toDateString())
                ->where('period_end_date', $periodEndDate->toDateString())
                ->first();

            // Tentukan apakah timesheet ini perlu diproses (dibuat baru atau di-regenerasi)
            $shouldProcess = false;
            $processReason = "";

            if (!$existingTimesheet) {
                $shouldProcess = true; // Buat baru jika belum ada
                $processReason = "Akan membuat rekap timesheet baru.";
            } elseif ($forceGenerate) {
                $shouldProcess = true; // Proses ulang jika ada flag --force
                $processReason = "Akan me-regenerasi rekap timesheet (opsi --force aktif).";
            } elseif ($existingTimesheet->status === 'rejected') {
                $shouldProcess = true; // Proses ulang jika statusnya 'rejected'
                $processReason = "Akan me-regenerasi rekap timesheet yang sebelumnya ditolak.";
            } else {
                // Lewati jika sudah ada, statusnya bukan 'rejected', dan tidak ada flag --force
                $processReason = "Dilewati: Rekap timesheet sudah ada dengan status '{$existingTimesheet->status}' (bukan 'rejected' dan tidak ada opsi --force).";
                $skippedCount++;
            }
            $this->line("  -> Status Proses: " . ($shouldProcess ? "YA" : "TIDAK") . ". Alasan: " . $processReason);
            Log::info("GenerateMonthlyTimesheet: User {$user->id} - {$user->name}. Should Process: " . ($shouldProcess ? "Yes" : "No") . ". Reason: " . $processReason);

            if (!$shouldProcess) {
                continue; // Lanjut ke pengguna berikutnya jika tidak perlu diproses
            }

            DB::beginTransaction(); // Memulai transaksi database untuk setiap pengguna
            try {
                // Hitung total hari kerja efektif dalam periode timesheet pengguna
                $totalWorkDays = $this->calculateWorkdays($periodStartDate, $periodEndDate, $periodHolidays);
                $this->info("  -> Total hari kerja efektif dalam periode: {$totalWorkDays} hari.");

                // Mengambil ringkasan data absensi dari tabel 'attendances' menggunakan query mentah untuk efisiensi
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
                    ->first(); // Mengambil satu baris hasil agregasi

                // Mengambil ringkasan data lembur yang sudah disetujui ('approved')
                $overtimeSummary = Overtime::where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereBetween('tanggal_lembur', [$periodStartDate->toDateString(), $periodEndDate->toDateString()])
                    ->selectRaw('COUNT(id) as occurrences, SUM(durasi_menit) as total_minutes')
                    ->first();

                // Menyiapkan array data untuk disimpan atau diupdate ke tabel 'monthly_timesheets'
                $timesheetData = [
                    'user_id' => $user->id,
                    'vendor_id' => $user->vendor_id, // Diambil dari relasi User yang sudah di-eager load
                    'period_start_date' => $periodStartDate->toDateString(),
                    'period_end_date' => $periodEndDate->toDateString(),
                    'total_work_days' => $totalWorkDays,
                    'total_present_days' => (int) ($attendanceSummary->total_present_days ?? 0),
                    'total_late_days' => (int) ($attendanceSummary->total_late_days ?? 0),
                    'total_early_leave_days' => (int) ($attendanceSummary->total_early_leave_days ?? 0),
                    'total_alpha_days' => (int) ($attendanceSummary->total_alpha_days ?? 0),
                    'total_leave_days' => (int) ($attendanceSummary->total_leave_days ?? 0),
                    'total_duty_days' => (int) ($attendanceSummary->total_duty_days ?? 0),
                    'total_holiday_duty_days' => (int) ($attendanceSummary->total_holiday_duty_days ?? 0), // Lembur di hari libur (berdasarkan status absensi)
                    'total_overtime_minutes' => (int) ($overtimeSummary->total_minutes ?? 0), // Total menit lembur dari modul Overtime
                    'total_overtime_occurrences' => (int) ($overtimeSummary->occurrences ?? 0), // Jumlah kejadian lembur
                    'status' => 'generated', // Status awal selalu 'generated' saat diproses/diproses ulang
                    'generated_at' => now(config('app.timezone', 'Asia/Jakarta')), // Timestamp saat timesheet ini di-generate/re-generate
                    // Reset semua field terkait approval dan rejection untuk memastikan alur persetujuan dimulai dari awal
                    'approved_by_asisten_id' => null,
                    'approved_at_asisten' => null,
                    'approved_by_manager_id' => null,
                    'approved_at_manager' => null,
                    'rejected_by_id' => null,
                    'rejected_at' => null,
                    'notes' => ($existingTimesheet && $existingTimesheet->status === 'rejected')
                        ? 'Dievaluasi ulang setelah penolakan sebelumnya pada ' . now(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d H:i:s')
                        : (($forceGenerate && $existingTimesheet) // Hanya tambahkan note force jika memang ada existing
                            ? 'Di-generate ulang (paksa) pada ' . now(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d H:i:s')
                            : null), // Tidak ada catatan khusus jika baru dibuat atau tidak ada kondisi di atas
                ];

                // Menggunakan updateOrCreate: membuat record baru jika belum ada, atau memperbarui jika sudah ada
                // Kriteria pencarian adalah kombinasi user_id, period_start_date, dan period_end_date.
                MonthlyTimesheet::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'period_start_date' => $periodStartDate->toDateString(),
                        'period_end_date' => $periodEndDate->toDateString()
                    ],
                    $timesheetData // Data yang akan di-insert atau di-update
                );

                DB::commit(); // Simpan semua perubahan ke database jika tidak ada error

                if ($existingTimesheet) { // Jika sebelumnya sudah ada record timesheet (baik rejected atau di-force)
                    $this->info("  -> Rekap timesheet berhasil di-update/di-regenerasi.");
                    Log::info("GenerateMonthlyTimesheet: Timesheet updated/re-evaluated for User ID {$user->id}, Period: {$periodStartDate->format('Y-m-d')} to {$periodEndDate->format('Y-m-d')}.");
                    $updatedCount++;
                } else { // Jika ini adalah pembuatan record timesheet baru
                    $this->info("  -> Rekap timesheet berhasil di-generate.");
                    Log::info("GenerateMonthlyTimesheet: New timesheet generated for User ID {$user->id}, Period: {$periodStartDate->format('Y-m-d')} to {$periodEndDate->format('Y-m-d')}.");
                    $generatedCount++;
                }
            } catch (\Exception $e) {
                DB::rollBack(); // Batalkan semua perubahan jika terjadi error
                $this->error("  -> Gagal memproses User ID: {$user->id} untuk periode {$periodStartDate->format('Y-m-d')} - {$periodEndDate->format('Y-m-d')}. Error: " . $e->getMessage());
                Log::error("GenerateMonthlyTimesheet: Error processing User ID {$user->id}, Period: {$periodStartDate->format('Y-m-d')} to {$periodEndDate->format('Y-m-d')}. Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                $errorCount++;
            }
        } // Akhir loop utama per pengguna

        // Menampilkan ringkasan hasil proses di console dan log
        $this->info("-----------------------------------------");
        $this->info("Proses generate timesheet bulanan selesai.");
        $this->info("Generated Baru                 : {$generatedCount}");
        $this->info("Updated/Re-evaluated         : {$updatedCount}");
        $this->info("Dilewati (Sudah Ada/Tidak Relevan): {$skippedCount}");
        $this->info("Error Terjadi                : {$errorCount}");
        Log::info("GenerateMonthlyTimesheet: Task finished. Target month: {$targetDate->translatedFormat('F Y')}. Generated: {$generatedCount}, Updated/Re-evaluated: {$updatedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}.");

        return Command::SUCCESS; // Mengembalikan 0 jika command selesai tanpa error fatal
    }

    /**
     * Menentukan periode (tanggal mulai dan selesai) untuk timesheet seorang pengguna.
     * Logika ini berbeda tergantung pada vendor pengguna, terutama untuk 'PT Cakra Satya Internusa'
     * yang memiliki periode cut-off pertengahan bulan (tanggal 16 hingga 15 bulan berikutnya).
     * Untuk vendor lain atau pengguna internal, periode diasumsikan awal hingga akhir bulan target.
     *
     * @param  \App\Models\User  $user Instance pengguna.
     * @param  \Carbon\Carbon  $targetDate Objek Carbon yang merepresentasikan bulan dan tahun target untuk pemrosesan.
     * @return array<int, \Carbon\Carbon> Array berisi dua objek Carbon: [0] => tanggal mulai periode, [1] => tanggal selesai periode.
     */
    private function getUserPeriod(User $user, Carbon $targetDate): array
    {
        $vendorName = $user->vendor?->name; // Mengambil nama vendor dari relasi User

        // Default periode: awal hingga akhir bulan dari $targetDate
        $startDate = $targetDate->copy()->startOfMonth();
        $endDate = $targetDate->copy()->endOfMonth();

        // Logika khusus untuk vendor 'PT Cakra Satya Internusa' (CSI)
        // Pastikan nama vendor ini sama persis dengan yang ada di $this->vendorGroupMap atau database Anda
        if ($vendorName === $this->vendorGroupMap['csi']) {
            // Periode CSI adalah dari tanggal 16 bulan sebelumnya hingga tanggal 15 bulan target,
            // ATAU dari tanggal 16 bulan target hingga tanggal 15 bulan berikutnya,
            // tergantung pada tanggal $targetDate yang menjadi acuan.
            // Logika ini bertujuan agar periode yang dihasilkan SELALU MENGACU pada $targetDate sebagai BULAN UTAMA.
            // Misalnya, jika $targetDate adalah Mei:
            // - Untuk vendor CSI, periode timesheet yang relevan dengan "Mei" adalah 16 April - 15 Mei.
            // Jadi, $startDate harus 16 April, $endDate harus 15 Mei.

            // Jika $targetDate adalah bulan M, tahun Y:
            // Periode untuk vendor CSI adalah tanggal 16 bulan (M-1) tahun Y, hingga tanggal 15 bulan M tahun Y.
            // Jika bulan M adalah Januari, maka bulan (M-1) adalah Desember tahun (Y-1).

            $startDate = $targetDate->copy()->subMonthNoOverflow()->day(16);
            $endDate = $targetDate->copy()->day(15);
        }
        // Untuk pengguna internal atau vendor lain (seperti TDP), periode default (awal-akhir bulan dari $targetDate) tetap berlaku.

        return [$startDate, $endDate];
    }

    /**
     * Menghitung jumlah hari kerja efektif antara dua tanggal.
     * Hari kerja efektif tidak termasuk akhir pekan (Sabtu, Minggu) dan
     * hari libur nasional yang terdaftar di tabel 'holidays'.
     *
     * @param  \Carbon\Carbon  $startDate Tanggal mulai periode.
     * @param  \Carbon\Carbon  $endDate Tanggal selesai periode.
     * @param  array<string>  $holidaysArray Array berisi tanggal-tanggal libur dalam format 'Y-m-d'.
     * @return int Jumlah hari kerja efektif.
     */
    private function calculateWorkdays(Carbon $startDate, Carbon $endDate, array $holidaysArray): int
    {
        if ($startDate->gt($endDate)) { // Jika tanggal mulai lebih besar dari tanggal selesai
            return 0;
        }
        $workDays = 0;
        // Membuat periode tanggal dari $startDate hingga $endDate
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            // Cek apakah tanggal saat ini bukan akhir pekan DAN tidak ada di daftar hari libur
            if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidaysArray)) {
                $workDays++; // Tambah counter hari kerja
            }
        }
        return $workDays;
    }
}
