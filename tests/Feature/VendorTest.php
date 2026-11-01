<?php

declare(strict_types=1);

use RobinsonRyan\Vendor\Actions\CreateVendor;
use RobinsonRyan\Vendor\Actions\UpdateVendor;
use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Data\VendorData;
use RobinsonRyan\Vendor\Models\Vendor;

describe('Vendor Model', function (): void {
    it('can be created with factory', function (): void {
        $vendor = Vendor::factory()->create();

        expect($vendor)->toBeInstanceOf(Vendor::class)
            ->and($vendor->id)->not->toBeNull()
            ->and($vendor->name)->not->toBeNull();
    });

    it('has default type of supplier', function (): void {
        $vendor = Vendor::factory()->create();

        expect($vendor->type)->toBe('supplier');
    });

    it('has default status of active', function (): void {
        $vendor = Vendor::factory()->create();

        expect($vendor->status)->toBe('active');
    });

    it('can be created as contractor type', function (): void {
        $vendor = Vendor::factory()->contractor()->create();

        expect($vendor->type)->toBe('contractor');
    });

    it('can be created as service provider type', function (): void {
        $vendor = Vendor::factory()->serviceProvider()->create();

        // Taxon slugs a value on the way in and on the way back out, so the
        // stored spelling is the hyphenated one.
        expect($vendor->type)->toBe('service-provider');
    });

    it('can be created as inactive', function (): void {
        $vendor = Vendor::factory()->inactive()->create();

        expect($vendor->status)->toBe('inactive');
    });

    it('can be created as suspended', function (): void {
        $vendor = Vendor::factory()->suspended()->create();

        expect($vendor->status)->toBe('suspended');
    });

    it('casts metadata to array', function (): void {
        $vendor = Vendor::factory()->create([
            'metadata' => ['key' => 'value'],
        ]);

        expect($vendor->metadata)->toBeArray()
            ->and($vendor->metadata['key'])->toBe('value');
    });
});

describe('CreateVendor Action', function (): void {
    it('creates a vendor from data object', function (): void {
        $data = new VendorData(
            name: 'Test Vendor',
            code: 'TEST-001',
            type: 'supplier',
            status: 'active',
        );

        $action = new CreateVendor;
        $vendor = $action->execute($data);

        expect($vendor)->toBeInstanceOf(Vendor::class)
            ->and($vendor->name)->toBe('Test Vendor')
            ->and($vendor->code)->toBe('TEST-001')
            ->and($vendor->type)->toBe('supplier')
            ->and($vendor->status)->toBe('active');
    });

    it('creates a vendor with all fields', function (): void {
        $data = new VendorData(
            name: 'Full Vendor',
            code: 'FULL-001',
            type: 'contractor',
            status: 'active',
            website: 'https://example.com',
            notes: 'Test notes',
            metadata: ['custom' => 'data'],
            contact: new VendorContact(
                name: 'John Doe',
                email: 'john@example.com',
                phone: '555-1234',
                line1: '123 Main St',
                city: 'Anytown',
                region: 'CA',
                postalCode: '12345',
                country: 'US',
            ),
        );

        $action = new CreateVendor;
        $vendor = $action->execute($data);

        expect($vendor->primaryContactName())->toBe('John Doe')
            ->and($vendor->primaryEmail())->toBe('john@example.com')
            ->and($vendor->primaryAddress()?->city)->toBe('Anytown')
            ->and($vendor->website)->toBe('https://example.com')
            ->and($vendor->metadata)->toBe(['custom' => 'data']);
    });
});

describe('UpdateVendor Action', function (): void {
    it('updates a vendor from data object', function (): void {
        $vendor = Vendor::factory()->create([
            'name' => 'Original Name',
        ]);

        $data = new VendorData(
            name: 'Updated Name',
            type: 'contractor',
            status: 'inactive',
        );

        $action = new UpdateVendor;
        $updated = $action->execute($vendor, $data);

        expect($updated->name)->toBe('Updated Name')
            ->and($updated->type)->toBe('contractor')
            ->and($updated->status)->toBe('inactive');
    });
});

describe('VendorData', function (): void {
    it('can be created from array', function (): void {
        $data = VendorData::fromArray([
            'name' => 'Array Vendor',
            'code' => 'ARR-001',
            'type' => 'supplier',
            'contact' => ['email' => 'test@example.com', 'line1' => '123 Main St', 'postal_code' => '84070'],
        ]);

        expect($data->name)->toBe('Array Vendor')
            ->and($data->code)->toBe('ARR-001')
            ->and($data->contact)->toEqual(new VendorContact(email: 'test@example.com', line1: '123 Main St', postalCode: '84070'));
    });

    it('can be converted to array', function (): void {
        $data = new VendorData(
            name: 'Test Vendor',
            code: 'TEST-001',
            contact: new VendorContact(email: 'test@example.com'),
        );

        $array = $data->toArray();

        expect($array)->toHaveKey('name', 'Test Vendor')
            ->and($array)->toHaveKey('code', 'TEST-001')
            ->and($array)->not->toHaveKey('contact');
    });
});
