<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\TagDefinitions;

use RobinsonRyan\Taxon\TagDefinition;

final class VendorTypeDefinition extends TagDefinition
{
    public static function slug(): string
    {
        return 'vendor-type';
    }

    public static function values(): array
    {
        return [
            'supplier',
            'contractor',
            'service_provider',
        ];
    }

    public static function defaultValue(): string
    {
        return 'supplier';
    }

    public static function labels(): array
    {
        return [
            'supplier' => 'Supplier',
            'contractor' => 'Contractor',
            'service_provider' => 'Service Provider',
        ];
    }
}
