<?php

namespace App\Providers;

use App\Services\Tenancy\TenantManager;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, fn() => new TenantManager());
    }
}
