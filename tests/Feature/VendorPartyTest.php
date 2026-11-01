<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\Vendor\Actions\CreateVendor;
use RobinsonRyan\Vendor\Actions\SyncVendorContacts;
use RobinsonRyan\Vendor\Actions\UpdateVendor;
use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Data\VendorData;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\VendorServiceProvider;

const DROP_COLUMNS = __DIR__.'/../../database/migrations/2026_12_01_000000_drop_vendor_contact_columns.php';
const COPY_CONTACTS = __DIR__.'/../../database/migrations/2026_10_31_000005_copy_vendor_contacts_to_parties.php';
const CONTACT_COLUMNS = [
    'contact_name',
    'contact_email',
    'contact_phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'country',
];

function loadMigration(string $path): Migration
{
    return require $path;
}

/**
 * @return array{parties: int, points: int, addresses: int}
 */
function countContactRows(): array
{
    return [
        'parties' => Party::withTrashed()->count(),
        'points' => ContactPoint::withTrashed()->count(),
        'addresses' => Address::withTrashed()->count(),
    ];
}

describe('party link', function (): void {
    it('gives a new vendor an organization party and records it', function (): void {
        $vendor = Vendor::factory()->create(['name' => 'Dynatrac']);

        $party = Party::query()->sole();

        expect($vendor->party_id)->toBe($party->id)
            ->and($vendor->fresh()->party_id)->toBe($party->id)
            ->and($vendor->isDirty('party_id'))->toBeFalse()
            ->and($party->partyable->is($vendor))->toBeTrue()
            ->and($party->display_name_cached)->toBe('Dynatrac')
            ->and($party->metadata)->toBe(['kind' => 'organization']);
    });

    it('declares the foreign key against the prefixed party table', function (): void {
        $foreignKey = collect(Schema::getForeignKeys('vendors'))
            ->first(fn (array $key): bool => $key['columns'] === ['party_id']);

        expect($foreignKey['foreign_table'])->toBe('heyyou_parties')
            ->and($foreignKey['on_delete'])->toBe('set null');
    });

    it('keeps party_id out of mass assignment', function (): void {
        $vendor = Vendor::factory()->create();
        $original = $vendor->party_id;

        $vendor->update(['party_id' => null]);

        expect($vendor->fresh()->party_id)->toBe($original);
    });
});

describe('vendor stock default', function (): void {
    it('is a stock vendor unless told otherwise', function (mixed $metadata, bool $expected): void {
        expect(Vendor::factory()->make(['metadata' => $metadata])->isStockVendor())->toBe($expected);
    })->with([
        'no metadata' => [null, true],
        'unset' => [[], true],
        'true' => [['stock_vendor' => true], true],
        'false' => [['stock_vendor' => false], false],
    ]);
});

