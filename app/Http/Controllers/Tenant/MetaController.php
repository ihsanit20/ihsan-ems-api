<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantManager;
use Illuminate\Http\Request;
use App\Models\Tenant as CentralTenant;

class MetaController extends Controller
{
    /**
     * GET /api/v1/tenant/meta
     * Public: শুধুই name, logo, favicon
     */
    public function show(Request $request, TenantManager $tm)
    {
        // DEV fallback — tenant context না থাকলে কুয়েরি/হেডার থেকে রেজলভ
        if (! $tm->tenant()) {
            $domain = strtolower($request->header('X-Tenant-Domain', $request->query('tenant', '')));
            if ($domain) {
                $t = CentralTenant::where('domain', $domain)->first();
                abort_unless($t, 404, "Tenant not found: {$domain}");
                abort_if(! $t->is_active, 403, "Tenant inactive: {$domain}");
                $tm->setTenant($t);
            }
        }

        $tenant = $tm->tenant();
        abort_unless($tenant, 400, 'Tenant context missing');

        $branding = $tenant->branding_urls ?? [];

        return response()->json([
            'id'       => $tenant->id,
            'domain'   => $tenant->domain,
            'name'     => $tenant->name,
            'branding' => [
                'logoUrl'    => $branding['logo_url'] ?? null,
                'faviconUrl' => $branding['favicon_url'] ?? null,
            ],
        ]);
    }
}
