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
        Schema::table('users', function (Blueprint $table) {
            // Tambahkan kolom untuk path tanda tangan (setelah 'jabatan')
            $table->string('signature_path')->nullable()->after('jabatan');

            // Tambahkan kolom untuk nama vendor (setelah 'signature_path')
            $table->string('vendor')->nullable()->after('signature_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom dalam urutan terbalik
            $table->dropColumn(['vendor', 'signature_path']);
        });
    }
};
