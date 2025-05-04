<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menjalankan migrasi untuk membuat tabel shifts.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id(); // Primary key auto-increment
            $table->string('name')->unique(); // Nama shift unik (cth: "Shift 1", "Shift 2 Pria")
            $table->time('start_time'); // Jam mulai shift
            $table->time('end_time');   // Jam selesai shift
            $table->boolean('crosses_midnight')->default(false); // Tanda jika shift melewati tengah malam
            $table->enum('applicable_gender', ['Semua', 'Laki-laki', 'Perempuan'])->default('Semua'); // Untuk siapa shift ini berlaku
            $table->boolean('is_active')->default(true); // Status aktif shift
            $table->timestamps(); // created_at dan updated_at (opsional)
        });
    }

    /**
     * Reverse the migrations.
     * Membatalkan migrasi dengan menghapus tabel.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