describe('contact details on the party', function (): void {
    it('writes them through CreateVendor', function (): void {
        $vendor = (new CreateVendor)->execute(new VendorData(
            name: 'Dynatrac',
            contact: new VendorContact(
                name: 'Pat Lee',
                email: 'Pat@Dynatrac.example',
                phone: '801-555-0100',
                line1: '123 Main St',
                line2: 'Suite 4',
                city: 'Sandy',
                region: 'UT',
                postalCode: '84070',
                country: 'US',
            ),
        ));

        $address = $vendor->primaryAddress();

        expect($vendor->primaryEmail())->toBe('Pat@Dynatrac.example')
            ->and($vendor->primaryPhone())->toBe('801-555-0100')
            ->and($vendor->primaryContactName())->toBe('Pat Lee')
            ->and($vendor->contactPoints()->where('channel', 'name')->sole()->label)->toBe('Contact')
            ->and($address?->line1)->toBe('123 Main St')
            ->and($address?->line2)->toBe('Suite 4')
            ->and($address?->city)->toBe('Sandy')
            ->and($address?->region)->toBe('UT')
            ->and($address?->postal_code)->toBe('84070')
            ->and($address?->country_code)->toBe('US')
            ->and($address?->purpose)->toBe('general')
            ->and($address?->is_primary)->toBeTrue();
    });

    it('marks what it writes as this action\'s own', function (): void {
        $vendor = Vendor::factory()->withContact()->create();

        expect($vendor->contactPoints()->count())->toBe(3)
            ->and($vendor->contactPoints()->get()->pluck('metadata')->unique()->all())->toBe([['source' => 'vendor_columns']])
            ->and($vendor->addresses()->sole()->metadata)->toBe(['source' => 'vendor_columns']);
    });

    it('follows UpdateVendor without leaving the old value behind', function (): void {
        $vendor = (new CreateVendor)->execute(new VendorData(
            name: 'Dynatrac',
            contact: new VendorContact(
                email: 'old@dynatrac.example',
                phone: '801-555-0100',
                line1: '123 Main St',
                city: 'Sandy',
            ),
        ));

        (new UpdateVendor)->execute($vendor, new VendorData(
            name: 'Dynatrac',
            contact: new VendorContact(
                email: 'new@dynatrac.example',
                phone: '801-555-0199',
                line1: '500 State St',
            ),
        ));

        $vendor = $vendor->fresh();

        expect($vendor->primaryEmail())->toBe('new@dynatrac.example')
            ->and($vendor->primaryPhone())->toBe('801-555-0199')
            ->and($vendor->contactPoints()->where('channel', 'email')->count())->toBe(1)
            ->and($vendor->contactPoints()->where('channel', 'phone')->count())->toBe(1)
            ->and($vendor->addresses()->sole()->line1)->toBe('500 State St')
            ->and($vendor->addresses()->sole()->city)->toBe('Sandy');
    });

    it('leaves the party alone when UpdateVendor carries no contact', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(email: 'ar@dynatrac.example'))->create();

        (new UpdateVendor)->execute($vendor, new VendorData(name: 'Dynatrac'));

        expect($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('promotes an existing point rather than colliding on the unique index', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(email: 'old@dynatrac.example'))->create();
        ContactPoint::query()->create([
            'party_id' => $vendor->party_id,
            'channel' => 'email',
            'value_raw' => 'ar@dynatrac.example',
            'metadata' => ['source' => 'document'],
        ]);

        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: 'AR@dynatrac.example'));

        expect($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example')
            ->and($vendor->contactPoints()->where('channel', 'email')->count())->toBe(1);
    });

    it('restores a removed point when it is named again', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(email: 'ar@dynatrac.example'))->create();
        $vendor->contactPoints()->sole()->delete();

        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: 'other@dynatrac.example'));
        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: 'ar@dynatrac.example'));

        expect($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('writes nothing when the contact is already in step', function (): void {
        $contact = new VendorContact(email: 'ar@dynatrac.example', phone: '801-555-0100', city: 'Sandy');
        $vendor = Vendor::factory()->withContact($contact)->create();
        $stamps = ContactPoint::query()->orderBy('id')->pluck('updated_at')->all();
        $addressStamp = $vendor->addresses()->sole()->updated_at;

        (new SyncVendorContacts)->execute($vendor, $contact);

        expect(ContactPoint::query()->orderBy('id')->pluck('updated_at')->all())->toEqual($stamps)
            ->and($vendor->addresses()->sole()->updated_at)->toEqual($addressStamp);
    });

    it('leaves out an empty value rather than clearing the party', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(
            email: 'ar@dynatrac.example',
            line1: '123 Main St',
            line2: 'Suite 4',
            city: 'Sandy',
            country: 'CA',
        ))->create();

        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: '  ', line1: '', city: 'Draper'));

        $address = $vendor->addresses()->sole();

        expect($vendor->primaryEmail())->toBe('ar@dynatrac.example')
            ->and($address->line1)->toBe('123 Main St')
            ->and($address->line2)->toBe('Suite 4')
            ->and($address->city)->toBe('Draper')
            ->and($address->country_code)->toBe('CA');
    });

    it('survives an empty contact', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(name: '', phone: '  ', country: '  '))->create();

        expect($vendor->contactPoints()->count())->toBe(0)
            ->and($vendor->addresses()->count())->toBe(0)
            ->and($vendor->primaryEmail())->toBeNull()
            ->and($vendor->primaryPhone())->toBeNull()
            ->and($vendor->primaryAddress())->toBeNull();
    });
});

