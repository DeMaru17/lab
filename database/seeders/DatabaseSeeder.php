<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {


        $this->call([
            // CutiSeeder::class,
            // JenisCutiSeeder::class,
            // CutiQuotaSeeder::class,
            // HolidaySeeder::class,
            VendorSeeder::class,
        ]);
        $this->command->info('Semua seeder default berhasil dijalankan.');
    }
}
