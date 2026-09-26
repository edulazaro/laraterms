<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attachTerms() returns an Eloquent collection, which it used to declare and not build.
 */
class AttachTermsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Global, since what is tested here is not scope resolution.
        config([
            'laraterms.taxonomies.tags.scope' => 'global',
            'laraterms.taxonomies.categories.scope' => 'global',
        ]);

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function test_attach_terms_attaches_every_one(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);

        $attached = $post->attachTerms(['Banca', 'Hipotecas'], 'tags');

        $this->assertInstanceOf(Collection::class, $attached);
        $this->assertEqualsCanonicalizing(['Banca', 'Hipotecas'], $post->termsIn('tags')->pluck('name')->all());
    }

    public function test_attach_terms_with_nothing_returns_an_empty_collection(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);

        $attached = $post->attachTerms([], 'tags');

        $this->assertInstanceOf(Collection::class, $attached);
        $this->assertTrue($attached->isEmpty());
    }

    public function test_attach_terms_accepts_models_and_ids(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $banca = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $consumo = Term::create(['taxonomy' => 'tags', 'name' => 'Consumo']);

        $post->attachTerms([$banca, $consumo->id], 'tags');

        $this->assertCount(2, $post->termsIn('tags'));
    }
}
