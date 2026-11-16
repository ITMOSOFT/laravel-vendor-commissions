<?php

namespace ItmoSoft\VendorCommissions;

use Illuminate\Support\ServiceProvider;

class VendorCommissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/itmosoft-vendor-commissions.php', 'itmosoft-vendor-commissions');
        $this->app->singleton(VendorCommissionManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/itmosoft-vendor-commissions.php' => config_path('itmosoft-vendor-commissions.php'),
        ], 'itmosoft-vendor-commissions-config');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
