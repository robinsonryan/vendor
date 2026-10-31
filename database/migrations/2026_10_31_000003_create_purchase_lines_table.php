<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `purchase_order_line_id` has no foreign key: the purchase order lines live
     * in the inventory package, and the column stays unused until 2027.
     */
    public function up(): void
    {
        Schema::create(config('vendor.tables.purchase_lines', 'purchase_lines'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('vendor_document_id')
                ->constrained(config('vendor.tables.vendor_documents', 'vendor_documents'))
                ->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->string('line_kind');
            $table->foreignUuid('vendor_item_id')
                ->nullable()
                ->index()
                ->constrained(config('vendor.tables.vendor_items', 'vendor_items'))
                ->nullOnDelete();
            $table->text('vendor_part_number')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 4)->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('extended', 12, 2)->nullable();
            $table->boolean('taxed')->default(false);
            $table->decimal('core_amount', 12, 2)->nullable();
            $table->jsonb('references')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->uuid('purchase_order_line_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['vendor_document_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vendor.tables.purchase_lines', 'purchase_lines'));
    }
};
