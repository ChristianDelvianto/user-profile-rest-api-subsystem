<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// For v1: we are using cookie-based authentication (Laravel Sanctum)

Route::prefix('v1')
->as('v1.')
->middleware([EnsureFrontendRequestsAreStateful::class])
->group(function () {
    require __DIR__ . '/auth.php';
    require __DIR__ . '/notifications.php';
    require __DIR__ . '/user.php';
});
