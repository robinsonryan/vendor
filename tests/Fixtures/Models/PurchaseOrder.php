<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Vendor\Concerns\BelongsToVendor;

/**
 * A consumer's model that belongs to a vendor.
 *
 * It exists so the BelongsToVendor trait has a class that uses it: PHPStan
 * cannot analyse a trait nothing mixes in (trait.unused), and the package's
 * own source has no consumer of its own.
 */
final class PurchaseOrder extends Model
{
    use BelongsToVendor;

    protected $table = 'purchase_orders';

    protected $guarded = [];
}
