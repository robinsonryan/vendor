<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Data;

/**
 * A vendor's contact as one set of values, in the party's words. It is what
 * SyncVendorContacts writes and what `Vendor::contact()` reads back.
 *
 * `country` is free text: a two-letter code, or a wording kept beside the code.
 */
final readonly class VendorContact
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postalCode = null,
        public ?string $country = null,
    ) {}

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null, line1?: string|null, line2?: string|null, city?: string|null, region?: string|null, postal_code?: string|null, country?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            line1: $data['line1'] ?? null,
            line2: $data['line2'] ?? null,
            city: $data['city'] ?? null,
            region: $data['region'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            country: $data['country'] ?? null,
        );
    }
}
