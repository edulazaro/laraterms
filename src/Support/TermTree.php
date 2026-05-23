<?php

namespace EduLazaro\Laraterms\Support;

use EduLazaro\Laraterms\Models\Term;
use Illuminate\Support\Collection;

/**
 * Build an in-memory tree from a flat collection of hierarchical terms.
 * Uses a single query (no N+1) and assembles the tree client-side.
 *
 *   $tree = TermTree::for('categories');
 *   foreach ($tree as $node) {
 *       // $node is a Term with a populated `children` Collection (recursive)
 *   }
 */
class TermTree
{
    /**
     * @return Collection<int, Term>
     */
    public static function for(string $taxonomy): Collection
    {
        $all = Term::inTaxonomy($taxonomy)->ordered()->get();
        return self::buildFromCollection($all);
    }

    /**
     * Build a tree from an already-fetched collection. Useful when you've
     * filtered or eager-loaded terms yourself.
     *
     * @param Collection<int, Term> $terms
     * @return Collection<int, Term>
     */
    public static function buildFromCollection(Collection $terms): Collection
    {
        $byParent = $terms->groupBy('parent_id');

        // Attach `children` to each node (in-memory, doesn't hit DB)
        $terms->each(function (Term $term) use ($byParent): void {
            $kids = $byParent->get($term->id, collect());
            $term->setRelation('children', $kids);
        });

        // Roots = terms whose parent_id is null
        return $byParent->get(null, collect())->values();
    }

    /**
     * Flatten a tree (depth-first), emitting [Term, depth] tuples — handy
     * for indented selects like:
     *
     *   - Tech
     *     - Web
     *       - Laravel
     *
     * @param Collection<int, Term> $tree
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
