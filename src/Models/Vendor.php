<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Traits\Contactable;
use RobinsonRyan\Taxon\HasTags;
use RobinsonRyan\Vendor\Concerns\HasUuidPrimaryKey;
use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Database\Factories\VendorFactory;
use RobinsonRyan\Vendor\TagDefinitions\VendorStatusDefinition;
use RobinsonRyan\Vendor\TagDefinitions\VendorTypeDefinition;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string|null $code
 * @property string|null $type taxon tag attribute, see $tagAttributes
 * @property string|null $status taxon tag attribute, see $tagAttributes
 * @property string|null $website
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property string|null $party_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Party|null $party
 */
final class Vendor extends Model
{
    use Contactable;

    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    use HasTags;
    use HasUuidPrimaryKey;

    /** @var array<string, class-string> */
    protected array $tagAttributes = [
        'type' => VendorTypeDefinition::class,
        'status' => VendorStatusDefinition::class,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'website',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Contactable's own `created` hook boots first, so the party exists by the
     * time this one records it, however the vendor was written.
     */
    protected static function booted(): void
    {
        self::created(function (Vendor $vendor): void {
            $vendor->ensureParty();
        });
    }

    public function getTable(): string
    {
        return config('vendor.tables.vendors', 'vendors');
    }

    /**
     * Find or make this vendor's party, mark it an organization and record it
     * in `party_id`. Written straight to the row because this runs inside the
     * `created` event, while the model's own save is still in flight.
     */
    public function ensureParty(): Party
    {
        $party = $this->party()->first() ?? $this->party()->create([
            'display_name_cached' => $this->getDisplayNameForParty(),
        ]);

        if (($party->metadata['kind'] ?? null) !== 'organization') {
            $party->metadata = [...($party->metadata ?? []), 'kind' => 'organization'];
            $party->save();
        }

        if ($this->party_id !== $party->id) {
            $this->newQueryWithoutScopes()->whereKey($this->getKey())->toBase()->update(['party_id' => $party->id]);
            $this->setAttribute('party_id', $party->id);
            $this->syncOriginalAttribute('party_id');
        }

        $this->unsetRelation('party');

        return $party;
    }

    public function primaryEmail(): ?string
    {
        return $this->contactPoint('email', true)?->value_raw;
    }

    public function primaryPhone(): ?string
    {
        return $this->contactPoint('phone', true)?->value_raw;
    }

    public function primaryContactName(): ?string
    {
        return $this->contactPoint('name', true)?->value_raw;
    }

    public function primaryAddress(): ?Address
    {
        return $this->address(true);
    }

    /**
     * The contact a person set on this vendor, as SyncVendorContacts wrote it.
     * Contacts read off documents join it only with `$fromDocuments`, so a caller
     * has to choose to show them: they are the purchasing record's.
     */
    public function contact(bool $fromDocuments = false): VendorContact
    {
        $address = $this->address($fromDocuments);

        return new VendorContact(
            name: $this->contactPoint('name', $fromDocuments)?->value_raw,
            email: $this->contactPoint('email', $fromDocuments)?->value_raw,
            phone: $this->contactPoint('phone', $fromDocuments)?->value_raw,
            line1: $address?->line1 === '' ? null : $address?->line1,
            line2: $address?->line2,
            city: $address?->city === '' ? null : $address?->city,
            region: $address?->region,
            postalCode: $address?->postal_code,
            country: $address?->metadata['country'] ?? $address?->country_code,
        );
    }

    /**
     * Whether this vendor's lines default to stock lines. Only an explicit
     * `metadata.stock_vendor = false` turns that off.
     */
    public function isStockVendor(): bool
    {
        return ($this->metadata['stock_vendor'] ?? true) !== false;
    }

    /**
     * Reads the party's rows through the relations rather than a query of its
     * own, so eager loading `party.contactPoints` serves a whole page.
     */
    private function contactPoint(string $channel, bool $fromDocuments): ?ContactPoint
    {
        return $this->party?->contactPoints
            ->where('channel', $channel)
            ->where('status', ContactPoint::STATUS_ACTIVE)
            ->when(! $fromDocuments, fn ($points) => $points->where('is_primary', true))
            ->sortBy([['is_primary', 'desc'], ['id', 'asc']])
            ->first();
    }

    /**
     * Likewise for `party.addresses`, with hey-you's `current` scope read in PHP.
     */
    private function address(bool $fromDocuments): ?Address
    {
        return $this->party?->addresses
            ->reject(fn (Address $address): bool => (bool) $address->valid_from?->isFuture() || (bool) $address->valid_to?->isPast())
            ->when(! $fromDocuments, fn ($addresses) => $addresses->where('is_primary', true))
            ->sortBy([['is_primary', 'desc'], ['id', 'asc']])
            ->first();
    }

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
    }
}
