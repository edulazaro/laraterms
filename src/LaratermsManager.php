<?php

namespace EduLazaro\Laraterms;

use Closure;
use EduLazaro\Laraterms\Support\Scope;
use EduLazaro\Laraterms\Taxonomy\TaxonomyDefinition;
use EduLazaro\Laraterms\Taxonomy\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Manager público del paquete. Holds the singleton TaxonomyRegistry and
 * exposes a global scope resolver for tenant-scoped taxonomies.
 *
 *   Laraterms::registry()->all();
 *   Laraterms::get('tags');
 *   Laraterms::has('regions');
 *   Laraterms::register('regions', [...]);
 *
 *   // Define how the package figures out the "scope" for any model
 *   // that doesn't override termsScope() itself:
 *   Laraterms::resolveScopeUsing(fn (Model $m) => $m->organization ?? null);
 */
class LaratermsManager
{
    /** @var Closure|null */
    private $scopeResolver = null;

    public function __construct(private readonly TaxonomyRegistry $registry) {}

    public function registry(): TaxonomyRegistry
    {
        return $this->registry;
    }

    public function get(string $handle): TaxonomyDefinition
    {
        return $this->registry->get($handle);
    }

    public function has(string $handle): bool
    {
        return $this->registry->has($handle);
    }

    public function register(string $handle, array $config): TaxonomyDefinition
    {
        return $this->registry->register($handle, $config);
    }

    /** @return list<string> */
    public function handles(): array
    {
        return $this->registry->handles();
    }

    /**
     * Register a global callback that returns the scope model for any taxable
     * model. Useful when most of your models share the same scope (e.g. always
     * the user's current organization) so you don't have to implement
     * termsScope() on each one.
     *
     *   Laraterms::resolveScopeUsing(fn (Model $m) => $m->organization);
     */
    public function resolveScopeUsing(?Closure $resolver): self
    {
        $this->scopeResolver = $resolver;
        return $this;
    }

    public function scopeResolver(): ?Closure
    {
        return $this->scopeResolver;
    }

    /**
     * Resolve the scope for a given taxable model using the global resolver,
     * normalized to a Scope value object. Returns Scope::global() if no
     * resolver registered or the resolver returns null.
     *
     * The resolver itself can return a Model, a Scope, an array
     * ['type'=>..., 'id'=>...] or null — all are normalized here.
     */
    public function scopeFor(Model $model): Scope
    {
        $raw = $this->scopeResolver ? ($this->scopeResolver)($model) : null;
        return Scope::from($raw);
    }
}
