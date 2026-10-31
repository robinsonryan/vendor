<?php

declare(strict_types=1);

use RobinsonRyan\Vendor\Purchasing\Support\VendorPartNumber;

describe('VendorPartNumber', function (): void {
    it('trims, collapses inner whitespace and upper-cases', function (): void {
        expect(VendorPartNumber::normalize("  pr60\t  asf \n"))->toBe('PR60 ASF');
    });

    it('keeps separators', function (): void {
        expect(VendorPartNumber::normalize('fc-34/2200.a_b'))->toBe('FC-34/2200.A_B');
    });

    it('upper-cases multibyte characters', function (): void {
        expect(VendorPartNumber::normalize('bücher-1'))->toBe('BÜCHER-1');
    });

    it('keys a description with the desc: prefix', function (): void {
        expect(VendorPartNumber::forDescription('  Pro-Rock 60   axle shaft '))
            ->toBe('desc:PRO-ROCK 60 AXLE SHAFT');
    });
});
