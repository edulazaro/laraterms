<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;

/**
 * termIn() and syncTerm(): the one-term reading and writing of a taxonomy, for those
 * with max_terms_per_model = 1.
 */
class SingleTermTest extends TestCase
{
    use CreatesPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
        $this->globalTaxonomies();
    }

    public function test_term_in_reads_the_term_of_a_taxonomy(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Civil', 'categories');

        $this->assertSame('Civil', $post->termIn('categories')->name);
    }

    public function test_term_in_is_null_when_there_is_none(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Banca', 'tags');

        $this->assertNull($post->termIn('categories'));
    }

    public function test_sync_term_replaces_whatever_was_there(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Civil', 'categories');

        $term = $post->syncTerm('Penal', 'categories');

        $this->assertSame('Penal', $term->name);
        $this->assertSame(['Penal'], $post->termsIn('categories')->pluck('name')->all());
    }

    public function test_sync_term_accepts_a_handle_a_model_or_an_id(): void
    {
        $civil = Term::create(['taxonomy' => 'categories', 'name' => 'Civil']);
        $penal = Term::create(['taxonomy' => 'categories', 'name' => 'Penal']);
        $post = Post::create(['title' => 'Clausula suelo']);

        $this->assertTrue($post->syncTerm('civil', 'categories')->is($civil));
        $this->assertTrue($post->syncTerm($penal, 'categories')->is($penal));
        $this->assertTrue($post->syncTerm($civil->id, 'categories')->is($civil));
        $this->assertSame(1, $post->terms()->count());
    }

    public function test_sync_term_with_null_empties_the_taxonomy(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $civil = $post->syncTerm('Civil', 'categories');

        $this->assertNull($post->syncTerm(null, 'categories'));
        $this->assertNull($post->termIn('categories'));
        $this->assertSame(0, $civil->fresh()->terms_count);
    }

    public function test_sync_term_leaves_other_taxonomies_alone(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerms(['Banca', 'Consumo'], 'tags');

        $post->syncTerm('Civil', 'categories');
        $post->syncTerm(null, 'categories');

        $this->assertCount(2, $post->termsIn('tags'));
    }

    public function test_sync_term_ignores_a_term_of_another_taxonomy(): void
    {
        $banca = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->syncTerm('Civil', 'categories');

        $this->assertNull($post->syncTerm($banca, 'categories'));
        $this->assertNull($post->termIn('categories'));
    }
}
