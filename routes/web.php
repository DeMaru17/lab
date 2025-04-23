<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;         // Import LoginController
use App\Http\Controllers\UserController;         // Import UserController
use App\Http\Controllers\DashboardController;    // Import DashboardController
use App\Http\Controllers\PerjalananDinasController; // Import PerjalananDinasController
use App\Http\Controllers\CutiQuotaController;    // Import CutiQuotaController
use App\Http\Controllers\CutiController;         // Import CutiController

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
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

    // Dashboard (Biasanya hanya perlu index)
    Route::resource('dashboard', DashboardController::class)->only(['index']);

    // User Management (Personil)
    Route::resource('personil', UserController::class);

    // Perjalanan Dinas
    Route::resource('perjalanan-dinas', PerjalananDinasController::class);

    // Cuti Quota Management (Hanya Index & Update sesuai kebutuhan user)
    Route::prefix('cuti-quota')->name('cuti-quota.')->group(function () {
        Route::get('/', [CutiQuotaController::class, 'index'])->name('index');
        // Lebih standar menggunakan PUT/PATCH untuk update
        Route::match(['put', 'patch'], '/{id}', [CutiQuotaController::class, 'update'])->name('update');
        // Jika tetap ingin pakai POST seperti sebelumnya:
        // Route::post('/{id}/update', [CutiQuotaController::class, 'update'])->name('update');
    });


    // Cuti (Pengajuan & List oleh User)
    Route::prefix('cuti')->name('cuti.')->group(function () {
        Route::get('/', [CutiController::class, 'index'])->name('index');
        Route::get('/create', [CutiController::class, 'create'])->name('create');
        Route::post('/', [CutiController::class, 'store'])->name('store'); // Standard: POST ke base URL resource
        Route::get('/get-quota-ajax', [CutiController::class, 'getQuota'])->name('getQuota.ajax'); // URL & Nama spesifik untuk AJAX
        Route::post('/{cuti}/cancel', [CutiController::class, 'cancel'])->name('cancel'); // Aksi Cancel

        // Route untuk Edit/Update (Nanti ditambahkan)
        Route::get('/{cuti}/edit', [CutiController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{cuti}', [CutiController::class, 'update'])->name('update');

        Route::get('/{cuti}/pdf', [CutiController::class, 'downloadPdf'])->name('pdf');

        // Route untuk Destroy (Jika pilih opsi delete)
        // Route::delete('/{cuti}', [CutiController::class, 'destroy'])->name('destroy');
    });


    // Cuti Approval (Khusus Manajemen)
    // Pisahkan grup agar bisa diberi middleware khusus (misal role:manajemen)
    // Middleware 'auth' sudah dicover oleh grup luar, tidak perlu diduplikasi
    Route::prefix('cuti-approval')->name('cuti.approval.')->group(function () {

        // Asisten Manager
        Route::get('/asisten', [CutiController::class, 'listForAsisten'])->name('asisten.list');
        Route::patch('/asisten/{cuti}/approve', [CutiController::class, 'approveAsisten'])->name('asisten.approve'); // Gunakan PATCH

        // Reject (Bisa oleh Asisten/Manager, otorisasi di controller)
        Route::post('/{cuti}/reject', [CutiController::class, 'reject'])->name('reject');

        // Manager (Nanti ditambahkan)
        Route::get('/manager', [CutiController::class, 'listForManager'])->name('manager.list');
        Route::patch('/manager/{cuti}/approve', [CutiController::class, 'approveManager'])->name('manager.approve');
    });
}); // Akhir middleware 'auth' group