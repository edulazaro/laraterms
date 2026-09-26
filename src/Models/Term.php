<?php

namespace EduLazaro\Laraterms\Models;

use EduLazaro\Laraterms\Exceptions\RequiresHierarchyException;
use EduLazaro\Laraterms\Facades\Laraterms;
use EduLazaro\Laraterms\Support\HandleGenerator;
use EduLazaro\Laraterms\Support\Scope;
use EduLazaro\Laraterms\Taxonomy\TaxonomyDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string $taxonomy
 * @property string $scope_type
 * @property int $scope_id
 * @property ?int $parent_id
 * @property string $name             Plain canonical, fallback if no translation matches
 * @property ?array $name_translations  ['en' => 'Tag', 'es' => 'Etiqueta']
 * @property string $handle
 * @property ?string $description
 * @property ?array $description_translations
 * @property ?string $search_text
 * @property ?string $color
 * @property int $sort_order
 * @property int $terms_count
 * @property ?array $meta
 */
class Term extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'taxonomy', 'scope_type', 'scope_id', 'parent_id',
        'name', 'name_translations',
        'handle',
        'description', 'description_translations',
        'search_text', 'color',
        'is_active', 'sort_order', 'terms_count', 'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scope_id'                 => 'integer',
        'parent_id'                => 'integer',
        'is_active'                => 'boolean',
        'sort_order'               => 'integer',
        'terms_count'              => 'integer',
        'name_translations'        => 'array',
        'description_translations' => 'array',
        'meta'                     => 'array',
    ];

    /**
     * The model's default values, the global scope sentinel.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope_type' => '',
        'scope_id'   => 0,
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('laraterms.tables.terms', 'terms');
    }

    /**
     * Get the name in the current locale.
     *
     * Reads the raw translations so a translatable package on the app side cannot override them.
     *
     * @return string
     */
    public function getNameAttribute(): string
    {
        return $this->localized('name', 'name_translations');
    }

    /**
     * Get the description in the current locale.
     *
     * @return string|null
     */
    public function getDescriptionAttribute(): ?string
    {
        $value = $this->localized('description', 'description_translations');
        return $value === '' ? null : $value;
    }

    /**
     * Get a translatable column in the current locale, then the fallback locale, then its plain value.
     *
     * @param  string  $plainCol
     * @param  string  $translationsCol
     * @return string
     */
    protected function localized(string $plainCol, string $translationsCol): string
    {
        $rawTrans = $this->attributes[$translationsCol] ?? null;
        if ($rawTrans !== null) {
            $arr = is_string($rawTrans) ? json_decode($rawTrans, true) : $rawTrans;
            if (is_array($arr)) {
                $locale = app()->getLocale();
                $fallback = app()->getFallbackLocale();
                if (!empty($arr[$locale]))   return (string) $arr[$locale];
                if (!empty($arr[$fallback])) return (string) $arr[$fallback];
                // Last resort: the first non-empty translation.
                foreach ($arr as $v) if ($v !== null && $v !== '') return (string) $v;
            }
        }
        return (string) ($this->attributes[$plainCol] ?? '');
    }

    /**
     * Generate the handle and rebuild the search text whenever the term is saved.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Term $term): void {
            $def = $term->taxonomyDefinition();

            if (empty($term->handle) && !empty($term->attributes['name'] ?? '')) {
                $term->handle = app(HandleGenerator::class)->generate(
                    sourceName: (string) $term->attributes['name'],
                    taxonomy: $term->taxonomy,
                    scopeType: $term->scope_type ?: '',
                    scopeId: (int) ($term->scope_id ?? 0),
                    ignoreTermId: $term->exists ? $term->id : null,
                );
            }

            if ($term->parent_id !== null && $def && !$def->hierarchical) {
                throw new RequiresHierarchyException(
                    "Taxonomy [{$term->taxonomy}] is flat. Cannot set a parent term."
                );
            }
            if ($term->parent_id !== null && $term->exists && (int) $term->parent_id === (int) $term->id) {
                throw new RequiresHierarchyException("A term cannot be its own parent.");
            }

            if ($term->scope_type === null) $term->scope_type = '';
            if ($term->scope_id === null)   $term->scope_id = 0;

            $term->rebuildSearchText();
        });
    }

    /**
     * Rebuild the search text from the name, the description and their translations.
     *
     * @return void
     */
    public function rebuildSearchText(): void
    {
        $bits = [];

        $name = $this->attributes['name'] ?? '';
        if ($name !== '') $bits[] = $name;

        foreach ($this->arrayFromAttribute('name_translations') as $v) {
            if ($v !== null && $v !== '') $bits[] = (string) $v;
        }

        $desc = $this->attributes['description'] ?? '';
        if ($desc !== '') $bits[] = $desc;

        foreach ($this->arrayFromAttribute('description_translations') as $v) {
            if ($v !== null && $v !== '') $bits[] = (string) $v;
        }

        $this->attributes['search_text'] = trim(implode(' ', array_unique($bits)));
    }

    /**
     * Decode a raw JSON attribute into an array.
     *
     * @param  string  $key
     * @return array
     */
    private function arrayFromAttribute(string $key): array
    {
        $raw = $this->attributes[$key] ?? null;
        if ($raw === null) return [];
        if (is_array($raw)) return $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Get the parent term.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child terms.
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the model the term is scoped to.
     *
     * @return MorphTo
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the models of the given type tagged with the term.
     *
     * Accepts a morph map alias or a class name.
     *
     * @param  string  $type
     * @return MorphToMany
     */
    public function termables(string $type): MorphToMany
    {
        return $this->morphedByMany(
            related: Relation::getMorphedModel($type) ?? $type,
            name: 'termable',
            table: config('laraterms.tables.termables', 'termables'),
            foreignPivotKey: 'term_id',
            relatedPivotKey: 'termable_id',
        );
    }

    /**
     * Scope the query to the given taxonomy.
     *
     * @param  Builder  $q
     * @param  string  $taxonomy
     * @return Builder
     */
    public function scopeInTaxonomy(Builder $q, string $taxonomy): Builder
    {
        return $q->where('taxonomy', $taxonomy);
    }

    /**
     * Scope the query to terms without a parent.
     *
     * @param  Builder  $q
     * @return Builder
     */
    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    /**
     * Order the query by sort order, then by name.
     *
     * @param  Builder  $q
     * @return Builder
     */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope the query to active terms.
     *
     * @param  Builder  $q
     * @return Builder
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Scope the query to inactive terms.
     *
     * @param  Builder  $q
     * @return Builder
     */
    public function scopeInactive(Builder $q): Builder
    {
        return $q->where('is_active', false);
    }

    /**
     * Scope the query to global terms.
     *
     * @param  Builder  $q
     * @return Builder
     */
    public function scopeGlobal(Builder $q): Builder
    {
        return $q->where('scope_type', '')->where('scope_id', 0);
    }

    /**
     * Scope the query to the given scope.
     *
     * @param  Builder  $q
     * @param  Model|Scope|array|null  $scope
     * @return Builder
     */
    public function scopeForScope(Builder $q, Model|Scope|array|null $scope): Builder
    {
        $s = Scope::from($scope);
        return $q->where('scope_type', $s->type)->where('scope_id', $s->id);
    }

    /**
     * Scope the query to the given scope and to global terms.
     *
     * @param  Builder  $q
     * @param  Model|Scope|array|null  $scope
     * @return Builder
     */
    public function scopeForScopeOrGlobal(Builder $q, Model|Scope|array|null $scope): Builder
    {
        $s = Scope::from($scope);
        if ($s->isGlobal()) return $q->global();
        return $q->where(function ($qq) use ($s) {
            $qq->where(function ($q2) use ($s) {
                $q2->where('scope_type', $s->type)->where('scope_id', $s->id);
            })->orWhere(function ($q2) {
                $q2->where('scope_type', '')->where('scope_id', 0);
            });
        });
    }

    /**
     * Scope the query to the given handle, optionally within a taxonomy.
     *
     * @param  Builder  $q
     * @param  string  $handle
     * @param  string|null  $taxonomy
     * @return Builder
     */
    public function scopeByHandle(Builder $q, string $handle, ?string $taxonomy = null): Builder
    {
        $q->where('handle', $handle);
        if ($taxonomy) $q->where('taxonomy', $taxonomy);
        return $q;
    }

    /**
     * Search the terms by name or description in any language.
     *
     * @param  Builder  $q
     * @param  string  $term
     * @return Builder
     */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') return $q;
        return $q->where('search_text', 'like', '%' . $term . '%');
    }

    /**
     * Get the definition of the term's taxonomy.
     *
     * @return TaxonomyDefinition|null
     */
    public function taxonomyDefinition(): ?TaxonomyDefinition
    {
        if (!$this->taxonomy) return null;
        $registry = Laraterms::registry();
        return $registry->has($this->taxonomy) ? $registry->get($this->taxonomy) : null;
    }

    /**
     * Determine if the term is global.
     *
     * @return bool
     */
    public function isGlobal(): bool
    {
        return $this->scope_type === '' && (int) $this->scope_id === 0;
    }

    /**
     * Get the term's ancestors, root first.
     *
     * @return Collection<int, Term>
     */
    public function ancestors(): Collection
    {
        $chain = collect();
        $current = $this->parent;
        $maxDepth = 50;
        while ($current && $maxDepth-- > 0) {
            $chain->prepend($current);
            $current = $current->parent;
        }
        return $chain;
    }

    /**
     * Get the path from the root to the term.
     *
     * @param  string  $separator
     * @return string
     */
    public function breadcrumb(string $separator = ' > '): string
    {
        return $this->ancestors()->push($this)->pluck('name')->implode($separator);
    }

    /**
     * Get the ids of every descendant term.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $queue = [(int) $this->id];
        while ($queue) {
            $batch = static::query()->whereIn('parent_id', $queue)->pluck('id')->all();
            if (!$batch) break;
            foreach ($batch as $id) $ids[] = (int) $id;
            $queue = $batch;
        }
        return $ids;
    }

    /**
     * Show the term in pickers again.
     *
     * @return $this
     */
    public function activate(): self
    {
        $this->is_active = true;
        return tap($this)->save();
    }

    /**
     * Hide the term from pickers, keeping it on the models that have it.
     *
     * @return $this
     */
    public function deactivate(): self
    {
        $this->is_active = false;
        return tap($this)->save();
    }

    /**
     * Move the term's models to another term of the same taxonomy and scope.
     *
     * @param  self  $into
     * @param  bool  $deactivateSource  Deactivate this term instead of deleting it.
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function mergeInto(self $into, bool $deactivateSource = true): self
    {
        if ($this->id === $into->id) {
            throw new \InvalidArgumentException('Cannot merge a term into itself.');
        }
        if ($this->taxonomy !== $into->taxonomy) {
            throw new \InvalidArgumentException(
                "Cannot merge terms across taxonomies ({$this->taxonomy} → {$into->taxonomy})."
            );
        }
        if ($this->scope_type !== $into->scope_type || $this->scope_id !== $into->scope_id) {
            throw new \InvalidArgumentException('Cannot merge terms across different scopes.');
        }

        $tableTermables = config('laraterms.tables.termables', 'termables');
        $conn = $this->getConnection();

        $conn->transaction(function () use ($into, $tableTermables, $conn) {
            // Rows the target already has would break the pivot's unique key.
            $duplicates = $conn->table($tableTermables . ' as src')
                ->join($tableTermables . ' as dst', function ($join) use ($into) {
                    $join->on('src.termable_type', '=', 'dst.termable_type')
                         ->on('src.termable_id', '=', 'dst.termable_id')
                         ->where('dst.term_id', '=', $into->id);
                })
                ->where('src.term_id', $this->id)
                ->pluck('src.id');

            if ($duplicates->isNotEmpty()) {
                $conn->table($tableTermables)->whereIn('id', $duplicates)->delete();
            }

            $conn->table($tableTermables)
                ->where('term_id', $this->id)
                ->update(['term_id' => $into->id, 'updated_at' => now()]);

            $into->refreshCount();

            if ($deactivateSource) {
                $this->is_active = false;
                $this->terms_count = 0;
                $this->saveQuietly();
            } else {
                $this->delete();
            }
        });

        return $into;
    }

    /**
     * Recount the models tagged with the term and store it.
     *
     * @return int
     */
    public function refreshCount(): int
    {
        $count = $this->newQuery()
            ->getConnection()
            ->table(config('laraterms.tables.termables', 'termables'))
            ->where('term_id', $this->id)
            ->count();
        $this->forceFill(['terms_count' => $count])->saveQuietly();
        return $count;
    }

    /**
     * Find a term by name within a taxonomy and scope, or create it.
     *
     * @param  string  $name
     * @param  string  $taxonomy
     * @param  Model|Scope|array|null  $scope
     * @param  int|null  $parentId
     * @return self
     */
    public static function findOrCreateByName(
        string $name,
        string $taxonomy,
        Model|Scope|array|null $scope = null,
        ?int $parentId = null,
    ): self {
        $s = Scope::from($scope);

        $existing = static::where('taxonomy', $taxonomy)
            ->where('scope_type', $s->type)
            ->where('scope_id', $s->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) return $existing;

        return static::create([
            'taxonomy'   => $taxonomy,
            'scope_type' => $s->type,
            'scope_id'   => $s->id,
            'parent_id'  => $parentId,
            'name'       => $name,
        ]);
    }
}
