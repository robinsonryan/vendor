<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RobinsonRyan\Taxon\HasTags;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    use HasTags;
    use HasUuids;

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

    public function getTable(): string
    {
        return config('vendor.tables.vendors', 'vendors');
    }

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
    }
}
