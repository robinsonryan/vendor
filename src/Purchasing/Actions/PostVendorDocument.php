<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Data\PostOutcome;
use RobinsonRyan\Vendor\Purchasing\Data\PurchaseLineData;
use RobinsonRyan\Vendor\Purchasing\Data\VendorDocumentData;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentKind;
use RobinsonRyan\Vendor\Purchasing\Enums\DocumentStatus;
use RobinsonRyan\Vendor\Purchasing\Enums\PostResult;
use RobinsonRyan\Vendor\Purchasing\Models\PurchaseLine;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;

/**
 * The idempotent write of a vendor document, keyed on (source, source ref).
 *
 * The same version, or an older one, writes nothing at all: not even the vendor
 * is resolved. A newer version replaces the header and every line, and a vendor
 * item is never deleted, because matches hang off it.
 *
 * Posts of one document run one at a time. The row lock alone cannot do that for
 * a first post, which has no row to lock, so a rival's insert would hit the
 * unique index and throw rather than compare versions. The lock is on a hash of
 * the key: a collision only makes two documents wait for each other.
 */
final readonly class PostVendorDocument
{
    public function __construct(
        private ResolveVendor $resolveVendor = new ResolveVendor,
        private ResolveVendorItem $resolveVendorItem = new ResolveVendorItem,
    ) {}

    public function execute(VendorDocumentData $data): PostOutcome
    {
        return DB::transaction(function () use ($data): PostOutcome {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$data->source->value.'|'.$data->sourceRef]);

            /** @var class-string<VendorDocument> $model */
            $model = config('vendor.models.vendor_document', VendorDocument::class);

            $document = $model::query()
                ->where('source', $data->source->value)
                ->where('source_ref', $data->sourceRef)
                ->lockForUpdate()
                ->first();

            if (! $document instanceof VendorDocument) {
                return new PostOutcome($this->write(new $model, $data), PostResult::Created);
            }

            if (! $this->isNewer($data->sourceVersion, $document->source_version)) {
                return new PostOutcome($document, PostResult::Unchanged);
            }

            return new PostOutcome($this->write($document, $data), PostResult::Replaced);
        });
    }

    /**
     * Versions from the intake contract are ISO timestamps, so two spellings of
     * one instant are one version; anything else compares naturally, so 10 is
     * newer than 9.
     */
    private function isNewer(string $incoming, string $stored): bool
    {
        $timestamp = '/^\d{4}-\d{2}-\d{2}/';

        if (preg_match($timestamp, $incoming) === 1 && preg_match($timestamp, $stored) === 1) {
            return Carbon::parse($incoming)->greaterThan(Carbon::parse($stored));
        }

        return strnatcmp($incoming, $stored) > 0;
    }

    private function write(VendorDocument $document, VendorDocumentData $data): VendorDocument
    {
        /** @var list<string> $replacedItemIds */
        $replacedItemIds = $document->exists
            ? $document->lines()->whereNotNull('vendor_item_id')->pluck('vendor_item_id')->all()
            : [];

        $vendor = $this->resolveVendor->execute($data->vendorName, $data->paperlessCorrespondentId);

        $document->fill([
            ...$data->toArray(),
            'vendor_id' => $vendor->id,
            'tenant_id' => $data->tenantId ?? $vendor->tenant_id,
            'status' => $data->linesSettled ? DocumentStatus::Complete : DocumentStatus::LinesUnsettled,
            'received_at' => $data->receivedAt ?? $document->received_at ?? Carbon::now(),
            'metadata' => $data->metadata ?? $document->metadata,
        ]);
        $document->save();

        $document->lines()->delete();

        $itemIds = [];

        foreach ($data->lines as $line) {
            $item = $this->vendorItemFor($vendor, $line);

            $document->lines()->create([
                ...$line->toArray(),
                'tenant_id' => $document->tenant_id,
                'vendor_item_id' => $item?->id,
            ]);

            if ($item instanceof VendorItem) {
                $itemIds[] = $item->id;
            }
        }

        foreach (array_unique([...$replacedItemIds, ...$itemIds]) as $itemId) {
            $this->refreshVendorItem($itemId);
        }

        return $document->unsetRelation('lines');
    }

    private function vendorItemFor(Vendor $vendor, PurchaseLineData $line): ?VendorItem
    {
        if (! $line->kind->isStock() || ! $vendor->isStockVendor()) {
            return null;
        }

        if (trim((string) $line->vendorPartNumber) === '' && trim($line->description) === '') {
            return null;
        }

        return $this->resolveVendorItem->execute($vendor, $line->vendorPartNumber, $line->description);
    }

    /**
     * Recomputed from the lines rather than taken from the document in hand, so
     * posting out of date order cannot regress an item, and a replace that takes
     * a line away falls back to the next one. A credit memo says when the item
     * was last seen, but its price is what was refunded rather than what the
     * vendor charges.
     */
    private function refreshVendorItem(string $itemId): void
    {
        /** @var class-string<VendorItem> $model */
        $model = config('vendor.models.vendor_item', VendorItem::class);

        $item = $model::query()->findOrFail($itemId);

        $priced = $this->newestLineFirst($itemId)
            ->whereNotNull('line.unit_price')
            ->where('document.kind', '!=', DocumentKind::CreditMemo->value)
            ->first();

        $seen = $this->newestLineFirst($itemId)->first();

        $item->last_unit_price = $priced?->getAttribute('unit_price');
        $item->last_seen_at = $seen?->getAttribute('seen_on');
        $item->save();
    }

    /**
     * @return Builder<PurchaseLine>
     */
    private function newestLineFirst(string $itemId): Builder
    {
        /** @var class-string<PurchaseLine> $lineModel */
        $lineModel = config('vendor.models.purchase_line', PurchaseLine::class);

        /** @var class-string<VendorDocument> $documentModel */
        $documentModel = config('vendor.models.vendor_document', VendorDocument::class);

        $lines = (new $lineModel)->getTable();
        $documents = (new $documentModel)->getTable();
        $seenOn = 'coalesce(document.document_date, document.received_at::date)';

        return $lineModel::query()
            ->from($lines, 'line')
            ->join($documents.' as document', 'document.id', '=', 'line.vendor_document_id')
            ->where('line.vendor_item_id', $itemId)
            ->select('line.unit_price')
            ->selectRaw($seenOn.' as seen_on')
            ->orderByRaw($seenOn.' desc nulls last')
            ->orderByDesc('document.received_at')
            ->orderByDesc('document.id')
            ->orderByDesc('line.seq');
    }
}
