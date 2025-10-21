<?php

namespace App\Http\Controllers;

use App\Models\TenantSetting;
use App\Services\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant as CentralTenant;

class TenantMetaController extends Controller
{
    /**
     * GET /api/v1/tenant/meta
     * লগইন ছাড়াই টেন্যান্ট-ভিত্তিক UI কনফিগ দেয়।
     * Dev: ?tenant=localhost:3000 (বা X-Tenant-Domain) ফেলে টেস্ট করা যাবে।
     * Prod: IdentifyTenant/BFF হেডার দিয়ে রেজলভ হবে।
     */
    public function show(Request $request, TenantManager $tm)
    {
        // DEV fallback — tenant context না থাকলে কুয়েরি/হেডার থেকে ধরার ট্রিক
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
            $s = TenantSetting::query()->first(); // একটাই রো রাখব

            // ডিফল্ট + override merge
            $branding = array_merge([
                'logoUrl'     => null,
                'faviconUrl'  => null,
                'primaryColor' => '#1476ff',
                'secondaryColor' => '#0ea5e9',
            ], (array) ($s->branding ?? []));

            $locale = array_merge([
                'default'      => 'bn',
                'supported'    => ['bn', 'en'],
                'numberSystem' => 'latn',     // বা 'bn'
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

        return response()->json($meta);
    }
}
