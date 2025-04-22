<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Holiday; // Import model Holiday
use Illuminate\Support\Facades\DB; // Jika ingin pakai DB facade

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar Hari Libur Nasional dan Cuti Bersama 2025 (Berdasarkan update)
        $holidays2025 = [
            // Libur Nasional 2025
            ['tanggal' => '2025-01-01', 'nama_libur' => 'Tahun Baru 2025 Masehi'],
            ['tanggal' => '2025-01-27', 'nama_libur' => 'Isra Miraj Nabi Muhammad SAW'],
            ['tanggal' => '2025-01-29', 'nama_libur' => 'Tahun Baru Imlek 2576 Kongzili'],
            ['tanggal' => '2025-03-29', 'nama_libur' => 'Hari Suci Nyepi Tahun Baru Saka 1947'], // Sabtu
            ['tanggal' => '2025-03-31', 'nama_libur' => 'Hari Raya Idul Fitri 1446 H (Hari ke-1)'],
            ['tanggal' => '2025-04-01', 'nama_libur' => 'Hari Raya Idul Fitri 1446 H (Hari ke-2)'],
            ['tanggal' => '2025-04-18', 'nama_libur' => 'Wafat Isa Al Masih'],
            ['tanggal' => '2025-04-20', 'nama_libur' => 'Kebangkitan Yesus Kristus (Paskah)'], // Minggu
            ['tanggal' => '2025-05-01', 'nama_libur' => 'Hari Buruh Internasional'],
            ['tanggal' => '2025-05-12', 'nama_libur' => 'Hari Raya Waisak 2569 BE'],
            ['tanggal' => '2025-05-29', 'nama_libur' => 'Kenaikan Yesus Kristus'],
            ['tanggal' => '2025-06-01', 'nama_libur' => 'Hari Lahir Pancasila'], // Minggu
            ['tanggal' => '2025-06-06', 'nama_libur' => 'Hari Raya Idul Adha 1446 H'],
            ['tanggal' => '2025-06-27', 'nama_libur' => 'Tahun Baru Islam 1447 H'],
            ['tanggal' => '2025-08-17', 'nama_libur' => 'Hari Kemerdekaan Republik Indonesia'], // Minggu
            ['tanggal' => '2025-09-05', 'nama_libur' => 'Maulid Nabi Muhammad SAW'],
            ['tanggal' => '2025-12-25', 'nama_libur' => 'Hari Raya Natal'],

            // Cuti Bersama 2025
            ['tanggal' => '2025-01-28', 'nama_libur' => 'Cuti Bersama Tahun Baru Imlek 2576 Kongzili'],
            ['tanggal' => '2025-03-28', 'nama_libur' => 'Cuti Bersama Hari Suci Nyepi Tahun Baru Saka 1947'],
            ['tanggal' => '2025-04-02', 'nama_libur' => 'Cuti Bersama Idul Fitri 1446 H'],
            ['tanggal' => '2025-04-03', 'nama_libur' => 'Cuti Bersama Idul Fitri 1446 H'],
            ['tanggal' => '2025-04-04', 'nama_libur' => 'Cuti Bersama Idul Fitri 1446 H'],
            ['tanggal' => '2025-04-07', 'nama_libur' => 'Cuti Bersama Idul Fitri 1446 H'],
            ['tanggal' => '2025-05-13', 'nama_libur' => 'Cuti Bersama Hari Raya Waisak 2569 BE'],
            ['tanggal' => '2025-05-30', 'nama_libur' => 'Cuti Bersama Kenaikan Yesus Kristus'],
            ['tanggal' => '2025-06-09', 'nama_libur' => 'Cuti Bersama Idul Adha 1446 H'],
            ['tanggal' => '2025-12-26', 'nama_libur' => 'Cuti Bersama Hari Raya Natal'],
        ];

        // Kosongkan data tahun 2025 saja sebelum diisi ulang (lebih aman)
        Holiday::whereYear('tanggal', 2025)->delete();
        // Atau kosongkan semua: Holiday::query()->delete();

        // Masukkan data ke tabel menggunakan Eloquent (update jika sudah ada, create jika belum)
        foreach ($holidays2025 as $holiday) {
            Holiday::updateOrCreate(
                ['tanggal' => $holiday['tanggal']], // Kondisi pencarian (primary key)
                ['nama_libur' => $holiday['nama_libur']] // Data yang di-create atau di-update
            );
        }

        $this->command->info('Seeder Hari Libur 2025 (update) berhasil dijalankan.');
    }
}
