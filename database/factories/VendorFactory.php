<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * @extends Factory<Vendor>
 */
final class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'code' => $this->faker->unique()->bothify('VND-####'),
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'address_line_1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'US',
            'website' => $this->faker->url(),
        ];
    }

    public function supplier(): self
    {
        return $this->state(fn (): array => [])->afterCreating(function (Vendor $vendor): void {
            $vendor->type = 'supplier';
            $vendor->save();
        });
    }

    public function contractor(): self
    {
        return $this->state(fn (): array => [])->afterCreating(function (Vendor $vendor): void {
            $vendor->type = 'contractor';
            $vendor->save();
        });
    }

    public function serviceProvider(): self
    {
        return $this->state(fn (): array => [])->afterCreating(function (Vendor $vendor): void {
            $vendor->type = 'service_provider';
            $vendor->save();
        });
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => [])->afterCreating(function (Vendor $vendor): void {
            $vendor->status = 'inactive';
            $vendor->save();
        });
    }

    public function suspended(): self
    {
        return $this->state(fn (): array => [])->afterCreating(function (Vendor $vendor): void {
            $vendor->status = 'suspended';
            $vendor->save();
        });
    }
}
