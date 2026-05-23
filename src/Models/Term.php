<?php

namespace EduLazaro\Laraterms\Models;

use EduLazaro\Laraterms\Exceptions\RequiresHierarchyException;
use EduLazaro\Laraterms\Facades\Laraterms;
use EduLazaro\Laraterms\Support\HandleGenerator;
use EduLazaro\Laraterms\Support\Owner;
use EduLazaro\Laraterms\Taxonomy\TaxonomyDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string $taxonomy
 * @property string $owner_type
 * @property int $owner_id
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
    protected $fillable = [
        'taxonomy', 'owner_type', 'owner_id', 'parent_id',
        'name', 'name_translations',
        'handle',
        'description', 'description_translations',
        'search_text', 'color',
        'is_active', 'sort_order', 'terms_count', 'meta',
    ];

    protected $casts = [
        'owner_id'                 => 'integer',
        'parent_id'                => 'integer',
        'is_active'                => 'boolean',
        'sort_order'               => 'integer',
        'terms_count'              => 'integer',
        'name_translations'        => 'array',
        'description_translations' => 'array',
        'meta'                     => 'array',
    ];

    protected $attributes = [
        'owner_type' => '',
        'owner_id'   => 0,
    ];

    public function getTable(): string
    {
        return config('laraterms.tables.terms', 'terms');
    }

    // ==================== Accessors (i18n-aware) ====================

    /**
     * Devuelve el `name` en el locale activo. Lee del campo plain como fallback.
     *
     * Importante: leemos `$this->attributes['name_translations']` directo en vez
     * de `$this->name_translations` para evitar conflicto con paquetes externos
     * tipo spatie/laravel-translatable que sobreescriban el accessor del campo
     * de traducciones — aquí queremos el array crudo siempre.
     */
    public function getNameAttribute(): string
    {
        return $this->localized('name', 'name_translations');
    }

    public function getDescriptionAttribute(): ?string
    {
        $value = $this->localized('description', 'description_translations');
        return $value === '' ? null : $value;
    }

    /**
     * Resuelve un campo translatable: usa traducción del locale activo, luego
     * fallback locale, luego columna plain.
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
                // Primer valor no vacío como último recurso
                foreach ($arr as $v) if ($v !== null && $v !== '') return (string) $v;
            }
        }
        return (string) ($this->attributes[$plainCol] ?? '');
    }

    // ==================== Boot ====================

    protected static function booted(): void
    {
        static::saving(function (Term $term): void {
            $def = $term->taxonomyDefinition();

            // Auto-handle si no se proporciona
            if (empty($term->handle) && !empty($term->attributes['name'] ?? '')) {
                $term->handle = app(HandleGenerator::class)->generate(
                    sourceName: (string) $term->attributes['name'],
                    taxonomy: $term->taxonomy,
                    ownerType: $term->owner_type ?: '',
                    ownerId: (int) ($term->owner_id ?? 0),
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

            if ($term->owner_type === null) $term->owner_type = '';
            if ($term->owner_id === null)   $term->owner_id = 0;

            // Reconstruir search_text desde todos los valores
            $term->rebuildSearchText();
        });
    }

    /**
     * Reconstruye search_text concatenando name, valores de name_translations,
     * description y valores de description_translations. Llamado automáticamente
     * en saving(). Disponible públicamente para re-construcciones batch tras
     * imports masivos.
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
     * Lee un atributo JSON crudo y lo decodifica a array. Necesario para evitar
     * que otros accessors (Spatie translatable user-side) interfieran.
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

    // ==================== Relations ====================

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function termables(): MorphToMany
    {
        return $this->morphedByMany(
            related: Model::class,
            name: 'termable',
            table: config('laraterms.tables.termables', 'termables'),
            foreignPivotKey: 'term_id',
            relatedPivotKey: 'termable_id',
        );
    }

    // ==================== Scopes ====================

    public function scopeInTaxonomy(Builder $q, string $taxonomy): Builder
    {
        return $q->where('taxonomy', $taxonomy);
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeInactive(Builder $q): Builder
    {
        return $q->where('is_active', false);
    }

    public function scopeGlobal(Builder $q): Builder
    {
        return $q->where('owner_type', '')->where('owner_id', 0);
    }

    public function scopeForOwner(Builder $q, Model|Owner|array|null $owner): Builder
    {
        $o = Owner::from($owner);
        return $q->where('owner_type', $o->type)->where('owner_id', $o->id);
    }

    public function scopeForOwnerOrGlobal(Builder $q, Model|Owner|array|null $owner): Builder
    {
        $o = Owner::from($owner);
        if ($o->isGlobal()) return $q->global();
        return $q->where(function ($qq) use ($o) {
            $qq->where(function ($q2) use ($o) {
                $q2->where('owner_type', $o->type)->where('owner_id', $o->id);
            })->orWhere(function ($q2) {
                $q2->where('owner_type', '')->where('owner_id', 0);
            });
        });
    }

    public function scopeByHandle(Builder $q, string $handle, ?string $taxonomy = null): Builder
    {
        $q->where('handle', $handle);
        if ($taxonomy) $q->where('taxonomy', $taxonomy);
        return $q;
    }

    /**
     * Búsqueda agnóstica de idioma. Usa LIKE sobre search_text. Para sitios con
     * muchos terms, considera FULLTEXT (ya creado en la migración default) y
     * sustituye esto por `whereFullText('search_text', $q)` en tu app.
     */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') return $q;
        return $q->where('search_text', 'like', '%' . $term . '%');
    }

    // ==================== Helpers ====================

    public function taxonomyDefinition(): ?TaxonomyDefinition
    {
        if (!$this->taxonomy) return null;
        $registry = Laraterms::registry();
        return $registry->has($this->taxonomy) ? $registry->get($this->taxonomy) : null;
    }

    public function isGlobal(): bool
    {
        return $this->owner_type === '' && (int) $this->owner_id === 0;
    }

    /** @return Collection<int, Term> */
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

    public function breadcrumb(string $separator = ' > '): string
    {
        return $this->ancestors()->push($this)->pluck('name')->implode($separator);
    }

    /** @return list<int> */
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

    public function activate(): self
    {
        $this->is_active = true;
        return tap($this)->save();
    }

    public function deactivate(): self
    {
        $this->is_active = false;
        return tap($this)->save();
    }

    /**
     * Fusiona este término en $into: mueve todos los termables del actual al
     * destino (sin duplicar), recalcula counts y opcionalmente desactiva o
     * borra el origen.
     *
     * Guard: ambos terms deben ser de la misma taxonomy y mismo owner.
     * Lanza InvalidArgumentException si no.
     *
     * @param self $into                       Término destino (canónico).
     * @param bool $deactivateSource           true: marca el origen como inactivo (conserva BD).
     *                                         false: delete real con cascade.
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
        if ($this->owner_type !== $into->owner_type || $this->owner_id !== $into->owner_id) {
            throw new \InvalidArgumentException('Cannot merge terms across different owners.');
        }

        $tableTermables = config('laraterms.tables.termables', 'termables');
        $conn = $this->getConnection();

        $conn->transaction(function () use ($into, $tableTermables, $conn) {
            // Mover termables que no creen duplicado en destino
            $duplicates = $conn->table($tableTermables . ' as src')
                ->join($tableTermables . ' as dst', function ($join) use ($into) {
                    $join->on('src.termable_type', '=', 'dst.termable_type')
                         ->on('src.termable_id', '=', 'dst.termable_id')
                         ->where('dst.term_id', '=', $into->id);
                })
                ->where('src.term_id', $this->id)
                ->pluck('src.id');

            // Borra los duplicados (ya están atachados al destino)
            if ($duplicates->isNotEmpty()) {
                $conn->table($tableTermables)->whereIn('id', $duplicates)->delete();
            }

            // Reasigna el resto al destino
            $conn->table($tableTermables)
                ->where('term_id', $this->id)
                ->update(['term_id' => $into->id, 'updated_at' => now()]);

            // Recalc counts del destino
            $into->refreshCount();

            // Origen: desactivar o borrar real
            if ($deactivateSource) {
                $this->is_active = false;
                $this->terms_count = 0;
                $this->saveQuietly();
            } else {
                $this->delete(); // cascade en termables (los que quedaran)
            }
        });

        return $into;
    }

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
     * Find-or-create within (taxonomy, owner) by canonical name. Owner acepta
     * Model, array, Owner VO o null (= global).
     */
    public static function findOrCreateByName(
        string $name,
        string $taxonomy,
        Model|Owner|array|null $owner = null,
        ?int $parentId = null,
    ): self {
        $o = Owner::from($owner);

        $existing = static::where('taxonomy', $taxonomy)
            ->where('owner_type', $o->type)
            ->where('owner_id', $o->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) return $existing;

        return static::create([
            'taxonomy'   => $taxonomy,
            'owner_type' => $o->type,
            'owner_id'   => $o->id,
            'parent_id'  => $parentId,
            'name'       => $name,
        ]);
    }
}
