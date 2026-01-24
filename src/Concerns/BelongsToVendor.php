<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * @method BelongsTo belongsTo(string $related, string|null $foreignKey = null, string|null $ownerKey = null, string|null $relation = null)
 */
trait BelongsToVendor
{
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(
            config('vendor.models.vendor', Vendor::class),
            'vendor_id'
        );
    }
}
