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
 * Hace clasificable a cualquier modelo. Resuelve el scope a partir de:
 *   1. $this->termsScope()                                   (override por modelo)
 *   2. Laraterms::resolveScopeUsing(callable)                  (resolver global)
 *   3. null → Scope::global()
 *
 * El resultado puede ser un Model, un array ['type'=>..., 'id'=>...], un
 * Scope instance o null — todo se normaliza al value-object Scope via
 * Scope::from(). No requiere morph map registrado.
 */
trait HasTerms
{
    /**
     * Limpia los pivots polimórficos `termables` al borrar el modelo. La
     * tabla pivot tiene cascade en `term_id` (cuando se borra un Term, todos
     * sus pivots se van), pero NO puede tener cascade en `termable_id` porque
     * es polimórfico (no admite foreign key). Sin esto, los pivots quedan
     * huérfanos apuntando a un modelo que ya no existe.
     *
     * Soft delete: si el modelo usa SoftDeletes y NO es force-delete, se
     * preservan los pivots para que `restore()` recupere las clasificaciones.
     * Sólo se detachan en hard delete (incluido force delete).
     *
     * Nota: este hook NO se dispara si el modelo se borra por cascade de BD
     * (ON DELETE CASCADE en una FK). En ese caso los pivots quedan huérfanos
     * de todos modos hasta que se haga una limpieza periódica o se elimine
     * por código.
     */
    protected static function bootHasTerms(): void
    {
        static::deleting(function ($model) {
            $isSoftDeleting = method_exists($model, 'isForceDeleting')
                && ! $model->isForceDeleting();

            if ($isSoftDeleting) {
                return;
            }

            $model->terms()->detach();
        });
    }

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

    public function termsIn(string $taxonomy): Collection
    {
        return $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->get();
    }

    public function hasTermsIn(string $taxonomy): bool
    {
        return $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->exists();
    }

    public function attachTerm(Term|int|string $term, ?string $taxonomy = null): Term
    {
        $resolved = $this->resolveTerm($term, $taxonomy, createIfMissing: true);
        $this->enforceMaxTerms($resolved->taxonomy, addingCount: 1);
        $this->terms()->syncWithoutDetaching([$resolved->id]);
        $this->touchTermCount($resolved);
        return $resolved;
    }

    /**
     * @param iterable<int|string|Term> $terms
     * @return Collection<int, Term>
     */
    public function attachTerms(iterable $terms, ?string $taxonomy = null): Collection
    {
        $resolved = collect($terms)->map(fn ($t) => $this->resolveTerm($t, $taxonomy, createIfMissing: true));
        if ($resolved->isEmpty()) return $resolved;

        $resolved->groupBy('taxonomy')->each(function (Collection $group, string $tax): void {
            $this->enforceMaxTerms($tax, addingCount: $group->count());
        });

        $this->terms()->syncWithoutDetaching($resolved->pluck('id')->all());
        $resolved->unique('id')->each(fn (Term $t) => $this->touchTermCount($t));
        return $resolved;
    }

    /**
     * @param iterable<int|string|Term> $terms
     * @return Collection<int, Term>
     */
    public function syncTerms(iterable $terms, string $taxonomy): Collection
    {
        $resolved = collect($terms)
            ->map(fn ($t) => $this->resolveTerm($t, $taxonomy, createIfMissing: true))
            ->filter(fn (Term $t) => $t->taxonomy === $taxonomy)
            ->values();

        $this->enforceMaxTerms($taxonomy, addingCount: $resolved->count(), replacing: true);

        $currentIds = $this->terms()
            ->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy)
            ->pluck('id')
            ->all();
        if ($currentIds) $this->terms()->detach($currentIds);

        if ($resolved->isNotEmpty()) $this->terms()->attach($resolved->pluck('id')->all());

        foreach (array_unique(array_merge($currentIds, $resolved->pluck('id')->all())) as $id) {
            Term::find($id)?->refreshCount();
        }
        return $resolved;
    }

    public function detachTerm(Term|int|string $term, ?string $taxonomy = null): void
    {
        $resolved = $this->resolveTerm($term, $taxonomy, createIfMissing: false);
        if (!$resolved) return;
        $this->terms()->detach($resolved->id);
        $this->touchTermCount($resolved);
    }

    public function detachAll(?string $taxonomy = null): void
    {
        $relation = $this->terms();
        if ($taxonomy) {
            $relation = $relation->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy);
        }
        $ids = $relation->pluck('id')->all();
        if (!$ids) return;
        $this->terms()->detach($ids);
        if (config('laraterms.cache_counts', true)) {
            Term::whereIn('id', $ids)->get()->each->refreshCount();
        }
    }

    // ==================== Query scopes ====================

    public function scopeWhereHasTerm(Builder $q, Term|int|string $term, ?string $taxonomy = null): Builder
    {
        $termModel = $this->resolveTerm($term, $taxonomy, createIfMissing: false);
        if (!$termModel) return $q->whereRaw('1 = 0');
        return $q->whereHas('terms', fn ($qq) => $qq->where(config('laraterms.tables.terms', 'terms') . '.id', $termModel->id));
    }

    public function scopeWhereHasAnyTerm(Builder $q, iterable $terms, ?string $taxonomy = null): Builder
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);
        if (empty($ids)) return $q->whereRaw('1 = 0');
        return $q->whereHas('terms', fn ($qq) => $qq->whereIn(config('laraterms.tables.terms', 'terms') . '.id', $ids));
    }

    public function scopeWhereHasAllTerms(Builder $q, iterable $terms, ?string $taxonomy = null): Builder
    {
        $ids = $this->resolveTermIds($terms, $taxonomy);
        if (empty($ids)) return $q->whereRaw('1 = 0');
        foreach ($ids as $id) {
            $q->whereHas('terms', fn ($qq) => $qq->where(config('laraterms.tables.terms', 'terms') . '.id', $id));
        }
        return $q;
    }

    public function scopeWhereInTaxonomy(Builder $q, string $taxonomy): Builder
    {
        return $q->whereHas('terms', fn ($qq) => $qq->where(config('laraterms.tables.terms', 'terms') . '.taxonomy', $taxonomy));
    }

    // ==================== Scope resolution ====================

    /**
     * Override en tu modelo si quieres lógica custom. Puede devolver:
     *   - Model         → usa getMorphClass()+getKey()
     *   - Scope         → tal cual
     *   - array         → ['type'=>..., 'id'=>...] o [type, id]
     *   - null          → término global
     *
     * Si no se sobrescribe, delega al resolver global de Laraterms::resolveScopeUsing().
     *
     * @return Model|Scope|array|null
     */
    public function termsScope(): Model|Scope|array|null
    {
        $resolver = Laraterms::scopeResolver();
        return $resolver ? $resolver($this) : null;
    }

    /**
     * Resuelve el Scope normalizado para una taxonomía concreta. Honra
     * `scope=global` (devuelve siempre Scope::global() ignorando el modelo).
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
     * @param iterable<int|string|Term> $terms
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

    protected function touchTermCount(Term $term): void
    {
        if (!config('laraterms.cache_counts', true)) return;
        $term->refreshCount();
    }
}
