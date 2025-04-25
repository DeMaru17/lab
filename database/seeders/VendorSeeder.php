<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vendor;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        Vendor::updateOrCreate(['name' => 'PT Trans Dana Profitri']); // Contoh vendor internal
        Vendor::updateOrCreate(['name' => 'PT Cakra Satya Internusa']);
    }
}
