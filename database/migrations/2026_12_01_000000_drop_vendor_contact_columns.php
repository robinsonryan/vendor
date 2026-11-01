<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DESTRUCTIVE. Destroys, for every row of the vendors table, the contents of these
 * columns:
 *
 *   contact_name, contact_email, contact_phone,
 *   address_line_1, address_line_2, city, state, postal_code, country
 *
 * That data is on each vendor's hey-you party, copied there by
 * `2026_10_31_000005_copy_vendor_contacts_to_parties`, which runs first and reads
 * the columns off the row. The party is the only copy from here on: `Vendor` and
 * `VendorData` no longer hold these fields, and SyncVendorContacts writes the party
 * directly. An application must have stopped reading the columns before it takes
 * this migration.
 *
 * It falls under the destructive-migration rule wherever vendors exist: a fresh
 * backup verified restorable, every vendor confirmed to have its party, and the
 * owner's explicit yes for that database.
 *
 * `down()` puts the columns back empty. It cannot restore their contents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('vendor.tables.vendors', 'vendors'), function (Blueprint $table) {
            $table->dropColumn([
                'contact_name',
                'contact_email',
                'contact_phone',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table(config('vendor.tables.vendors', 'vendors'), function (Blueprint $table) {
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
        });
    }
};
