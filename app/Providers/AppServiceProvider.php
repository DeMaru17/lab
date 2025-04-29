<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // <-- Import Gate Facade
use App\Models\Cuti;                 // <-- Import model Cuti
use App\Policies\CutiPolicy;         // <-- Import CutiPolicy
use App\Models\PerjalananDinas;      // <-- Import model PerjalananDinas
use App\Policies\PerjalananDinasPolicy; // <-- Import PerjalananDinasPolicy
use Illuminate\Pagination\Paginator;

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
        Paginator::useBootstrapFive();

        // Daftarkan policy lain jika ada
        // Gate::policy(User::class, UserPolicy::class);
        // Gate::policy(Vendor::class, VendorPolicy::class);
    }
}
