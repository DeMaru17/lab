<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // Ditambahkan untuk logging
use Illuminate\Support\Facades\DB; // Ditambahkan untuk transaksi database

/**
 * Class GrantAnnualLeaveQuota
 *
 * Command Artisan untuk memberikan kuota cuti tahunan secara otomatis kepada pengguna
 * yang telah mencapai satu tahun masa kerja pada hari command ini dijalankan.
 * Command ini akan memeriksa setiap pengguna, menghitung masa kerja mereka,
 * dan jika tepat satu tahun serta belum memiliki kuota cuti tahunan,
 * maka kuota akan dibuatkan.
 *
 * @package App\Console\Commands
 */
class GrantAnnualLeaveQuota extends Command
{
    /**
     * Nama dan signature (tanda tangan) dari command Artisan.
     * Mendefinisikan bagaimana command ini dipanggil dari CLI.
     * Nama 'leave:grant-annual' menunjukkan fungsinya terkait pemberian cuti tahunan.
     *
     * @var string
     */
    protected $signature = 'leave:grant-annual'; // Nama perintah artisan yang akan dijalankan

    /**
     * Deskripsi console command.
     * Deskripsi ini akan muncul saat pengguna menjalankan 'php artisan list'
     * atau 'php artisan help leave:grant-annual'.
     *
     * @var string
     */
    protected $description = 'Memberikan kuota cuti tahunan kepada pengguna yang telah mencapai 1 tahun masa kerja pada hari ini.';

    /**
     * Menjalankan logika utama dari command Artisan.
     * Method ini akan dipanggil ketika command `leave:grant-annual` dieksekusi,
     * biasanya melalui penjadwal tugas (scheduler) setiap hari.
     *
     * @return int Kode status eksekusi (0 untuk sukses, selain itu untuk error).
     */
    public function handle()
    {
        $this->info('Memulai pengecekan kelayakan pemberian kuota cuti tahunan...');
        Log::info('GrantAnnualLeaveQuota command started.');

        // 1. Cari ID dan durasi default untuk jenis cuti 'Cuti Tahunan'
        // Ini penting karena kuota yang diberikan akan berdasarkan jenis cuti ini.
        $jenisCutiTahunan = JenisCuti::where('nama_cuti', 'Cuti Tahunan')->first();

        // Jika jenis cuti 'Cuti Tahunan' tidak ditemukan di database, command tidak bisa melanjutkan.
        if (!$jenisCutiTahunan) {
            $this->error('Jenis Cuti "Cuti Tahunan" tidak ditemukan di database. Proses dibatalkan.');
            Log::error('GrantAnnualLeaveQuota: Jenis Cuti "Cuti Tahunan" not found. Command terminated.');
            return 1; // Keluar dengan kode error
        }

        // Ambil ID dan durasi default dari jenis cuti tahunan yang ditemukan
        $jenisCutiId = $jenisCutiTahunan->id;
        $durasiDefault = $jenisCutiTahunan->durasi_default; // Durasi kuota yang akan diberikan

        // 2. Tentukan tanggal target: tepat satu tahun yang lalu dari hari ini.
        // Pengguna yang tanggal mulai bekerjanya sama dengan tanggal ini akan berhak.
        $targetAnniversaryDate = Carbon::today(config('app.timezone', 'Asia/Jakarta'))->subYear();

        $this->info("Mencari pengguna yang ulang tahun masa kerja 1 tahun jatuh pada: " . $targetAnniversaryDate->format('Y-m-d'));

        // 3. Cari pengguna yang memenuhi kriteria:
        //    - Memiliki 'tanggal_mulai_bekerja'.
        //    - 'tanggal_mulai_bekerja' mereka adalah tepat satu tahun yang lalu dari hari ini.
        //    - BELUM memiliki kuota cuti untuk jenis 'Cuti Tahunan' (mencegah duplikasi).
        $eligibleUsers = User::whereNotNull('tanggal_mulai_bekerja')
                             ->whereDate('tanggal_mulai_bekerja', $targetAnniversaryDate->toDateString()) // Hanya yang anniversary-nya hari ini
                             ->whereDoesntHave('cutiQuotas', function ($query) use ($jenisCutiId) {
                                 // Memastikan pengguna belum memiliki kuota untuk jenis cuti tahunan ini
                                 $query->where('jenis_cuti_id', $jenisCutiId);
                             })
                             ->get();

        // Jika tidak ada pengguna yang memenuhi syarat, tampilkan pesan dan keluar.
        if ($eligibleUsers->isEmpty()) {
            $this->info('Tidak ada pengguna yang mencapai 1 tahun masa kerja hari ini atau mereka sudah memiliki kuota cuti tahunan.');
            Log::info('GrantAnnualLeaveQuota: No eligible users found for annual leave grant today.');
            return 0; // Selesai tanpa ada yang diproses, dianggap sukses
        }

        $this->info("Menemukan {$eligibleUsers->count()} pengguna yang berhak mendapatkan kuota cuti tahunan hari ini.");
        $successCount = 0;
        $failCount = 0;

        // 4. Loop untuk setiap pengguna yang memenuhi syarat dan berikan kuota cuti tahunan.
        foreach ($eligibleUsers as $user) {
            $this->line(" -> Memproses pengguna: {$user->name} (ID: {$user->id})");
            DB::beginTransaction(); // Memulai transaksi untuk setiap user
            try {
                // Buat record baru di tabel 'cuti_quota'
                CutiQuota::create([
                    'user_id' => $user->id,
                    'jenis_cuti_id' => $jenisCutiId,
                    'durasi_cuti' => $durasiDefault, // Menggunakan durasi default dari JenisCuti
                ]);
                DB::commit(); // Simpan jika berhasil
                $this->info("    Kuota cuti tahunan ({$durasiDefault} hari) berhasil diberikan kepada {$user->name}.");
                Log::info("GrantAnnualLeaveQuota: Annual leave quota ({$durasiDefault} days) granted to User ID: {$user->id} ({$user->name}).");
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack(); // Batalkan jika terjadi error
                $this->error("    Gagal memberikan kuota cuti tahunan kepada {$user->name}. Error: " . $e->getMessage());
                Log::error("GrantAnnualLeaveQuota: Failed to grant annual leave quota to User ID: {$user->id} ({$user->name}). Error: " . $e->getMessage());
                $failCount++;
            }
        }

        $this->info("-----------------------------------------");
        $this->info('Proses pemberian kuota cuti tahunan selesai.');
        $this->info("Total Kuota Berhasil Diberikan: {$successCount}");
        $this->info("Total Gagal Diberikan       : {$failCount}");
        Log::info("GrantAnnualLeaveQuota command finished. Granted: {$successCount}, Failed: {$failCount}.");

        return 0; // Mengembalikan 0 (Command::SUCCESS) jika command selesai
    }
}
