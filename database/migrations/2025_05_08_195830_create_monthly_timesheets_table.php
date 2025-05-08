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
        Schema::create('monthly_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->onDelete('set null');
            $table->date('period_start_date'); // Tanggal mulai periode rekap
            $table->date('period_end_date');   // Tanggal akhir periode rekap

            // Kolom untuk rekapitulasi data kehadiran
            $table->integer('total_work_days')->default(0)->comment('Jumlah hari kerja seharusnya dalam periode');
            $table->integer('total_present_days')->default(0)->comment('Jumlah hari hadir (Hadir, Terlambat, Pulang Cepat)');
            $table->integer('total_late_days')->default(0)->comment('Jumlah hari Terlambat / Terlambat & Pulang Cepat');
            $table->integer('total_early_leave_days')->default(0)->comment('Jumlah hari Pulang Cepat / Terlambat & Pulang Cepat');
            $table->integer('total_alpha_days')->default(0)->comment('Jumlah hari Alpha (termasuk tidak lengkap)');
            $table->integer('total_leave_days')->default(0)->comment('Jumlah hari Cuti (termasuk Sakit)');
            $table->integer('total_duty_days')->default(0)->comment('Jumlah hari Dinas Luar');
            $table->integer('total_holiday_duty_days')->default(0)->comment('Jumlah hari Lembur di hari libur/weekend'); // Hari Lembur di Libur

            // Kolom untuk rekapitulasi lembur
            $table->integer('total_overtime_minutes')->default(0)->comment('Total menit lembur (approved) dalam periode');
            $table->integer('total_overtime_occurrences')->default(0)->comment('Jumlah pengajuan lembur (approved) dalam periode');

            // Kolom untuk alur approval timesheet
            $table->enum('status', [
                'generated', // Baru dibuat oleh sistem/command
                'pending_asisten', // Menunggu approval Asisten
                'pending_manager', // Menunggu approval Manager
                'approved',        // Disetujui Manager
                'rejected'         // Ditolak (oleh Asisten atau Manager)
            ])->default('generated'); // Status awal

            $table->foreignId('approved_by_asisten_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at_asisten')->nullable();
            $table->foreignId('approved_by_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at_manager')->nullable();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable()->comment('Catatan approval/rejection timesheet'); // Catatan dari approver/rejecter

            $table->timestamp('generated_at')->useCurrent()->comment('Waktu rekap ini dibuat'); // Kapan rekap digenerate
            $table->timestamps(); // created_at, updated_at

            // Unique constraint untuk memastikan hanya ada 1 rekap per user per periode
            $table->unique(['user_id', 'period_start_date', 'period_end_date'], 'user_period_unique');
            // Index untuk query umum
            $table->index(['period_start_date', 'period_end_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_timesheets');
    }
};
