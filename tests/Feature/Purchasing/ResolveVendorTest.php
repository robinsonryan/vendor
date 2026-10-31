<?php

declare(strict_types=1);

use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Actions\ResolveVendor;

describe('ResolveVendor', function (): void {
    it('matches on the paperless correspondent id before the name', function (): void {
        $byId = Vendor::factory()->create(['name' => 'Dynatrac Products', 'metadata' => ['paperless_correspondent_id' => 57]]);
        Vendor::factory()->create(['name' => 'Dynatrac']);

        expect((new ResolveVendor)->execute('Dynatrac', 57)->id)->toBe($byId->id);
    });

    it('matches a correspondent id stored as a string', function (): void {
        $vendor = Vendor::factory()->create(['metadata' => ['paperless_correspondent_id' => '57']]);

        expect((new ResolveVendor)->execute('Something Else', 57)->id)->toBe($vendor->id);
    });

    it('falls back to a case-insensitive exact name', function (): void {
        $vendor = Vendor::factory()->create(['name' => 'Dynatrac']);
        Vendor::factory()->create(['name' => 'Dynatrac Axles']);

        expect((new ResolveVendor)->execute('  DYNATRAC ', null)->id)->toBe($vendor->id);
    });

    it('fills an absent correspondent id on a name match', function (): void {
        $vendor = Vendor::factory()->create(['name' => 'Dynatrac', 'metadata' => ['stock_vendor' => false]]);

        (new ResolveVendor)->execute('Dynatrac', 57);

        expect($vendor->fresh()->metadata)->toEqual(['stock_vendor' => false, 'paperless_correspondent_id' => 57]);
    });

    it('never overwrites an existing correspondent id', function (): void {
        $vendor = Vendor::factory()->create(['name' => 'Dynatrac', 'metadata' => ['paperless_correspondent_id' => 12]]);

        $resolved = (new ResolveVendor)->execute('Dynatrac', 57);

        expect($resolved->id)->toBe($vendor->id)
            ->and($vendor->fresh()->metadata['paperless_correspondent_id'])->toBe(12);
    });

    it('creates an unknown vendor with the correspondent id', function (): void {
        $vendor = (new ResolveVendor)->execute('Dynatrac', 57);

        expect($vendor->exists)->toBeTrue()
            ->and($vendor->name)->toBe('Dynatrac')
            ->and($vendor->type)->toBe('supplier')
            ->and($vendor->status)->toBe('active')
            ->and($vendor->metadata)->toBe(['paperless_correspondent_id' => 57])
            ->and($vendor->party_id)->not->toBeNull();
    });

    it('creates an unknown vendor with no correspondent id', function (): void {
        $vendor = (new ResolveVendor)->execute('Dynatrac', null);

        expect($vendor->metadata)->toBeNull()
            ->and(Vendor::query()->count())->toBe(1);
    });

    it('resolves to the same vendor twice', function (): void {
        $first = (new ResolveVendor)->execute('Dynatrac', 57);
        $second = (new ResolveVendor)->execute('Dynatrac', 57);

        expect($second->id)->toBe($first->id)
            ->and(Vendor::query()->count())->toBe(1);
    });
});
