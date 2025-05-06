<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('attendance_status', [
                'Hadir',
                'Terlambat',
                'Pulang Cepat',
                'Terlambat & Pulang Cepat',
                'Alpha', // Tidak ada check-in/out sama sekali
                'Sakit', // Diisi jika ada pengajuan Sakit (terpisah?) atau dari Cuti
                'Cuti', // Diisi dari data Cuti
                'Dinas Luar', // Diisi dari data Perjalanan Dinas
                'Lembur'
            ])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            //
        });
    }
};
