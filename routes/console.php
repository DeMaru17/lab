<?php

// routes/console.php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\GenerateCutiQuota; // Pastikan use statement ada jika dipanggil via class
use Illuminate\Support\Facades\Schedule;   // Pastikan use statement Schedule ada
use Illuminate\Support\Facades\Log;         // Tambahkan Log jika belum ada

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
