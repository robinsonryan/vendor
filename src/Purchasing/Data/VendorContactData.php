<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Data;

use RobinsonRyan\Vendor\Purchasing\Enums\VendorContactKind;

final readonly class VendorContactData
{
    public function __construct(
        public VendorContactKind $kind,
        public string $value,
    ) {}

    /**
     * @param  array{kind: string, value: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: VendorContactKind::from($data['kind']),
            value: $data['value'],
        );
    }

    /**
     * @return array{kind: string, value: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'value' => $this->value,
        ];
    }
}
