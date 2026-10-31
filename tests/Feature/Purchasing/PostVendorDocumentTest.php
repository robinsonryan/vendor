<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Actions\PostVendorDocument;
use RobinsonRyan\Vendor\Purchasing\Data\PostOutcome;
use RobinsonRyan\Vendor\Purchasing\Data\VendorDocumentData;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentSource;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentStatus;
use RobinsonRyan\Vendor\Purchasing\Enums\LineKind;
use RobinsonRyan\Vendor\Purchasing\Enums\PostResult;
use RobinsonRyan\Vendor\Purchasing\Models\PurchaseLine;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;

/**
 * @param  array<string, mixed>  $line
 * @return array<string, mixed>
 */
function stockLine(array $line = []): array
{
    return [
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
        'references' => [],
        'page' => 1,
        ...$line,
    ];
}

/**
 * @param  array<string, mixed>  $document
 * @param  list<array<string, mixed>>|null  $lines
 * @param  array<string, mixed>  $source
 */
function purchase(array $document = [], ?array $lines = null, array $source = []): VendorDocumentData
{
    return VendorDocumentData::fromArray([
        'source' => [
            'kind' => 'scan',
            'ref' => 'paperless:afwd:1234',
            'version' => '2026-10-13T04:11:00Z',
            'paperless_document_id' => 1234,
            ...$source,
        ],
        'vendor' => ['name' => 'Dynatrac', 'paperless_correspondent_id' => 57],
        'document' => [
            'kind' => 'invoice',
            'number' => 'INV-88123',
            'date' => '2025-04-02',
            'subtotal' => '1840.00',
            'tax' => '0.00',
            'freight' => '95.00',
            'total' => '1935.00',
            'lines_settled' => true,
            ...$document,
        ],
        'lines' => $lines ?? [stockLine()],
    ]);
}

function post(VendorDocumentData $data): PostOutcome
{
    return (new PostVendorDocument)->execute($data);
}

/**
 * @param  array<string, mixed>  $document
 */
function postDated(string $ref, string $date, string $price, array $document = []): void
{
    post(purchase(['date' => $date, ...$document], [stockLine(['unit_price' => $price])], ['ref' => $ref]));
}

/**
 * Every row of the purchasing tables, so "nothing written" is a comparison
 * rather than a count: an UPDATE would leave the counts alone.
 *
 * @return array<string, mixed>
 */
function purchasingSnapshot(): array
{
    return [
        'documents' => DB::table('vendor_documents')->orderBy('id')->get()->toArray(),
        'lines' => DB::table('purchase_lines')->orderBy('id')->get()->toArray(),
        'items' => DB::table('vendor_items')->orderBy('id')->get()->toArray(),
        'vendors' => DB::table('vendors')->orderBy('id')->get()->toArray(),
    ];
}

