<?php

declare(strict_types=1);

/**
 * One side of a race between two first posts of one document. A process of its
 * own, because a race needs two real sessions and a commit the other can see.
 *
 * It waits for its rival twice: at the start, so neither is ahead on booting,
 * and again just before the insert, so an action that does not serialize first
 * posts has both past their lookup. An action that does never brings the second
 * worker to the insert, so that wait runs out and the first carries on.
 *
 * Usage: php post-document-worker.php <barrier dir> <schema> <version>
 */

use Illuminate\Support\Facades\DB;
use RobinsonRyan\Vendor\Purchasing\Actions\PostVendorDocument;
use RobinsonRyan\Vendor\Purchasing\Data\VendorDocumentData;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;
use RobinsonRyan\Vendor\Tests\TestCase;

require __DIR__.'/../../vendor/autoload.php';

[, $barrier, $schema, $version] = $argv;

(new class('worker') extends TestCase {})->createApplication();

config()->set('database.connections.testing.search_path', $schema);
DB::purge('testing');

$meetRival = function (string $stage, float $seconds) use ($barrier): void {
    touch(sprintf('%s/%s-%d', $barrier, $stage, getmypid()));

    $deadline = microtime(true) + $seconds;

    while (count((array) glob(sprintf('%s/%s-*', $barrier, $stage))) < 2 && microtime(true) < $deadline) {
        usleep(5_000);
    }
};

VendorDocument::creating(function () use ($meetRival): void {
    $meetRival('insert', 1.0);
});

$meetRival('start', 30.0);

try {
    $outcome = (new PostVendorDocument)->execute(VendorDocumentData::fromArray([
        'source' => ['kind' => 'scan', 'ref' => 'paperless:afwd:1234', 'version' => $version],
        'vendor' => ['name' => 'Dynatrac'],
        'document' => ['kind' => 'invoice', 'number' => 'INV-88123', 'lines_settled' => true],
        'lines' => [[
            'seq' => 1,
            'kind' => 'part',
            'vendor_part_number' => 'PR60-ASF',
            'description' => 'Pro-Rock 60 axle shaft',
            'quantity' => '2',
            'uom' => 'each',
            'unit_price' => '920.00',
            'extended' => '1840.00',
        ]],
    ]));
} catch (Throwable $failure) {
    // Testbench's handler would render this and exit 0.
    fwrite(STDERR, $failure->getMessage());

    exit(1);
}

echo $outcome->result->value;
