<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\MetaController;
use App\Http\Controllers\Tenant\AuthController;

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

        /* ---------- Public (no auth) ---------- */
        // Tenant UI meta (branding, locale, features, etc.)
        Route::get('meta', [MetaController::class, 'show'])
            ->name('meta.show');

        // Token login (email/phone + password) -> PAT
        Route::post('auth/login', [AuthController::class, 'tokenLogin'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.login');

        // Optional: self-register on tenant
        Route::post('auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.register');

        /* ---------- Auth required (Bearer token) ---------- */
        Route::middleware('auth:sanctum')
            ->as('auth.')
            ->group(function () {
                Route::get('me', [AuthController::class, 'me'])->name('me');

                Route::post('auth/logout', [AuthController::class, 'tokenLogout'])
                    ->name('logout');

                Route::post('auth/logout-all', [AuthController::class, 'revokeAllTokens'])
                    ->name('logout_all');
            });

        /* ---------- v1 Fallback (JSON 404) ---------- */
        Route::fallback(fn() => response()->json(['message' => 'Not Found.'], 404))
            ->name('fallback');
    });
