<?php

use App\Http\Controllers\v1\Auth\LoginController;
use App\Http\Controllers\v1\Auth\LogoutController;
use App\Http\Controllers\v1\Auth\SignupController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
->as('auth.')
->group(function () {
    Route::post('login', LoginController::class)->name('login');

    Route::post('logout', LogoutController::class)
    ->middleware(['auth:web'])
    ->name('logout');

    Route::post('signup', SignupController::class)->name('signup');
});
