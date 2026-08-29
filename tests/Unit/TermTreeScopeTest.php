<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Support\TermTree;
use EduLazaro\Laraterms\Tests\TestCase;

class TermTreeScopeTest extends TestCase
{
    private function term(array $attributes): Term
    {
        return Term::create($attributes + ['taxonomy' => 'categories']);
    }

    public function test_the_tree_only_contains_terms_of_the_given_scope(): void
    {
        $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'Suya']);
        $this->term(['scope_type' => 'organization', 'scope_id' => 2, 'name' => 'Ajena']);

        $tree = TermTree::for('categories', ['type' => 'organization', 'id' => 1]);

        $this->assertCount(1, $tree);
        $this->assertSame('Suya', $tree->first()->name);
    }

    public function test_a_null_scope_means_the_global_catalog_not_everything(): void
    {
        $this->term(['name' => 'Global']);
        $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'De la org']);

        $tree = TermTree::for('categories');

        $this->assertCount(1, $tree);
        $this->assertSame('Global', $tree->first()->name);
    }

    public function test_include_global_adds_the_shared_terms_to_the_scope(): void
    {
        $this->term(['name' => 'Global']);
        $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'De la org']);
        $this->term(['scope_type' => 'organization', 'scope_id' => 2, 'name' => 'Ajena']);

        $tree = TermTree::for('categories', ['type' => 'organization', 'id' => 1], includeGlobal: true);

        $this->assertEqualsCanonicalizing(
            ['De la org', 'Global'],
            $tree->pluck('name')->all()
        );
    }

    public function test_only_active_leaves_the_disabled_terms_out(): void
    {
        $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'Viva']);
        $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'Muerta', 'is_active' => false]);

        $scope = ['type' => 'organization', 'id' => 1];

        $this->assertCount(2, TermTree::for('categories', $scope));
        $this->assertCount(1, TermTree::for('categories', $scope, onlyActive: true));
    }

    public function test_children_stay_attached_within_the_scope(): void
    {
        $root  = $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'Raiz']);
        $this->term(['scope_type' => 'organization', 'scope_id' => 1, 'name' => 'Hija', 'parent_id' => $root->id]);

        $tree = TermTree::for('categories', ['type' => 'organization', 'id' => 1]);

        $this->assertCount(1, $tree);
        $this->assertSame('Hija', $tree->first()->children->first()->name);
    }
}
