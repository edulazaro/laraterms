<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\Support\Models\SoftPost;
use EduLazaro\Laraterms\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DeletingModelsTest extends TestCase
{
    use CreatesPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
        $this->globalTaxonomies();
    }

    public function test_deleting_a_model_detaches_its_terms(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->attachTerms(['Banca', 'Consumo'], 'tags');

        $post->delete();

        $this->assertSame(0, DB::table('termables')->count());
    }

    public function test_deleting_a_model_keeps_the_counts_right(): void
    {
        $post = Post::create(['title' => 'Clausula suelo']);
        $term = $post->attachTerm('Banca', 'tags');

        $post->delete();

        $this->assertSame(0, $term->fresh()->terms_count);
    }

    public function test_a_soft_delete_keeps_the_terms_for_restore(): void
    {
        $post = SoftPost::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Banca', 'tags');

        $post->delete();
        $post->restore();

        $this->assertSame(['Banca'], $post->termsIn('tags')->pluck('name')->all());
    }

    public function test_a_force_delete_detaches_them(): void
    {
        $post = SoftPost::create(['title' => 'Clausula suelo']);
        $post->attachTerm('Banca', 'tags');

        $post->forceDelete();

        $this->assertSame(0, DB::table('termables')->count());
    }
}
