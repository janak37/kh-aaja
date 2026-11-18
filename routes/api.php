<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
| Kh-aaja API, version 1. Every route here starts with /api/v1.
| The website keeps its own session login; this API uses Sanctum tokens.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // At most 6 attempts a minute per visitor, to stop password guessing.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
