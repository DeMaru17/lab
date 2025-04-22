<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            // Ubah enum status untuk mengakomodasi state baru
            $table->enum('status', [
                'pending',                  // Baru diajukan / Menunggu Asisten Mgr
                'pending_manager_approval', // Disetujui Asisten Mgr / Menunggu Mgr
                'approved',                 // Disetujui Manager (Final)
                'rejected'                  // Ditolak (oleh Asisten Mgr atau Mgr)
            ])->default('pending')->change(); // Ubah kolom status yang ada

            // Kolom untuk approval Asisten Manager
            $table->foreignId('approved_by_asisten_id')->nullable()->constrained('users')->onDelete('set null')->after('status');
            $table->timestamp('approved_at_asisten')->nullable()->after('approved_by_asisten_id');

            // Kolom untuk approval Manager
            $table->foreignId('approved_by_manager_id')->nullable()->constrained('users')->onDelete('set null')->after('approved_at_asisten');
            $table->timestamp('approved_at_manager')->nullable()->after('approved_by_manager_id');

            // Kolom untuk rejection
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->onDelete('set null')->after('approved_at_manager');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_id');

            // Index untuk kolom foreign key (opsional tapi bagus untuk performa)
            $table->index('approved_by_asisten_id');
            $table->index('approved_by_manager_id');
            $table->index('rejected_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            // Hati-hati saat rollback, tentukan status default lama
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();

            // Hapus foreign key constraint dulu sebelum drop kolom
            $table->dropForeign(['approved_by_asisten_id']);
            $table->dropForeign(['approved_by_manager_id']);
            $table->dropForeign(['rejected_by_id']);

            // Hapus kolom (dalam urutan terbalik dari penambahan)
            $table->dropColumn([
                'rejected_at',
                'rejected_by_id',
                'approved_at_manager',
                'approved_by_manager_id',
                'approved_at_asisten',
                'approved_by_asisten_id'
            ]);
        });
    }
};
