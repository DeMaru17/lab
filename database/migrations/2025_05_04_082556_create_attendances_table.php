<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menjalankan migrasi untuk membuat tabel attendances.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            // Relasi ke user dan shift
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict'); // Shift yg dipilih saat check-in

            $table->date('attendance_date'); // Tanggal absensi

            // Data Check-in
            $table->dateTime('clock_in_time')->nullable(); // Waktu check-in aktual (pakai dateTime)
            $table->decimal('clock_in_latitude', 10, 8)->nullable(); // Latitude check-in
            $table->decimal('clock_in_longitude', 11, 8)->nullable(); // Longitude check-in
            $table->string('clock_in_photo_path')->nullable(); // Path foto selfie check-in
            $table->enum('clock_in_location_status', ['Dalam Radius', 'Luar Radius', 'Tidak Diketahui'])->nullable(); // Status lokasi check-in

            // Data Check-out
            $table->dateTime('clock_out_time')->nullable(); // Waktu check-out aktual (pakai dateTime)
            $table->decimal('clock_out_latitude', 10, 8)->nullable(); // Latitude check-out
            $table->decimal('clock_out_longitude', 11, 8)->nullable(); // Longitude check-out
            $table->string('clock_out_photo_path')->nullable(); // Path foto selfie check-out
            $table->enum('clock_out_location_status', ['Dalam Radius', 'Luar Radius', 'Tidak Diketahui'])->nullable(); // Status lokasi check-out

            // Status Kehadiran (diisi oleh scheduled task)
            $table->enum('attendance_status', [
                'Hadir',
                'Terlambat',
                'Pulang Cepat',
                'Terlambat & Pulang Cepat',
                'Alpha', // Tidak ada check-in/out sama sekali
                'Sakit', // Diisi jika ada pengajuan Sakit (terpisah?) atau dari Cuti
                'Cuti', // Diisi dari data Cuti
                'Dinas Luar' // Diisi dari data Perjalanan Dinas
            ])->nullable();

            $table->text('notes')->nullable(); // Catatan (misal: Telat X menit, Pulang Cepat Y menit, alasan koreksi)
            $table->boolean('is_corrected')->default(false); // Tanda jika data ini hasil koreksi

            $table->timestamps(); // created_at, updated_at

            // Index
            $table->index(['user_id', 'attendance_date']);
            $table->index('attendance_status');
            // Unique constraint untuk mencegah double check-in/out pada hari yang sama per user
            $table->unique(['user_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     * Membatalkan migrasi dengan menghapus tabel.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
