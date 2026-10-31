<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Actions;

use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Data\VendorContactData;
use RobinsonRyan\Vendor\Purchasing\Enums\VendorContactKind;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;
use RobinsonRyan\Vendor\Purchasing\Support\VendorPartNumber;

/**
 * Adds a contact detail read off a document to the vendor's party, carrying the
 * document it came from. A value already held is left alone, the soft-deleted
 * included: the unique index covers those rows too, and a contact someone
 * removed should not come back with the next scan.
 */
final class RecordVendorContact
{
    public function execute(Vendor $vendor, VendorContactData $contact, VendorDocument $source): void
    {
        $value = trim($contact->value);

        if ($value === '') {
            return;
        }

        match ($contact->kind) {
            VendorContactKind::RemitTo => $this->recordRemitTo($vendor->ensureParty(), $value, $source),
            VendorContactKind::Phone => $this->recordPoint($vendor->ensureParty(), $contact->kind, 'phone', 'Phone', $value, $source),
            VendorContactKind::ApEmail => $this->recordPoint($vendor->ensureParty(), $contact->kind, 'email', 'AP email', $value, $source),
            VendorContactKind::SalesRep => $this->recordPoint($vendor->ensureParty(), $contact->kind, 'name', 'Sales rep', $value, $source),
            VendorContactKind::AccountNumber => $this->recordAccountNumber($vendor, $value, $source),
        };
    }

    private function recordPoint(
        Party $party,
        VendorContactKind $kind,
        string $channel,
        string $label,
        string $value,
        VendorDocument $source,
    ): void {
        $normalized = app(NormalizerRegistry::class)->for($channel)->normalize($value);

        $present = ContactPoint::withTrashed()
            ->where('party_id', $party->id)
            ->where('channel', $channel)
            ->where('value_normalized', $normalized)
            ->exists();

        if ($present) {
            return;
        }

        ContactPoint::query()->create([
            'party_id' => $party->id,
            'channel' => $channel,
            'value_raw' => $value,
            'label' => $label,
            'metadata' => $this->sourceMetadata($kind, $source),
        ]);
    }

    private function recordRemitTo(Party $party, string $value, VendorDocument $source): void
    {
        $address = $this->parseAddress($value);
        $key = $this->addressKey($address['line1'], $address['postal_code']);

        $present = Address::withTrashed()
            ->where('party_id', $party->id)
            ->get()
            ->contains(fn (Address $held): bool => $this->addressKey($held->line1, $held->postal_code) === $key);

        if ($present) {
            return;
        }

        Address::query()->create([
            ...$address,
            'party_id' => $party->id,
            'purpose' => 'billing',
            'label' => 'Remit-to address',
            'country_code' => 'US',
            'formatted_cached' => $value,
            'metadata' => $this->sourceMetadata(VendorContactKind::RemitTo, $source),
        ]);
    }

    /**
     * Intake carries an address as one string. What cannot be parsed stays
     * whole in `line1`, so nothing read off the document is lost.
     *
     * @return array{line1: string, city: string, region: string|null, postal_code: string|null}
     */
    private function parseAddress(string $value): array
    {
        $flat = (string) preg_replace('/\s*[\r\n]+\s*/', ', ', $value);
        $tail = '[\s,]+(?<region>[A-Za-z]{2})[\s,]+(?<postal>\d{5}(?:-\d{4})?)(?:[\s,]+(?:USA?|United States))?$/i';

        if (preg_match('/^(?<street>.+),\s*(?<city>[^,]+?)'.$tail, $flat, $parts) === 1) {
            return [
                'line1' => $parts['street'],
                'city' => $parts['city'],
                'region' => strtoupper($parts['region']),
                'postal_code' => $parts['postal'],
            ];
        }

        if (preg_match('/^(?<street>.+?)'.$tail, $flat, $parts) === 1) {
            return [
                'line1' => $parts['street'],
                'city' => '',
                'region' => strtoupper($parts['region']),
                'postal_code' => $parts['postal'],
            ];
        }

        return ['line1' => $flat, 'city' => '', 'region' => null, 'postal_code' => null];
    }

    /**
     * Line one without punctuation, so `P.O. Box 1` and `PO BOX 1` agree, plus
     * the five-digit postal code, so ZIP+4 agrees with ZIP.
     */
    private function addressKey(string $line1, ?string $postalCode): string
    {
        $bareLine = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $line1);

        return VendorPartNumber::normalize($bareLine).'|'.substr(trim((string) $postalCode), 0, 5);
    }

    private function recordAccountNumber(Vendor $vendor, string $value, VendorDocument $source): void
    {
        $metadata = $vendor->metadata ?? [];

        if (! isset($metadata['account_number'])) {
            $vendor->metadata = [
                ...$metadata,
                'account_number' => $value,
                'account_number_source_document_id' => $source->id,
            ];
            $vendor->save();

            return;
        }

        /** @var list<array{value: string, source_document_id: string}> $seen */
        $seen = $metadata['account_number_seen'] ?? [];
        $normalized = VendorPartNumber::normalize($value);

        $known = [(string) $metadata['account_number'], ...array_column($seen, 'value')];

        foreach ($known as $held) {
            if (VendorPartNumber::normalize($held) === $normalized) {
                return;
            }
        }

        $metadata['account_number_seen'] = [...$seen, ['value' => $value, 'source_document_id' => $source->id]];
        $vendor->metadata = $metadata;
        $vendor->save();
    }

    /**
     * @return array{kind: string, source: string, source_document_id: string}
     */
    private function sourceMetadata(VendorContactKind $kind, VendorDocument $source): array
    {
        return [
            'kind' => $kind->value,
            'source' => 'document',
            'source_document_id' => $source->id,
        ];
    }
}