describe('a contact value that changes', function (): void {
    /**
     * @return array{Vendor, ContactPoint}
     */
    function vendorWithPoint(string $channel, string $field, string $value): array
    {
        $vendor = Vendor::factory()->withContact(new VendorContact(...[$field => $value]))->create();

        return [$vendor, $vendor->contactPoints()->where('channel', $channel)->sole()];
    }

    function setContact(Vendor $vendor, string $field, string $value): void
    {
        (new SyncVendorContacts)->execute($vendor, new VendorContact(...[$field => $value]));
    }

    it('does not hand the old value\'s verification to the new one', function (string $channel, string $field, string $old, string $new): void {
        [$vendor, $point] = vendorWithPoint($channel, $field, $old);
        $point->markVerified('link');

        setContact($vendor, $field, $new);

        $current = $vendor->contactPoints()->where('channel', $channel)->sole();

        expect($current->value_raw)->toBe($new)
            ->and($current->id)->not->toBe($point->id)
            ->and($current->is_primary)->toBeTrue()
            ->and($current->isCurrentlyVerified())->toBeFalse()
            ->and($current->verified_at)->toBeNull()
            ->and($current->verification_method)->toBeNull()
            ->and($current->verificationEvents()->count())->toBe(0);
    })->with([
        'email' => ['email', 'email', 'old@dynatrac.example', 'new@dynatrac.example'],
        'phone' => ['phone', 'phone', '801-555-0100', '801-555-0199'],
        'name' => ['name', 'name', 'Pat Lee', 'Sam Ortiz'],
    ]);

    it('keeps the old value\'s history on the row it belongs to', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'email', 'old@dynatrac.example');
        $point->markVerified('link');
        $consent = ContactPointConsent::query()->create([
            'contact_point_id' => $point->id,
            'purpose_category' => 'billing',
            'status' => ContactPointConsent::STATUS_OPTED_IN,
            'captured_at' => now(),
            'source' => 'test',
        ]);

        setContact($vendor, 'email', 'new@dynatrac.example');

        $retired = ContactPoint::onlyTrashed()->where('channel', 'email')->sole();
        $current = $vendor->contactPoints()->where('channel', 'email')->sole();

        expect($retired->id)->toBe($point->id)
            ->and($retired->value_raw)->toBe('old@dynatrac.example')
            ->and($retired->is_primary)->toBeFalse()
            ->and($retired->isCurrentlyVerified())->toBeTrue()
            ->and($retired->verificationEvents()->count())->toBe(1)
            ->and($consent->fresh()?->contact_point_id)->toBe($point->id)
            ->and($current->consents()->count())->toBe(0);
    });

    it('does not hand a bounced status to the new value', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'email', 'old@dynatrac.example');
        $point->update(['status' => ContactPoint::STATUS_BOUNCED]);

        setContact($vendor, 'email', 'new@dynatrac.example');

        expect($vendor->fresh()->primaryEmail())->toBe('new@dynatrac.example');
    });

    it('keeps a label someone gave the point', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'email', 'old@dynatrac.example');
        $point->update(['label' => 'Accounts']);

        setContact($vendor, 'email', 'new@dynatrac.example');

        expect($vendor->contactPoints()->where('channel', 'email')->sole()->label)->toBe('Accounts');
    });

    it('gets the verification back with the value', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'email', 'old@dynatrac.example');
        $point->markVerified('link');

        setContact($vendor, 'email', 'new@dynatrac.example');
        setContact($vendor, 'email', 'old@dynatrac.example');

        $current = $vendor->contactPoints()->where('channel', 'email')->sole();

        expect($current->id)->toBe($point->id)
            ->and($current->is_primary)->toBeTrue()
            ->and($current->isCurrentlyVerified())->toBeTrue()
            ->and(ContactPoint::onlyTrashed()->where('channel', 'email')->sole()->value_raw)->toBe('new@dynatrac.example');
    });

    it('stays verified when only the spelling changes', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'email', 'ar@dynatrac.example');
        $point->markVerified('link');

        setContact($vendor, 'email', 'AR@Dynatrac.example');

        $current = $vendor->contactPoints()->where('channel', 'email')->sole();

        expect($current->id)->toBe($point->id)
            ->and($current->isCurrentlyVerified())->toBeTrue();
    });

    it('stops vouching for an address that moved', function (string $field, string $value): void {
        $vendor = Vendor::factory()->withContact()->create();
        $vendor->addresses()->sole()->update([
            'validation_status' => Address::STATUS_VERIFIED,
            'geocode' => ['lat' => 40.57, 'lng' => -111.88],
        ]);

        setContact($vendor, $field, $value);

        $address = $vendor->addresses()->sole();

        expect($address->validation_status)->toBe(Address::STATUS_UNVERIFIED)
            ->and($address->geocode)->toBeNull();
    })->with([
        'line one' => ['line1', '500 State St'],
        'line two' => ['line2', 'Suite 9'],
        'city' => ['city', 'Draper'],
        'state' => ['region', 'NV'],
        'postal code' => ['postalCode', '84020'],
        'country' => ['country', 'CA'],
    ]);

    it('keeps vouching for an address that did not move', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(line1: '123 Main St', country: 'United States'))->create();
        $vendor->addresses()->sole()->update(['validation_status' => Address::STATUS_VERIFIED]);

        (new SyncVendorContacts)->execute($vendor, new VendorContact(phone: '801-555-0199', country: 'USA'));

        expect($vendor->addresses()->sole()->validation_status)->toBe(Address::STATUS_VERIFIED)
            ->and($vendor->addresses()->sole()->metadata['country'])->toBe('USA');
    });
});

