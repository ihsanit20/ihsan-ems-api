<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Central admin permission (unchanged)
        Gate::define('manage-tenants', fn(User $u) => (bool) $u->is_super_admin);

        // Sanctum PAT model: context-aware (central vs tenant)
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /* -------------------- Rate Limiters -------------------- */

        // Default API limiter (use with: throttle:api)
        RateLimiter::for('api', function (Request $request) {
            // logged-in হলে user id দিয়ে, না হলে IP দিয়ে সীমা ধার্য
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Login / Auth endpoints limiter (use with: throttle:tenant-auth)
        RateLimiter::for('tenant-auth', function (Request $request) {
            // টেন্যান্ট ডোমেইন + IP মিলিয়ে কড়া সীমা
            $tenantKey = strtolower($request->header('X-Tenant-Domain', $request->getHost()));
            return Limit::perMinute(20)->by($tenantKey . '|' . $request->ip());
        });
    }
}
