<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('vendor.tables.vendors', 'vendors'), function (Blueprint $table) {
            $table->foreignUuid('party_id')
                ->nullable()
                ->index()
                ->constrained(TablePrefixer::prefix('parties'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(config('vendor.tables.vendors', 'vendors'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('party_id');
        });
    }
};
