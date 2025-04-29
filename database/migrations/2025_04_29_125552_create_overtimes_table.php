<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menjalankan migrasi untuk membuat tabel overtimes.
     */
    public function up(): void
    {
        Schema::create('overtimes', function (Blueprint $table) {
            $table->id(); // Kolom id auto-increment primary key

            // Foreign key ke tabel users
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Jika user dihapus, data lembur ikut terhapus

            $table->date('tanggal_lembur');      // Tanggal pelaksanaan lembur
            $table->time('jam_mulai');           // Jam mulai lembur
            $table->time('jam_selesai');         // Jam selesai lembur
            $table->integer('durasi_menit')->nullable(); // Durasi dalam menit (dihitung otomatis)
            $table->text('uraian_pekerjaan');    // Deskripsi pekerjaan lembur

            // Status persetujuan (sama seperti cuti, termasuk cancelled)
            $table->enum('status', [
                'pending',
                'pending_manager_approval',
                'approved',
                'rejected',
                'cancelled' // Tambahkan status cancelled
            ])->default('pending');

            // Kolom untuk approval Asisten Manager
            $table->foreignId('approved_by_asisten_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at_asisten')->nullable();

             // Kolom untuk approval Manager
            $table->foreignId('approved_by_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at_manager')->nullable();

             // Kolom untuk rejection
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();

            // Kolom untuk catatan (misal alasan reject)
            $table->text('notes')->nullable();

            // Kolom untuk pelacakan reminder email
            $table->timestamp('last_reminder_sent_at')->nullable();

            $table->timestamps(); // Kolom created_at dan updated_at

            // Index untuk performa query (opsional tapi disarankan)
            $table->index('user_id');
            $table->index('tanggal_lembur');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     * Membatalkan migrasi dengan menghapus tabel.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtimes');
    }
};
