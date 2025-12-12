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
    Route::get('ads/{ad}', [AdController::class, 'show'])->name('ads.show');

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');

        // Use apiResource for standard CRUD (we expose store/update/destroy via resource)
        Route::apiResource('ads', AdController::class)->only(['store', 'update', 'destroy']);

        // Extra: my-ads
        Route::get('my-ads', [AdController::class, 'myAds'])->name('ads.my');
    });
});
