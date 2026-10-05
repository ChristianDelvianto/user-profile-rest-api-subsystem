<?php

use Illuminate\Support\Facades\Route;

Route::as('api.')
// We are using Laravel Sanctum with Cookie-based authentication,
// so we need to use the "web" middleware group to handle the session and CSRF protection.
->middleware(['web'])
->group(function () {
    require __DIR__ . '/api/v1/index.php';
});
