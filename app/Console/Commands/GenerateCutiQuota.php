<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // Ditambahkan untuk logging yang lebih baik

/**
 * Class GenerateCutiQuota
 *
 * Command Artisan untuk membuat (generate) kuota cuti awal untuk semua pengguna
 * berdasarkan jenis-jenis cuti yang terdaftar. Command ini akan memeriksa setiap pengguna
 * dan setiap jenis cuti, lalu membuat entri kuota di tabel 'cuti_quota' jika belum ada.
 * Terdapat logika khusus untuk 'Cuti Tahunan' yang hanya diberikan jika karyawan
 * telah bekerja minimal 12 bulan.
 *
 * @package App\Console\Commands
 */
class GenerateCutiQuota extends Command
{
    /**
     * Nama dan signature (tanda tangan) dari command Artisan.
     * Mendefinisikan bagaimana command ini dipanggil dari CLI.
     * Saat ini tidak memiliki opsi tambahan.
     *
     * @var string
     */
    protected $signature = 'cuti:generate-quota';

    /**
     * Deskripsi command Artisan.
     * Deskripsi ini akan muncul saat pengguna menjalankan 'php artisan list'.
     *
     * @var string
     */
    protected $description = 'Generate kuota cuti untuk semua pengguna berdasarkan jenis cuti yang ada.';

    /**
     * Menjalankan logika utama dari command Artisan.
     * Method ini akan dipanggil ketika command `cuti:generate-quota` dieksekusi.
     *
     * @return int Kode status eksekusi (0 untuk sukses, selain itu untuk error).
     */
    public function handle()
    {
        $this->info('Memulai proses pembuatan kuota cuti awal...');
        Log::info('GenerateCutiQuota command started.');

        // Mengambil semua pengguna dari database
        $users = User::all();
        if ($users->isEmpty()) {
            $this->info('Tidak ada pengguna ditemukan. Proses dihentikan.');
            Log::info('GenerateCutiQuota: No users found. Process terminated.');
            return 0;
        }

        // Mengambil semua jenis cuti yang terdaftar
        $jenisCutiAll = JenisCuti::all(); // Menggunakan variabel yang berbeda agar tidak konflik dengan $cuti di dalam loop
        if ($jenisCutiAll->isEmpty()) {
            $this->info('Tidak ada jenis cuti ditemukan. Proses dihentikan.');
            Log::info('GenerateCutiQuota: No leave types (JenisCuti) found. Process terminated.');
            return 0;
        }

        $generatedCount = 0;
        $skippedCount = 0;

        // Loop untuk setiap pengguna
        foreach ($users as $user) {
            $this->line("Memproses pengguna: {$user->name} (ID: {$user->id})");

            // Loop untuk setiap jenis cuti
            foreach ($jenisCutiAll as $jenisCutiItem) { // Menggunakan $jenisCutiItem agar tidak konflik
                $this->line("  -> Memeriksa jenis cuti: {$jenisCutiItem->nama_cuti}");

                // Logika khusus untuk "Cuti Tahunan"
                if ($jenisCutiItem->nama_cuti === 'Cuti Tahunan') {
                    // Periksa apakah karyawan telah bekerja selama minimal 12 bulan
                    $tanggalMulaiKerja = $user->tanggal_mulai_bekerja;
                    if (!$tanggalMulaiKerja) {
                        $this->warn("    User {$user->name} tidak memiliki tanggal mulai bekerja. Melewati Cuti Tahunan.");
                        Log::warning("GenerateCutiQuota: User {$user->name} (ID: {$user->id}) does not have a start date. Skipping annual leave quota.");
                        $skippedCount++;
                        continue; // Lanjut ke jenis cuti berikutnya untuk user ini
                    }

                    // Hitung lama bekerja dalam bulan
                    $lamaBekerjaBulan = Carbon::parse($tanggalMulaiKerja)->diffInMonths(now(config('app.timezone', 'Asia/Jakarta')));
                    if ($lamaBekerjaBulan < 12) {
                        $this->info("    User {$user->name} baru bekerja {$lamaBekerjaBulan} bulan (kurang dari 12 bulan). Kuota Cuti Tahunan tidak diberikan saat ini.");
                        Log::info("GenerateCutiQuota: User {$user->name} (ID: {$user->id}) has worked for {$lamaBekerjaBulan} months. Annual leave quota not granted yet.");
                        $skippedCount++;
                        continue; // Lanjut ke jenis cuti berikutnya untuk user ini
                    }
                    $this->info("    User {$user->name} telah bekerja {$lamaBekerjaBulan} bulan. Memenuhi syarat untuk Cuti Tahunan.");
                }

                // Tentukan durasi default untuk jenis cuti ini dari tabel 'jenis_cuti'
                $durasiCuti = $jenisCutiItem->durasi_default;

                // Periksa apakah kuota untuk jenis cuti ini sudah ada untuk pengguna ini
                $existingQuota = CutiQuota::where('user_id', $user->id)
                    ->where('jenis_cuti_id', $jenisCutiItem->id)
                    ->first();

                if (!$existingQuota) {
                    // Jika kuota belum ada, buat record baru di tabel 'cuti_quota'
                    try {
                        CutiQuota::create([
                            'user_id' => $user->id,
                            'jenis_cuti_id' => $jenisCutiItem->id,
                            'durasi_cuti' => $durasiCuti, // Menggunakan durasi default dari jenis cuti
                        ]);
                        $this->info("    Kuota cuti '{$jenisCutiItem->nama_cuti}' ({$durasiCuti} hari) berhasil dibuat untuk user {$user->name}.");
                        Log::info("GenerateCutiQuota: Leave quota '{$jenisCutiItem->nama_cuti}' ({$durasiCuti} days) created for User ID {$user->id}.");
                        $generatedCount++;
                    } catch (\Exception $e) {
                        $this->error("    Gagal membuat kuota '{$jenisCutiItem->nama_cuti}' untuk user {$user->name}. Error: " . $e->getMessage());
                        Log::error("GenerateCutiQuota: Failed to create quota '{$jenisCutiItem->nama_cuti}' for User ID {$user->id}. Error: " . $e->getMessage());
                    }
                } else {
                    // Jika kuota sudah ada, informasikan dan lewati (command ini tidak melakukan update kuota yang sudah ada)
                    $this->line("    Kuota cuti '{$jenisCutiItem->nama_cuti}' sudah ada untuk user {$user->name}. Dilewati.");
                    $skippedCount++;
                }
            } // Akhir loop jenis cuti
        } // Akhir loop pengguna

        $this->info("-----------------------------------------");
        $this->info('Proses pembuatan kuota cuti awal selesai!');
        $this->info("Total Kuota Baru Dibuat: {$generatedCount}");
        $this->info("Total Kuota Dilewati (Sudah Ada/Belum Memenuhi Syarat): {$skippedCount}");
        Log::info("GenerateCutiQuota command finished. New Quotas Generated: {$generatedCount}, Skipped: {$skippedCount}.");
        return 0; // Mengembalikan 0 (Command::SUCCESS) jika command selesai
    }
}