describe('PostVendorDocument, first post', function (): void {
    it('writes the document and its lines', function (): void {
        Carbon::setTestNow('2026-10-31 12:00:00');

        $outcome = post(purchase());
        $document = $outcome->document;

        expect($outcome->result)->toBe(PostResult::Created)
            ->and($document->exists)->toBeTrue()
            ->and($document->vendor?->name)->toBe('Dynatrac')
            ->and($document->vendor?->metadata)->toBe(['paperless_correspondent_id' => 57])
            ->and($document->kind)->toBe(DocumentKind::Invoice)
            ->and($document->number)->toBe('INV-88123')
            ->and($document->document_date?->toDateString())->toBe('2025-04-02')
            ->and($document->total)->toBe('1935.00')
            ->and($document->source)->toBe(DocumentSource::Scan)
            ->and($document->source_ref)->toBe('paperless:afwd:1234')
            ->and($document->source_version)->toBe('2026-10-13T04:11:00Z')
            ->and($document->paperless_document_id)->toBe(1234)
            ->and($document->status)->toBe(DocumentStatus::Complete)
            ->and($document->received_at?->toDateTimeString())->toBe('2026-10-31 12:00:00');

        $line = $document->lines()->sole();

        expect($line->line_kind)->toBe(LineKind::Part)
            ->and($line->vendor_part_number)->toBe('PR60-ASF')
            ->and($line->unit_of_measure)->toBe('each')
            ->and($line->quantity)->toBe('2.0000')
            ->and($line->unit_price)->toBe('920.0000')
            ->and($line->extended)->toBe('1840.00')
            ->and($line->page)->toBe(1);
    });

    it('stores the received_at it is given', function (): void {
        $data = new VendorDocumentData(
            vendorName: 'Dynatrac',
            source: DocumentSource::Email,
            sourceRef: 'gmail:ap@4x4van.example:abc',
            sourceVersion: '2026-10-13T04:11:00Z',
            kind: DocumentKind::Invoice,
            receivedAt: Carbon::parse('2026-10-01 08:30:00'),
        );

        expect(post($data)->document->received_at?->toDateTimeString())->toBe('2026-10-01 08:30:00');
    });

    it('marks a document whose lines did not settle', function (): void {
        $document = post(purchase(['lines_settled' => false]))->document;

        expect($document->status)->toBe(DocumentStatus::LinesUnsettled);
    });

    it('carries the vendor tenant onto the document, lines and items', function (): void {
        $tenant = '0199a3f1-0000-7000-8000-000000000001';
        Vendor::factory()->create([
            'name' => 'Dynatrac',
            'tenant_id' => $tenant,
            'metadata' => ['paperless_correspondent_id' => 57],
        ]);

        $document = post(purchase())->document;

        expect($document->tenant_id)->toBe($tenant)
            ->and($document->lines()->sole()->tenant_id)->toBe($tenant)
            ->and(VendorItem::query()->sole()->tenant_id)->toBe($tenant);
    });
});

describe('PostVendorDocument, vendor items', function (): void {
    it('gives stock lines a vendor item', function (string $kind): void {
        post(purchase(lines: [stockLine(['kind' => $kind])]));

        $line = PurchaseLine::query()->sole();

        expect($line->vendor_item_id)->not->toBeNull()
            ->and($line->vendorItem?->vendor_part_number)->toBe('PR60-ASF');
    })->with(['part', 'consumable']);

    it('gives other lines none', function (string $kind): void {
        post(purchase(lines: [stockLine(['kind' => $kind])]));

        expect(PurchaseLine::query()->sole()->vendor_item_id)->toBeNull()
            ->and(VendorItem::query()->count())->toBe(0);
    })->with(['freight', 'tax', 'core_charge', 'core_credit', 'discount', 'fee', 'service', 'other']);

    it('keys a stock line with no printed number on its description', function (): void {
        post(purchase(lines: [stockLine(['vendor_part_number' => null, 'description' => 'Grade 8 bolt kit'])]));

        expect(VendorItem::query()->sole()->vendor_part_number)->toBe('desc:GRADE 8 BOLT KIT');
    });

    it('skips a stock line with neither number nor description', function (): void {
        post(purchase(lines: [stockLine(['vendor_part_number' => null, 'description' => '  '])]));

        expect(PurchaseLine::query()->sole()->vendor_item_id)->toBeNull()
            ->and(VendorItem::query()->count())->toBe(0);
    });

    it('gives a stock_vendor=false vendor no items', function (): void {
        Vendor::factory()->create([
            'name' => 'Dynatrac',
            'metadata' => ['paperless_correspondent_id' => 57, 'stock_vendor' => false],
        ]);

        post(purchase());

        expect(PurchaseLine::query()->sole()->vendor_item_id)->toBeNull()
            ->and(VendorItem::query()->count())->toBe(0);
    });

    it('treats a missing stock_vendor as stock', function (): void {
        Vendor::factory()->create([
            'name' => 'Dynatrac',
            'metadata' => ['paperless_correspondent_id' => 57],
        ]);

        post(purchase());

        expect(VendorItem::query()->count())->toBe(1);
    });

    it('sets last price and last seen from the line', function (): void {
        post(purchase());

        $item = VendorItem::query()->sole();

        expect($item->last_unit_price)->toBe('920.0000')
            ->and($item->last_seen_at?->toDateString())->toBe('2025-04-02');
    });
});

