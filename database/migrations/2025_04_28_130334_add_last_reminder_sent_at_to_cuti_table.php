<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menjalankan migrasi untuk menambah kolom.
     */
    public function up(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            // Tambahkan kolom timestamp nullable setelah 'notes'
            $table->timestamp('last_reminder_sent_at')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     * Membatalkan migrasi dengan menghapus kolom.
     */
    public function down(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            // Pastikan kolom ada sebelum dihapus (jika rollback)
            if (Schema::hasColumn('cuti', 'last_reminder_sent_at')) {
                $table->dropColumn('last_reminder_sent_at');
            }
        });
    }
};
