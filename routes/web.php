<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

// Login routes
Route::get('login', [\App\Http\Controllers\LoginController::class, 'index'])->name('login');
Route::post('action-login', [\App\Http\Controllers\LoginController::class, 'actionLogin'])->name('action-login');

// register route
Route::get('register', [\App\Http\Controllers\RegisterController::class, 'index'])->name('register');
Route::post('register', [\App\Http\Controllers\RegisterController::class, 'store'])->name('action-register');

// index route
Route::get('index', [\App\Http\Controllers\DashboardController::class, 'index']);


Route::resource('personil', \App\Http\Controllers\UserController::class);
