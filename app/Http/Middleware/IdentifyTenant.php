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
     * Resolve tenant by host and switch connection to `tenant`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Current host (no port)
        $host = strtolower($request->getHost());

        // Central domains list (config fallback to env)
        $centralCsv = (string) (Config::get('tenancy.central_domains') ?? env('CENTRAL_DOMAINS', ''));
        $centralDomains = array_values(array_filter(array_map(
            fn($d) => strtolower(trim($d)),
            explode(',', $centralCsv)
        )));

        // If central domain, skip switching
        if ($host === 'localhost' || in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        // Match tenant by exact domain
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = Tenant::where('domain', $host)->first();

        if (! $tenant) {
            abort(404, 'Tenant not found for host: ' . $host);
        }

        if (! $tenant->is_active) {
            abort(403, 'Tenant is inactive.');
        }

        // Switch DB connection to this tenant
        $this->tenancy->setTenant($tenant);

        // Optionally force app.url to current tenant domain
        Config::set('app.url', $request->getScheme() . '://' . $host);

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

        // If you ever want to make Eloquent default to tenant connection:
        // \DB::setDefaultConnection('tenant'); // (সতর্কতা: সেন্ট্রাল কুয়েরিগুলোর ক্ষেত্রে সতর্ক থাকবেন)

        return $next($request);
    }
}
