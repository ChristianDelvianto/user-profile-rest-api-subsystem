<?php

use App\Http\Controllers\v1\Post\LikeController;
use Illuminate\Support\Facades\Route;

Route::prefix(prefix: 'post')
->as(value: 'post.')
->group(callback: function () {
    Route::prefix(prefix: '{post}') // Route key name is 'uuid', see Post model for more detail
    ->group(callback: function () {
        // 

        Route::prefix(prefix: 'like')
        ->middleware(['auth:web'])
        ->group(callback: function () {
            Route::post(uri: '/', action: LikeController::class)->withTrashed()->name(name: 'like');
        });
    });
});
