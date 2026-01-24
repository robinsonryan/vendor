<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\TagDefinitions;

use RobinsonRyan\Taxon\TagDefinition;

final class VendorStatusDefinition extends TagDefinition
{
    public static function slug(): string
    {
        return 'vendor-status';
    }

    public static function values(): array
    {
        return [
            'active',
            'inactive',
            'suspended',
        ];
    }

    public static function defaultValue(): string
    {
        return 'active';
    }

    public static function labels(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
        ];
    }
}
