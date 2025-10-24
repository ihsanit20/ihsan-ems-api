<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        protected TenantManager $tenancy
    ) {
        //
    }

    /**
     * Resolve tenant and switch connection to `tenant`.
     * Priority:
     * 1) X-Tenant-Domain header (or ?tenant=query)
     * 2) Request host (subdomain / custom domain)
     * If central domain and no override -> skip.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow CORS preflight to pass
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // Normalize host (no port)
        $host = strtolower($request->getHost());

        // Central domains
        $centralCsv = (string) (Config::get('tenancy.central_domains') ?? env('CENTRAL_DOMAINS', ''));
        $centralDomains = array_values(array_filter(array_map(
            fn($d) => strtolower(trim($d)),
            explode(',', $centralCsv)
        )));

        // Explicit override (dev/proxy-safe): header wins, then query
        $override = strtolower((string) $request->headers->get('X-Tenant-Domain', ''));
        if (! $override) {
            $override = strtolower((string) $request->query('tenant', ''));
        }

        // Determine which domain to use for tenant lookup
        $lookupDomain = null;

        if ($override) {
            // If override provided, always honor it
            $lookupDomain = $override;
        } else {
            // Else, use request host if it's not a central domain
            if ($host !== 'localhost' && ! in_array($host, $centralDomains, true)) {
                $lookupDomain = $host;
            }
        }

        // If no lookup domain -> central path (no tenant context)
        if (! $lookupDomain) {
            return $next($request);
        }

        // Resolve tenant
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = Tenant::where('domain', $lookupDomain)->first();

        if (! $tenant) {
            abort(404, 'Tenant not found for host: ' . $lookupDomain);
        }

        if (! $tenant->is_active) {
            abort(403, 'Tenant is inactive.');
        }

        // Switch DB connection to this tenant
        $this->tenancy->setTenant($tenant);

        // Optionally force app.url to current tenant (helps URL generation)
        Config::set('app.url', $request->getScheme() . '://' . $lookupDomain);

        // Share current tenant to Inertia / Blade views (handy in UI)
        if (class_exists(Inertia::class)) {
            Inertia::share('tenant', fn() => [
                'id'     => $tenant->id,
                'name'   => $tenant->name,
                'domain' => $tenant->domain,
            ]);
        } else {
            view()->share('tenant', $tenant);
        }

        return $next($request);
    }
}
