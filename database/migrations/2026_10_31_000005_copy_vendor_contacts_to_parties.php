<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use RobinsonRyan\Vendor\Actions\SyncVendorContacts;
use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * Additive: gives every existing vendor a party, records it in `party_id`, and
 * copies the contact columns into contact points and an address marked
 * `metadata.source = vendor_columns`. Safe to rerun, because a vendor already in
 * step writes nothing, and a rerun after the drop finds the columns empty. This
 * is the one reader of the columns left: it reads them off the row itself, and
 * `drop_vendor_contact_columns` runs after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var class-string<Vendor> $model */
        $model = config('vendor.models.vendor', Vendor::class);
        $sync = new SyncVendorContacts;

        foreach ($model::query()->lazyById() as $vendor) {
            $sync->execute($vendor, new VendorContact(
                name: $vendor->getAttribute('contact_name'),
                email: $vendor->getAttribute('contact_email'),
                phone: $vendor->getAttribute('contact_phone'),
                line1: $vendor->getAttribute('address_line_1'),
                line2: $vendor->getAttribute('address_line_2'),
                city: $vendor->getAttribute('city'),
                region: $vendor->getAttribute('state'),
                postalCode: $vendor->getAttribute('postal_code'),
                country: $vendor->getAttribute('country'),
            ));
        }
    }

    /**
     * Deliberately empty. Removing the copied rows could take contact points a
     * person has since edited, and nothing was taken away that needs restoring.
     */
    public function down(): void {}
};
