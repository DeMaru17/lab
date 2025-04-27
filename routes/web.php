<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;         // Import LoginController
use App\Http\Controllers\UserController;         // Import UserController
use App\Http\Controllers\DashboardController;    // Import DashboardController
use App\Http\Controllers\PerjalananDinasController; // Import PerjalananDinasController
use App\Http\Controllers\CutiQuotaController;    // Import CutiQuotaController
use App\Http\Controllers\CutiController;         // Import CutiController
use App\Http\Controllers\VendorController;       // Import VendorController

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// == Public Routes ==
Route::get('/', function () {
    return view('auth.login');
});
Route::get('login', [LoginController::class, 'index'])->name('login');
Route::post('action-login', [LoginController::class, 'actionLogin'])->name('action-login');


// == Authenticated Routes ==
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard
    Route::resource('dashboard', DashboardController::class)->only(['index']);

    // User Management (Personil) - Asumsi bisa diakses Admin?
    // Jika hanya admin, bungkus dengan middleware role:admin
    Route::middleware(['role:admin'])->group(function () { // <-- Middleware untuk Admin
        Route::resource('personil', UserController::class);
        Route::resource('vendors', VendorController::class); // Pindahkan Vendor ke sini
    });

    // Perjalanan Dinas - Asumsi bisa diakses semua user terotentikasi?
    // Jika perlu pembatasan, tambahkan middleware role di sini atau di controllernya
    Route::resource('perjalanan-dinas', PerjalananDinasController::class)
        ->parameters(['perjalanan-dinas' => 'perjalananDina']);


    // Cuti Quota Management
    Route::prefix('cuti-quota')->name('cuti-quota.')->group(function () {
        // Index bisa dilihat semua (controller handle view per role)
        Route::get('/', [CutiQuotaController::class, 'index'])->name('index');

        // Update hanya oleh Admin
        Route::middleware(['role:admin'])->group(function () { // <-- Middleware Admin untuk Update
            Route::match(['put', 'patch'], '/{id}', [CutiQuotaController::class, 'update'])->name('update');
            // Jika pakai POST:
            // Route::post('/{id}/update', [CutiQuotaController::class, 'update'])->name('update');
        });
    });


    // Cuti (Pengajuan & List oleh User) - Tetap di bawah auth saja
    Route::prefix('cuti')->name('cuti.')->group(function () {
        Route::get('/', [CutiController::class, 'index'])->name('index');
        Route::get('/create', [CutiController::class, 'create'])->name('create');
        Route::post('/', [CutiController::class, 'store'])->name('store');
        Route::get('/get-quota-ajax', [CutiController::class, 'getQuota'])->name('getQuota.ajax');
        Route::post('/{cuti}/cancel', [CutiController::class, 'cancel'])->name('cancel');
        Route::get('/{cuti}/edit', [CutiController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{cuti}', [CutiController::class, 'update'])->name('update');
        Route::get('/{cuti}/pdf', [CutiController::class, 'downloadPdf'])->name('pdf');
    });


    // Cuti Approval (Khusus Manajemen)
    Route::prefix('cuti-approval')->name('cuti.approval.')
        ->middleware(['role:manajemen']) // <-- Terapkan Middleware Manajemen di sini
        ->group(function () {
            // Asisten Manager
            Route::get('/asisten', [CutiController::class, 'listForAsisten'])->name('asisten.list');
            Route::patch('/asisten/{cuti}/approve', [CutiController::class, 'approveAsisten'])->name('asisten.approve');

            // Manager
            Route::get('/manager', [CutiController::class, 'listForManager'])->name('manager.list');
            Route::patch('/manager/{cuti}/approve', [CutiController::class, 'approveManager'])->name('manager.approve');

            // Reject (Bisa oleh Asisten/Manager, otorisasi di controller)
            Route::post('/{cuti}/reject', [CutiController::class, 'reject'])->name('reject');
        });
}); // Akhir middleware 'auth' group