<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Actions;

use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;
use RobinsonRyan\Vendor\Purchasing\Support\VendorPartNumber;

final class ResolveVendorItem
{
    public function execute(Vendor $vendor, ?string $partNumber, string $description): VendorItem
    {
        $key = trim((string) $partNumber) === ''
            ? VendorPartNumber::forDescription($description)
            : VendorPartNumber::normalize((string) $partNumber);

        /** @var class-string<VendorItem> $model */
        $model = config('vendor.models.vendor_item', VendorItem::class);

        $item = $model::query()->firstOrCreate(
            ['vendor_id' => $vendor->id, 'vendor_part_number' => $key],
            ['tenant_id' => $vendor->tenant_id, 'description' => $description],
        );

        if (trim($description) !== '' && $item->description !== $description) {
            $item->description = $description;
            $item->save();
        }

        return $item;
    }
}
