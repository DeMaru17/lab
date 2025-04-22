<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon;

class GrantAnnualLeaveQuota extends Command
{
    /**
     * Nama dan signature dari console command.
     *
     * @var string
     */
    protected $signature = 'leave:grant-annual'; // Nama perintah artisan

    /**
     * Deskripsi console command.
     *
     * @var string
     */
    protected $description = 'Memberikan kuota cuti tahunan kepada user yang telah mencapai 1 tahun masa kerja';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai pengecekan kelayakan cuti tahunan...');

        // Cari ID dan durasi default untuk 'Cuti Tahunan'
        $jenisCutiTahunan = JenisCuti::where('nama_cuti', 'Cuti Tahunan')->first();

        if (!$jenisCutiTahunan) {
            $this->error('Jenis Cuti "Cuti Tahunan" tidak ditemukan.');
            return 1; // Keluar dengan error
        }

        $jenisCutiId = $jenisCutiTahunan->id;
        $durasiDefault = $jenisCutiTahunan->durasi_default;

        // Cari user yang 'tanggal_mulai_bekerja'-nya tepat 1 tahun yang lalu HARI INI
        // dan belum memiliki kuota cuti tahunan
        $targetDate = Carbon::today()->subYear(); // Tanggal 1 tahun yang lalu dari hari ini

        $eligibleUsers = User::whereNotNull('tanggal_mulai_bekerja')
                             ->whereDate('tanggal_mulai_bekerja', $targetDate) // Hanya yg anniversary hari ini
                             ->whereDoesntHave('cutiQuotas', function ($query) use ($jenisCutiId) {
                                 $query->where('jenis_cuti_id', $jenisCutiId); // Yang BELUM punya kuota jenis ini
                             })
                             ->get();

        if ($eligibleUsers->isEmpty()) {
            $this->info('Tidak ada user yang mencapai 1 tahun masa kerja hari ini atau mereka sudah memiliki kuota.');
            return 0; // Selesai tanpa ada yang diproses
        }

        $this->info("Menemukan {$eligibleUsers->count()} user yang berhak mendapatkan cuti tahunan hari ini.");

        foreach ($eligibleUsers as $user) {
            try {
                CutiQuota::create([
                    'user_id' => $user->id,
                    'jenis_cuti_id' => $jenisCutiId,
                    'durasi_cuti' => $durasiDefault,
                ]);
                $this->info("-> Kuota cuti tahunan berhasil diberikan kepada user ID: {$user->id} ({$user->name})");
            } catch (\Exception $e) {
                $this->error("-> Gagal memberikan kuota kepada user ID: {$user->id}. Error: " . $e->getMessage());
            }
        }

        $this->info('Pengecekan kelayakan cuti tahunan selesai.');
        return 0; // Selesai sukses
    }

 
}