<?php

declare(strict_types=1);

use RobinsonRyan\Vendor\TagDefinitions\VendorStatusDefinition;
use RobinsonRyan\Vendor\TagDefinitions\VendorTypeDefinition;

describe('VendorTypeDefinition', function () {
    it('has correct slug', function () {
        expect(VendorTypeDefinition::slug())->toBe('vendor-type');
    });

    it('has expected values', function () {
        $values = VendorTypeDefinition::values();

        expect($values)->toContain('supplier')
            ->and($values)->toContain('contractor')
            ->and($values)->toContain('service_provider');
    });

    it('has supplier as default', function () {
        expect(VendorTypeDefinition::defaultValue())->toBe('supplier');
    });

    it('has labels for all values', function () {
        $labels = VendorTypeDefinition::labels();

        expect($labels)->toHaveKey('supplier', 'Supplier')
            ->and($labels)->toHaveKey('contractor', 'Contractor')
            ->and($labels)->toHaveKey('service_provider', 'Service Provider');
    });
});

describe('VendorStatusDefinition', function () {
    it('has correct slug', function () {
        expect(VendorStatusDefinition::slug())->toBe('vendor-status');
    });

    it('has expected values', function () {
        $values = VendorStatusDefinition::values();

        expect($values)->toContain('active')
            ->and($values)->toContain('inactive')
            ->and($values)->toContain('suspended');
    });

    it('has active as default', function () {
        expect(VendorStatusDefinition::defaultValue())->toBe('active');
    });

    it('has labels for all values', function () {
        $labels = VendorStatusDefinition::labels();

        expect($labels)->toHaveKey('active', 'Active')
            ->and($labels)->toHaveKey('inactive', 'Inactive')
            ->and($labels)->toHaveKey('suspended', 'Suspended');
    });
});
