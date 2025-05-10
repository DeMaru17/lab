<?php

// routes/console.php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\GenerateCutiQuota; // Pastikan use statement ada jika dipanggil via class
use Illuminate\Support\Facades\Schedule;   // Pastikan use statement Schedule ada
use Illuminate\Support\Facades\Log;         // Tambahkan Log jika belum ada
use Carbon\Carbon; // Import Carbon untuk manipulasi tanggal

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cuti:generate-quota', function () {
    // Memanggil command via class lebih disarankan daripada closure jika logic kompleks
    $this->call('cuti:generate-quota'); // Panggil signature command-nya
    // Atau jika Anda punya command class:
    // (new GenerateCutiQuota)->handle(); // Atau cara ini jika command tidak terdaftar global
});


// --- PENJADWALAN COMMAND ---

// Jadwal yang sudah ada:
Schedule::command('leave:grant-annual')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta'); // Tambahkan timezone

Schedule::command('leave:refresh-all-quotas')
    ->yearlyOn(1, 1, '02:00')
    ->timezone('Asia/Jakarta'); // Tambahkan timezone

Schedule::command('reminders:send-overdue')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta'); // Tambahkan timezone


Schedule::command('attendance:process-daily') // Panggil signature command
    ->dailyAt('08:00')                // Waktu eksekusi
    ->timezone('Asia/Jakarta')        // Timezone
    ->withoutOverlapping()            // Hindari tumpang tindih
    ->onSuccess(function () {         // Logging Sukses (Opsional)
        Log::info('Scheduled task attendance:process-daily completed successfully.');
    })
    ->onFailure(function () {         // Logging Gagal (Opsional)
        Log::error('Scheduled task attendance:process-daily failed.');
        // Mungkin tambahkan notifikasi ke admin jika gagal?
    });

// --- PENJADWALAN TIMESHEET ---

// 1. Jadwal Harian untuk Re-evaluasi Timesheet Rejected
//    (Bulan Lalu & Bulan Ini untuk mengakomodasi semua periode vendor)
Schedule::command('timesheet:generate-monthly', [
    '--month' => now()->subMonthNoOverflow()->month, // Bulan lalu
    '--year' => now()->subMonthNoOverflow()->year,
    '--group' => ['internal', 'csi', 'tdp'] // Proses semua grup
])->dailyAt('01:00') // Setiap hari jam 1 pagi
    ->timezone('Asia/Jakarta')
    ->name('re_evaluate_prev_month_timesheets')
    ->withoutOverlapping(60);

Schedule::command('timesheet:generate-monthly', [
    '--month' => now()->month, // Bulan ini
    '--year' => now()->year,
    '--group' => ['internal', 'csi', 'tdp'] // Proses semua grup
])->dailyAt('01:15') // Setiap hari jam 1:15 pagi
    ->timezone('Asia/Jakarta')
    ->name('re_evaluate_curr_month_timesheets')
    ->withoutOverlapping(60);


// 2. Jadwal Generasi Awal (Seperti yang sudah Anda miliki)
//    Untuk Internal & TDP (Periode Bulan Lalu) - Setiap Tanggal 1
Schedule::call(function () {
    $targetDate = Carbon::now()->subMonthNoOverflow();
    Log::info("Scheduling initial_generate_timesheet_internal_tdp for " . $targetDate->format('F Y'));
    Artisan::call('timesheet:generate-monthly', [
        '--group' => ['internal', 'tdp'],
        '--month' => $targetDate->month,
        '--year' => $targetDate->year
        // Tambahkan --force jika Anda ingin generasi awal selalu menimpa data yang mungkin sudah ada (hati-hati)
        // '--force' => true
    ]);
})->monthlyOn(1, '03:00') // Setiap tanggal 1 jam 3 pagi
    ->timezone('Asia/Jakarta')
    ->name('initial_generate_timesheet_internal_tdp')
    ->withoutOverlapping(120)
    ->onSuccess(function () {
        Log::info('Scheduled task initial_generate_timesheet_internal_tdp completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Scheduled task initial_generate_timesheet_internal_tdp failed.');
    });

// Untuk Vendor CSI (Periode Potongan Bulan Ini) - Setiap Tanggal 16
Schedule::call(function () {
    $targetDate = Carbon::now(); // Untuk CSI, target bulan ini
    Log::info("Scheduling initial_generate_timesheet_csi for " . $targetDate->format('F Y'));
    Artisan::call('timesheet:generate-monthly', [
        '--group' => ['csi'],
        '--month' => $targetDate->month,
        '--year' => $targetDate->year
        // '--force' => true // idem
    ]);
})->monthlyOn(16, '03:15') // Setiap tanggal 16 jam 3:15 pagi
    ->timezone('Asia/Jakarta')
    ->name('initial_generate_timesheet_csi')
    ->withoutOverlapping(120)
    ->onSuccess(function () {
        Log::info('Scheduled task initial_generate_timesheet_csi completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Scheduled task initial_generate_timesheet_csi failed.');
    });

Schedule::command('attendance:delete-old-selfies')
    ->dailyAt('02:30') // Setiap hari jam 2:30 pagi
    ->timezone('Asia/Jakarta')
    ->name('delete_old_attendance_selfies')
    ->withoutOverlapping(60) // Timeout 60 menit jika task berjalan lama
    ->onSuccess(function () {
        Log::info('Scheduled task attendance:delete-old-selfies completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Scheduled task attendance:delete-old-selfies failed.');
    });
