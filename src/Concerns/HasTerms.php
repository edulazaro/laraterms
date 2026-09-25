<?php

namespace EduLazaro\Laraterms\Concerns;

use EduLazaro\Laraterms\Exceptions\TooManyTermsException;
use EduLazaro\Laraterms\Facades\Laraterms;
use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Support\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Lets a model be classified with terms.
 *
 * The scope comes from termsScope(), else Laraterms::resolveScopeUsing(), else the global one.
 */
trait HasTerms
{
    /**
     * Detach the model's terms when it is hard deleted.
     *
     * Soft deletes keep them, so restore() brings the classification back.
     *
     * @return void
     */
    protected static function bootHasTerms(): void
    {
        static::deleting(function ($model) {
            $isSoftDeleting = method_exists($model, 'isForceDeleting')
                && ! $model->isForceDeleting();

            if ($isSoftDeleting) {
                return;
            }

            $model->detachAll();
        });
    }

    /**
     * Get all of the terms attached to the model.
     *
     * @return MorphToMany
     */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(
            related: Term::class,
            name: 'termable',
            table: config('laraterms.tables.termables', 'termables'),
            foreignPivotKey: 'termable_id',
            relatedPivotKey: 'term_id',
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * Get the model's terms in the given taxonomy.
     *
     * @param  string  $taxonomy
     * @return Collection<int, Term>
     */
    public function termsIn(string $taxonomy): Collection
    {
        return $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->get();
    }

    /**
     * Determine if the model has any term in the given taxonomy.
     *
     * @param  string  $taxonomy
     * @return bool
     */
    public function hasTermsIn(string $taxonomy): bool
    {
        return $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->exists();
    }

    /**
     * Attach a term to the model, creating it when it does not exist.
     *
     * @param  Term|int|string  $term
     * @param  string|null  $taxonomy
     * @return Term
     */
    public function attachTerm(Term|int|string $term, ?string $taxonomy = null): Term
    {
        $resolved = $this->resolveTerm($term, $taxonomy, createIfMissing: true);
        $this->enforceMaxTerms($resolved->taxonomy, addingCount: 1);
        $this->terms()->syncWithoutDetaching([$resolved->id]);
        $this->touchTermCount($resolved);
        return $resolved;
    }

    /**
     * Attach several terms to the model, creating the missing ones.
     *
     * @param  iterable<int|string|Term>  $terms
     * @param  string|null  $taxonomy
     * @return Collection<int, Term>
     */
    public function attachTerms(iterable $terms, ?string $taxonomy = null): Collection
    {
        $resolved = Collection::make($terms)->map(fn ($t) => $this->resolveTerm($t, $taxonomy, createIfMissing: true));
        if ($resolved->isEmpty()) return $resolved;

        $resolved->groupBy('taxonomy')->each(function (Collection $group, string $tax): void {
            $this->enforceMaxTerms($tax, addingCount: $group->count());
        });

        $this->terms()->syncWithoutDetaching($resolved->pluck('id')->all());
        $resolved->unique('id')->each(fn (Term $t) => $this->touchTermCount($t));
        return $resolved;
    }

    /**
     * Replace the model's terms in the given taxonomy.
     *
     * @param  iterable<int|string|Term>  $terms
     * @param  string  $taxonomy
     * @return Collection<int, Term>
     */
    public function syncTerms(iterable $terms, string $taxonomy): Collection
    {
        $resolved = Collection::make($terms)
            ->map(fn ($t) => $this->resolveTerm($t, $taxonomy, createIfMissing: true))
            ->filter(fn (Term $t) => $t->taxonomy === $taxonomy)
            ->values();

        $this->enforceMaxTerms($taxonomy, addingCount: $resolved->count(), replacing: true);

        $currentIds = $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->pluck(config('laraterms.tables.terms', 'terms') . '.id')
            ->all();
        if ($currentIds) $this->terms()->detach($currentIds);

        if ($resolved->isNotEmpty()) $this->terms()->attach($resolved->pluck('id')->all());

        foreach (array_unique(array_merge($currentIds, $resolved->pluck('id')->all())) as $id) {
            Term::find($id)?->refreshCount();
        }
        return $resolved;
    }

    /**
     * Detach a term from the model.
     *
     * @param  Term|int|string  $term
     * @param  string|null  $taxonomy
     * @return void
     */
    public function detachTerm(Term|int|string $term, ?string $taxonomy = null): void
    {
        $resolved = $this->resolveTerm($term, $taxonomy, createIfMissing: false);
        if (!$resolved) return;
        $this->terms()->detach($resolved->id);
        $this->touchTermCount($resolved);
    }

    /**
     * Detach every term from the model, or only those in the given taxonomy.
     *
     * @param  string|null  $taxonomy
     * @return void
     */
    public function detachAll(?string $taxonomy = null): void
    {
        $relation = $this->terms();
        if ($taxonomy) {
            $relation = $relation->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy);
        }
        $ids = $relation->pluck(config('laraterms.tables.terms', 'terms') . '.id')->all();
        if (!$ids) return;
        $this->terms()->detach($ids);
        if (config('laraterms.cache_counts', true)) {
            Term::whereIn('id', $ids)->get()->each->refreshCount();
        }
    }

