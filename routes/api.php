<?php
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Public ad viewing
    Route::get('/ads', [AdController::class, 'index']);
    Route::get('/ads/{ad}', [AdController::class, 'show']);
});

// Protected routes (requires Sanctum authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Ads management
    Route::post('/ads', [AdController::class, 'store']);
    Route::get('/my-ads', [AdController::class, 'myAds']);
    Route::put('/ads/{ad}', [AdController::class, 'update']);
    Route::patch('/ads/{ad}', [AdController::class, 'update']);
    Route::delete('/ads/{ad}', [AdController::class, 'destroy']);
});
