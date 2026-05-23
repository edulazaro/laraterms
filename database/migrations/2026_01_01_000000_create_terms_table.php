<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $terms = config('laraterms.tables.terms', 'terms');

        Schema::create($terms, function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 64)->index();

            // Polimórfico al "owner" del término (Organization, Team, etc.).
            // Sentinela para globales: owner_type='', owner_id=0.
            $table->string('owner_type', 64)->default('');
            $table->unsignedBigInteger('owner_id')->default(0);

            $table->foreignId('parent_id')->nullable()->index();

            // name: plain text. Es el fallback canónico, usado para handle, ORDER BY,
            // index y debugging SQL trivial. Si name_translations tiene valor para
            // el locale activo, el accessor lo devuelve en su lugar.
            $table->string('name');
            $table->json('name_translations')->nullable();

            // Identificador estable, único por owner+taxonomía. Reemplaza al slug.
            $table->string('handle');

            $table->text('description')->nullable();
            $table->json('description_translations')->nullable();

            // Concatenación de name + valores de name_translations + description
            // + valores de description_translations. Auto-mantenido en saving().
            // Permite búsquedas LIKE agnósticas de idioma con un solo column.
            $table->text('search_text')->nullable();

            $table->string('color', 7)->nullable();           // hex #rrggbb
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('terms_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            // Handle único por (owner, taxonomía). Cada despacho tiene su
            // espacio de handles aislado.
            $table->unique(['owner_type', 'owner_id', 'taxonomy', 'handle'], 'terms_unique');

            // Lookups frecuentes
            $table->index(['owner_type', 'owner_id', 'taxonomy'], 'terms_owner_tax_idx');
            $table->index(['taxonomy', 'parent_id']);
        });

        // FULLTEXT en search_text para búsquedas multi-locale eficientes.
        // Solo MySQL/Postgres (>=10). Quitar/ajustar si tu motor no lo soporta.
        try {
            Schema::table($terms, function (Blueprint $table) {
                $table->fullText('search_text', 'terms_search_text_ft');
            });
        } catch (\Throwable $e) {
            // SQLite u otros sin FULLTEXT: ignoramos. El paquete sigue funcionando
            // con LIKE sin índice (más lento pero correcto).
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laraterms.tables.terms', 'terms'));
    }
};
