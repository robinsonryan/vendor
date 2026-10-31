<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Data;

use RobinsonRyan\Vendor\Purchasing\Enums\LineKind;

final readonly class PurchaseLineData
{
    /**
     * @param  list<string>  $references
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $seq,
        public LineKind $kind,
        public string $description,
        public ?string $vendorPartNumber = null,
        public ?string $quantity = null,
        public ?string $unitOfMeasure = null,
        public ?string $unitPrice = null,
        public ?string $extended = null,
        public bool $taxed = false,
        public ?string $coreAmount = null,
        public array $references = [],
        public ?int $page = null,
        public ?string $purchaseOrderLineId = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Reads one entry of the intake contract's `lines`.
     *
     * @param  array{
     *     seq: int,
     *     kind: string,
     *     description: string,
     *     vendor_part_number?: string|null,
     *     quantity?: string|null,
     *     uom?: string|null,
     *     unit_price?: string|null,
     *     extended?: string|null,
     *     taxed?: bool,
     *     core_amount?: string|null,
     *     references?: list<string>|null,
     *     page?: int|null,
     *     purchase_order_line_id?: string|null,
     *     metadata?: array<string, mixed>|null,
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            seq: $data['seq'],
            kind: LineKind::from($data['kind']),
            description: $data['description'],
            vendorPartNumber: $data['vendor_part_number'] ?? null,
            quantity: $data['quantity'] ?? null,
            unitOfMeasure: $data['uom'] ?? null,
            unitPrice: $data['unit_price'] ?? null,
            extended: $data['extended'] ?? null,
            taxed: $data['taxed'] ?? false,
            coreAmount: $data['core_amount'] ?? null,
            references: $data['references'] ?? [],
            page: $data['page'] ?? null,
            purchaseOrderLineId: $data['purchase_order_line_id'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * The line's columns, nulls included: a replaced line is written whole.
     *
     * @return array{
     *     seq: int,
     *     line_kind: string,
     *     vendor_part_number: string|null,
     *     description: string,
     *     quantity: string|null,
     *     unit_of_measure: string|null,
     *     unit_price: string|null,
     *     extended: string|null,
     *     taxed: bool,
     *     core_amount: string|null,
     *     references: list<string>,
     *     page: int|null,
     *     purchase_order_line_id: string|null,
     *     metadata: array<string, mixed>|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'seq' => $this->seq,
            'line_kind' => $this->kind->value,
            'vendor_part_number' => $this->vendorPartNumber,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_of_measure' => $this->unitOfMeasure,
            'unit_price' => $this->unitPrice,
            'extended' => $this->extended,
            'taxed' => $this->taxed,
            'core_amount' => $this->coreAmount,
            'references' => $this->references,
            'page' => $this->page,
            'purchase_order_line_id' => $this->purchaseOrderLineId,
            'metadata' => $this->metadata,
        ];
    }
}
