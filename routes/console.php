<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\GenerateCutiQuota;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cuti:generate-quota', function () {
    (new GenerateCutiQuota)->handle();
});


Schedule::command('leave:grant-annual')->dailyAt('01:00');
// -----------------------------------------
Schedule::command('leave:refresh-all-quotas')->yearlyOn(1, 1, '02:00');

Schedule::command('leave:send-overdue-reminders')->dailyAt('08:00');
