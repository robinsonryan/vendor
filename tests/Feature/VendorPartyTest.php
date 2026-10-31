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
use RobinsonRyan\Vendor\Data\VendorData;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\VendorServiceProvider;

const HELD_DROP = __DIR__.'/../../database/migrations-held/2026_12_01_000000_drop_vendor_contact_columns.php';
const COPY_CONTACTS = __DIR__.'/../../database/migrations/2026_10_31_000005_copy_vendor_contacts_to_parties.php';

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
            contactName: 'Pat Lee',
            contactEmail: 'Pat@Dynatrac.example',
            contactPhone: '801-555-0100',
            addressLine1: '123 Main St',
            addressLine2: 'Suite 4',
            city: 'Sandy',
            state: 'UT',
            postalCode: '84070',
            country: 'US',
        ));

        $address = $vendor->primaryAddress();

        expect($vendor->contact_email)->toBe('Pat@Dynatrac.example')
            ->and($vendor->primaryEmail())->toBe('Pat@Dynatrac.example')
            ->and($vendor->primaryPhone())->toBe('801-555-0100')
            ->and($vendor->contactPoints()->where('channel', 'name')->sole()->value_raw)->toBe('Pat Lee')
            ->and($address?->line1)->toBe('123 Main St')
            ->and($address?->line2)->toBe('Suite 4')
            ->and($address?->city)->toBe('Sandy')
            ->and($address?->region)->toBe('UT')
            ->and($address?->postal_code)->toBe('84070')
            ->and($address?->country_code)->toBe('US')
            ->and($address?->purpose)->toBe('general');
    });

    it('marks what it writes as coming from the columns', function (): void {
        $vendor = Vendor::factory()->create();

        expect($vendor->contactPoints()->count())->toBe(3)
            ->and($vendor->contactPoints()->get()->pluck('metadata')->unique()->all())->toBe([['source' => 'vendor_columns']])
            ->and($vendor->addresses()->sole()->metadata)->toBe(['source' => 'vendor_columns']);
    });

    it('follows UpdateVendor without leaving the old value behind', function (): void {
        $vendor = (new CreateVendor)->execute(new VendorData(
            name: 'Dynatrac',
            contactEmail: 'old@dynatrac.example',
            contactPhone: '801-555-0100',
            addressLine1: '123 Main St',
            city: 'Sandy',
        ));

        (new UpdateVendor)->execute($vendor, new VendorData(
            name: 'Dynatrac',
            contactEmail: 'new@dynatrac.example',
            contactPhone: '801-555-0199',
            addressLine1: '500 State St',
        ));

        $vendor = $vendor->fresh();

        expect($vendor->primaryEmail())->toBe('new@dynatrac.example')
            ->and($vendor->primaryPhone())->toBe('801-555-0199')
            ->and($vendor->contactPoints()->where('channel', 'email')->count())->toBe(1)
            ->and($vendor->contactPoints()->where('channel', 'phone')->count())->toBe(1)
            ->and($vendor->addresses()->sole()->line1)->toBe('500 State St')
            ->and($vendor->addresses()->sole()->city)->toBe('Sandy');
    });

    it('follows a direct model write, the path the shop app uses', function (): void {
        $vendor = new Vendor(['name' => 'Dynatrac', 'contact_email' => 'old@dynatrac.example']);
        $vendor->save();

        $vendor->fill(['contact_email' => 'new@dynatrac.example', 'city' => 'Sandy']);
        $vendor->save();

        expect($vendor->fresh()->primaryEmail())->toBe('new@dynatrac.example')
            ->and($vendor->fresh()->primaryAddress()?->city)->toBe('Sandy');
    });

    it('promotes an existing point rather than colliding on the unique index', function (): void {
        $vendor = (new CreateVendor)->execute(new VendorData(name: 'Dynatrac', contactEmail: 'old@dynatrac.example'));
        ContactPoint::query()->create([
            'party_id' => $vendor->party_id,
            'channel' => 'email',
            'value_raw' => 'ar@dynatrac.example',
            'metadata' => ['source' => 'document'],
        ]);

        (new UpdateVendor)->execute($vendor, new VendorData(name: 'Dynatrac', contactEmail: 'AR@dynatrac.example'));

        expect($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example')
            ->and($vendor->contactPoints()->where('channel', 'email')->count())->toBe(1);
    });

    it('restores a removed point the columns name again', function (): void {
        $vendor = (new CreateVendor)->execute(new VendorData(name: 'Dynatrac', contactEmail: 'ar@dynatrac.example'));
        $vendor->contactPoints()->sole()->delete();

        (new UpdateVendor)->execute($vendor, new VendorData(name: 'Dynatrac', contactEmail: 'other@dynatrac.example'));
        (new UpdateVendor)->execute($vendor->fresh(), new VendorData(name: 'Dynatrac', contactEmail: 'ar@dynatrac.example'));

        expect($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('does nothing when an unrelated column changes', function (): void {
        $vendor = Vendor::factory()->create();
        $stamps = ContactPoint::query()->orderBy('id')->pluck('updated_at')->all();

        $vendor->update(['notes' => 'Net 30']);

        expect(ContactPoint::query()->orderBy('id')->pluck('updated_at')->all())->toEqual($stamps);
    });

    it('survives the columns being empty', function (): void {
        $vendor = Vendor::factory()->create([
            'contact_name' => '',
            'contact_email' => null,
            'contact_phone' => '  ',
            'address_line_1' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => '  ',
        ]);

        expect($vendor->contactPoints()->count())->toBe(0)
            ->and($vendor->addresses()->count())->toBe(0)
            ->and($vendor->primaryEmail())->toBeNull()
            ->and($vendor->primaryPhone())->toBeNull()
            ->and($vendor->primaryAddress())->toBeNull();
    });
});

describe('a mirrored value that changes', function (): void {
    /**
     * @return array{Vendor, ContactPoint}
     */
    function vendorWithPoint(string $channel, string $column, string $value): array
    {
        $vendor = Vendor::factory()->create([$column => $value]);

        return [$vendor, $vendor->contactPoints()->where('channel', $channel)->sole()];
    }

    it('does not hand the old value\'s verification to the new one', function (string $channel, string $column, string $old, string $new): void {
        [$vendor, $point] = vendorWithPoint($channel, $column, $old);
        $point->markVerified('link');

        $vendor->update([$column => $new]);

        $current = $vendor->contactPoints()->where('channel', $channel)->sole();

        expect($current->value_raw)->toBe($new)
            ->and($current->id)->not->toBe($point->id)
            ->and($current->is_primary)->toBeTrue()
            ->and($current->isCurrentlyVerified())->toBeFalse()
            ->and($current->verified_at)->toBeNull()
            ->and($current->verification_method)->toBeNull()
            ->and($current->verificationEvents()->count())->toBe(0);
    })->with([
        'email' => ['email', 'contact_email', 'old@dynatrac.example', 'new@dynatrac.example'],
        'phone' => ['phone', 'contact_phone', '801-555-0100', '801-555-0199'],
        'name' => ['name', 'contact_name', 'Pat Lee', 'Sam Ortiz'],
    ]);

    it('keeps the old value\'s history on the row it belongs to', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'contact_email', 'old@dynatrac.example');
        $point->markVerified('link');
        $consent = ContactPointConsent::query()->create([
            'contact_point_id' => $point->id,
            'purpose_category' => 'billing',
            'status' => ContactPointConsent::STATUS_OPTED_IN,
            'captured_at' => now(),
            'source' => 'test',
        ]);

        $vendor->update(['contact_email' => 'new@dynatrac.example']);

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
        [$vendor, $point] = vendorWithPoint('email', 'contact_email', 'old@dynatrac.example');
        $point->update(['status' => ContactPoint::STATUS_BOUNCED]);

        $vendor->update(['contact_email' => 'new@dynatrac.example']);

        expect($vendor->fresh()->primaryEmail())->toBe('new@dynatrac.example');
    });

    it('keeps a label someone gave the mirrored point', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'contact_email', 'old@dynatrac.example');
        $point->update(['label' => 'Accounts']);

        $vendor->update(['contact_email' => 'new@dynatrac.example']);

        expect($vendor->contactPoints()->where('channel', 'email')->sole()->label)->toBe('Accounts');
    });

    it('gets the verification back with the value', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'contact_email', 'old@dynatrac.example');
        $point->markVerified('link');

        $vendor->update(['contact_email' => 'new@dynatrac.example']);
        $vendor->update(['contact_email' => 'old@dynatrac.example']);

        $current = $vendor->contactPoints()->where('channel', 'email')->sole();

        expect($current->id)->toBe($point->id)
            ->and($current->is_primary)->toBeTrue()
            ->and($current->isCurrentlyVerified())->toBeTrue()
            ->and(ContactPoint::onlyTrashed()->where('channel', 'email')->sole()->value_raw)->toBe('new@dynatrac.example');
    });

    it('stays verified when only the spelling changes', function (): void {
        [$vendor, $point] = vendorWithPoint('email', 'contact_email', 'ar@dynatrac.example');
        $point->markVerified('link');

        $vendor->update(['contact_email' => 'AR@Dynatrac.example']);

        $current = $vendor->contactPoints()->where('channel', 'email')->sole();

        expect($current->id)->toBe($point->id)
            ->and($current->isCurrentlyVerified())->toBeTrue();
    });

    it('stops vouching for an address that moved', function (string $column, string $value): void {
        $vendor = Vendor::factory()->create();
        $vendor->addresses()->sole()->update([
            'validation_status' => Address::STATUS_VERIFIED,
            'geocode' => ['lat' => 40.57, 'lng' => -111.88],
        ]);

        $vendor->update([$column => $value]);

        $address = $vendor->addresses()->sole();

        expect($address->validation_status)->toBe(Address::STATUS_UNVERIFIED)
            ->and($address->geocode)->toBeNull();
    })->with([
        'line one' => ['address_line_1', '500 State St'],
        'line two' => ['address_line_2', 'Suite 9'],
        'city' => ['city', 'Draper'],
        'state' => ['state', 'NV'],
        'postal code' => ['postal_code', '84020'],
        'country' => ['country', 'CA'],
    ]);

    it('keeps vouching for an address that did not move', function (): void {
        $vendor = Vendor::factory()->create(['country' => 'United States']);
        $vendor->addresses()->sole()->update(['validation_status' => Address::STATUS_VERIFIED]);

        $vendor->update(['contact_phone' => '801-555-0199', 'country' => 'USA']);

        expect($vendor->addresses()->sole()->validation_status)->toBe(Address::STATUS_VERIFIED)
            ->and($vendor->addresses()->sole()->metadata['country'])->toBe('USA');
    });
});

