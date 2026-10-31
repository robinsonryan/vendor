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
use RobinsonRyan\Vendor\Database\Factories\VendorItemFactory;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $vendor_id
 * @property string $vendor_part_number
 * @property string|null $description
 * @property string|null $last_unit_price
 * @property Carbon|null $last_seen_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vendor|null $vendor
 * @property-read Collection<int, PurchaseLine> $lines
 */
final class VendorItem extends Model
{
    use BelongsToVendor;

    /** @use HasFactory<VendorItemFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'vendor_part_number',
        'description',
        'last_unit_price',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'last_unit_price' => 'decimal:4',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('vendor.tables.vendor_items', 'vendor_items');
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        /** @var class-string<PurchaseLine> $model */
        $model = config('vendor.models.purchase_line', PurchaseLine::class);

        return $this->hasMany($model, 'vendor_item_id');
    }

    protected static function newFactory(): VendorItemFactory
    {
        return VendorItemFactory::new();
    }
}
