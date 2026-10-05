<?php

use App\Http\Controllers\v1\Auth\LoginController;
use App\Http\Controllers\v1\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
->as('auth.')
->group(function () {
    Route::post('login', LoginController::class)->name('login');

    Route::post('logout', LogoutController::class)
    ->middleware(['auth'])
    ->name('logout');
});
