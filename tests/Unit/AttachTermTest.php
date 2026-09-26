<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Exceptions\UnknownTaxonomyException;
use EduLazaro\Laraterms\Facades\Laraterms;
use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;

/**
 * attachTerm(): resolving, creating and refusing terms.
 */
class AttachTermTest extends TestCase
{
    use CreatesPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
        $this->globalTaxonomies();
    }

    public function test_a_missing_term_is_created_and_attached(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);

        $term = $post->attachTerm('Banca', 'tags');

        $this->assertSame('banca', $term->handle);
        $this->assertSame(['banca'], $post->termsIn('tags')->pluck('handle')->all());
        $this->assertSame(1, $term->fresh()->terms_count);
    }

    public function test_an_existing_term_is_found_by_handle(): void
    {
        $existing = Term::create(['taxonomy' => 'tags', 'name' => 'Banca privada']);
        $post = Post::create(['title' => 'Clausula suelo']);

        $this->assertTrue($post->attachTerm('banca-privada', 'tags')->is($existing));
        $this->assertSame(1, Term::count());
    }

    public function test_an_existing_term_is_found_by_name_whatever_the_case(): void
    {
        $existing = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $post = Post::create(['title' => 'Clausula suelo']);

        $this->assertTrue($post->attachTerm('BANCA', 'tags')->is($existing));
        $this->assertSame(1, Term::count());
    }

    public function test_a_term_model_or_id_is_attached_as_it_is(): void
    {
        $banca = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $consumo = Term::create(['taxonomy' => 'tags', 'name' => 'Consumo']);
        $post = Post::create(['title' => 'Clausula suelo']);

        $post->attachTerm($banca);
        $post->attachTerm($consumo->id);

        $this->assertEqualsCanonicalizing(['Banca', 'Consumo'], $post->termsIn('tags')->pluck('name')->all());
    }

    public function test_attaching_twice_does_not_duplicate(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);

        $post->attachTerm('Banca', 'tags');
        $term = $post->attachTerm('Banca', 'tags');

        $this->assertCount(1, $post->terms()->get());
        $this->assertSame(1, $term->fresh()->terms_count);
    }

    public function test_a_taxonomy_restricted_to_other_models_refuses(): void
    {
        Laraterms::register('invoice_tags', ['scope' => 'global', 'models' => ['App\\Models\\Invoice']]);

        $this->expectException(\InvalidArgumentException::class);

        Post::create(['title' => 'Clausula suelo'])->attachTerm('Banca', 'invoice_tags');
    }

    public function test_an_unknown_taxonomy_is_refused(): void
    {
        $this->expectException(UnknownTaxonomyException::class);

        Post::create(['title' => 'Clausula suelo'])->attachTerm('Banca', 'regions');
    }
}
