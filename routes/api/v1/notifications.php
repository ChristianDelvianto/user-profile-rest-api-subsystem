<?php

use App\Http\Controllers\v1\Notification\IndexController;
use App\Http\Controllers\v1\Notification\ReadController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')
->as('notifications.')
->middleware(['auth:web'])
->group(function () {
    Route::get('/', IndexController::class)->name('index');

    /**
     * @todo: explain how it works
     */
    Route::patch('read', ReadController::class)->name('read');
});
