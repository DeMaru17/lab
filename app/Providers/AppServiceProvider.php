<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // <-- Import Gate Facade
use App\Models\Cuti;                 // <-- Import model Cuti
use App\Policies\CutiPolicy;         // <-- Import CutiPolicy
use App\Models\PerjalananDinas;      // <-- Import model PerjalananDinas
use App\Policies\PerjalananDinasPolicy; // <-- Import PerjalananDinasPolicy
use Illuminate\Pagination\Paginator;
use App\Models\Overtime;            // <-- Import model Overtime
use App\Policies\OvertimePolicy;    // <-- Import OvertimePolicy
use App\Models\AttendanceCorrection; // <-- Import model AttendanceCorrection
use App\Policies\AttendanceCorrectionPolicy; // <-- Import AttendanceCorrectionPolicy
use App\Models\MonthlyTimesheet;   // <-- Import model MonthlyTimesheet
use App\Policies\MonthlyTimesheetPolicy; // <-- Import MonthlyTimesheetPolicy

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Daftarkan Policy di sini
        Gate::policy(Cuti::class, CutiPolicy::class);
        Gate::policy(PerjalananDinas::class, PerjalananDinasPolicy::class);
        Gate::policy(Overtime::class, OvertimePolicy::class);
        Gate::policy(AttendanceCorrection::class, AttendanceCorrectionPolicy::class);
        Gate::policy(MonthlyTimesheet::class, MonthlyTimesheetPolicy::class);
        Paginator::useBootstrapFive();

        // Daftarkan policy lain jika ada
        // Gate::policy(User::class, UserPolicy::class);
        // Gate::policy(Vendor::class, VendorPolicy::class);
    }
}
