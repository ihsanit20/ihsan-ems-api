<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use App\Models\Tenant as CentralTenant;
use App\Models\Tenant\Setting;

class MetaController extends Controller
{
    /**
     * GET /api/v1/tenant/meta
     * টেন্যান্টভিত্তিক UI কনফিগ (public, no auth).
     * Dev: ?tenant=localhost:3000 বা X-Tenant-Domain হেডার দিয়ে টেস্ট করুন।
     * Prod: IdentifyTenant middleware/BFF হেডারে রিজলভ হবে।
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

        // 5 মিনিট ক্যাশ—meta সাধারণত ঘন ঘন পাল্টায় না
        $cacheKey = "tenant:{$tenant->id}:meta:v1";
        $meta = Cache::remember($cacheKey, 300, function () use ($tenant) {
            // Tenant DB এর ১টা সেটিংস রো
            $s = Setting::query()->first();

            // --- CENTRAL BRANDING (source of truth) ---
            // Tenant model-এ accessor getBrandingUrlsAttribute() আছে বলে ধরা হয়েছে
            $centralLogoUrl    = $tenant->branding_urls['logo_url']    ?? null;
            $centralFaviconUrl = $tenant->branding_urls['favicon_url'] ?? null;
            $brandingVersion   = is_array($tenant->branding ?? null)
                ? ($tenant->branding['version'] ?? null)
                : null;

            // Settings->branding থেকে রঙ ইত্যাদি নিতে পারি, কিন্তু logo/favicon কেন্দ্রীয় রাখি
            $settingsBranding = (array) ($s->branding ?? []);
            unset($settingsBranding['logoUrl'], $settingsBranding['faviconUrl']); // logos stay central

            $branding = array_merge([
                'logoUrl'       => $centralLogoUrl,
                'faviconUrl'    => $centralFaviconUrl,
                'version'       => $brandingVersion, // cache-busting জন্য কাজে লাগবে
                'primaryColor'  => '#1476ff',
                'secondaryColor' => '#0ea5e9',
            ], $settingsBranding);

            $locale = array_merge([
                'default'      => 'bn',
                'supported'    => ['bn', 'en'],
                'numberSystem' => 'latn',
                'calendarMode' => 'gregorian',
                'timezone'     => 'Asia/Dhaka',
                'dateFormat'   => 'dd/MM/yyyy',
                'timeFormat'   => 'HH:mm',
            ], (array) ($s->locale ?? []));

            $currency = array_merge([
                'code'     => 'BDT',
                'symbol'   => '৳',
                'position' => 'prefix',
            ], (array) ($s->currency ?? []));

            $features = array_merge([
                'admission'  => true,
                'attendance' => true,
                'fees'       => true,
                'exam'       => false,
            ], (array) ($s->features ?? []));

            $policy = array_merge([
                'maxUploadMB' => 10,
            ], (array) ($s->policy ?? []));

            return [
                'id'        => $tenant->id,
                'domain'    => $tenant->domain,
                'name'      => $s->name ?? $tenant->name,
                'shortName' => $s->short_name ?? null,

                // ফ্রন্টএন্ডে সরাসরি ইউজ করার মতো সেকশনগুলো
                'branding'  => $branding,
                'locale'    => $locale,
                'currency'  => $currency,
                'features'  => $features,
                'policy'    => $policy,

                'status'    => [
                    'active'      => (bool) $tenant->is_active,
                    'maintenance' => (bool) ($s->maintenance ?? false),
                ],
            ];
        });

        // অপশনাল: ETag সাপোর্ট (SPA ক্যাশ/রিভ্যালিডেশনের জন্য ভালো)
        $etag = '"' . sha1(json_encode($meta)) . '"';
        if ($request->header('If-None-Match') === $etag) {
            return Response::make('', 304, ['ETag' => $etag]);
        }

        return response()
            ->json($meta)
            ->header('Cache-Control', 'public, max-age=60') // edge cache চাইলে বাড়াতে পারেন
            ->header('ETag', $etag);
    }
}