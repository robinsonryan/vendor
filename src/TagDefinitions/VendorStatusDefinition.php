<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\TagDefinitions;

use RobinsonRyan\Taxon\TagDefinition;

final class VendorStatusDefinition extends TagDefinition
{
    public static string $slug = 'vendor-status';

    public static string $name = 'Vendor Status';

    public static bool $singleSelect = true;

    public static bool $global = true;

    /**
     * @var list<string>
     */
    public const array DEFAULT_VALUES = ['active', 'inactive', 'suspended'];
}