    // ==================== Query scopes ====================

    /**
     * Scope the query to models that have the given term.
     *
     * @param  Builder  $q
     * @param  Term|int|string  $term
     * @param  string|null  $taxonomy
     * @return Builder
     */
    public function scopeWhereHasTerm(Builder $q, Term|int|string $term, ?string $taxonomy = null): Builder
    {
        $termModel = $this->resolveTerm($term, $taxonomy, createIfMissing: false);
        if (!$termModel) return $q->whereRaw('1 = 0');
        return $q->whereHas('terms', fn ($qq) => $qq->where(config('laraterms.tables.terms', 'terms') . '.id', $termModel->id));
    }

    /**
     * Scope the query to models that have any of the given terms.
     *
     * @param  Builder  $q
     * @param  iterable<int|string|Term>  $terms
     * @param  string|null  $taxonomy
     * @return Builder
     */
    public function scopeWhereHasAnyTerm(Builder $q, iterable $terms, ?string $taxonomy = null): Builder
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);
        if (empty($ids)) return $q->whereRaw('1 = 0');
        return $q->whereHas('terms', fn ($qq) => $qq->whereIn(config('laraterms.tables.terms', 'terms') . '.id', $ids));
    }

    /**
     * Scope the query to models that have all of the given terms.
     *
     * A term that does not exist matches nothing, since no model can have it.
     *
     * @param  Builder  $q
     * @param  iterable<int|string|Term>  $terms
     * @param  string|null  $taxonomy
     * @return Builder
     */
    public function scopeWhereHasAllTerms(Builder $q, iterable $terms, ?string $taxonomy = null): Builder
    {
        $ids = [];
        foreach ($terms as $term) {
            $resolved = $this->resolveTerm($term, $taxonomy, createIfMissing: false);
            if (!$resolved) return $q->whereRaw('1 = 0');
            $ids[] = $resolved->id;
        }
        $ids = array_unique($ids);
        if (empty($ids)) return $q->whereRaw('1 = 0');
        foreach ($ids as $id) {
            $q->whereHas('terms', fn ($qq) => $qq->where(config('laraterms.tables.terms', 'terms') . '.id', $id));
        }
        return $q;
    }

    /**
     * Scope the query to models with any term in the given taxonomy.
     *
     * @param  Builder  $q
     * @param  string  $taxonomy
     * @return Builder
     */
    public function scopeWhereInTaxonomy(Builder $q, string $taxonomy): Builder
    {
        return $q->whereHas('terms', fn ($qq) => $qq->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy));
    }

    // ==================== Scope resolution ====================

    /**
     * Get the scope the model's terms belong to.
     *
     * Override it per model; by default it asks the resolver set with Laraterms::resolveScopeUsing().
     *
     * @return Model|Scope|array|null
     */
    public function termsScope(): Model|Scope|array|null
    {
        $resolver = Laraterms::scopeResolver();
        return $resolver ? $resolver($this) : null;
    }

    /**
     * Get the scope to use for the given taxonomy. Global taxonomies ignore the model.
     *
     * @param  string  $taxonomy
     * @return Scope
     */
    protected function scopeForTaxonomy(string $taxonomy): Scope
    {
        if (!Laraterms::registry()->has($taxonomy)) {
            return Scope::from($this->termsScope());
        }
        $def = Laraterms::registry()->get($taxonomy);
        if ($def->isGlobal()) return Scope::global();
        return Scope::from($this->termsScope());
    }

    // ==================== Internals ====================

    /**
     * Resolve a term from a model, an id, a handle or a name.
     *
     * @param  Term|int|string  $input
     * @param  string|null  $taxonomy
     * @param  bool  $createIfMissing
     * @return Term|null
     */
    protected function resolveTerm(Term|int|string $input, ?string $taxonomy, bool $createIfMissing): ?Term
    {
        if ($input instanceof Term) return $input;
        if (is_int($input)) return Term::find($input);

        $scope = $taxonomy ? $this->scopeForTaxonomy($taxonomy) : Scope::from($this->termsScope());

        if ($taxonomy === null) {
            return Term::query()
                ->where('scope_type', $scope->type)
                ->where('scope_id', $scope->id)
                ->where('handle', $input)
                ->first();
        }

        // Try handle
        $hit = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->where('handle', $input)
            ->first();
        if ($hit) return $hit;

        $hit = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($input)])
            ->first();
        if ($hit) return $hit;

        if (!$createIfMissing) return null;

        $def = Laraterms::registry()->get($taxonomy);
        if (!$def->allowsModel(static::class)) {
            throw new \InvalidArgumentException(
                "Taxonomy [{$taxonomy}] does not allow model [" . static::class . "]."
            );
        }

        if ($def->isTenantScoped() && $scope->isGlobal()) {
            throw new \RuntimeException(
                "Taxonomy [{$taxonomy}] is tenant-scoped but no scope could be resolved for [" . static::class . "]. " .
                "Implement termsScope() on your model or configure Laraterms::resolveScopeUsing()."
            );
        }

        return Term::findOrCreateByName($input, $taxonomy, $scope);
    }

    /**
     * Resolve the ids of the given terms, skipping unknown ones.
     *
     * @param  iterable<int|string|Term>  $terms
     * @param  string|null  $taxonomy
     * @return list<int>
     */
    protected function resolveTermIds(iterable $terms, ?string $taxonomy): array
    {
        return collect($terms)
            ->map(fn ($t) => $this->resolveTerm($t, $taxonomy, createIfMissing: false))
            ->filter()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure the model stays within the taxonomy's term limit.
     *
     * @param  string  $taxonomy
     * @param  int  $addingCount
     * @param  bool  $replacing
     * @return void
     *
     * @throws \EduLazaro\Laraterms\Exceptions\TooManyTermsException
     */
    protected function enforceMaxTerms(string $taxonomy, int $addingCount, bool $replacing = false): void
    {
        if (!Laraterms::registry()->has($taxonomy)) return;
        $max = Laraterms::registry()->get($taxonomy)->maxTermsPerModel;
        if ($max === null) return;

        $current = $replacing ? 0 : $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->count();

        if ($current + $addingCount > $max) {
            throw new TooManyTermsException(
                "Taxonomy [{$taxonomy}] allows at most {$max} term(s) per " . class_basename(static::class) . "."
            );
        }
    }

    /**
     * Refresh the cached count of the given term when counts are enabled.
     *
     * @param  Term  $term
     * @return void
     */
    protected function touchTermCount(Term $term): void
    {
        if (!config('laraterms.cache_counts', true)) return;
        $term->refreshCount();
    }
}
