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
        $termables = config('laraterms.tables.termables', 'termables');
        $terms     = config('laraterms.tables.terms', 'terms');

        Schema::create($termables, function (Blueprint $table) use ($terms) {
            $table->id();
            $table->foreignId('term_id')->constrained($terms)->cascadeOnDelete();
            $table->morphs('termable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['term_id', 'termable_type', 'termable_id'], 'termables_unique');
            $table->index(['termable_type', 'termable_id'], 'termables_morph_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laraterms.tables.termables', 'termables'));
    }
};
