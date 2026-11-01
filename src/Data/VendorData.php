<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Data;

final readonly class VendorData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  VendorContact|null  $contact  Goes to the vendor's party, never the row.
     */
    public function __construct(
        public string $name,
        public ?string $tenantId = null,
        public ?string $code = null,
        public string $type = 'supplier',
        public string $status = 'active',
        public ?string $website = null,
        public ?string $notes = null,
        public ?array $metadata = null,
        public ?VendorContact $contact = null,
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
            website: $data['website'] ?? null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
            contact: isset($data['contact']) ? VendorContact::fromArray($data['contact']) : null,
        );
    }

    /**
     * The vendor row's own attributes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'code' => $this->code,
            'website' => $this->website,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ], fn (string|array|null $value): bool => $value !== null);
    }
}