describe('a contact sent as a form\'s groups', function (): void {
    /**
     * @param  array<string, string|null>|null  $contact
     * @param  array<string, string|null>|null  $address
     */
    function sendGroups(Vendor $vendor, ?array $contact, ?array $address): void
    {
        (new SyncVendorContacts)->execute($vendor, VendorContact::fromGroups($contact, $address));
    }

    function fullContact(): VendorContact
    {
        return new VendorContact(
            name: 'Pat Lee',
            email: 'old@example.com',
            phone: '801-555-0100',
            line1: '123 Main St',
            line2: 'Suite 4',
            city: 'Sandy',
            region: 'UT',
            postalCode: '84070',
            country: 'CA',
        );
    }

    /**
     * @return array<string, string>
     */
    function emptyAddress(): array
    {
        return ['line1' => '', 'line2' => '', 'city' => '', 'region' => '', 'postal_code' => '', 'country' => ''];
    }

    function documentPoint(Vendor $vendor, string $channel, string $value): ContactPoint
    {
        return ContactPoint::query()->create([
            'party_id' => $vendor->party_id,
            'channel' => $channel,
            'value_raw' => $value,
            'metadata' => ['source' => 'document'],
        ]);
    }

    function documentAddress(Vendor $vendor): Address
    {
        return Address::query()->create([
            'party_id' => $vendor->party_id,
            'purpose' => 'billing',
            'line1' => 'PO Box 1',
            'city' => 'Sandy',
            'country_code' => 'US',
            'metadata' => ['source' => 'document'],
        ]);
    }

    it('becomes what was sent, with the emptied fields cleared and the street moved', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups(
            $vendor,
            ['name' => 'Pat Lee', 'email' => '', 'phone' => ''],
            ['line1' => '500 State St', 'line2' => '', 'city' => 'Sandy', 'region' => 'UT', 'postal_code' => '', 'country' => 'CA'],
        );

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            line1: '500 State St',
            city: 'Sandy',
            region: 'UT',
            country: 'CA',
        ));
    });

    it('clears a point whatever empty looks like', function (string $channel, mixed $empty): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups($vendor, [$channel => $empty], null);

        expect($vendor->contactPoints()->where('channel', $channel)->count())->toBe(0)
            ->and($vendor->contactPoints()->count())->toBe(2)
            ->and($vendor->fresh()->contact()->{$channel})->toBeNull();
    })->with(['email', 'phone', 'name'])->with([
        'an empty string' => [''],
        'spaces' => ['   '],
        'null' => [null],
    ]);

    it('retires a cleared point with its history, and brings it back when named again', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(email: 'old@example.com'))->create();
        $point = $vendor->contactPoints()->sole();
        $point->markVerified('link');

        sendGroups($vendor, ['email' => ''], null);

        $retired = ContactPoint::onlyTrashed()->sole();

        expect($retired->id)->toBe($point->id)
            ->and($retired->is_primary)->toBeFalse()
            ->and($retired->verificationEvents()->count())->toBe(1);

        sendGroups($vendor, ['email' => 'old@example.com'], null);

        $current = $vendor->contactPoints()->sole();

        expect($current->id)->toBe($point->id)
            ->and($current->is_primary)->toBeTrue()
            ->and($current->isCurrentlyVerified())->toBeTrue()
            ->and($vendor->fresh()->contact()->email)->toBe('old@example.com');
    });

    it('changes nothing for a group that was not sent', function (mixed $notSent): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups($vendor, $notSent, $notSent);

        expect($vendor->fresh()->contact())->toEqual(fullContact());
    })->with(['null' => [null], 'an empty group' => [[]]]);

    it('leaves the address when only the contact group is sent', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups($vendor, ['name' => '', 'email' => '', 'phone' => ''], null);

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            line1: '123 Main St',
            line2: 'Suite 4',
            city: 'Sandy',
            region: 'UT',
            postalCode: '84070',
            country: 'CA',
        ));
    });

    it('leaves the points when only the address group is sent', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups($vendor, null, emptyAddress());

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            email: 'old@example.com',
            phone: '801-555-0100',
        ));
    });

    it('leaves a field the contact group does not carry', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups($vendor, ['email' => 'new@example.com'], null);

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            email: 'new@example.com',
            phone: '801-555-0100',
            line1: '123 Main St',
            line2: 'Suite 4',
            city: 'Sandy',
            region: 'UT',
            postalCode: '84070',
            country: 'CA',
        ));
    });

    it('replaces the address as a whole, fields it leaves out included', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();
        $address = $vendor->addresses()->sole();

        sendGroups($vendor, null, ['line1' => '500 State St', 'city' => 'Draper']);

        $moved = $vendor->addresses()->sole();

        expect($moved->id)->toBe($address->id)
            ->and($moved->is_primary)->toBeTrue()
            ->and($moved->line1)->toBe('500 State St')
            ->and($moved->line2)->toBeNull()
            ->and($moved->city)->toBe('Draper')
            ->and($moved->region)->toBeNull()
            ->and($moved->postal_code)->toBeNull();
    });

    it('stops vouching for an address that lost a field', function (string $kept): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();
        $vendor->addresses()->sole()->update([
            'validation_status' => Address::STATUS_VERIFIED,
            'geocode' => ['lat' => 40.57, 'lng' => -111.88],
        ]);

        sendGroups($vendor, null, [
            'line1' => '123 Main St',
            'line2' => 'Suite 4',
            'city' => 'Sandy',
            'region' => 'UT',
            'postal_code' => '84070',
            'country' => 'CA',
            $kept => '',
        ]);

        $address = $vendor->addresses()->sole();

        expect($address->validation_status)->toBe(Address::STATUS_UNVERIFIED)
            ->and($address->geocode)->toBeNull();
    })->with(['line1', 'line2', 'city', 'region', 'postal_code', 'country']);

    it('falls back to the default code when the country is cleared, and forgets its wording', function (string $country): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(line1: '123 Main St', city: 'Sandy', country: $country))->create();

        sendGroups($vendor, null, ['line1' => '123 Main St', 'city' => 'Sandy', 'country' => '']);

        $address = $vendor->addresses()->sole();

        expect($address->country_code)->toBe('US')
            ->and($address->metadata)->toBe(['source' => 'vendor_columns']);
    })->with(['a code' => ['CA'], 'a wording' => ['Canada']]);

    it('keeps an address that only a country is left of', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        sendGroups($vendor, null, ['country' => 'CA']);

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            email: 'old@example.com',
            phone: '801-555-0100',
            country: 'CA',
        ));
    });

    it('retires an address cleared entirely, and takes a new one afterwards', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();
        $address = $vendor->addresses()->sole();

        sendGroups($vendor, null, emptyAddress());

        $retired = Address::onlyTrashed()->sole();

        expect($retired->id)->toBe($address->id)
            ->and($retired->is_primary)->toBeFalse()
            ->and($retired->line1)->toBe('123 Main St')
            ->and($vendor->addresses()->count())->toBe(0)
            ->and($vendor->fresh()->contact()->line1)->toBeNull()
            ->and($vendor->fresh()->primaryAddress())->toBeNull();

        sendGroups($vendor, null, ['line1' => '500 State St', 'city' => 'Draper']);

        expect($vendor->addresses()->sole()->is_primary)->toBeTrue()
            ->and($vendor->addresses()->sole()->id)->not->toBe($address->id)
            ->and($vendor->fresh()->contact()->line1)->toBe('500 State St');
    });

    it('does not touch what documents added', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();
        $phone = documentPoint($vendor, 'phone', '801-555-0101');
        $email = documentPoint($vendor, 'email', 'ar@example.com');
        $remitTo = documentAddress($vendor);
        $before = [$phone->fresh()->getAttributes(), $email->fresh()->getAttributes(), $remitTo->fresh()->getAttributes()];

        sendGroups($vendor, ['name' => '', 'email' => '', 'phone' => ''], emptyAddress());

        $fresh = $vendor->fresh();

        expect([$phone->fresh()->getAttributes(), $email->fresh()->getAttributes(), $remitTo->fresh()->getAttributes()])->toBe($before)
            ->and($fresh->contact())->toEqual(new VendorContact)
            ->and($fresh->contact(fromDocuments: true)->phone)->toBe('801-555-0101')
            ->and($fresh->contact(fromDocuments: true)->email)->toBe('ar@example.com')
            ->and($fresh->contact(fromDocuments: true)->line1)->toBe('PO Box 1');
    });

    it('hands a promoted document point back to the documents rather than removing it', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(email: 'old@example.com'))->create();
        $point = documentPoint($vendor, 'email', 'ar@example.com');

        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: 'ar@example.com'));

        expect($point->fresh()->is_primary)->toBeTrue()
            ->and($vendor->fresh()->contact()->email)->toBe('ar@example.com');

        sendGroups($vendor, ['email' => ''], null);

        $kept = $point->fresh();

        expect($kept->trashed())->toBeFalse()
            ->and($kept->is_primary)->toBeFalse()
            ->and($kept->metadata['source'])->toBe('document')
            ->and($vendor->fresh()->contact()->email)->toBeNull()
            ->and($vendor->fresh()->contact(fromDocuments: true)->email)->toBe('ar@example.com');
    });

    it('writes nothing when the same groups are sent again', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();
        $contact = ['name' => 'Pat Lee', 'email' => '', 'phone' => ''];
        $address = ['line1' => '500 State St', 'line2' => '', 'city' => 'Sandy', 'region' => '', 'postal_code' => '', 'country' => ''];

        sendGroups($vendor, $contact, $address);

        $stamps = ContactPoint::withTrashed()->orderBy('id')->pluck('updated_at')->all();
        $addressStamp = $vendor->addresses()->sole()->updated_at;

        sendGroups($vendor, $contact, $address);

        expect(ContactPoint::withTrashed()->orderBy('id')->pluck('updated_at')->all())->toEqual($stamps)
            ->and($vendor->addresses()->sole()->updated_at)->toEqual($addressStamp);
    });

    it('writes nothing for a vendor with no contact sent an empty form', function (): void {
        $vendor = Vendor::factory()->create();

        sendGroups($vendor, ['name' => '', 'email' => '', 'phone' => ''], emptyAddress());

        expect(ContactPoint::withTrashed()->count())->toBe(0)
            ->and(Address::withTrashed()->count())->toBe(0);
    });

    it('carries through UpdateVendor', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        (new UpdateVendor)->execute($vendor, new VendorData(
            name: 'Dynatrac',
            contact: VendorContact::fromGroups(['email' => ''], ['line1' => '500 State St']),
        ));

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            phone: '801-555-0100',
            line1: '500 State St',
            country: 'US',
        ));
    });

    it('keeps a value given for a field it was also told to clear', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: 'new@example.com', city: 'Draper', cleared: ['email', 'city']));

        expect($vendor->fresh()->contact()->email)->toBe('new@example.com')
            ->and($vendor->fresh()->contact()->city)->toBe('Draper');
    });

    it('clears only the fields it names', function (): void {
        $vendor = Vendor::factory()->withContact(fullContact())->create();

        (new SyncVendorContacts)->execute($vendor, new VendorContact(cleared: ['line2']));

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            email: 'old@example.com',
            phone: '801-555-0100',
            line1: '123 Main St',
            city: 'Sandy',
            region: 'UT',
            postalCode: '84070',
            country: 'CA',
        ));
    });
});

