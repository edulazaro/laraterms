<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * detachAll() lee los ids con una query sobre la relación, que hace join con `termables`.
 * La pivot tiene su propio `id`, así que un `pluck('id')` sin calificar es ambiguo en MySQL
 * y en SQLite.
 */
class DetachAllTest extends TestCase
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

    public function test_detach_all_in_one_taxonomy(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerms(['Banca', 'Hipotecas'], 'tags');
        $post->attachTerm('Civil', 'categories');

        $post->detachAll('tags');

        $this->assertFalse($post->hasTermsIn('tags'));
        $this->assertTrue($post->hasTermsIn('categories'));
    }

    public function test_detach_all_everywhere(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Banca', 'tags');
        $post->attachTerm('Civil', 'categories');

        $post->detachAll();

        $this->assertCount(0, $post->terms()->get());
    }
}
