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
use RobinsonRyan\Vendor\Actions\SyncVendorContacts;
use RobinsonRyan\Vendor\Concerns\HasUuidPrimaryKey;
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
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
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

    /**
     * The columns mirrored onto the party until the held drop runs, see
     * SyncVendorContacts.
     *
     * @var list<string>
     */
    public const CONTACT_COLUMNS = [
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

    /** @var array<string, class-string> */
    protected array $tagAttributes = [
        'type' => VendorTypeDefinition::class,
        'status' => VendorStatusDefinition::class,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * The sync sits on the model, not in CreateVendor and UpdateVendor, because
     * the shop app writes vendors straight through the model and an action-only
     * sync would let its edits drift.
     *
     * Contactable's own `created` hook boots first, so the party exists by the
     * time this one runs.
     */
    protected static function booted(): void
    {
        self::created(function (Vendor $vendor): void {
            (new SyncVendorContacts)->execute($vendor);
        });

        self::updated(function (Vendor $vendor): void {
            if ($vendor->wasChanged(self::CONTACT_COLUMNS)) {
                (new SyncVendorContacts)->execute($vendor);
            }
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
        return $this->primaryContactPoint('email')?->value_raw;
    }

    public function primaryPhone(): ?string
    {
        return $this->primaryContactPoint('phone')?->value_raw;
    }

    public function primaryAddress(): ?Address
    {
        return $this->party?->addresses()
            ->current()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    /**
     * Whether this vendor's lines default to stock lines. Only an explicit
     * `metadata.stock_vendor = false` turns that off.
     */
    public function isStockVendor(): bool
    {
        return ($this->metadata['stock_vendor'] ?? true) !== false;
    }

    private function primaryContactPoint(string $channel): ?ContactPoint
    {
        return $this->party?->contactPoints()
            ->where('channel', $channel)
            ->where('status', ContactPoint::STATUS_ACTIVE)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
    }
}
