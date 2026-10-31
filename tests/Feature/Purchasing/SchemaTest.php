<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Actions\ResolveVendorItem;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentSource;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentStatus;
use RobinsonRyan\Vendor\Purchasing\Enums\LineKind;
use RobinsonRyan\Vendor\Purchasing\Models\PurchaseLine;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;

const UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

/**
 * Runs the write inside a savepoint. A failed statement otherwise aborts the
 * transaction RefreshDatabase wraps the test in, and Testbench's migration
 * teardown then dies on it.
 */
function expectConstraintViolation(Closure $write): void
{
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class);
}

describe('database-generated keys', function (): void {
    it('declares uuidv7() as the column default on every new table', function (string $table): void {
        $default = DB::table('information_schema.columns')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', $table)
            ->where('column_name', 'id')
            ->value('column_default');

        expect($default)->toBe('uuidv7()');
    })->with(['vendor_documents', 'purchase_lines', 'vendor_items']);

    it('leaves the key to the database', function (string $modelClass): void {
        $keyAtInsert = 'unset';
        $modelClass::creating(function ($model) use (&$keyAtInsert): void {
            $keyAtInsert = $model->getKey();
        });

        $model = $modelClass::factory()->create();

        expect($keyAtInsert)->toBeNull()
            ->and($model->getKey())->toMatch(UUID_V7)
            ->and(class_uses_recursive($modelClass))->not->toContain(HasUuids::class);
    })->with([VendorDocument::class, PurchaseLine::class, VendorItem::class]);
});

describe('vendor_documents', function (): void {
    it('has every spec column', function (): void {
        expect(Schema::hasColumns('vendor_documents', [
            'tenant_id', 'vendor_id', 'kind', 'number', 'document_date', 'subtotal', 'tax', 'freight',
            'total', 'source', 'source_ref', 'source_version', 'paperless_document_id', 'status',
            'received_at', 'metadata',
        ]))->toBeTrue();
    });

    it('is unique on source and source ref', function (): void {
        VendorDocument::factory()->create(['source' => DocumentSource::Scan, 'source_ref' => 'paperless:afwd:1']);

        expectConstraintViolation(fn (): VendorDocument => VendorDocument::factory()->create([
            'source' => DocumentSource::Scan,
            'source_ref' => 'paperless:afwd:1',
        ]));
    });

    it('lets two sources share a ref', function (): void {
        VendorDocument::factory()->create(['source' => DocumentSource::Scan, 'source_ref' => 'x']);
        $second = VendorDocument::factory()->create(['source' => DocumentSource::Email, 'source_ref' => 'x']);

        expect($second->exists)->toBeTrue();
    });

    it('casts its enums and money', function (): void {
        $document = VendorDocument::factory()->create([
            'kind' => DocumentKind::CreditMemo,
            'status' => DocumentStatus::LinesUnsettled,
            'total' => '19.5',
            'paperless_document_id' => 9_000_000_001,
        ])->fresh();

        expect($document->kind)->toBe(DocumentKind::CreditMemo)
            ->and($document->status)->toBe(DocumentStatus::LinesUnsettled)
            ->and($document->total)->toBe('19.50')
            ->and($document->paperless_document_id)->toBe(9_000_000_001);
    });

    it('stops a vendor with documents from being deleted', function (): void {
        $document = VendorDocument::factory()->create();

        expectConstraintViolation(fn (): ?bool => $document->vendor?->delete());
    });
});

describe('purchase_lines', function (): void {
    it('has every spec column', function (): void {
        expect(Schema::hasColumns('purchase_lines', [
            'tenant_id', 'vendor_document_id', 'seq', 'line_kind', 'vendor_item_id', 'vendor_part_number',
            'description', 'quantity', 'unit_of_measure', 'unit_price', 'extended', 'taxed', 'core_amount',
            'references', 'page', 'purchase_order_line_id', 'metadata',
        ]))->toBeTrue();
    });

    it('goes with its document', function (): void {
        $line = PurchaseLine::factory()->create();

        $line->document->delete();

        expect(PurchaseLine::query()->whereKey($line->id)->exists())->toBeFalse();
    });

    it('keeps four places on unit price and quantity, two on the rest', function (): void {
        $line = PurchaseLine::factory()->create([
            'line_kind' => LineKind::CoreCharge,
            'quantity' => '1.2345',
            'unit_price' => '12.3456',
            'extended' => '15.24',
            'core_amount' => '4',
            'references' => ['A', 'B'],
        ])->fresh();

        expect($line->line_kind)->toBe(LineKind::CoreCharge)
            ->and($line->quantity)->toBe('1.2345')
            ->and($line->unit_price)->toBe('12.3456')
            ->and($line->extended)->toBe('15.24')
            ->and($line->core_amount)->toBe('4.00')
            ->and($line->references)->toBe(['A', 'B']);
    });

    it('has no foreign key on the purchase order line link', function (): void {
        $columns = collect(Schema::getForeignKeys('purchase_lines'))->pluck('columns')->flatten();

        expect($columns)->toContain('vendor_document_id', 'vendor_item_id')
            ->not->toContain('purchase_order_line_id');
    });
});

