<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Support;

/**
 * The key a vendor item is unique on. Separators are kept: to a vendor, `12-34`
 * and `1234` can be different parts.
 */
final class VendorPartNumber
{
    private const DESCRIPTION_PREFIX = 'desc:';

    public static function normalize(string $partNumber): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($partNumber)));
    }

    public static function forDescription(string $description): string
    {
        return self::DESCRIPTION_PREFIX.self::normalize($description);
    }

    /**
     * The stored form of a key that is already one or the other. The prefix is
     * matched in lower case because normalize() upper-cases everything else, so
     * a printed `DESC:12` stays a part number.
     */
    public static function key(string $key): string
    {
        $key = trim($key);

        return str_starts_with($key, self::DESCRIPTION_PREFIX)
            ? self::forDescription(substr($key, strlen(self::DESCRIPTION_PREFIX)))
            : self::normalize($key);
    }
}
