<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\TagDefinitions;

use RobinsonRyan\Taxon\TagDefinition;

final class VendorTypeDefinition extends TagDefinition
{
    public static string $slug = 'vendor-type';

    public static string $name = 'Vendor Type';

    public static bool $singleSelect = true;

    public static bool $global = true;

    /**
     * @var list<string>
     */
    public const array DEFAULT_VALUES = ['supplier', 'contractor', 'service_provider'];
}
