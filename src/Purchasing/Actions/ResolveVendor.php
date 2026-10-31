<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Actions;

use Illuminate\Database\Eloquent\Builder;
use RobinsonRyan\Vendor\Actions\CreateVendor;
use RobinsonRyan\Vendor\Data\VendorData;
use RobinsonRyan\Vendor\Models\Vendor;

final readonly class ResolveVendor
{
    public function __construct(
        private CreateVendor $createVendor = new CreateVendor,
    ) {}

    public function execute(string $name, ?int $paperlessCorrespondentId): Vendor
    {
        $name = trim($name);
        $vendor = $this->findByCorrespondent($paperlessCorrespondentId) ?? $this->findByName($name);

        if (! $vendor instanceof Vendor) {
            return $this->createVendor->execute(new VendorData(
                name: $name,
                metadata: $paperlessCorrespondentId === null
                    ? null
                    : ['paperless_correspondent_id' => $paperlessCorrespondentId],
            ));
        }

        $this->fillCorrespondent($vendor, $paperlessCorrespondentId);

        return $vendor;
    }

    /**
     * The id goes in as a string because `->>` yields text, whichever JSON type
     * it was stored as.
     */
    private function findByCorrespondent(?int $paperlessCorrespondentId): ?Vendor
    {
        if ($paperlessCorrespondentId === null) {
            return null;
        }

        return $this->vendors()
            ->where('metadata->paperless_correspondent_id', (string) $paperlessCorrespondentId)
            ->orderBy('id')
            ->first();
    }

    private function findByName(string $name): ?Vendor
    {
        return $this->vendors()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->orderBy('id')
            ->first();
    }

    private function fillCorrespondent(Vendor $vendor, ?int $paperlessCorrespondentId): void
    {
        $metadata = $vendor->metadata ?? [];

        if ($paperlessCorrespondentId === null || isset($metadata['paperless_correspondent_id'])) {
            return;
        }

        $vendor->metadata = [...$metadata, 'paperless_correspondent_id' => $paperlessCorrespondentId];
        $vendor->save();
    }

    /**
     * @return Builder<Vendor>
     */
    private function vendors(): Builder
    {
        /** @var class-string<Vendor> $model */
        $model = config('vendor.models.vendor', Vendor::class);

        return $model::query();
    }
}
