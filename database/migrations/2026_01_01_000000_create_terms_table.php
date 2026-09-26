<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $terms = config('laraterms.tables.terms', 'terms');

        Schema::create($terms, function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 64)->index();

            // Each (scope_type, scope_id) is an isolated catalog. Global terms use '' and 0,
            // not null: MySQL treats nulls as distinct and the unique key would let duplicates in.
            $table->string('scope_type', 64)->default('');
            $table->unsignedBigInteger('scope_id')->default(0);

            $table->foreignId('parent_id')->nullable()->index();

            // Plain text, so ORDER BY and indexes work without JSON functions. The accessor
            // prefers name_translations for the current locale.
            $table->string('name');
            $table->json('name_translations')->nullable();

            $table->string('handle');

            $table->text('description')->nullable();
            $table->json('description_translations')->nullable();

            // Every name and description in every language, rebuilt on save, so one LIKE
            // searches them all.
            $table->text('search_text')->nullable();

            $table->string('color', 7)->nullable();           // hex #rrggbb
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('terms_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'taxonomy', 'handle'], 'terms_unique');

            $table->index(['scope_type', 'scope_id', 'taxonomy'], 'terms_scope_tax_idx');
            $table->index(['taxonomy', 'parent_id']);
        });

        try {
            Schema::table($terms, function (Blueprint $table) {
                $table->fullText('search_text', 'terms_search_text_ft');
            });
        } catch (\Throwable $e) {
            // Engines without FULLTEXT, such as SQLite, search with an unindexed LIKE.
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laraterms.tables.terms', 'terms'));
    }
};
