<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('vendor.tables.vendor_documents', 'vendor_documents'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('vendor_id')->constrained(config('vendor.tables.vendors', 'vendors'));
            $table->string('kind');
            $table->string('number')->nullable();
            $table->date('document_date')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax', 12, 2)->nullable();
            $table->decimal('freight', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('source');
            $table->string('source_ref');
            $table->string('source_version');
            $table->unsignedBigInteger('paperless_document_id')->nullable()->index();
            $table->string('status');
            $table->timestamp('received_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index(['vendor_id', 'document_date']);
            $table->index(['vendor_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vendor.tables.vendor_documents', 'vendor_documents'));
    }
};
