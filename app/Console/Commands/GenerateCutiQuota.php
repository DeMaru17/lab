<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon;

class GenerateCutiQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuti:generate-quota';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate cuti quota for all users based on jenis cuti';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Ambil semua pengguna
        $users = User::all();

        // Ambil semua jenis cuti
        $jenisCuti = JenisCuti::all();

        // Loop untuk setiap pengguna
        foreach ($users as $user) {
            // Loop untuk setiap jenis cuti
            foreach ($jenisCuti as $cuti) {
                // Periksa apakah jenis cuti adalah "Cuti Tahunan"
                if ($cuti->nama_cuti === 'Cuti Tahunan') {
                    // Periksa apakah karyawan telah bekerja selama lebih dari 12 bulan
                    $tanggalMulaiKerja = $user->tanggal_mulai_bekerja;
                    if (!$tanggalMulaiKerja) {
                        $this->info("User {$user->name} tidak memiliki tanggal mulai bekerja. Lewati.");
                        continue;
                    }

                    $lamaBekerja = Carbon::parse($tanggalMulaiKerja)->diffInMonths(now());
                    if ($lamaBekerja < 12) {
                        $this->info("User {$user->name} belum bekerja selama 12 bulan. Kuota Cuti Tahunan tidak diberikan.");
                        continue;
                    }
                }

                // Tentukan durasi default untuk jenis cuti
                $durasiCuti = $cuti->durasi_default;

                // Periksa apakah kuota sudah ada
                $existingQuota = CutiQuota::where('user_id', $user->id)
                    ->where('jenis_cuti_id', $cuti->id)
                    ->first();

                if (!$existingQuota) {
                    // Buat kuota cuti jika belum ada
                    CutiQuota::create([
                        'user_id' => $user->id,
                        'jenis_cuti_id' => $cuti->id,
                        'durasi_cuti' => $durasiCuti,
                    ]);
                    $this->info("Kuota cuti untuk user {$user->name} dan jenis cuti {$cuti->nama_cuti} berhasil dibuat.");
                }
            }
        }

        $this->info('Cuti quota generation completed successfully!');
        return 0;
    }
}
