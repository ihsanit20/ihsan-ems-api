<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\MetaController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\UserController;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
| - Tenant identification is handled globally via IdentifyTenant (API stack).
| - Clean, feature-based paths (no 'tenant' segment).
| - Public vs Auth-protected separated.
| - Sensible rate limiting: throttle:api (general), throttle:tenant-auth (login).
*/

Route::prefix('v1')
    ->as('api.v1.')
    ->middleware(['throttle:api'])
    ->group(function () {
        /* ---------- Diagnostics (optional) ---------- */
        Route::get('ping', fn() => response()->json([
            'ok' => true,
            'ts' => now()->toIso8601String(),
        ]))->name('ping');

        Route::get('tenant/meta', [MetaController::class, 'show'])->name('meta.show');

        Route::post('auth/login', [AuthController::class, 'tokenLogin'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.login');

        Route::post('auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.register');

        /* ---------- Auth required (Bearer token) ---------- */
        Route::middleware('auth:sanctum')->group(function () {
            Route::as('auth.')->group(function () {
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::post('auth/logout', [AuthController::class, 'tokenLogout']);
                Route::post('auth/logout-all', [AuthController::class, 'revokeAllTokens']);
            });

            /* ---------- Users (CRUD; individual routes) ---------- */
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
            Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::patch('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.patch');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
        });

        /* ---------- v1 Fallback (JSON 404) ---------- */
        Route::fallback(fn() => response()->json(['message' => 'Not Found.'], 404))
            ->name('fallback');
    });
