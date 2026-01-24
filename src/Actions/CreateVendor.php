<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Actions;

use RobinsonRyan\Vendor\Data\VendorData;
use RobinsonRyan\Vendor\Models\Vendor;

final class CreateVendor
{
    public function execute(VendorData $data): Vendor
    {
        $vendor = new Vendor($data->toArray());
        $vendor->type = $data->type;
        $vendor->status = $data->status;
        $vendor->save();

        return $vendor;
    }
}
