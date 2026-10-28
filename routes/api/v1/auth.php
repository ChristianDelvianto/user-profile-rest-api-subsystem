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
        /**
         * For login route, we will use custom throttle,
         * So we need disable throttle api global for login route
         * 
         * To verify the implementation:
         * 1. Please run: php artisan route:list -v
         * 2. Then verify the v1 login route middlewares
         */
        Route::post('login', LoginController::class)
        ->withoutMiddleware(['throttle:' . config('custom.throttle.api.global.throttle_name')])
        ->middleware(['throttle:' . config('custom.throttle.api.v1.login.throttle_name')])
        ->name('login');

        Route::post('signup', SignupController::class)->name('signup');
    });

    Route::middleware(['auth:web'])
    ->group(function () {
        Route::post('logout', LogoutController::class)->name('logout');
    });
});
