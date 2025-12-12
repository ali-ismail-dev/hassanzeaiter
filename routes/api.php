<?php

use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth public
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');

    // Public ads
    Route::get('ads', [AdController::class, 'index'])->name('ads.index');
    // constrain to numeric id to avoid conflicts and help binding
    Route::get('ads/{ad}', [AdController::class, 'show'])->whereNumber('ad')->name('ads.show');

    // Protected routes (Sanctum + basic throttle)
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');

        // Use apiResource for store/update/destroy (these require auth)
        Route::apiResource('ads', AdController::class)->only(['store', 'update', 'destroy']);

        // Extra: my-ads
        Route::get('my-ads', [AdController::class, 'myAds'])->name('ads.my');
    });
});