describe('PostVendorDocument, idempotency', function (): void {
    it('writes nothing for the same version', function (): void {
        post(purchase());
        $before = purchasingSnapshot();
        Carbon::setTestNow(now()->addDay());

        $outcome = post(purchase(['total' => '1.00'], [stockLine(['unit_price' => '1.00'])]));

        expect($outcome->result)->toBe(PostResult::Unchanged)
            ->and($outcome->document->total)->toBe('1935.00')
            ->and(purchasingSnapshot())->toEqual($before);
    });

    it('treats the same instant in another spelling as the same version', function (): void {
        post(purchase());
        $before = purchasingSnapshot();

        $outcome = post(purchase(source: ['version' => '2026-10-13T04:11:00+00:00']));

        expect($outcome->result)->toBe(PostResult::Unchanged)
            ->and(purchasingSnapshot())->toEqual($before);
    });

    it('writes nothing for an older version', function (): void {
        post(purchase());
        $before = purchasingSnapshot();

        $outcome = post(purchase(['total' => '1.00'], source: ['version' => '2026-09-01T00:00:00Z']));

        expect($outcome->result)->toBe(PostResult::Unchanged)
            ->and(purchasingSnapshot())->toEqual($before);
    });

    it('does not resolve a vendor for a repeat', function (): void {
        post(purchase());

        $data = VendorDocumentData::fromArray([
            'source' => ['kind' => 'scan', 'ref' => 'paperless:afwd:1234', 'version' => '2026-10-13T04:11:00Z'],
            'vendor' => ['name' => 'Someone New'],
            'document' => ['kind' => 'invoice', 'lines_settled' => true],
            'lines' => [],
        ]);

        post($data);

        expect(Vendor::query()->count())->toBe(1);
    });

    it('compares non-date versions numerically', function (): void {
        post(purchase(source: ['version' => '9']));

        expect(post(purchase(source: ['version' => '10']))->result)->toBe(PostResult::Replaced)
            ->and(post(purchase(source: ['version' => '9']))->result)->toBe(PostResult::Unchanged);
    });

    it('replaces lines and header for a newer version', function (): void {
        $first = post(purchase())->document;
        $firstLine = $first->lines()->sole();

        $outcome = post(purchase(
            ['total' => '2030.00', 'lines_settled' => false],
            [stockLine(['unit_price' => '930.00']), stockLine(['seq' => 2, 'kind' => 'freight', 'vendor_part_number' => null, 'description' => 'Freight'])],
            ['version' => '2026-10-14T00:00:00Z'],
        ));

        expect($outcome->result)->toBe(PostResult::Replaced)
            ->and($outcome->document->id)->toBe($first->id)
            ->and($outcome->document->total)->toBe('2030.00')
            ->and($outcome->document->status)->toBe(DocumentStatus::LinesUnsettled)
            ->and($outcome->document->source_version)->toBe('2026-10-14T00:00:00Z')
            ->and($outcome->document->lines()->pluck('seq')->all())->toBe([1, 2])
            ->and(PurchaseLine::query()->whereKey($firstLine->id)->exists())->toBeFalse()
            ->and(VendorDocument::query()->count())->toBe(1);
    });

    it('keeps received_at across a replace', function (): void {
        Carbon::setTestNow('2026-10-13 05:00:00');
        post(purchase());
        Carbon::setTestNow('2026-10-14 05:00:00');

        $document = post(purchase(source: ['version' => '2026-10-14T00:00:00Z']))->document;

        expect($document->received_at?->toDateTimeString())->toBe('2026-10-13 05:00:00');
    });

    it('never deletes a vendor item on replace', function (): void {
        post(purchase());
        $item = VendorItem::query()->sole();

        post(purchase(lines: [stockLine(['vendor_part_number' => 'DIFFERENT-1'])], source: ['version' => '2026-10-14T00:00:00Z']));

        expect(VendorItem::query()->whereKey($item->id)->exists())->toBeTrue()
            ->and(VendorItem::query()->count())->toBe(2);
    });

    it('reuses the vendor item when the line survives a replace', function (): void {
        post(purchase());
        $item = VendorItem::query()->sole();

        post(purchase(lines: [stockLine(['unit_price' => '930.00'])], source: ['version' => '2026-10-14T00:00:00Z']));

        expect(PurchaseLine::query()->sole()->vendor_item_id)->toBe($item->id)
            ->and($item->fresh()->last_unit_price)->toBe('930.0000');
    });

    it('rolls a replace back whole when a line cannot be written', function (): void {
        post(purchase());
        $before = purchasingSnapshot();

        $tooBig = stockLine(['seq' => 2, 'unit_price' => '99999999999.00']);

        try {
            post(purchase(lines: [stockLine(), $tooBig], source: ['version' => '2026-10-14T00:00:00Z']));
        } catch (QueryException) {
            // The assertion is the state left behind, not the exception.
        }

        expect(purchasingSnapshot())->toEqual($before);
    });
});

