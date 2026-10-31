<?php

declare(strict_types=1);

use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Actions\ResolveVendorItem;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;

describe('ResolveVendorItem', function (): void {
    it('creates an item on the normalized part number', function (): void {
        $vendor = Vendor::factory()->create(['tenant_id' => '0199a3f1-0000-7000-8000-000000000001']);

        $item = (new ResolveVendorItem)->execute($vendor, ' pr60-asf ', 'Pro-Rock 60 axle shaft');

        expect($item->vendor_part_number)->toBe('PR60-ASF')
            ->and($item->vendor_id)->toBe($vendor->id)
            ->and($item->tenant_id)->toBe($vendor->tenant_id)
            ->and($item->description)->toBe('Pro-Rock 60 axle shaft');
    });

    it('finds the same item however the number was printed', function (): void {
        $vendor = Vendor::factory()->create();

        $first = (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', 'Axle shaft');
        $second = (new ResolveVendorItem)->execute($vendor, 'pr60-asf  ', 'Axle shaft');

        expect($second->id)->toBe($first->id)
            ->and(VendorItem::query()->count())->toBe(1);
    });

    it('keeps two vendors apart', function (): void {
        $first = (new ResolveVendorItem)->execute(Vendor::factory()->create(), 'PR60-ASF', 'Axle shaft');
        $second = (new ResolveVendorItem)->execute(Vendor::factory()->create(), 'PR60-ASF', 'Axle shaft');

        expect($second->id)->not->toBe($first->id);
    });

    it('keys on the description when no part number is printed', function (?string $partNumber): void {
        $item = (new ResolveVendorItem)->execute(Vendor::factory()->create(), $partNumber, 'Grade 8  bolt kit');

        expect($item->vendor_part_number)->toBe('desc:GRADE 8 BOLT KIT')
            ->and($item->description)->toBe('Grade 8  bolt kit');
    })->with([[null], [''], ['   ']]);

    it('finds a description-keyed item again', function (): void {
        $vendor = Vendor::factory()->create();

        $first = (new ResolveVendorItem)->execute($vendor, null, 'Grade 8 bolt kit');
        $second = (new ResolveVendorItem)->execute($vendor, null, 'GRADE 8 BOLT KIT');

        expect($second->id)->toBe($first->id);
    });

    it('keeps the description current', function (): void {
        $vendor = Vendor::factory()->create();
        (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', 'Axle shaft');

        $item = (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', 'Pro-Rock 60 axle shaft');

        expect($item->fresh()->description)->toBe('Pro-Rock 60 axle shaft');
    });

    it('does not blank the description', function (): void {
        $vendor = Vendor::factory()->create();
        (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', 'Axle shaft');

        $item = (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', '  ');

        expect($item->fresh()->description)->toBe('Axle shaft');
    });

    it('leaves the last price alone', function (): void {
        $vendor = Vendor::factory()->create();
        $existing = VendorItem::factory()->create([
            'vendor_id' => $vendor->id,
            'vendor_part_number' => 'PR60-ASF',
            'last_unit_price' => '920.0000',
            'last_seen_at' => '2025-04-02',
        ]);

        (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', 'New description');

        $fresh = $existing->fresh();

        expect($fresh->last_unit_price)->toBe('920.0000')
            ->and($fresh->last_seen_at?->toDateString())->toBe('2025-04-02');
    });
});
