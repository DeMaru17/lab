<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shift; // <-- Import model Shift
use Illuminate\Support\Facades\DB; // <-- Import DB jika ingin truncate

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Mengisi tabel shifts dengan data shift kerja.
     */
    public function run(): void
    {
        $this->command->info('Memulai Shift Seeder...');

        // Opsional: Kosongkan tabel sebelum mengisi (hati-hati jika ada relasi)
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Shift::truncate();
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Data shift sesuai informasi Anda
        $shifts = [
            [
                'name' => 'Shift 1',
                'start_time' => '07:30:00',
                'end_time' => '16:30:00',
                'crosses_midnight' => false,
                'applicable_gender' => 'Semua',
                'is_active' => true,
            ],
            [
                'name' => 'Shift 2 Laki-laki', // Nama spesifik untuk Pria
                'start_time' => '14:00:00',
                'end_time' => '23:00:00',
                'crosses_midnight' => false,
                'applicable_gender' => 'Laki-laki', // Hanya untuk Pria
                'is_active' => true,
            ],
            [
                'name' => 'Shift 2 Perempuan', // Nama spesifik untuk Wanita
                'start_time' => '13:00:00',
                'end_time' => '22:00:00',
                'crosses_midnight' => false,
                'applicable_gender' => 'Perempuan', // Hanya untuk Wanita
                'is_active' => true,
            ],
            [
                'name' => 'Shift 3', // Shift malam
                'start_time' => '22:00:00',
                'end_time' => '07:00:00', // Selesai keesokan harinya
                'crosses_midnight' => true,  // Tandai melewati tengah malam
                'applicable_gender' => 'Laki-laki', // Hanya untuk Pria
                'is_active' => true,
            ],
        ];

        // Masukkan data menggunakan updateOrCreate
        $count = 0;
        foreach ($shifts as $shiftData) {
            Shift::updateOrCreate(
                ['name' => $shiftData['name']], // Cari berdasarkan nama shift
                $shiftData // Data lengkap untuk create atau update
            );
            $count++;
        }

        $this->command->info("Shift Seeder selesai. {$count} shift berhasil diproses/dibuat.");
    }
}
