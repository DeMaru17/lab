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

Schedule::call(function () {
    $targetDate = Carbon::now()->subMonth(); // Ambil bulan lalu
    $month = $targetDate->month;
    $year = $targetDate->year;
    // Panggil command dengan group internal dan tdp, serta bulan & tahun lalu
    Artisan::call('timesheet:generate-monthly', [
        '--group' => ['internal', 'tdp'], // Target grup
        '--month' => $month,
        '--year' => $year
    ]);
})
    ->monthlyOn(1, '03:00') // Setiap tanggal 1 jam 3 pagi
    ->timezone('Asia/Jakarta')
    ->name('generate-timesheet-internal-tdp') // Beri nama unik
    ->withoutOverlapping(120) // Timeout 120 menit jika perlu
    ->onSuccess(function () {
        Log::info('Scheduled task generate-timesheet-internal-tdp completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Scheduled task generate-timesheet-internal-tdp failed.');
    });


// 2. Generate untuk Vendor CSI (Periode: Tgl 16 Bulan Lalu s/d Tgl 15 Bulan Ini)
//    Jalankan setiap tanggal 16, jam 03:15 pagi
Schedule::call(function () {
    // Target tanggal untuk getUserPeriod adalah bulan ini,
    // karena periode CSI berakhir di bulan ini (tanggal 15)
    $targetDate = Carbon::now();
    $month = $targetDate->month;
    $year = $targetDate->year;
    // Panggil command hanya untuk grup csi, dengan bulan & tahun ini
    Artisan::call('timesheet:generate-monthly', [
        '--group' => ['csi'], // Target grup csi
        '--month' => $month,
        '--year' => $year
    ]);
})
    ->monthlyOn(16, '03:15') // Setiap tanggal 16 jam 3:15 pagi
    ->timezone('Asia/Jakarta')
    ->name('generate-timesheet-csi') // Beri nama unik
    ->withoutOverlapping(120)
    ->onSuccess(function () {
        Log::info('Scheduled task generate-timesheet-csi completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Scheduled task generate-timesheet-csi failed.');
    });
