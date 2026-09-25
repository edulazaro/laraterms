<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;

class TermQueriesTest extends TestCase
{
    use CreatesPosts;

    private Post $banking;
    private Post $both;
    private Post $untagged;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
        $this->globalTaxonomies();

        $this->banking = Post::create(['title' => 'Banca']);
        $this->banking->attachTerm('Banca', 'tags');

        $this->both = Post::create(['title' => 'Ambas']);
        $this->both->attachTerms(['Banca', 'Consumo'], 'tags');
        $this->both->attachTerm('Civil', 'categories');

        $this->untagged = Post::create(['title' => 'Nada']);
    }

    public function test_terms_in_reads_one_taxonomy(): void
    {
        $this->assertEqualsCanonicalizing(['Banca', 'Consumo'], $this->both->termsIn('tags')->pluck('name')->all());
        $this->assertSame(['Civil'], $this->both->termsIn('categories')->pluck('name')->all());
        $this->assertTrue($this->untagged->termsIn('tags')->isEmpty());
    }

    public function test_has_terms_in(): void
    {
        $this->assertTrue($this->both->hasTermsIn('categories'));
        $this->assertFalse($this->banking->hasTermsIn('categories'));
    }

    public function test_where_has_term(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->banking->id, $this->both->id],
            Post::whereHasTerm('banca', 'tags')->pluck('id')->all(),
        );
    }

    public function test_where_has_any_term(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->banking->id, $this->both->id],
            Post::whereHasAnyTerm(['consumo', 'banca'], 'tags')->pluck('id')->all(),
        );
    }

    public function test_where_has_all_terms(): void
    {
        $this->assertSame(
            [$this->both->id],
            Post::whereHasAllTerms(['banca', 'consumo'], 'tags')->pluck('id')->all(),
        );
    }

    public function test_where_in_taxonomy(): void
    {
        $this->assertSame([$this->both->id], Post::whereInTaxonomy('categories')->pluck('id')->all());
    }

    public function test_an_unknown_term_matches_nothing_and_is_never_created(): void
    {
        $terms = Term::count();

        $this->assertSame(0, Post::whereHasTerm('nadie', 'tags')->count());
        $this->assertSame(0, Post::whereHasAnyTerm(['nadie'], 'tags')->count());
        $this->assertSame($terms, Term::count());
    }
}
