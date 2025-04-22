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
        // Modifikasi skema tabel 'cuti'
        Schema::table('cuti', function (Blueprint $table) {
            // Tambahkan kolom 'surat_sakit' setelah kolom 'alamat_selama_cuti'
            // Tipe string cocok untuk path file, nullable karena tidak selalu wajib
            $table->string('surat_sakit')->nullable()->after('alamat_selama_cuti');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Modifikasi skema tabel 'cuti' untuk membatalkan perubahan
        Schema::table('cuti', function (Blueprint $table) {
            // Hapus kolom 'surat_sakit' jika migration di-rollback
            $table->dropColumn('surat_sakit');
        });
    }
};
