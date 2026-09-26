<?php

namespace EduLazaro\Laraterms\Support;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Support\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds in-memory trees of hierarchical terms without N+1 queries.
 */
class TermTree
{
    /**
     * Build the tree of a taxonomy within a scope, in a single query.
     *
     * A null scope means the global one; pass the tenant's so their trees never mix.
     *
     * @param  string  $taxonomy
     * @param  Model|Scope|array|null  $scope
     * @param  bool  $includeGlobal
     * @param  bool  $onlyActive
     * @return Collection<int, Term>
     */
    public static function for(
        string $taxonomy,
        Model|Scope|array|null $scope = null,
        bool $includeGlobal = false,
        bool $onlyActive = false,
    ): Collection {
        $query = Term::inTaxonomy($taxonomy);

        $includeGlobal
            ? $query->forScopeOrGlobal($scope)
            : $query->forScope($scope);

        if ($onlyActive) {
            $query->active();
        }

        return self::buildFromCollection($query->ordered()->get());
    }

    /**
     * Build a tree from terms already fetched.
     *
     * @param  Collection<int, Term>  $terms
     * @return Collection<int, Term>
     */
    public static function buildFromCollection(Collection $terms): Collection
    {
        $byParent = $terms->groupBy('parent_id');

        // In memory: setting the relation avoids a query per node.
        $terms->each(function (Term $term) use ($byParent): void {
            $kids = $byParent->get($term->id, collect());
            $term->setRelation('children', $kids);
        });

        return $byParent->get(null, collect())->values();
    }

    /**
     * Flatten a tree depth first into [term, depth] pairs, for indented selects.
     *
     * @param  Collection<int, Term>  $tree
     * @param  int  $depth
     * @return Collection<int, array{0: Term, 1: int}>
     */
    public static function flatten(Collection $tree, int $depth = 0): Collection
    {
        $result = collect();
        foreach ($tree as $node) {
            $result->push([$node, $depth]);
            $children = $node->children ?? collect();
            if ($children->isNotEmpty()) {
                $result = $result->concat(self::flatten($children, $depth + 1));
            }
        }
        return $result;
    }
}