describe('vendor_items', function (): void {
    it('has every spec column', function (): void {
        expect(Schema::hasColumns('vendor_items', [
            'tenant_id', 'vendor_id', 'vendor_part_number', 'description', 'last_unit_price', 'last_seen_at',
            'metadata',
        ]))->toBeTrue();
    });

    it('is unique on vendor and part number', function (): void {
        $vendor = Vendor::factory()->create();
        VendorItem::factory()->create(['vendor_id' => $vendor->id, 'vendor_part_number' => 'PR60-ASF']);

        expectConstraintViolation(fn (): VendorItem => VendorItem::factory()->create([
            'vendor_id' => $vendor->id,
            'vendor_part_number' => 'PR60-ASF',
        ]));
    });

    it('stores the normalized key however the model is written', function (Closure $write): void {
        $vendor = Vendor::factory()->create();

        expect($write($vendor)->fresh()->vendor_part_number)->toBe('PR60-ASF');
    })->with([
        'create' => [fn (Vendor $vendor): VendorItem => VendorItem::query()->create([
            'vendor_id' => $vendor->id,
            'vendor_part_number' => ' pr60-asf ',
        ])],
        'update' => [function (Vendor $vendor): VendorItem {
            $item = VendorItem::factory()->create(['vendor_id' => $vendor->id]);
            $item->update(['vendor_part_number' => 'pr60-asf']);

            return $item;
        }],
        'assignment' => [function (Vendor $vendor): VendorItem {
            $item = VendorItem::factory()->create(['vendor_id' => $vendor->id]);
            $item->vendor_part_number = 'pr60-asf';
            $item->save();

            return $item;
        }],
        'updateOrCreate' => [fn (Vendor $vendor): VendorItem => VendorItem::query()->updateOrCreate(
            ['vendor_id' => $vendor->id, 'vendor_part_number' => 'pr60-asf'],
            ['description' => 'Axle shaft'],
        )],
        'no model events' => [fn (Vendor $vendor): VendorItem => VendorItem::factory()->createQuietly([
            'vendor_id' => $vendor->id,
            'vendor_part_number' => 'pr60-asf',
        ])],
    ]);

    it('keeps the description prefix of a description key', function (): void {
        $item = VendorItem::factory()->create(['vendor_part_number' => 'desc:Grade 8  bolt kit']);

        expect($item->fresh()->vendor_part_number)->toBe('desc:GRADE 8 BOLT KIT');
    });

    it('refuses a second spelling of a part number', function (): void {
        $vendor = Vendor::factory()->create();
        VendorItem::factory()->create(['vendor_id' => $vendor->id, 'vendor_part_number' => 'PR60-ASF']);

        expectConstraintViolation(fn (): VendorItem => VendorItem::factory()->create([
            'vendor_id' => $vendor->id,
            'vendor_part_number' => 'pr60-asf',
        ]));
    });

    it('is the item the next invoice line resolves to', function (): void {
        $vendor = Vendor::factory()->create();
        $item = VendorItem::query()->create(['vendor_id' => $vendor->id, 'vendor_part_number' => 'pr60-asf']);

        $resolved = (new ResolveVendorItem)->execute($vendor, 'PR60-ASF', 'Axle shaft');

        expect($resolved->id)->toBe($item->id)
            ->and(VendorItem::query()->count())->toBe(1);
    });

    it('lets two vendors share a part number', function (): void {
        VendorItem::factory()->create(['vendor_part_number' => 'PR60-ASF']);
        $second = VendorItem::factory()->create(['vendor_part_number' => 'PR60-ASF']);

        expect($second->exists)->toBeTrue();
    });

    it('holds a description key longer than a varchar', function (): void {
        $key = 'desc:'.trim(str_repeat('LONG DESCRIPTION ', 30));

        $item = VendorItem::factory()->create(['vendor_part_number' => $key]);

        expect($item->fresh()->vendor_part_number)->toBe($key);
    });
});

describe('config', function (): void {
    it('takes table names from config', function (): void {
        $defaults = config('vendor.tables');

        config(['vendor.tables' => [
            'vendor_documents' => 'custom_documents',
            'purchase_lines' => 'custom_lines',
            'vendor_items' => 'custom_items',
        ]]);

        $tables = [(new VendorDocument)->getTable(), (new PurchaseLine)->getTable(), (new VendorItem)->getTable()];

        // Testbench rolls the migrations back through this same config.
        config(['vendor.tables' => $defaults]);

        expect($tables)->toBe(['custom_documents', 'custom_lines', 'custom_items']);
    });
});
