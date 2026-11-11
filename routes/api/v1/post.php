<?php

use App\Http\Controllers\v1\Post\LikeController;
use Illuminate\Support\Facades\Route;

Route::prefix('post')
->as('post.')
->group(function () {
    Route::prefix('{post}') // Route key name is 'uuid', see Post model for more detail
    ->group(function () {
        // 

        Route::prefix('like')
        ->middleware(['auth:web'])
        ->group(function () {
            Route::post('/', LikeController::class)->withTrashed()->name('like');
        });
    });
});
