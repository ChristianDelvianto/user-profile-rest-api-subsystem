<?php

use App\Http\Controllers\v1\Auth\LoginController;
use App\Http\Controllers\v1\Auth\LogoutController;
use App\Http\Controllers\v1\Auth\SignupController;
use App\Http\Middleware\Api\EnsureGuestMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
->as('auth.')
->group(function () {
    Route::middleware([EnsureGuestMiddleware::class])
    ->group(function () {
        Route::post('login', LoginController::class)->name('login');

        Route::post('signup', SignupController::class)->name('signup');
    });

    Route::post('logout', LogoutController::class)
    ->middleware(['auth:web'])
    ->name('logout');
});
