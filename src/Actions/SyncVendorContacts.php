<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Actions;

use Illuminate\Database\Eloquent\Collection;
use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * Mirrors a vendor's contact columns onto its party, marking what it writes with
 * `metadata.source = vendor_columns`. The columns stay in this release because
 * the shop app still reads them; this is what stops the two drifting until the
 * held drop runs.
 *
 * Idempotent: a vendor already in step writes nothing, so the data migration can
 * rerun. An empty column is left out rather than clearing the party.
 */
final class SyncVendorContacts
{
    private const SOURCE = 'vendor_columns';

    public function execute(Vendor $vendor): void
    {
        $party = $vendor->ensureParty();

        $this->syncPoint($party, 'email', $vendor->contact_email, null);
        $this->syncPoint($party, 'phone', $vendor->contact_phone, null);
        $this->syncPoint($party, 'name', $vendor->contact_name, 'Contact');
        $this->syncAddress($party, $vendor);
    }

    /**
     * One mirrored point per channel. The unique index covers soft-deleted rows,
     * so a value already held anywhere on the party is promoted, never inserted.
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

        $point = $match ?? $mirror ?? new ContactPoint([
            'party_id' => $party->id,
            'channel' => $channel,
            'label' => $label,
            'metadata' => ['source' => self::SOURCE],
        ]);

        if ($match !== null && $mirror !== null && ! $mirror->is($match)) {
            $mirror->delete();
        }

        if ($match === null) {
            $point->value_raw = $value;
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

    private function syncAddress(Party $party, Vendor $vendor): void
    {
        $held = array_filter(
            [$vendor->address_line_1, $vendor->address_line_2, $vendor->city, $vendor->state, $vendor->postal_code],
            fn (?string $value): bool => trim((string) $value) !== '',
        );

        if ($held === []) {
            return;
        }

        $address = Address::query()
            ->where('party_id', $party->id)
            ->where('metadata->source', self::SOURCE)
            ->first() ?? new Address(['party_id' => $party->id, 'purpose' => 'general', 'is_primary' => true]);

        $address->fill([
            'line1' => (string) $vendor->address_line_1,
            'line2' => $vendor->address_line_2,
            'city' => (string) $vendor->city,
            'region' => $vendor->state,
            'postal_code' => $vendor->postal_code,
            'country_code' => $this->countryCode($vendor->country),
            'metadata' => $this->addressMetadata($vendor->country),
        ]);

        if (! $address->exists || $address->isDirty()) {
            $address->save();
        }
    }

    /**
     * The column is free text and `country_code` is two letters and required.
     * AFWD's vendors are American, so that is the default for anything else.
     */
    private function countryCode(?string $country): string
    {
        $country = trim((string) $country);

        return strlen($country) === 2 ? strtoupper($country) : 'US';
    }

    /**
     * A country that does not fit the code keeps its wording, because the column
     * it came from is going away.
     *
     * @return array{source: string, country?: string}
     */
    private function addressMetadata(?string $country): array
    {
        $country = trim((string) $country);

        if ($country === '' || strlen($country) === 2) {
            return ['source' => self::SOURCE];
        }

        return ['source' => self::SOURCE, 'country' => $country];
    }
}
