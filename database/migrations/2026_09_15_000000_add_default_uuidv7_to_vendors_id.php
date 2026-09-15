<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives `vendors.id` the `uuidv7()` column default on installs built before
 * 0.2.0, where the create migration declared the column without one and the
 * model minted its own key in PHP through Laravel's `HasUuids`.
 *
 * 0.2.0 stops generating keys in PHP, so without this migration the next
 * insert fails with a not-null violation. SET DEFAULT is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = (string) config('vendor.tables.vendors', 'vendors');

        if (! Schema::hasTable($table)) {
            return;
        }

        $type = DB::table('information_schema.columns')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', $table)
            ->where('column_name', 'id')
            ->value('data_type');

        if ($type !== 'uuid') {
            return;
        }

        DB::statement("alter table {$table} alter column id set default uuidv7()");
    }

    /**
     * Deliberately empty: dropping the default would leave inserts with no key
     * generator. The create migration's own `down()` drops the table.
     */
    public function down(): void {}
};
