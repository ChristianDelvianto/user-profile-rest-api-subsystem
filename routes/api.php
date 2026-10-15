<?php

use Illuminate\Support\Facades\Route;

Route::as('api.')
->middleware([
    'throttle:' . config('custom.throttle.api.global.throttle_name')
])
->group(function () {
    require __DIR__ . '/api/v1/index.php';
});
