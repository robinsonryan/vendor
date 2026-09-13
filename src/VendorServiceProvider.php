<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor;

use Illuminate\Support\ServiceProvider;
use RobinsonRyan\Pact\Facades\Pact as PactFacade;
use RobinsonRyan\Pact\Pact;

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
        $this->registerShapes();
    }

    /**
     * Hand the vendor shape and its two vocabularies to Pact when the
     * application has Pact installed.
     *
     * Guarded rather than required: this package works without Pact, and an
     * application that has both should not have to restate the vendor contract
     * in a form request, a resource and a TypeScript interface.
     *
     * The path is the package's pact root, not its `shapes/` directory: Pact
     * reads `shapes/`, `vocabularies/` and `groups/` beneath the path it is
     * given (spec §5.1).
     */
    private function registerShapes(): void
    {
        if (! class_exists(Pact::class)) {
            return;
        }

        PactFacade::shapesFrom(__DIR__.'/../resources/pact', 'vendor');
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
