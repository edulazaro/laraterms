<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade migration for existing installs:
 *   `terms.owner_type`  →  `terms.scope_type`
 *   `terms.owner_id`    →  `terms.scope_id`
 *
 * The conceptual rename clarifies intent: the column tracks the scope of the
 * term catalog (which entity isolates this set of terms), not "ownership" in
 * a creator/permissions sense.
 *
 * Idempotent: skips work if the columns are already renamed (fresh installs
 * created with the post-rename create migration).
 */
return new class extends Migration {
    public function up(): void
    {
        $table = config('laraterms.tables.terms', 'terms');

        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'scope_type') && ! Schema::hasColumn($table, 'owner_type')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table) {
            $t->dropUnique('terms_unique');
            $t->dropIndex('terms_owner_tax_idx');
        });

        Schema::table($table, function (Blueprint $t) {
            $t->renameColumn('owner_type', 'scope_type');
            $t->renameColumn('owner_id', 'scope_id');
        });

        Schema::table($table, function (Blueprint $t) {
            $t->unique(['scope_type', 'scope_id', 'taxonomy', 'handle'], 'terms_unique');
            $t->index(['scope_type', 'scope_id', 'taxonomy'], 'terms_scope_tax_idx');
        });
    }

    public function down(): void
    {
        $table = config('laraterms.tables.terms', 'terms');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'scope_type')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) {
            $t->dropUnique('terms_unique');
            $t->dropIndex('terms_scope_tax_idx');
        });

        Schema::table($table, function (Blueprint $t) {
            $t->renameColumn('scope_type', 'owner_type');
            $t->renameColumn('scope_id', 'owner_id');
        });

        Schema::table($table, function (Blueprint $t) {
            $t->unique(['owner_type', 'owner_id', 'taxonomy', 'handle'], 'terms_unique');
            $t->index(['owner_type', 'owner_id', 'taxonomy'], 'terms_owner_tax_idx');
        });
    }
};
