<?php

declare(strict_types=1);

use RobinsonRyan\Vendor\TagDefinitions\VendorStatusDefinition;
use RobinsonRyan\Vendor\TagDefinitions\VendorTypeDefinition;

describe('VendorTypeDefinition', function (): void {
    it('has correct slug', function (): void {
        expect(VendorTypeDefinition::$slug)->toBe('vendor-type');
    });

    it('has correct name', function (): void {
        expect(VendorTypeDefinition::$name)->toBe('Vendor Type');
    });

    it('is single select', function (): void {
        expect(VendorTypeDefinition::$singleSelect)->toBeTrue();
    });

    it('is global', function (): void {
        expect(VendorTypeDefinition::$global)->toBeTrue();
    });

    it('has expected default values', function (): void {
        expect(VendorTypeDefinition::DEFAULT_VALUES)->toContain('supplier')
            ->and(VendorTypeDefinition::DEFAULT_VALUES)->toContain('contractor')
            ->and(VendorTypeDefinition::DEFAULT_VALUES)->toContain('service_provider');
    });
});

describe('VendorStatusDefinition', function (): void {
    it('has correct slug', function (): void {
        expect(VendorStatusDefinition::$slug)->toBe('vendor-status');
    });

    it('has correct name', function (): void {
        expect(VendorStatusDefinition::$name)->toBe('Vendor Status');
    });

    it('is single select', function (): void {
        expect(VendorStatusDefinition::$singleSelect)->toBeTrue();
    });

    it('is global', function (): void {
        expect(VendorStatusDefinition::$global)->toBeTrue();
    });

    it('has expected default values', function (): void {
        expect(VendorStatusDefinition::DEFAULT_VALUES)->toContain('active')
            ->and(VendorStatusDefinition::DEFAULT_VALUES)->toContain('inactive')
            ->and(VendorStatusDefinition::DEFAULT_VALUES)->toContain('suspended');
    });
});
