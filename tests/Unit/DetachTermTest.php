<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;

/**
 * detachTerm(): the ways to name a term, and its count.
 */
class DetachTermTest extends TestCase
{
    use CreatesPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
        $this->globalTaxonomies();
    }

    public function test_a_term_is_detached_by_handle_name_model_or_id(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $terms = $post->attachTerms(['Banca', 'Consumo', 'Hipotecas', 'Civil'], 'tags');

        $post->detachTerm('banca', 'tags');
        $post->detachTerm('CONSUMO', 'tags');
        $post->detachTerm($terms[2]);
        $post->detachTerm($terms[3]->id);

        $this->assertFalse($post->hasTermsIn('tags'));
    }

    public function test_only_that_term_goes(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerms(['Banca', 'Consumo'], 'tags');

        $post->detachTerm('Banca', 'tags');

        $this->assertSame(['Consumo'], $post->termsIn('tags')->pluck('name')->all());
    }

    public function test_the_count_follows(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $term = $post->attachTerm('Banca', 'tags');

        $post->detachTerm($term);

        $this->assertSame(0, $term->fresh()->terms_count);
    }

    public function test_an_unknown_term_is_ignored_and_never_created(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);

        $post->detachTerm('Nadie', 'tags');

        $this->assertSame(0, Term::count());
    }
}
