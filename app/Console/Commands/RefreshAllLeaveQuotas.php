<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon; // Pastikan Carbon diimport
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Untuk transaksi jika diperlukan

class RefreshAllLeaveQuotas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:refresh-all-quotas'; // Nama perintah artisan

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mereset kuota semua jenis cuti untuk semua karyawan yang memenuhi syarat pada awal periode (misal: tahunan).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai proses refresh semua kuota cuti...');
        Log::info('Scheduled Task leave:refresh-all-quotas started.');

        // 1. Ambil semua jenis cuti
        $allJenisCuti = JenisCuti::all();
        if ($allJenisCuti->isEmpty()) {
            $this->error('Tidak ada data Jenis Cuti ditemukan di database.');
            Log::error('RefreshAllLeaveQuotas: No Jenis Cuti found.');
            return 1;
        }

        // 2. Ambil semua user yang relevan (misal: semua user aktif)
        //    Asumsikan semua user di tabel users adalah target, filter jika perlu
        $allUsers = User::whereNotNull('tanggal_mulai_bekerja')->get(); // Hanya proses user yg punya tgl mulai kerja
        // Jika ada status aktif: ->where('status_aktif', true)->get();

        if ($allUsers->isEmpty()) {
            $this->warn('Tidak ada user yang memenuhi kriteria untuk diproses.');
            Log::warning('RefreshAllLeaveQuotas: No eligible users found.');
            return 0;
        }

        $processedCount = 0;
        $errorCount = 0;
        $today = Carbon::today(); // Tanggal hari ini

        // 3. Loop untuk setiap user
        foreach ($allUsers as $user) {
            $this->info("Memproses user: {$user->id} - {$user->name}");

            // 4. Loop untuk setiap jenis cuti
            foreach ($allJenisCuti as $jenisCuti) {
                $targetQuota = 0; // Default kuota jika tidak memenuhi syarat

                // 5. Tentukan kuota default berdasarkan aturan
                if (strtolower($jenisCuti->nama_cuti) === 'cuti tahunan') {
                    // Aturan Cuti Tahunan: Cek masa kerja > 12 bulan
                    if ($user->lama_bekerja >= 12) { // Gunakan accessor lama_bekerja
                        $targetQuota = $jenisCuti->durasi_default;
                    } else {
                        $targetQuota = 0; // Belum setahun, kuota tahunan 0
                    }
                } else {
                    // Aturan Jenis Cuti Lain: Langsung pakai durasi default
                    $targetQuota = $jenisCuti->durasi_default;
                }

                // 6. Update atau Buat kuota di CutiQuota
                try {
                    // Gunakan transaksi per user-jenis cuti untuk keamanan data individual
                    // DB::transaction(function () use ($user, $jenisCuti, $targetQuota) { // Opsi transaksi
                    CutiQuota::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'jenis_cuti_id' => $jenisCuti->id,
                        ],
                        [
                            'durasi_cuti' => $targetQuota // Set kuota sesuai hasil perhitungan
                        ]
                    );
                    // }); // Akhir Opsi transaksi
                } catch (\Exception $e) {
                    $errorCount++;
                    $errorMessage = "Gagal update kuota '{$jenisCuti->nama_cuti}' untuk user ID: {$user->id}. Error: " . $e->getMessage();
                    $this->error($errorMessage);
                    Log::error("RefreshAllLeaveQuotas: " . $errorMessage);
                    // Lanjutkan ke jenis cuti / user berikutnya jika 1 gagal
                }
            } // End loop jenis cuti
            $processedCount++;
        } // End loop user

        $this->info("-----------------------------------------");
        $this->info("Proses refresh kuota cuti selesai.");
        $this->info("Total Karyawan Diproses : {$processedCount}");
        $this->info("Total Error             : {$errorCount}");
        Log::info("Scheduled Task leave:refresh-all-quotas finished. Processed: {$processedCount}, Errors: {$errorCount}.");

        return 0; // Selesai sukses (meskipun mungkin ada error individual)
    }
}
