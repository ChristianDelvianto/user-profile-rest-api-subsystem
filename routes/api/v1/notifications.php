<?php

use App\Http\Controllers\v1\Notification\IndexController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')
->as('notifications.')
->middleware(['auth:web'])
->group(function () {
    Route::get('/', IndexController::class)->name('index');
});
