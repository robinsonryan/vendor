<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Data;

use Illuminate\Support\Carbon;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentSource;

final readonly class VendorDocumentData
{
    /**
     * @param  list<PurchaseLineData>  $lines
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $vendorName,
        public DocumentSource $source,
        public string $sourceRef,
        public string $sourceVersion,
        public DocumentKind $kind,
        public ?int $paperlessCorrespondentId = null,
        public ?int $paperlessDocumentId = null,
        public ?string $number = null,
        public ?string $documentDate = null,
        public ?string $subtotal = null,
        public ?string $tax = null,
        public ?string $freight = null,
        public ?string $total = null,
        public bool $linesSettled = false,
        public array $lines = [],
        public ?string $tenantId = null,
        public ?Carbon $receivedAt = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Reads the intake contract's body. `received_at`, `tenant_id` and
     * `metadata` are not in the contract and stay optional. A document that
     * does not say its lines settled is treated as unsettled.
     *
     * @param  array{
     *     source: array{kind: string, ref: string, version: string, paperless_document_id?: int|null},
     *     vendor: array{name: string, paperless_correspondent_id?: int|null},
     *     document: array{
     *         kind: string,
     *         number?: string|null,
     *         date?: string|null,
     *         subtotal?: string|null,
     *         tax?: string|null,
     *         freight?: string|null,
     *         total?: string|null,
     *         lines_settled?: bool,
     *     },
     *     lines?: list<array{seq: int, kind: string, description: string}>,
     *     received_at?: string|null,
     *     tenant_id?: string|null,
     *     metadata?: array<string, mixed>|null,
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $document = $data['document'];

        return new self(
            vendorName: $data['vendor']['name'],
            source: DocumentSource::from($data['source']['kind']),
            sourceRef: $data['source']['ref'],
            sourceVersion: $data['source']['version'],
            kind: DocumentKind::from($document['kind']),
            paperlessCorrespondentId: $data['vendor']['paperless_correspondent_id'] ?? null,
            paperlessDocumentId: $data['source']['paperless_document_id'] ?? null,
            number: $document['number'] ?? null,
            documentDate: $document['date'] ?? null,
            subtotal: $document['subtotal'] ?? null,
            tax: $document['tax'] ?? null,
            freight: $document['freight'] ?? null,
            total: $document['total'] ?? null,
            linesSettled: $document['lines_settled'] ?? false,
            lines: array_map(PurchaseLineData::fromArray(...), $data['lines'] ?? []),
            tenantId: $data['tenant_id'] ?? null,
            receivedAt: isset($data['received_at']) ? Carbon::parse($data['received_at']) : null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * The header columns this object fixes outright, nulls included: a newer
     * version replaces the header whole. The vendor, status, tenant, received
     * time and metadata depend on what is stored, so PostVendorDocument sets them.
     *
     * @return array{
     *     kind: string,
     *     number: string|null,
     *     document_date: string|null,
     *     subtotal: string|null,
     *     tax: string|null,
     *     freight: string|null,
     *     total: string|null,
     *     source: string,
     *     source_ref: string,
     *     source_version: string,
     *     paperless_document_id: int|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'number' => $this->number,
            'document_date' => $this->documentDate,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'freight' => $this->freight,
            'total' => $this->total,
            'source' => $this->source->value,
            'source_ref' => $this->sourceRef,
            'source_version' => $this->sourceVersion,
            'paperless_document_id' => $this->paperlessDocumentId,
        ];
    }
}
