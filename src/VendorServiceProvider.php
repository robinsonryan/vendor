<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor;

use Illuminate\Support\ServiceProvider;

final class VendorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vendor.php', 'vendor');
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/vendor.php' => config_path('vendor.php'),
        ], 'vendor-config');
    }

    private function publishMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'vendor-migrations');
    }
}
