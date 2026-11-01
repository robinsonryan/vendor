<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Data;

/**
 * A vendor's contact as one set of values, in the party's words. It is what
 * SyncVendorContacts writes and what `Vendor::contact()` reads back.
 *
 * `country` is free text: a two-letter code, or a wording kept beside the code.
 *
 * An empty value says nothing, so the sync leaves that field as it is. A caller
 * that means to empty a field names it in `$cleared`, and fromGroups() works that
 * out from what a form sent.
 */
final readonly class VendorContact
{
    /**
     * The fields of an address, which is sent and replaced as a whole.
     *
     * @var list<string>
     */
    private const ADDRESS_FIELDS = ['line1', 'line2', 'city', 'region', 'postal_code', 'country'];

    /**
     * @param  list<string>  $cleared  Fields to empty, by their fromArray() keys. A value given for one wins.
     */
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
        public array $cleared = [],
    ) {}

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null, line1?: string|null, line2?: string|null, city?: string|null, region?: string|null, postal_code?: string|null, country?: string|null}  $data
     * @param  list<string>  $cleared
     */
    public static function fromArray(array $data, array $cleared = []): self
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
            cleared: $cleared,
        );
    }

    /**
     * A contact from a form's two groups, where `null` or `[]` is a group it did
     * not send. A group not sent changes nothing. A field sent empty clears, and
     * a field left out of the contact group stays. An address sent is the whole
     * address, so a field left out of it clears too.
     *
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}|null  $contact
     * @param  array{line1?: string|null, line2?: string|null, city?: string|null, region?: string|null, postal_code?: string|null, country?: string|null}|null  $address
     */
    public static function fromGroups(?array $contact, ?array $address): self
    {
        $values = [...$contact ?? [], ...$address ?? []];
        $sent = [...array_keys($contact ?? []), ...($address === null || $address === [] ? [] : self::ADDRESS_FIELDS)];

        return self::fromArray($values, array_values(array_filter(
            $sent,
            fn (string $field): bool => trim((string) ($values[$field] ?? null)) === '',
        )));
    }

    public function clears(string $field): bool
    {
        return in_array($field, $this->cleared, true);
    }
}
