<?php

use Illuminate\Support\Facades\Route;

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
});
