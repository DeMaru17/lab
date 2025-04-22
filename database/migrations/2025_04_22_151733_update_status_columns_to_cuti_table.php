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
        Schema::table('cuti', function (Blueprint $table) {
            $table->enum('status', [
                'pending',                  // Baru diajukan / Menunggu Asisten Mgr
                'pending_manager_approval', // Disetujui Asisten Mgr / Menunggu Mgr
                'approved',                 // Disetujui Manager (Final)
                'rejected',
                'cancelled'                 // Dibatalkan oleh pengguna                  // Ditolak (oleh Asisten Mgr atau Mgr)
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            //
        });
    }
};
