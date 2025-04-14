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
        Schema::create('cuti', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('user_id'); // Foreign key ke tabel users
            $table->unsignedBigInteger('jenis_cuti_id'); // Foreign key ke tabel jenis_cuti
            $table->date('mulai_cuti'); // Tanggal mulai cuti
            $table->date('selesai_cuti'); // Tanggal selesai cuti
            $table->integer('lama_cuti'); // Lama cuti (dalam hari)
            $table->text('alasan'); // Alasan pengajuan cuti
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // Status pengajuan
            $table->text('notes')->nullable(); // Catatan dari manajemen
            $table->timestamps(); // created_at and updated_at

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('jenis_cuti_id')->references('id')->on('jenis_cuti')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuti');
    }
};
