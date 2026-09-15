<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\TagDefinitions;

use Illuminate\Support\Str;
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
    public const DEFAULT_VALUES = ['active', 'inactive', 'suspended'];

    /**
     * The fixed vocabulary, in the slugged form taxon stores and returns
     * (`service_provider` is held as `service-provider`). Declaring it makes
     * the set immutable — a value outside it is refused on assignment, and the
     * first assignment no longer narrows the set to the one child it created.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(Str::slug(...), self::DEFAULT_VALUES);
    }
}
