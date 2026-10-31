<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Actions\RecordVendorContact;
use RobinsonRyan\Vendor\Purchasing\Data\VendorContactData;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;

/**
 * A vendor with no contact columns, so the only contact points in play are
 * the ones the test records.
 */
function bareVendor(): Vendor
{
    return Vendor::factory()->create([
        'contact_name' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'address_line_1' => null,
        'city' => null,
        'state' => null,
        'postal_code' => null,
        'country' => null,
    ]);
}

function recordContact(Vendor $vendor, string $kind, string $value, ?VendorDocument $source = null): void
{
    (new RecordVendorContact)->execute(
        $vendor,
        VendorContactData::fromArray(['kind' => $kind, 'value' => $value]),
        $source ?? VendorDocument::factory()->create(['vendor_id' => $vendor->id]),
    );
}

describe('RecordVendorContact, contact points', function (): void {
    it('records a phone with its source document', function (): void {
        $vendor = bareVendor();
        $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id]);

        recordContact($vendor, 'phone', '(801) 555-0100', $document);

        $point = $vendor->contactPoints()->sole();

        expect($point->channel)->toBe('phone')
            ->and($point->value_raw)->toBe('(801) 555-0100')
            ->and($point->value_normalized)->toBe('+18015550100')
            ->and($point->metadata)->toBe([
                'kind' => 'phone',
                'source' => 'document',
                'source_document_id' => $document->id,
            ]);
    });

    it('records an AP email', function (): void {
        $vendor = bareVendor();

        recordContact($vendor, 'ap_email', 'AR@Dynatrac.example');

        $point = $vendor->contactPoints()->sole();

        expect($point->channel)->toBe('email')
            ->and($point->value_normalized)->toBe('ar@dynatrac.example')
            ->and($point->label)->toBe('AP email')
            ->and($point->metadata['kind'])->toBe('ap_email');
    });

    it('records a sales rep name as a labelled contact point', function (): void {
        $vendor = bareVendor();

        recordContact($vendor, 'sales_rep', 'Pat Lee');

        $point = $vendor->contactPoints()->sole();

        expect($point->channel)->toBe('name')
            ->and($point->value_raw)->toBe('Pat Lee')
            ->and($point->label)->toBe('Sales rep')
            ->and($point->metadata['kind'])->toBe('sales_rep');
    });

    it('does not duplicate a value already present, however it is spelled', function (string $kind, string $first, string $second): void {
        $vendor = bareVendor();

        recordContact($vendor, $kind, $first);
        recordContact($vendor, $kind, $second);

        expect($vendor->contactPoints()->count())->toBe(1);
    })->with([
        'phone' => ['phone', '801-555-0100', '(801) 555 0100'],
        'email' => ['ap_email', 'ar@dynatrac.example', ' AR@DYNATRAC.EXAMPLE '],
        'name' => ['sales_rep', 'Pat Lee', ' Pat Lee '],
    ]);

    it('does not duplicate a value the columns already put on the party', function (): void {
        $vendor = Vendor::factory()->create(['contact_email' => 'ar@dynatrac.example']);
        $before = ContactPoint::query()->count();

        recordContact($vendor, 'ap_email', 'ar@dynatrac.example');

        expect(ContactPoint::query()->count())->toBe($before);
    });

    it('keeps the first source when a value repeats', function (): void {
        $vendor = bareVendor();
        $first = VendorDocument::factory()->create(['vendor_id' => $vendor->id]);

        recordContact($vendor, 'phone', '801-555-0100', $first);
        recordContact($vendor, 'phone', '801-555-0100');

        expect($vendor->contactPoints()->sole()->metadata['source_document_id'])->toBe($first->id);
    });

    it('does not bring back or trip over a removed contact point', function (): void {
        $vendor = bareVendor();
        recordContact($vendor, 'phone', '801-555-0100');
        $vendor->contactPoints()->sole()->delete();

        recordContact($vendor, 'phone', '801-555-0100');

        expect($vendor->contactPoints()->count())->toBe(0)
            ->and(ContactPoint::withTrashed()->count())->toBe(1);
    });

    it('records nothing for a blank value', function (): void {
        $vendor = bareVendor();

        recordContact($vendor, 'phone', '   ');
        recordContact($vendor, 'remit_to', '   ');
        recordContact($vendor, 'account_number', '   ');

        expect($vendor->contactPoints()->count())->toBe(0)
            ->and($vendor->addresses()->count())->toBe(0)
            ->and($vendor->fresh()->metadata)->toBeNull();
    });

    it('makes a party for a vendor that predates the link', function (): void {
        $vendor = Vendor::factory()->createQuietly(['contact_email' => null]);

        recordContact($vendor, 'phone', '801-555-0100');

        expect($vendor->fresh()->party_id)->not->toBeNull()
            ->and($vendor->contactPoints()->count())->toBe(1);
    });
});

