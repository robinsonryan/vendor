<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Actions;

use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Data\VendorData;
use RobinsonRyan\Vendor\Models\Vendor;

final class UpdateVendor
{
    public function execute(Vendor $vendor, VendorData $data): Vendor
    {
        $vendor->fill($data->toArray());
        $vendor->type = $data->type;
        $vendor->status = $data->status;
        $vendor->save();

        if ($data->contact instanceof VendorContact) {
            (new SyncVendorContacts)->execute($vendor, $data->contact);
        }

        return $vendor;
    }
}
