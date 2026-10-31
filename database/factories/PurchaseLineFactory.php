<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Vendor\Purchasing\Enums\LineKind;
use RobinsonRyan\Vendor\Purchasing\Models\PurchaseLine;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;

/**
 * @extends Factory<PurchaseLine>
 */
final class PurchaseLineFactory extends Factory
{
    protected $model = PurchaseLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_document_id' => VendorDocument::factory(),
            'seq' => 1,
            'line_kind' => LineKind::Part,
            'vendor_part_number' => $this->faker->bothify('??##-###'),
            'description' => 'Ford E-350 axle shaft',
            'quantity' => '1.0000',
            'unit_of_measure' => 'each',
            'unit_price' => '100.0000',
            'extended' => '100.00',
            'taxed' => false,
            'references' => [],
        ];
    }
}
