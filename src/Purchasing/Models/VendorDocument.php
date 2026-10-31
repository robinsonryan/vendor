<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RobinsonRyan\Vendor\Concerns\BelongsToVendor;
use RobinsonRyan\Vendor\Concerns\HasUuidPrimaryKey;
use RobinsonRyan\Vendor\Database\Factories\VendorDocumentFactory;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentSource;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentStatus;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $vendor_id
 * @property DocumentKind $kind
 * @property string|null $number
 * @property Carbon|null $document_date
 * @property string|null $subtotal
 * @property string|null $tax
 * @property string|null $freight
 * @property string|null $total
 * @property DocumentSource $source
 * @property string $source_ref
 * @property string $source_version
 * @property int|null $paperless_document_id
 * @property DocumentStatus $status
 * @property Carbon|null $received_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vendor|null $vendor
 * @property-read Collection<int, PurchaseLine> $lines
 */
final class VendorDocument extends Model
{
    use BelongsToVendor;

    /** @use HasFactory<VendorDocumentFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'kind',
        'number',
        'document_date',
        'subtotal',
        'tax',
        'freight',
        'total',
        'source',
        'source_ref',
        'source_version',
        'paperless_document_id',
        'status',
        'received_at',
        'metadata',
    ];

    protected $casts = [
        'kind' => DocumentKind::class,
        'document_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'freight' => 'decimal:2',
        'total' => 'decimal:2',
        'source' => DocumentSource::class,
        'paperless_document_id' => 'integer',
        'status' => DocumentStatus::class,
        'received_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('vendor.tables.vendor_documents', 'vendor_documents');
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        /** @var class-string<PurchaseLine> $model */
        $model = config('vendor.models.purchase_line', PurchaseLine::class);

        return $this->hasMany($model, 'vendor_document_id')->orderBy('seq');
    }

    protected static function newFactory(): VendorDocumentFactory
    {
        return VendorDocumentFactory::new();
    }
}
