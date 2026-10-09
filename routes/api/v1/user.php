<?php

use App\Http\Controllers\v1\User\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')
->as('user.')
->group(function () {
    Route::get('@{user:username}', ProfileController::class)->name('profile');
});
