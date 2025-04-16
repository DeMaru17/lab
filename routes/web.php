<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CutiQuotaController;
use App\Http\Controllers\CutiController;

Route::get('/', function () {
    return view('auth.login');
});

// Login routes
Route::get('login', [\App\Http\Controllers\LoginController::class, 'index'])->name('login');
Route::post('action-login', [\App\Http\Controllers\LoginController::class, 'actionLogin'])->name('action-login');


Route::resource('personil', \App\Http\Controllers\UserController::class);
Route::middleware('auth')->group(function () {
    Route::post('/logout', [App\Http\Controllers\LoginController::class, 'logout'])->name('logout');
    Route::resource('dashboard', \App\Http\Controllers\DashboardController::class);

    Route::resource('perjalanan-dinas', \App\Http\Controllers\PerjalananDinasController::class);
    // Menampilkan kuota cuti
    Route::get('/cuti/kuota', [CutiQuotaController::class, 'index'])->name('cuti.quota.index');

    // Mengupdate kuota cuti
    Route::post('/cuti/kuota/{id}/update', [CutiQuotaController::class, 'update'])->name('cuti.quota.update');

    Route::get('/cuti', [CutiController::class, 'index'])->name('cuti.index');
    Route::get('/cuti/create', [CutiController::class, 'create'])->name('cuti.create');
    Route::post('/cuti/store', [CutiController::class, 'store'])->name('cuti.store');
    Route::get('/cuti/quota', [CutiController::class, 'getQuota'])->name('cuti.quota');
});
