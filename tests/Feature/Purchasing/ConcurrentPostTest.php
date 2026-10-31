<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

describe('PostVendorDocument, two first posts at once', function (): void {
    /**
     * The workers commit, so they get a throwaway schema: the rows cannot reach
     * another test, and the shared `testing` tables stay as RefreshDatabase
     * expects them. `race` is a connection of its own because the default one
     * sits in the test's transaction, where the workers could not see the
     * tables and a rollback would undo the cleanup.
     */
    it('settles on one document holding the newer version', function (): void {
        config()->set('database.connections.race', config('database.connections.testing'));
        $race = DB::connection('race');

        $schema = 'race_'.getmypid();
        $barrier = sys_get_temp_dir().'/vendor-'.$schema;
        File::ensureDirectoryExists($barrier);

        try {
            $race->statement(sprintf('create schema %s', $schema));

            $tables = $race->table('information_schema.tables')->where('table_schema', 'public')->pluck('table_name');

            foreach ($tables as $table) {
                $race->statement(sprintf('create table %s."%s" (like public."%s" including all)', $schema, $table, $table));
            }

            $older = '2026-10-13T04:11:00Z';
            $newer = '2026-10-14T04:11:00Z';

            $workers = array_map(function (string $version) use ($barrier, $schema): Process {
                $worker = new Process(
                    [PHP_BINARY, __DIR__.'/../../Fixtures/post-document-worker.php', $barrier, $schema, $version],
                    timeout: 60,
                );
                $worker->start();

                return $worker;
            }, [$older, $newer]);

            foreach ($workers as $worker) {
                $worker->wait();

                expect($worker->getExitCode())->toBe(0, $worker->getOutput().$worker->getErrorOutput());
            }

            $results = array_map(fn (Process $worker): string => $worker->getOutput(), $workers);

            expect($results)->toContain('created')
                ->and(array_diff($results, ['created']))->toHaveCount(1)
                ->and(array_values(array_diff($results, ['created']))[0])->toBeIn(['replaced', 'unchanged'])
                ->and($race->table($schema.'.vendor_documents')->pluck('source_version')->all())->toBe([$newer])
                ->and($race->table($schema.'.purchase_lines')->count())->toBe(1)
                ->and($race->table($schema.'.vendor_items')->count())->toBe(1)
                ->and($race->table($schema.'.vendors')->count())->toBe(1);
        } finally {
            $race->statement(sprintf('drop schema if exists %s cascade', $schema));
            DB::purge('race');
            File::deleteDirectory($barrier);
        }
    });
});
