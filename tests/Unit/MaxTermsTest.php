<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Exceptions\TooManyTermsException;
use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;

/**
 * `categories` allows one term per model in the default config.
 */
class MaxTermsTest extends TestCase
{
    use CreatesPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
        $this->globalTaxonomies();
    }

    public function test_attach_term_stops_at_the_limit(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Civil', 'categories');

        $this->expectException(TooManyTermsException::class);

        $post->attachTerm('Penal', 'categories');
    }

    public function test_attach_terms_stops_at_the_limit(): void
    {
        $this->expectException(TooManyTermsException::class);

        Post::create(['title' => 'Clausula suelo'])->attachTerms(['Civil', 'Penal'], 'categories');
    }

    public function test_sync_replaces_within_the_limit(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Civil', 'categories');

        $post->syncTerms(['Penal'], 'categories');

        $this->assertSame(['Penal'], $post->termsIn('categories')->pluck('name')->all());
    }

    public function test_sync_beyond_the_limit_is_refused_and_changes_nothing(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Civil', 'categories');

        try {
            $post->syncTerms(['Penal', 'Laboral'], 'categories');
            $this->fail('Two categories were synced.');
        } catch (TooManyTermsException) {
            $this->assertSame(['Civil'], $post->termsIn('categories')->pluck('name')->all());
        }
    }

    public function test_a_taxonomy_without_a_limit_takes_any_number(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);

        $post->attachTerms(['Uno', 'Dos', 'Tres', 'Cuatro'], 'tags');

        $this->assertCount(4, $post->termsIn('tags'));
    }
}
