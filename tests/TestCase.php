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
        // so every vendor write reads the `tags` table. Taxon is an installed
        // dependency here, so its migrations come from vendor/.
        $this->loadMigrationsFrom(__DIR__.'/../vendor/robinsonryan/taxon/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        // The package schema relies on PostgreSQL's native uuidv7() as a column
        // default, so the suite runs against a real Postgres database (the DDEV
        // `db` service) rather than SQLite.
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

        // Vendors are uuid-keyed, so taggables.taggable_id must be a uuid column.
        $app['config']->set('taxon.id_type', 'uuid7');
    }
}
