<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use RobinsonRyan\Taxon\TaxonServiceProvider;
use RobinsonRyan\Vendor\VendorServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            TaxonServiceProvider::class,
            VendorServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // Taxon's tables first: `type` and `status` are taxon tag attributes,
        // so every vendor write reads the `tags` table. Without this the whole
        // Feature suite fails on "no such table: tags".
        $this->loadMigrationsFrom(dirname(__DIR__).'/../taxon/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Real PostgreSQL, not SQLite: the package's own doctrine is that a
        // suite runs on the engine production runs on, and SQLite's loose type
        // affinity cannot see a uuid/bigint/varchar mismatch.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => env('VENDOR_TEST_DB_HOST', 'db'),
            'port' => (int) env('VENDOR_TEST_DB_PORT', 5432),
            'database' => env('VENDOR_TEST_DB_DATABASE', 'testing'),
            'username' => env('VENDOR_TEST_DB_USERNAME', 'db'),
            'password' => env('VENDOR_TEST_DB_PASSWORD', 'db'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        // Vendors are keyed by UUID, so the `taggables.taggable_id` column that
        // holds them must be too. Left at taxon's `incrementing` default it is
        // a bigint, and every tag write fails on "invalid input syntax for type
        // bigint".
        $app['config']->set('taxon.taggable_id_type', 'uuid7');
    }
}
