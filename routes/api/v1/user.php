<?php

use App\Http\Controllers\v1\User\FollowController;
use App\Http\Controllers\v1\User\ProfileController;
use App\Http\Controllers\v1\User\UnfollowController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')
->as('user.')
->group(function () {
    /**
     * This will be the first endpoint when fetching a user's profile.
     */
    Route::get('@{user:username}', ProfileController::class)->name('profile');

    /**
     * The reason user's id used for route below, because it's immutable
     * 
     * So here's a real life scenario:
     * 1. user_1 successfully fetch user_2's profile
     * 2. Within few seconds, user_2 update their username to something else
     * 
     * If we keep implement route binding using user's username, like first time user_1 fetch user_2's profile,
     * user_1 will encounter 404, because user_2 changed their username to something else.
     * 
     * For better understanding, here network requests example below:
     * 1. .../api/v1/user/@user_2                           ----> OK (200)
     * 2. (user_2 change their username to 'user_100')
     * 3. .../api/v1/user/@user_2/...                       ----> Not found (404)
     * 
     * So to prevent it and to preserve user_1's experience,
     * that is why we use user's id, like below.
     */
    Route::prefix('{user}')
    ->group(function () {
        Route::prefix('follow')
        ->middleware(['auth:web'])
        ->group(function () {
            Route::post('/', FollowController::class)->name('follow');
            Route::delete('/', UnfollowController::class)->name('unfollow');
        });
    });
});