describe('RecordVendorContact, remit-to', function (): void {
    it('parses the contract example', function (): void {
        $vendor = bareVendor();
        $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id]);

        recordContact($vendor, 'remit_to', 'PO Box 1, Huntington Beach CA 92648', $document);

        $address = $vendor->addresses()->sole();

        expect($address->line1)->toBe('PO Box 1')
            ->and($address->city)->toBe('Huntington Beach')
            ->and($address->region)->toBe('CA')
            ->and($address->postal_code)->toBe('92648')
            ->and($address->country_code)->toBe('US')
            ->and($address->purpose)->toBe('billing')
            ->and($address->label)->toBe('Remit-to address')
            ->and($address->formatted_cached)->toBe('PO Box 1, Huntington Beach CA 92648')
            ->and($address->metadata)->toBe([
                'kind' => 'remit_to',
                'source' => 'document',
                'source_document_id' => $document->id,
            ]);
    });

    it('parses other layouts', function (string $value, string $line1, string $city, string $region, string $postal): void {
        $vendor = bareVendor();

        recordContact($vendor, 'remit_to', $value);

        $address = $vendor->addresses()->sole();

        expect($address->line1)->toBe($line1)
            ->and($address->city)->toBe($city)
            ->and($address->region)->toBe($region)
            ->and($address->postal_code)->toBe($postal);
    })->with([
        'comma before state' => ['123 Main St, Salt Lake City, UT 84101', '123 Main St', 'Salt Lake City', 'UT', '84101'],
        'newlines' => ["123 Main St\nSalt Lake City, UT 84101-1234", '123 Main St', 'Salt Lake City', 'UT', '84101-1234'],
        'two street parts' => ['123 Main St, Suite 4, Sandy, UT 84070', '123 Main St, Suite 4', 'Sandy', 'UT', '84070'],
        'trailing country' => ['123 Main St, Sandy, UT 84070, USA', '123 Main St', 'Sandy', 'UT', '84070'],
        'no comma before the city' => ['PO Box 1 Sandy UT 84070', 'PO Box 1 Sandy', '', 'UT', '84070'],
    ]);

    it('keeps an address it cannot parse whole', function (): void {
        $vendor = bareVendor();

        recordContact($vendor, 'remit_to', 'See reverse of invoice');

        $address = $vendor->addresses()->sole();

        expect($address->line1)->toBe('See reverse of invoice')
            ->and($address->city)->toBe('')
            ->and($address->postal_code)->toBeNull();
    });

    it('does not duplicate on normalized line one and postal code', function (string $second): void {
        $vendor = bareVendor();

        recordContact($vendor, 'remit_to', 'P.O. Box 1, Huntington Beach CA 92648');
        recordContact($vendor, 'remit_to', $second);

        expect($vendor->addresses()->count())->toBe(1);
    })->with([
        ['po box 1, Huntington Beach, CA 92648'],
        ['PO  BOX 1, HUNTINGTON BEACH CA 92648-0001'],
    ]);

    it('adds a different address', function (): void {
        $vendor = bareVendor();

        recordContact($vendor, 'remit_to', 'PO Box 1, Huntington Beach CA 92648');
        recordContact($vendor, 'remit_to', 'PO Box 2, Huntington Beach CA 92648');
        recordContact($vendor, 'remit_to', 'PO Box 1, Fountain Valley CA 92708');

        expect($vendor->addresses()->count())->toBe(3);
    });

    it('does not duplicate an address the columns already put on the party', function (): void {
        $vendor = Vendor::factory()->create([
            'address_line_1' => 'PO Box 1',
            'city' => 'Huntington Beach',
            'state' => 'CA',
            'postal_code' => '92648',
            'country' => 'US',
        ]);

        recordContact($vendor, 'remit_to', 'PO Box 1, Huntington Beach CA 92648');

        expect($vendor->addresses()->count())->toBe(1);
    });

    it('reads a suite the same in one line or two', function (string $line1, ?string $line2): void {
        $vendor = bareVendor();
        Address::query()->create([
            'party_id' => $vendor->party_id,
            'purpose' => 'general',
            'line1' => $line1,
            'line2' => $line2,
            'city' => 'Sandy',
            'postal_code' => '84070',
            'country_code' => 'US',
        ]);

        recordContact($vendor, 'remit_to', '123 Main St, Suite 4, Sandy, UT 84070');

        expect($vendor->addresses()->count())->toBe(1);
    })->with([
        'two lines, the migrated layout' => ['123 Main St', 'Suite 4'],
        'one line, the parsed layout' => ['123 Main St, Suite 4', null],
    ]);

    it('does not duplicate a suite the columns already put on the party', function (): void {
        $vendor = Vendor::factory()->create([
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 4',
            'city' => 'Sandy',
            'state' => 'UT',
            'postal_code' => '84070',
        ]);

        recordContact($vendor, 'remit_to', '123 Main St, Suite 4, Sandy, UT 84070');

        expect($vendor->addresses()->count())->toBe(1);
    });

    it('keeps two suites at one street address apart', function (): void {
        $vendor = Vendor::factory()->create([
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 4',
            'city' => 'Sandy',
            'state' => 'UT',
            'postal_code' => '84070',
        ]);

        recordContact($vendor, 'remit_to', '123 Main St, Suite 5, Sandy, UT 84070');

        expect($vendor->addresses()->count())->toBe(2);
    });

    it('does not bring back or trip over a removed address', function (): void {
        $vendor = bareVendor();
        recordContact($vendor, 'remit_to', 'PO Box 1, Huntington Beach CA 92648');
        $vendor->addresses()->sole()->delete();

        recordContact($vendor, 'remit_to', 'PO Box 1, Huntington Beach CA 92648');

        expect($vendor->addresses()->count())->toBe(0)
            ->and(Address::withTrashed()->count())->toBe(1);
    });
});

