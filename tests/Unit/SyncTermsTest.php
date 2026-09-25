<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * syncTerms() lee los ids actuales con una query sobre la relación, que hace join con
 * `termables`. La pivot tiene su propio `id`, así que un `pluck('id')` sin calificar es
 * ambiguo en MySQL y en SQLite.
 */
class SyncTermsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Globales: lo que se prueba aquí es la query, no la resolución del scope.
        config([
            'laraterms.taxonomies.tags.scope' => 'global',
            'laraterms.taxonomies.categories.scope' => 'global',
        ]);

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function test_sync_replaces_the_terms_of_that_taxonomy(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerms(['Banca', 'Hipotecas'], 'tags');

        $post->syncTerms(['Hipotecas', 'Consumo'], 'tags');

        $this->assertEqualsCanonicalizing(
            ['Hipotecas', 'Consumo'],
            $post->termsIn('tags')->pluck('name')->all(),
        );
    }

    public function test_sync_leaves_other_taxonomies_alone(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Banca', 'tags');
        $post->attachTerm('Civil', 'categories');

        $post->syncTerms(['Consumo'], 'tags');

        $this->assertSame(['Civil'], $post->termsIn('categories')->pluck('name')->all());
    }

    public function test_sync_to_nothing_empties_the_taxonomy(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerms(['Banca', 'Hipotecas'], 'tags');

        $post->syncTerms([], 'tags');

        $this->assertFalse($post->hasTermsIn('tags'));
    }

    public function test_sync_keeps_the_counts_right(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Banca', 'tags');

        $post->syncTerms(['Consumo'], 'tags');

        $this->assertSame(0, Term::where('name', 'Banca')->first()->terms_count);
        $this->assertSame(1, Term::where('name', 'Consumo')->first()->terms_count);
    }
}