describe('primary accessors', function (): void {
    it('falls back to a non-primary point', function (): void {
        $vendor = Vendor::factory()->create();
        ContactPoint::query()->create(['party_id' => $vendor->party_id, 'channel' => 'email', 'value_raw' => 'ar@dynatrac.example']);

        expect($vendor->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('skips points that are not active', function (): void {
        $vendor = Vendor::factory()->create();
        ContactPoint::query()->create([
            'party_id' => $vendor->party_id,
            'channel' => 'email',
            'value_raw' => 'bounced@dynatrac.example',
            'status' => ContactPoint::STATUS_BOUNCED,
        ]);

        expect($vendor->primaryEmail())->toBeNull();
    });

    it('skips an address past its validity', function (): void {
        $vendor = Vendor::factory()->create();
        Address::query()->create([
            'party_id' => $vendor->party_id,
            'purpose' => 'general',
            'line1' => '1 Old Rd',
            'city' => 'Sandy',
            'country_code' => 'US',
            'valid_to' => now()->subDay(),
        ]);

        expect($vendor->primaryAddress())->toBeNull();
    });

    it('returns null for a vendor with no party', function (): void {
        $vendor = Vendor::factory()->createQuietly();

        expect($vendor->primaryEmail())->toBeNull()
            ->and($vendor->primaryPhone())->toBeNull()
            ->and($vendor->primaryContactName())->toBeNull()
            ->and($vendor->primaryAddress())->toBeNull();
    });

    it('asks nothing more of a page that loaded the party rows', function (): void {
        Vendor::factory()->count(3)->withContact()->create();

        $vendors = Vendor::query()->with('party.contactPoints', 'party.addresses')->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($vendors as $vendor) {
            $vendor->primaryEmail();
            $vendor->primaryPhone();
            $vendor->primaryContactName();
            $vendor->primaryAddress();
            $vendor->contact();
        }

        expect(DB::getQueryLog())->toBe([]);

        DB::disableQueryLog();
    });
});

describe('the contact as one set of values', function (): void {
    /**
     * A vendor a person has set a contact on, and whose paperwork adds more.
     */
    function vendorWithDocumentContacts(?VendorContact $contact = null): Vendor
    {
        $vendor = Vendor::factory()->withContact($contact ?? new VendorContact(email: 'pat@dynatrac.example'))->create();

        ContactPoint::query()->create(['party_id' => $vendor->party_id, 'channel' => 'phone', 'value_raw' => '801-555-0100', 'metadata' => ['source' => 'document']]);
        Address::query()->create([
            'party_id' => $vendor->party_id,
            'purpose' => 'billing',
            'line1' => 'PO Box 1',
            'city' => 'Sandy',
            'country_code' => 'US',
            'metadata' => ['source' => 'document'],
        ]);

        return $vendor->fresh();
    }

    it('reads what was written', function (): void {
        $contact = new VendorContact(
            name: 'Pat Lee',
            email: 'pat@dynatrac.example',
            phone: '801-555-0100',
            line1: '123 Main St',
            line2: 'Suite 4',
            city: 'Sandy',
            region: 'UT',
            postalCode: '84070',
            country: 'CA',
        );

        expect(Vendor::factory()->withContact($contact)->create()->fresh()->contact())->toEqual($contact);
    });

    it('keeps the wording of a country that does not fit the code', function (): void {
        $vendor = Vendor::factory()->withContact(new VendorContact(city: 'Vancouver', country: 'Canada'))->create();

        expect($vendor->fresh()->contact()->country)->toBe('Canada');
    });

    it('leaves out what documents added', function (): void {
        $contact = vendorWithDocumentContacts()->contact();

        expect($contact->email)->toBe('pat@dynatrac.example')
            ->and($contact->phone)->toBeNull()
            ->and($contact->line1)->toBeNull()
            ->and($contact->country)->toBeNull();
    });

    it('takes what documents added when asked', function (): void {
        $contact = vendorWithDocumentContacts()->contact(fromDocuments: true);

        expect($contact->email)->toBe('pat@dynatrac.example')
            ->and($contact->phone)->toBe('801-555-0100')
            ->and($contact->line1)->toBe('PO Box 1')
            ->and($contact->city)->toBe('Sandy');
    });

    it('prefers what a person set over what documents added', function (): void {
        $vendor = vendorWithDocumentContacts(new VendorContact(phone: '801-555-0199', city: 'Draper'));

        expect($vendor->contact(fromDocuments: true)->phone)->toBe('801-555-0199')
            ->and($vendor->contact(fromDocuments: true)->city)->toBe('Draper');
    });

    it('is empty for a vendor with no party', function (): void {
        expect(Vendor::factory()->createQuietly()->contact())->toEqual(new VendorContact);
    });
});

describe('a party made after the first look', function (): void {
    it('is what the accessors read', function (): void {
        $vendor = Vendor::factory()->createQuietly();

        expect($vendor->primaryEmail())->toBeNull();

        (new SyncVendorContacts)->execute($vendor, new VendorContact(email: 'ar@dynatrac.example'));

        expect($vendor->primaryEmail())->toBe('ar@dynatrac.example');
    });
});

describe('copying the contact columns to parties', function (): void {
    /**
     * A vendor from before the party link: the columns are back, as they were
     * then, and this row holds them and has no party.
     *
     * @param  array<string, string|null>  $columns
     */
    function vendorOnColumns(array $columns = []): Vendor
    {
        $vendor = Vendor::factory()->createQuietly();

        DB::table('vendors')->where('id', $vendor->id)->update([
            'contact_name' => 'Pat Lee',
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => '801-555-0100',
            'address_line_1' => '123 Main St',
            'city' => 'Sandy',
            'state' => 'UT',
            'postal_code' => '84070',
            'country' => 'US',
            ...$columns,
        ]);

        return $vendor->fresh();
    }

    beforeEach(function (): void {
        loadMigration(DROP_COLUMNS)->down();
    });

    /**
     * Testbench rolls every loaded migration back between some tests, and the
     * rollback runs this one's `down()`, so a test leaves the schema as it found
     * it instead of leaning on the transaction.
     */
    afterEach(function (): void {
        if (Schema::hasColumn('vendors', 'contact_name')) {
            loadMigration(DROP_COLUMNS)->up();
        }
    });

    it('copies a vendor from before the link', function (): void {
        $vendor = vendorOnColumns([
            'contact_name' => 'Pat Lee',
            'contact_email' => 'Pat@Dynatrac.example',
            'contact_phone' => '(801) 555-0100',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 4',
            'city' => 'Sandy',
            'state' => 'UT',
            'postal_code' => '84070',
            'country' => 'US',
        ]);

        expect($vendor->party_id)->toBeNull();

        loadMigration(COPY_CONTACTS)->up();

        $vendor = $vendor->fresh();
        $address = $vendor->addresses()->sole();

        expect($vendor->party_id)->not->toBeNull()
            ->and($vendor->party?->metadata)->toBe(['kind' => 'organization'])
            ->and($vendor->primaryEmail())->toBe('Pat@Dynatrac.example')
            ->and($vendor->primaryPhone())->toBe('(801) 555-0100')
            ->and($vendor->contactPoints()->where('channel', 'email')->sole()->value_normalized)->toBe('pat@dynatrac.example')
            ->and($vendor->contactPoints()->where('channel', 'phone')->sole()->value_normalized)->toBe('+18015550100')
            ->and($vendor->contactPoints()->where('channel', 'name')->sole()->value_raw)->toBe('Pat Lee')
            ->and($address->line1)->toBe('123 Main St')
            ->and($address->line2)->toBe('Suite 4')
            ->and($address->city)->toBe('Sandy')
            ->and($address->region)->toBe('UT')
            ->and($address->postal_code)->toBe('84070')
            ->and($address->metadata)->toBe(['source' => 'vendor_columns']);
    });

    it('adds nothing on a second run', function (): void {
        vendorOnColumns();
        vendorOnColumns();
        vendorOnColumns();
        $migration = loadMigration(COPY_CONTACTS);

        $migration->up();
        $after = countContactRows();
        $stamps = ContactPoint::query()->orderBy('id')->pluck('updated_at')->all();

        $migration->up();

        expect($after)->toBe(['parties' => 3, 'points' => 9, 'addresses' => 3])
            ->and(countContactRows())->toBe($after)
            ->and(ContactPoint::query()->orderBy('id')->pluck('updated_at')->all())->toEqual($stamps);
    });

    it('fills in a vendor whose party exists but has no contact points', function (): void {
        $vendor = vendorOnColumns(['contact_email' => 'ar@dynatrac.example']);
        $vendor->party()->create(['display_name_cached' => $vendor->name]);

        loadMigration(COPY_CONTACTS)->up();

        expect(Party::query()->count())->toBe(1)
            ->and($vendor->fresh()->party_id)->toBe(Party::query()->sole()->id)
            ->and($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('keeps a partial address rather than losing it', function (): void {
        $vendor = vendorOnColumns([
            'address_line_1' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
        ]);

        loadMigration(COPY_CONTACTS)->up();

        $address = $vendor->fresh()->addresses()->sole();

        expect($address->line1)->toBe('')
            ->and($address->city)->toBe('Sandy')
            ->and($address->country_code)->toBe('US');
    });

    it('keeps a country that is the only address field, across reruns', function (string $country, string $code, array $metadata): void {
        $vendor = vendorOnColumns([
            'address_line_1' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => $country,
        ]);
        $migration = loadMigration(COPY_CONTACTS);

        $migration->up();
        $migration->up();

        $address = $vendor->fresh()->addresses()->sole();

        expect($address->line1)->toBe('')
            ->and($address->city)->toBe('')
            ->and($address->country_code)->toBe($code)
            ->and($address->metadata)->toBe($metadata);
    })->with([
        'code' => ['CA', 'CA', ['source' => 'vendor_columns']],
        'name' => ['Canada', 'US', ['source' => 'vendor_columns', 'country' => 'Canada']],
    ]);

    it('turns a spelled-out country into a code and keeps what it read', function (string $country, string $code, array $metadata): void {
        $vendor = vendorOnColumns(['country' => $country]);

        loadMigration(COPY_CONTACTS)->up();

        $address = $vendor->fresh()->addresses()->sole();

        expect($address->country_code)->toBe($code)
            ->and($address->metadata)->toBe($metadata);
    })->with([
        'code' => ['us', 'US', ['source' => 'vendor_columns']],
        'name' => ['United States', 'US', ['source' => 'vendor_columns', 'country' => 'United States']],
        'other' => ['Canada', 'US', ['source' => 'vendor_columns', 'country' => 'Canada']],
    ]);

    it('has a down that leaves the copied rows alone', function (): void {
        vendorOnColumns();
        loadMigration(COPY_CONTACTS)->up();
        $before = countContactRows();

        loadMigration(COPY_CONTACTS)->down();

        expect(countContactRows())->toBe($before);
    });

    it('has copied every column before the drop takes them', function (): void {
        $vendor = vendorOnColumns(['address_line_2' => 'Suite 4']);

        loadMigration(COPY_CONTACTS)->up();
        loadMigration(DROP_COLUMNS)->up();

        expect($vendor->fresh()->contact())->toEqual(new VendorContact(
            name: 'Pat Lee',
            email: $vendor->getAttribute('contact_email'),
            phone: '801-555-0100',
            line1: '123 Main St',
            line2: 'Suite 4',
            city: 'Sandy',
            region: 'UT',
            postalCode: '84070',
            country: 'US',
        ));
    });

    it('still gives a vendor its party when rerun after the drop', function (): void {
        $vendor = vendorOnColumns();
        loadMigration(DROP_COLUMNS)->up();

        loadMigration(COPY_CONTACTS)->up();

        expect($vendor->fresh()->party_id)->not->toBeNull()
            ->and($vendor->fresh()->contactPoints()->count())->toBe(0);
    });
});

describe('contact column drop', function (): void {
    it('ships in the migrations, after the copy', function (): void {
        $migrations = array_map(basename(...), (array) glob(__DIR__.'/../../database/migrations/*.php'));

        expect(file_exists(DROP_COLUMNS))->toBeTrue()
            ->and(file_exists(__DIR__.'/../../database/migrations-held'))->toBeFalse()
            ->and(array_search(basename(DROP_COLUMNS), $migrations, true))
            ->toBeGreaterThan(array_search(basename(COPY_CONTACTS), $migrations, true));
    });

    it('is published with the rest', function (): void {
        $published = array_map(realpath(...), array_keys(ServiceProvider::pathsToPublish(VendorServiceProvider::class)));

        expect($published)->toContain(realpath(dirname(DROP_COLUMNS)));
    });

    it('names exactly the data it destroys', function (): void {
        $source = (string) file_get_contents(DROP_COLUMNS);

        foreach (CONTACT_COLUMNS as $column) {
            expect($source)->toContain($column);
        }
    });

    it('has dropped the contact columns and nothing else', function (): void {
        foreach (CONTACT_COLUMNS as $column) {
            expect(Schema::hasColumn('vendors', $column))->toBeFalse();
        }

        expect(Schema::hasColumns('vendors', ['id', 'tenant_id', 'name', 'code', 'website', 'notes', 'metadata', 'party_id']))->toBeTrue();
    });

    it('puts the empty columns back on rollback', function (): void {
        $migration = loadMigration(DROP_COLUMNS);

        $migration->down();

        expect(Schema::hasColumns('vendors', CONTACT_COLUMNS))->toBeTrue();

        $migration->up();
    });
});
