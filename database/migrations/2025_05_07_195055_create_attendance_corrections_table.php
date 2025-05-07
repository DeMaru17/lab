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
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade'); // Bisa null jika koreksi Alpha murni
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User yang mengajukan
            $table->date('correction_date'); // Tanggal absensi yg dikoreksi
            $table->time('requested_clock_in')->nullable(); // Jam masuk yg diajukan (pakai TIME)
            $table->time('requested_clock_out')->nullable(); // Jam keluar yg diajukan (pakai TIME)
            $table->foreignId('requested_shift_id')->nullable()->constrained('shifts')->onDelete('set null'); // Shift yg seharusnya (jika ingin dikoreksi juga)
            $table->text('reason'); // Alasan pengajuan koreksi
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null'); // User (Asisten) yg memproses
            $table->timestamp('processed_at')->nullable(); // Waktu approve/reject
            $table->text('reject_reason')->nullable(); // Alasan jika ditolak
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
