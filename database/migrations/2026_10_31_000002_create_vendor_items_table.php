<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `vendor_part_number` is text because a description-keyed item holds a
     * whole line description behind its `desc:` prefix.
     */
    public function up(): void
    {
        Schema::create(config('vendor.tables.vendor_items', 'vendor_items'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('vendor_id')->constrained(config('vendor.tables.vendors', 'vendors'));
            $table->text('vendor_part_number');
            $table->text('description')->nullable();
            $table->decimal('last_unit_price', 12, 4)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'vendor_part_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vendor.tables.vendor_items', 'vendor_items'));
    }
};
