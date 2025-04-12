<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

// Login routes
Route::get('login', [\App\Http\Controllers\LoginController::class, 'index'])->name('login');
Route::post('action-login', [\App\Http\Controllers\LoginController::class, 'actionLogin'])->name('action-login');


// index route
Route::get('index', [\App\Http\Controllers\DashboardController::class, 'index']);



// Route::resource('perjalanan-dinas', \App\Http\Controllers\PerjalananDinasController::class);


Route::middleware('auth')->group(function () {
    Route::resource('personil', \App\Http\Controllers\UserController::class);
    Route::resource('perjalanan-dinas', \App\Http\Controllers\PerjalananDinasController::class);
});
