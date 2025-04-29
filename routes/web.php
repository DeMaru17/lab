<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;         // Import LoginController
use App\Http\Controllers\UserController;         // Import UserController
use App\Http\Controllers\DashboardController;    // Import DashboardController
use App\Http\Controllers\PerjalananDinasController; // Import PerjalananDinasController
use App\Http\Controllers\CutiQuotaController;    // Import CutiQuotaController
use App\Http\Controllers\CutiController;         // Import CutiController
use App\Http\Controllers\VendorController;       // Import VendorController
use App\Http\Controllers\OvertimeController;    // Import OvertimeController
use App\Http\Controllers\OvertimeRecapController; // Import OvertimeRecapController

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

    Route::prefix('overtimes')->name('overtimes.')->group(function () { // Grup untuk route lembur
        Route::get('/', [OvertimeController::class, 'index'])->name('index');
        Route::get('/create', [OvertimeController::class, 'create'])->name('create');
        Route::post('/', [OvertimeController::class, 'store'])->name('store');
        Route::get('/{overtime}/edit', [OvertimeController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{overtime}', [OvertimeController::class, 'update'])->name('update');
        Route::delete('/{overtime}', [OvertimeController::class, 'destroy'])->name('destroy'); // Jika pakai resource, ini sudah ada
        Route::post('/{overtime}/cancel', [OvertimeController::class, 'cancel'])->name('cancel');
        Route::post('/bulk-pdf', [OvertimeController::class, 'bulkDownloadPdf'])->name('bulk.pdf');

        // === TAMBAHKAN ROUTE PDF LEMBUR ===
        Route::get('/{overtime}/pdf', [OvertimeController::class, 'downloadOvertimePdf'])->name('pdf');
        // === AKHIR ROUTE PDF LEMBUR ===
    });




    // Lembur Approval
    Route::prefix('overtime-approval')->name('overtimes.approval.')->middleware(['role:manajemen'])->group(function () {
        // ... (Route approval lembur: asisten.list, asisten.approve, manager.list, manager.approve, reject, bulk.approve) ...
        Route::get('/asisten', [OvertimeController::class, 'listForAsisten'])->name('asisten.list');
        Route::patch('/asisten/{overtime}/approve', [OvertimeController::class, 'approveAsisten'])->name('asisten.approve');
        Route::get('/manager', [OvertimeController::class, 'listForManager'])->name('manager.list');
        Route::patch('/manager/{overtime}/approve', [OvertimeController::class, 'approveManager'])->name('manager.approve');
        Route::post('/{overtime}/reject', [OvertimeController::class, 'reject'])->name('reject');
        Route::post('/bulk-approve', [OvertimeController::class, 'bulkApprove'])->name('bulk.approve');
    });

    Route::prefix('overtime-recap')->name('overtimes.recap.')->group(function () {
        // Halaman utama rekap (menampilkan filter & hasil)
        Route::get('/', [OvertimeRecapController::class, 'index'])->name('index');
        // Aksi untuk ekspor ke Excel
        Route::get('/export', [OvertimeRecapController::class, 'export'])->name('export');
    });
}); // Akhir middleware 'auth' group
