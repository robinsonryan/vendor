<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;

/**
 * @extends Factory<VendorItem>
 */
final class VendorItemFactory extends Factory
{
    protected $model = VendorItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'vendor_part_number' => strtoupper($this->faker->unique()->bothify('??##-###')),
            'description' => 'Ford E-350 axle shaft',
        ];
    }
}
