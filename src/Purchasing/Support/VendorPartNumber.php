<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Support;

/**
 * The key a vendor item is unique on. Separators are kept: to a vendor, `12-34`
 * and `1234` can be different parts.
 */
final class VendorPartNumber
{
    public static function normalize(string $partNumber): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($partNumber)));
    }

    public static function forDescription(string $description): string
    {
        return 'desc:'.self::normalize($description);
    }
}
