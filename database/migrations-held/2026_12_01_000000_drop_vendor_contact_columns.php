<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HELD. DESTRUCTIVE. Not published and not run: it sits outside
 * `database/migrations` on purpose, and moves in only on Ryan's explicit yes,
 * under the destructive-migration rule, with a fresh backup taken first.
 *
 * Destroys, for every row of the vendors table, the contents of these columns:
 *
 *   contact_name, contact_email, contact_phone,
 *   address_line_1, address_line_2, city, state, postal_code, country
 *
 * That data should already be on each vendor's hey-you party, copied there by
 * `2026_10_31_000005_copy_vendor_contacts_to_parties` and kept in step since by
 * SyncVendorContacts. Before running this, the shop app must have stopped reading
 * the columns, `Vendor`, `VendorData` and `SyncVendorContacts` must stop touching
 * them, and every vendor must be confirmed to have its party.
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
