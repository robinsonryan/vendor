<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Data;

final readonly class VendorData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $name,
        public ?string $tenantId = null,
        public ?string $code = null,
        public string $type = 'supplier',
        public string $status = 'active',
        public ?string $contactName = null,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public ?string $website = null,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            tenantId: $data['tenant_id'] ?? null,
            code: $data['code'] ?? null,
            type: $data['type'] ?? 'supplier',
            status: $data['status'] ?? 'active',
            contactName: $data['contact_name'] ?? null,
            contactEmail: $data['contact_email'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            country: $data['country'] ?? null,
            website: $data['website'] ?? null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'code' => $this->code,
            'contact_name' => $this->contactName,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'website' => $this->website,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ], fn (string|array|null $value): bool => $value !== null);
    }
}