describe('primary accessors', function (): void {
    it('reads from the party, not the columns', function (): void {
        $vendor = Vendor::factory()->create(['contact_email' => 'column@dynatrac.example']);
        DB::table('vendors')->where('id', $vendor->id)->update(['contact_email' => null]);

        expect($vendor->fresh()->primaryEmail())->toBe('column@dynatrac.example');
    });

    it('falls back to a non-primary point', function (): void {
        $vendor = Vendor::factory()->create(['contact_email' => null, 'contact_phone' => null]);
        ContactPoint::query()->create(['party_id' => $vendor->party_id, 'channel' => 'email', 'value_raw' => 'ar@dynatrac.example']);

        expect($vendor->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('skips points that are not active', function (): void {
        $vendor = Vendor::factory()->create(['contact_email' => null]);
        ContactPoint::query()->create([
            'party_id' => $vendor->party_id,
            'channel' => 'email',
            'value_raw' => 'bounced@dynatrac.example',
            'status' => ContactPoint::STATUS_BOUNCED,
        ]);

        expect($vendor->primaryEmail())->toBeNull();
    });

    it('returns null for a vendor with no party', function (): void {
        $vendor = Vendor::factory()->createQuietly();

        expect($vendor->primaryEmail())->toBeNull()
            ->and($vendor->primaryPhone())->toBeNull()
            ->and($vendor->primaryAddress())->toBeNull();
    });
});

describe('a party made after the first look', function (): void {
    it('is what the accessors read', function (): void {
        $vendor = Vendor::factory()->createQuietly(['contact_email' => 'ar@dynatrac.example']);

        expect($vendor->primaryEmail())->toBeNull();

        (new SyncVendorContacts)->execute($vendor);

        expect($vendor->primaryEmail())->toBe('ar@dynatrac.example');
    });
});

describe('copying the contact columns to parties', function (): void {
    it('copies a vendor from before the link', function (): void {
        $vendor = Vendor::factory()->createQuietly([
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

        expect($vendor->party_id)->not->toBeNull()
            ->and($vendor->party?->metadata)->toBe(['kind' => 'organization'])
            ->and($vendor->primaryEmail())->toBe('Pat@Dynatrac.example')
            ->and($vendor->primaryPhone())->toBe('(801) 555-0100')
            ->and($vendor->contactPoints()->where('channel', 'email')->sole()->value_normalized)->toBe('pat@dynatrac.example')
            ->and($vendor->contactPoints()->where('channel', 'phone')->sole()->value_normalized)->toBe('+18015550100')
            ->and($vendor->contactPoints()->where('channel', 'name')->sole()->value_raw)->toBe('Pat Lee')
            ->and($vendor->addresses()->sole()->line2)->toBe('Suite 4')
            ->and($vendor->addresses()->sole()->metadata)->toBe(['source' => 'vendor_columns']);
    });

    it('adds nothing on a second run', function (): void {
        Vendor::factory()->count(3)->createQuietly();
        $migration = loadMigration(COPY_CONTACTS);

        $migration->up();
        $after = countContactRows();
        $stamps = ContactPoint::query()->orderBy('id')->pluck('updated_at')->all();

        $migration->up();

        expect($after)->toBe(['parties' => 3, 'points' => 9, 'addresses' => 3])
            ->and(countContactRows())->toBe($after)
            ->and(ContactPoint::query()->orderBy('id')->pluck('updated_at')->all())->toEqual($stamps);
    });

    it('adds nothing to a vendor already linked', function (): void {
        Vendor::factory()->create();
        $before = countContactRows();

        loadMigration(COPY_CONTACTS)->up();

        expect(countContactRows())->toBe($before);
    });

    it('fills in a vendor whose party exists but has no contact points', function (): void {
        $vendor = Vendor::factory()->createQuietly(['contact_email' => 'ar@dynatrac.example']);
        $vendor->party()->create(['display_name_cached' => $vendor->name]);

        loadMigration(COPY_CONTACTS)->up();

        expect(Party::query()->count())->toBe(1)
            ->and($vendor->fresh()->party_id)->toBe(Party::query()->sole()->id)
            ->and($vendor->fresh()->primaryEmail())->toBe('ar@dynatrac.example');
    });

    it('keeps a partial address rather than losing it', function (): void {
        $vendor = Vendor::factory()->createQuietly([
            'address_line_1' => null,
            'city' => 'Sandy',
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
        $vendor = Vendor::factory()->createQuietly([
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
        $vendor = Vendor::factory()->createQuietly(['country' => $country]);

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
        Vendor::factory()->create();
        $before = countContactRows();

        loadMigration(COPY_CONTACTS)->down();

        expect(countContactRows())->toBe($before);
    });
});

describe('held contact column drop', function (): void {
    it('exists, outside the migrations that ship', function (): void {
        expect(file_exists(HELD_DROP))->toBeTrue()
            ->and(glob(__DIR__.'/../../database/migrations/*drop_vendor_contact_columns*'))->toBe([]);
    });

    it('is not in any publish set', function (): void {
        $published = array_keys(ServiceProvider::pathsToPublish(VendorServiceProvider::class));

        expect($published)->not->toBeEmpty();

        foreach ($published as $path) {
            expect(realpath($path))->not->toContain('migrations-held');
        }
    });

    it('names exactly the data it destroys', function (): void {
        $source = (string) file_get_contents(HELD_DROP);

        foreach (Vendor::CONTACT_COLUMNS as $column) {
            expect($source)->toContain($column);
        }
    });

    it('drops the contact columns and nothing else', function (): void {
        loadMigration(HELD_DROP)->up();

        foreach (Vendor::CONTACT_COLUMNS as $column) {
            expect(Schema::hasColumn('vendors', $column))->toBeFalse();
        }

        expect(Schema::hasColumns('vendors', ['id', 'tenant_id', 'name', 'code', 'website', 'notes', 'metadata', 'party_id']))->toBeTrue();
    });

    it('puts the empty columns back on rollback', function (): void {
        $migration = loadMigration(HELD_DROP);

        $migration->up();
        $migration->down();

        expect(Schema::hasColumns('vendors', Vendor::CONTACT_COLUMNS))->toBeTrue();
    });
});
