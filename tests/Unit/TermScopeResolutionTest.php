<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Facades\Laraterms;
use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\CreatesPosts;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\Support\Models\ScopedPost;
use EduLazaro\Laraterms\Tests\TestCase;

/**
 * Which scope a term belongs to, from the model, the global resolver or the taxonomy.
 */
class TermScopeResolutionTest extends TestCase
{
    use CreatesPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPostsTable();
    }

    protected function tearDown(): void
    {
        Laraterms::resolveScopeUsing(null);

        parent::tearDown();
    }

    public function test_a_tenant_taxonomy_without_a_scope_refuses_to_create(): void
    {
        $this->expectException(\RuntimeException::class);

        Post::create(['title' => 'Sin scope'])->attachTerm('VIP', 'tags');
    }

    public function test_each_scope_gets_its_own_catalog(): void
    {
        $first = ScopedPost::create(['title' => 'Uno', 'organization_id' => 1]);
        $second = ScopedPost::create(['title' => 'Dos', 'organization_id' => 2]);

        $a = $first->attachTerm('VIP', 'tags');
        $b = $second->attachTerm('VIP', 'tags');

        $this->assertFalse($a->is($b));
        $this->assertSame(['organization', 1], [$a->scope_type, $a->scope_id]);
        $this->assertSame(['organization', 2], [$b->scope_type, $b->scope_id]);
    }

    public function test_a_term_of_another_scope_is_never_resolved(): void
    {
        $foreign = Term::create(['taxonomy' => 'tags', 'name' => 'VIP', 'scope_type' => 'organization', 'scope_id' => 1]);
        $post = ScopedPost::create(['title' => 'Dos', 'organization_id' => 2]);

        $this->assertFalse($post->attachTerm('vip', 'tags')->is($foreign));
        $this->assertSame(0, $foreign->fresh()->terms_count);
    }

    public function test_the_global_resolver_scopes_models_without_their_own(): void
    {
        Laraterms::resolveScopeUsing(fn ($model) => ['type' => 'organization', 'id' => 7]);

        $term = Post::create(['title' => 'Con resolver'])->attachTerm('VIP', 'tags');

        $this->assertSame(['organization', 7], [$term->scope_type, $term->scope_id]);
    }

    public function test_a_model_scope_wins_over_the_global_resolver(): void
    {
        Laraterms::resolveScopeUsing(fn ($model) => ['type' => 'organization', 'id' => 7]);

        $term = ScopedPost::create(['title' => 'Propio', 'organization_id' => 3])->attachTerm('VIP', 'tags');

        $this->assertSame(3, $term->scope_id);
    }

    public function test_a_global_taxonomy_ignores_the_models_scope(): void
    {
        config(['laraterms.taxonomies.tags.scope' => 'global']);
        Laraterms::register('tags', config('laraterms.taxonomies.tags'));

        $term = ScopedPost::create(['title' => 'Uno', 'organization_id' => 1])->attachTerm('VIP', 'tags');

        $this->assertTrue($term->isGlobal());
    }
}
