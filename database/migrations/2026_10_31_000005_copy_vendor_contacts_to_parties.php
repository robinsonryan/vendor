<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use RobinsonRyan\Vendor\Actions\SyncVendorContacts;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * Additive: gives every existing vendor a party, records it in `party_id`, and
 * copies the contact columns into contact points and an address marked
 * `metadata.source = vendor_columns`. Safe to rerun, because a vendor already in
 * step writes nothing. The columns themselves are untouched here; the drop is the
 * held migration in `database/migrations-held`.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var class-string<Vendor> $model */
        $model = config('vendor.models.vendor', Vendor::class);
        $sync = new SyncVendorContacts;

        foreach ($model::query()->lazyById() as $vendor) {
            $sync->execute($vendor);
        }
    }

    /**
     * Deliberately empty. Removing the copied rows could take contact points a
     * person has since edited, and nothing was taken away that needs restoring.
     */
    public function down(): void {}
};
