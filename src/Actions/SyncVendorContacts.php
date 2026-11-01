<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Actions;

use Illuminate\Database\Eloquent\Collection;
use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * Sets a vendor's contact on its party. This is the only writer of the party's
 * primary rows: one point per channel and one address, which documents never
 * touch, since RecordVendorContact adds its rows beside them.
 *
 * Idempotent: a contact already in step writes nothing. An empty value is left
 * out rather than clearing the party.
 */
final class SyncVendorContacts
{
    /**
     * The mark on the rows this action owns. The name is where those rows first
     * came from, and it stays because rows already carry it.
     */
    private const SOURCE = 'vendor_columns';

    /**
     * What an address's validation vouches for. The purpose, label and metadata
     * can change without the place changing.
     *
     * @var list<string>
     */
    private const ADDRESS_LOCATION = ['line1', 'line2', 'city', 'region', 'postal_code', 'country_code'];

    public function execute(Vendor $vendor, VendorContact $contact): void
    {
        $party = $vendor->ensureParty();

        $this->syncPoint($party, 'email', $contact->email, null);
        $this->syncPoint($party, 'phone', $contact->phone, null);
        $this->syncPoint($party, 'name', $contact->name, 'Contact');
        $this->syncAddress($party, $contact);
    }

    /**
     * One owned point per channel. A row stands for its value: verification,
     * consent and delivery status belong to that value, so a new value gets a new
     * row and the old row is retired with its history intact. The unique index
     * covers soft-deleted rows, so a value already held anywhere on the party is
     * promoted, never inserted.
     */
    private function syncPoint(Party $party, string $channel, ?string $value, ?string $label): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $normalized = app(NormalizerRegistry::class)->for($channel)->normalize($value);

        /** @var Collection<int, ContactPoint> $points */
        $points = ContactPoint::withTrashed()
            ->where('party_id', $party->id)
            ->where('channel', $channel)
            ->get();

        $match = $points->firstWhere('value_normalized', $normalized);
        $mirror = $points->first(
            fn (ContactPoint $point): bool => ! $point->trashed() && ($point->metadata['source'] ?? null) === self::SOURCE
        );

        $point = $match ?? new ContactPoint([
            'party_id' => $party->id,
            'channel' => $channel,
            'value_raw' => $value,
            'label' => $mirror->label ?? $label,
            'metadata' => ['source' => self::SOURCE],
        ]);

        if ($mirror !== null && ! $mirror->is($point)) {
            $mirror->delete();
        }

        $point->is_primary = true;

        if ($point->trashed()) {
            $point->restore();
        } elseif (! $point->exists || $point->isDirty()) {
            $point->save();
        }

        $points->reject(fn (ContactPoint $other): bool => $other->is($point) || ! $other->is_primary)
            ->each(fn (ContactPoint $other): bool => $other->update(['is_primary' => false]));
    }

    private function syncAddress(Party $party, VendorContact $contact): void
    {
        $place = array_filter(
            [
                'line1' => $contact->line1,
                'line2' => $contact->line2,
                'city' => $contact->city,
                'region' => $contact->region,
                'postal_code' => $contact->postalCode,
            ],
            fn (?string $value): bool => trim((string) $value) !== '',
        );
        $country = trim((string) $contact->country);

        if ($place === [] && $country === '') {
            return;
        }

        $address = Address::query()
            ->where('party_id', $party->id)
            ->where('metadata->source', self::SOURCE)
            ->first() ?? new Address([
                'party_id' => $party->id,
                'purpose' => 'general',
                'is_primary' => true,
                'line1' => '',
                'city' => '',
                'country_code' => 'US',
                'metadata' => ['source' => self::SOURCE],
            ]);

        $address->fill($place);

        if ($country !== '') {
            $address->fill([
                'country_code' => $this->countryCode($country),
                'metadata' => $this->addressMetadata($country),
            ]);
        }

        if ($address->isDirty(self::ADDRESS_LOCATION)) {
            $address->validation_status = Address::STATUS_UNVERIFIED;
            $address->geocode = null;
        }

        if (! $address->exists || $address->isDirty()) {
            $address->save();
        }
    }

    /**
     * A country is free text and `country_code` is two letters and required.
     * AFWD's vendors are American, so that is the default for anything else.
     */
    private function countryCode(string $country): string
    {
        return strlen($country) === 2 ? strtoupper($country) : 'US';
    }

    /**
     * A country that does not fit the code keeps its wording, because the party
     * holds the only copy.
     *
     * @return array{source: string, country?: string}
     */
    private function addressMetadata(string $country): array
    {
        if (strlen($country) === 2) {
            return ['source' => self::SOURCE];
        }

        return ['source' => self::SOURCE, 'country' => $country];
    }
}
