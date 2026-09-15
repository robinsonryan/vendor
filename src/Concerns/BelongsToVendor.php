<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * Gives a consumer's model its `vendor()` relation.
 *
 * @mixin Model
 */
trait BelongsToVendor
{
    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        /** @var class-string<Vendor> $model */
        $model = config('vendor.models.vendor', Vendor::class);

        return $this->belongsTo($model, 'vendor_id');
    }
}
