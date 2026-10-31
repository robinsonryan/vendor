<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentSource;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentStatus;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;

/**
 * @extends Factory<VendorDocument>
 */
final class VendorDocumentFactory extends Factory
{
    protected $model = VendorDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paperlessId = $this->faker->unique()->numberBetween(1, 9_999_999);

        return [
            'vendor_id' => Vendor::factory(),
            'kind' => DocumentKind::Invoice,
            'number' => $this->faker->unique()->bothify('INV-#####'),
            'document_date' => $this->faker->date(),
            'subtotal' => '100.00',
            'tax' => '0.00',
            'freight' => '10.00',
            'total' => '110.00',
            'source' => DocumentSource::Scan,
            'source_ref' => 'paperless:afwd:'.$paperlessId,
            'source_version' => '2026-10-13T04:11:00Z',
            'paperless_document_id' => $paperlessId,
            'status' => DocumentStatus::Complete,
            'received_at' => now(),
        ];
    }
}
