<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\TenantController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:manage-tenants')->prefix('admin/tenants')->name('tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::post('/', [TenantController::class, 'store'])->name('store');
        Route::put('/{tenant}', [TenantController::class, 'update'])->name('update');
        Route::delete('/{tenant}', [TenantController::class, 'destroy'])->name('destroy');

        // actions
        Route::post('/{tenant}/migrate', [TenantController::class, 'migrate'])->name('migrate');
        Route::post('/{tenant}/provision', [TenantController::class, 'provision'])->name('provision');
        Route::get('/{tenant}/status', [TenantController::class, 'status'])->name('status');

        Route::post('/{tenant}/toggle', [TenantController::class, 'toggle'])->name('toggle');
        Route::get('/{tenant}/db-check', [TenantController::class, 'dbCheck'])->name('db-check');

        Route::get('/{tenant}/migrations/status', [TenantController::class, 'migrationStatus'])->name('migrations.status');
        Route::get('/{tenant}/migrations/pending', [TenantController::class, 'pendingMigrations'])->name('migrations.pending');
    });
});
