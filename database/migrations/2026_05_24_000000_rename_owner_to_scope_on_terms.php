<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renames terms.owner_* to terms.scope_* on installs created before 0.2.
 *
 * Never remove it: apps that published the 0.1 create_terms_table run their own copy,
 * which still creates owner_*, and rely on this rename. It is idempotent, so installs
 * that already have scope_* skip it.
 */
return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
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
