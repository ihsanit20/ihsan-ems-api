<?php

use App\Http\Controllers\TenantAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok'  => true,
        'env' => app()->environment(),
        'ts'  => now()->toIso8601String(),
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [TenantAuthController::class, 'register']);
    Route::post('login',    [TenantAuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me',    [TenantAuthController::class, 'me']);
        Route::post('logout', [TenantAuthController::class, 'logout']);
    });
});