describe('PostVendorDocument, last price', function (): void {
    it('moves forward with newer purchases', function (): void {
        postDated('a', '2025-01-10', '100.00');
        postDated('b', '2025-06-10', '110.00');

        $item = VendorItem::query()->sole();

        expect($item->last_unit_price)->toBe('110.0000')
            ->and($item->last_seen_at?->toDateString())->toBe('2025-06-10');
    });

    it('does not regress when an older document posts later', function (): void {
        postDated('newer', '2025-06-10', '110.00');
        postDated('older', '2025-01-10', '100.00');

        $item = VendorItem::query()->sole();

        expect($item->last_unit_price)->toBe('110.0000')
            ->and($item->last_seen_at?->toDateString())->toBe('2025-06-10');
    });

    it('falls back when the newest document changes date on replace', function (): void {
        postDated('a', '2025-01-10', '100.00');
        postDated('b', '2025-06-10', '110.00');

        post(purchase(['date' => '2024-01-01'], [stockLine(['unit_price' => '110.00'])], ['ref' => 'b', 'version' => '2026-10-14T00:00:00Z']));

        $item = VendorItem::query()->sole();

        expect($item->last_unit_price)->toBe('100.0000')
            ->and($item->last_seen_at?->toDateString())->toBe('2025-01-10');
    });

    it('recomputes an item a replace dropped its line from', function (): void {
        postDated('a', '2025-01-10', '100.00');
        postDated('b', '2025-06-10', '110.00');
        $item = VendorItem::query()->sole();

        post(purchase(['date' => '2025-06-10'], [stockLine(['vendor_part_number' => 'OTHER-1'])], ['ref' => 'b', 'version' => '2026-10-14T00:00:00Z']));

        expect($item->fresh()->last_unit_price)->toBe('100.0000');
    });

    it('clears price evidence when no line is left, keeping the item', function (): void {
        postDated('a', '2025-01-10', '100.00');
        $item = VendorItem::query()->sole();

        post(purchase(lines: [], source: ['ref' => 'a', 'version' => '2026-10-14T00:00:00Z']));

        $fresh = $item->fresh();

        expect($fresh->last_unit_price)->toBeNull()
            ->and($fresh->last_seen_at)->toBeNull();
    });

    it('breaks a same-date tie on the later line of the document', function (): void {
        post(purchase(lines: [stockLine(['unit_price' => '100.00']), stockLine(['seq' => 2, 'unit_price' => '105.00'])]));

        expect(VendorItem::query()->sole()->last_unit_price)->toBe('105.0000');
    });

    it('does not take a price from a credit memo', function (): void {
        postDated('invoice', '2025-01-10', '100.00');
        postDated('memo', '2025-02-10', '-100.00', ['kind' => 'credit_memo']);

        $item = VendorItem::query()->sole();

        expect($item->last_unit_price)->toBe('100.0000')
            ->and($item->last_seen_at?->toDateString())->toBe('2025-02-10');
    });

    it('ignores a line with no price', function (): void {
        postDated('invoice', '2025-01-10', '100.00');
        post(purchase(['date' => '2025-02-10'], [stockLine(['unit_price' => null])], ['ref' => 'unpriced']));

        expect(VendorItem::query()->sole()->last_unit_price)->toBe('100.0000');
    });
});