describe('RecordVendorContact, account number', function (): void {
    it('sets the account number when absent, with its source', function (): void {
        $vendor = bareVendor();
        $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id]);

        recordContact($vendor, 'account_number', 'A-1009', $document);

        expect($vendor->fresh()->metadata)->toEqual([
            'account_number' => 'A-1009',
            'account_number_source_document_id' => $document->id,
        ]);
    });

    it('keeps other metadata', function (): void {
        $vendor = bareVendor();
        $vendor->update(['metadata' => ['paperless_correspondent_id' => 57]]);

        recordContact($vendor, 'account_number', 'A-1009');

        expect($vendor->fresh()->metadata)->toHaveKey('paperless_correspondent_id', 57)
            ->and($vendor->fresh()->metadata)->toHaveKey('account_number', 'A-1009');
    });

    it('does nothing when the value repeats', function (): void {
        $vendor = bareVendor();
        recordContact($vendor, 'account_number', 'A-1009');
        $before = $vendor->fresh()->metadata;

        recordContact($vendor, 'account_number', ' a-1009 ');

        expect($vendor->fresh()->metadata)->toBe($before);
    });

    it('never overwrites; a differing value goes in the seen list', function (): void {
        $vendor = bareVendor();
        $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id]);
        recordContact($vendor, 'account_number', 'A-1009');

        recordContact($vendor, 'account_number', 'B-2002', $document);

        $metadata = $vendor->fresh()->metadata;

        expect($metadata['account_number'])->toBe('A-1009')
            ->and($metadata['account_number_seen'])->toBe([
                ['value' => 'B-2002', 'source_document_id' => $document->id],
            ]);
    });

    it('lists a differing value once', function (): void {
        $vendor = bareVendor();
        recordContact($vendor, 'account_number', 'A-1009');

        recordContact($vendor, 'account_number', 'B-2002');
        recordContact($vendor, 'account_number', 'b-2002');
        recordContact($vendor, 'account_number', 'C-3003');

        expect(array_column($vendor->fresh()->metadata['account_number_seen'], 'value'))->toBe(['B-2002', 'C-3003']);
    });
});
