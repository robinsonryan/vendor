<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RobinsonRyan\Vendor\Concerns\HasUuidPrimaryKey;
use RobinsonRyan\Vendor\Database\Factories\PurchaseLineFactory;
use RobinsonRyan\Vendor\Purchasing\Enums\LineKind;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $vendor_document_id
 * @property int $seq
 * @property LineKind $line_kind
 * @property string|null $vendor_item_id
 * @property string|null $vendor_part_number
 * @property string $description
 * @property string|null $quantity
 * @property string|null $unit_of_measure
 * @property string|null $unit_price
 * @property string|null $extended
 * @property bool $taxed
 * @property string|null $core_amount
 * @property list<string>|null $references
 * @property int|null $page
 * @property string|null $purchase_order_line_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VendorDocument $document
 * @property-read VendorItem|null $vendorItem
 */
final class PurchaseLine extends Model
{
    /** @use HasFactory<PurchaseLineFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'vendor_document_id',
        'seq',
        'line_kind',
        'vendor_item_id',
        'vendor_part_number',
        'description',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'extended',
        'taxed',
        'core_amount',
        'references',
        'page',
        'purchase_order_line_id',
        'metadata',
    ];

    protected $casts = [
        'seq' => 'integer',
        'line_kind' => LineKind::class,
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'extended' => 'decimal:2',
        'taxed' => 'boolean',
        'core_amount' => 'decimal:2',
        'references' => 'array',
        'page' => 'integer',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('vendor.tables.purchase_lines', 'purchase_lines');
    }

    /**
     * @return BelongsTo<VendorDocument, $this>
     */
    public function document(): BelongsTo
    {
        /** @var class-string<VendorDocument> $model */
        $model = config('vendor.models.vendor_document', VendorDocument::class);

        return $this->belongsTo($model, 'vendor_document_id');
    }

    /**
     * @return BelongsTo<VendorItem, $this>
     */
    public function vendorItem(): BelongsTo
    {
        /** @var class-string<VendorItem> $model */
        $model = config('vendor.models.vendor_item', VendorItem::class);

        return $this->belongsTo($model, 'vendor_item_id');
    }

    protected static function newFactory(): PurchaseLineFactory
    {
        return PurchaseLineFactory::new();
    }
}
