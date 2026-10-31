<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\Vendor\Purchasing\Data\PurchaseLineData;
use RobinsonRyan\Vendor\Purchasing\Data\VendorContactData;
use RobinsonRyan\Vendor\Purchasing\Data\VendorDocumentData;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentSource;
use RobinsonRyan\Vendor\Purchasing\Enums\LineKind;
use RobinsonRyan\Vendor\Purchasing\Enums\VendorContactKind;

/**
 * @return array<string, mixed>
 */
function intakeBody(): array
{
    return [
        'source' => [
            'kind' => 'scan',
            'ref' => 'paperless:afwd:1234',
            'version' => '2026-10-13T04:11:00Z',
            'paperless_document_id' => 1234,
        ],
        'vendor' => [
            'name' => 'Dynatrac',
            'paperless_correspondent_id' => 57,
            'account_number' => 'A-1009',
            'contacts' => [['kind' => 'ap_email', 'value' => 'ar@dynatrac.example']],
        ],
        'document' => [
            'kind' => 'invoice',
            'number' => 'INV-88123',
            'date' => '2025-04-02',
            'subtotal' => '1840.00',
            'tax' => '0.00',
            'freight' => '95.00',
            'total' => '1935.00',
            'lines_settled' => true,
        ],
        'lines' => [[
            'seq' => 1,
            'kind' => 'part',
            'vendor_part_number' => 'PR60-ASF',
            'description' => 'Pro-Rock 60 axle shaft',
            'quantity' => '2',
            'uom' => 'each',
            'unit_price' => '920.00',
            'extended' => '1840.00',
            'taxed' => false,
            'core_amount' => null,
            'references' => ['1FDXE45S5XHA12345'],
            'page' => 1,
        ]],
    ];
}

describe('LineKind', function (): void {
    it('counts only parts and consumables as stock', function (): void {
        $stock = array_filter(LineKind::cases(), fn (LineKind $kind): bool => $kind->isStock());

        expect(array_values($stock))->toBe([LineKind::Part, LineKind::Consumable]);
    });

    it('carries the spec line kinds', function (): void {
        expect(array_map(fn (LineKind $kind): string => $kind->value, LineKind::cases()))->toBe([
            'part', 'consumable', 'freight', 'tax', 'core_charge', 'core_credit',
            'discount', 'fee', 'service', 'other',
        ]);
    });
});

describe('VendorDocumentData', function (): void {
    it('reads the intake contract body', function (): void {
        $data = VendorDocumentData::fromArray(intakeBody());

        expect($data->source)->toBe(DocumentSource::Scan)
            ->and($data->sourceRef)->toBe('paperless:afwd:1234')
            ->and($data->sourceVersion)->toBe('2026-10-13T04:11:00Z')
            ->and($data->paperlessDocumentId)->toBe(1234)
            ->and($data->vendorName)->toBe('Dynatrac')
            ->and($data->paperlessCorrespondentId)->toBe(57)
            ->and($data->kind)->toBe(DocumentKind::Invoice)
            ->and($data->number)->toBe('INV-88123')
            ->and($data->documentDate)->toBe('2025-04-02')
            ->and($data->total)->toBe('1935.00')
            ->and($data->linesSettled)->toBeTrue()
            ->and($data->receivedAt)->toBeNull()
            ->and($data->lines)->toHaveCount(1)
            ->and($data->lines[0])->toBeInstanceOf(PurchaseLineData::class);
    });

    it('defaults lines_settled to false when the reader did not say', function (): void {
        $body = intakeBody();
        unset($body['document']['lines_settled']);

        expect(VendorDocumentData::fromArray($body)->linesSettled)->toBeFalse();
    });

    it('takes an optional received_at', function (): void {
        $data = VendorDocumentData::fromArray([...intakeBody(), 'received_at' => '2026-10-13T05:00:00Z']);

        expect($data->receivedAt)->toBeInstanceOf(Carbon::class)
            ->and($data->receivedAt?->toIso8601ZuluString())->toBe('2026-10-13T05:00:00Z');
    });

    it('turns into the header columns, without vendor or lines', function (): void {
        expect(VendorDocumentData::fromArray(intakeBody())->toArray())->toBe([
            'kind' => 'invoice',
            'number' => 'INV-88123',
            'document_date' => '2025-04-02',
            'subtotal' => '1840.00',
            'tax' => '0.00',
            'freight' => '95.00',
            'total' => '1935.00',
            'source' => 'scan',
            'source_ref' => 'paperless:afwd:1234',
            'source_version' => '2026-10-13T04:11:00Z',
            'paperless_document_id' => 1234,
        ]);
    });
});

describe('PurchaseLineData', function (): void {
    it('maps the contract line onto the line columns', function (): void {
        $line = PurchaseLineData::fromArray(intakeBody()['lines'][0]);

        expect($line->kind)->toBe(LineKind::Part)
            ->and($line->toArray())->toBe([
                'seq' => 1,
                'line_kind' => 'part',
                'vendor_part_number' => 'PR60-ASF',
                'description' => 'Pro-Rock 60 axle shaft',
                'quantity' => '2',
                'unit_of_measure' => 'each',
                'unit_price' => '920.00',
                'extended' => '1840.00',
                'taxed' => false,
                'core_amount' => null,
                'references' => ['1FDXE45S5XHA12345'],
                'page' => 1,
                'purchase_order_line_id' => null,
                'metadata' => null,
            ]);
    });
});

describe('VendorContactData', function (): void {
    it('round-trips a contract contact', function (): void {
        $contact = VendorContactData::fromArray(['kind' => 'remit_to', 'value' => 'PO Box 1, Huntington Beach CA 92648']);

        expect($contact->kind)->toBe(VendorContactKind::RemitTo)
            ->and($contact->toArray())->toBe(['kind' => 'remit_to', 'value' => 'PO Box 1, Huntington Beach CA 92648']);
    });

    it('rejects a kind outside the contract', function (): void {
        VendorContactData::fromArray(['kind' => 'fax', 'value' => '1']);
    })->throws(ValueError::class);
});
