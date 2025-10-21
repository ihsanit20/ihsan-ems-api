<?php

use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Services\Tenancy\TenantManager;
use App\Http\Controllers\TenantAuthController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TenantMetaController;

Route::prefix('v1')->group(function () {
    Route::get('tenant/meta', [TenantMetaController::class, 'show']);
});


Route::get('/check', function (Request $r, TenantManager $tm) {
    $domain = strtolower($r->query('tenant', $r->header('X-Tenant-Domain', '')));

    if ($domain) {
        $t = Tenant::where('domain', $domain)->first();
        abort_if(!$t, 404, "Tenant not found: {$domain}");
        abort_if(!$t->is_active, 403, "Tenant inactive: {$domain}");
        $tm->setTenant($t);
    }

    $t = $tm->tenant();

    return response()->json([
        'ok' => true,
        'tenant' => $t ? [
            'id' => $t->id,
            'name' => $t->name,
            'domain' => $t->domain,
            'db' => $t->db_name,
        ] : null,
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
