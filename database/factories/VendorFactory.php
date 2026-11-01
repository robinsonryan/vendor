<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Vendor\Actions\SyncVendorContacts;
use RobinsonRyan\Vendor\Data\VendorContact;
use RobinsonRyan\Vendor\Models\Vendor;

/**
 * @extends Factory<Vendor>
 */
final class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * A vendor made by the factory carries the same defaults a vendor made
     * through CreateVendor / VendorData does: type `supplier`, status `active`.
     * Both are taxon tag attributes, so they can only be applied once the row
     * exists; an explicit state (contractor(), inactive(), ...) wins.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Vendor $vendor): void {
            if ($vendor->type === null) {
                $vendor->type = 'supplier';
            }

            if ($vendor->status === null) {
                $vendor->status = 'active';
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'code' => $this->faker->unique()->bothify('VND-####'),
            'website' => $this->faker->url(),
        ];
    }

    /**
     * Sets a contact on the vendor's party: the one given, or a full made-up one.
     */
    public function withContact(?VendorContact $contact = null): self
    {
        return $this->afterCreating(function (Vendor $vendor) use ($contact): void {
            (new SyncVendorContacts)->execute($vendor, $contact ?? new VendorContact(
                name: $this->faker->name(),
                email: $this->faker->companyEmail(),
                phone: $this->faker->phoneNumber(),
                line1: $this->faker->streetAddress(),
                city: $this->faker->city(),
                region: $this->faker->stateAbbr(),
                postalCode: $this->faker->postcode(),
                country: 'US',
            ));
        });
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
            // Taxon stores a value slugged, so this is the spelling the row
            // holds and the spelling a read gives back.
            $vendor->type = 'service-provider';
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
