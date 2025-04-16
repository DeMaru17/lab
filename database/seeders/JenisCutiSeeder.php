<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisCuti;

class JenisCutiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data jenis cuti
        $jenisCuti = [
            [
                'nama_cuti' => 'Cuti Tahunan',
                'durasi_default' => 12, // Default 12 hari per tahun
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti Khusus Perjalanan Dinas',
                'durasi_default' => 0, // Tidak ada default, dihitung berdasarkan perjalanan dinas
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti Pernikahan',
                'durasi_default' => 3, // 3 hari
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti Pernikahan Anak',
                'durasi_default' => 2, // 2 hari
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti Khitanan/Pembaptisan Anak',
                'durasi_default' => 2, // Default 2 hari
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti Sakit',
                'durasi_default' => 0, // Tidak ada default, tergantung kebijakan perusahaan
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti Melahirkan/Keguguran',
                'durasi_default' => 2, // Default 3 hari
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti suami/isteri, orang tua/mertua atau anak atau menantu meninggal dunia',
                'durasi_default' => 2, // Default 2 hari
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cuti' => 'Cuti anggota keluarga dalam satu rumah meninggal dunia',
                'durasi_default' => 1, // Default 1 hari
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ];

        // Insert data ke tabel jenis_cuti
        JenisCuti::insert($jenisCuti);
    }
}
