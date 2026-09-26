<?php

namespace EduLazaro\Laraterms;

use Closure;
use EduLazaro\Laraterms\Support\Scope;
use EduLazaro\Laraterms\Taxonomy\TaxonomyDefinition;
use EduLazaro\Laraterms\Taxonomy\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * The service behind the Laraterms facade: the taxonomy registry and the scope resolver.
 */
class LaratermsManager
{
    /**
     * The callback that resolves the scope of models without their own termsScope().
     *
     * @var Closure|null
     */
    private $scopeResolver = null;

    /**
     * Create a new Laraterms manager instance.
     *
     * @param  TaxonomyRegistry  $registry
     */
    public function __construct(private readonly TaxonomyRegistry $registry) {}

    /**
     * Get the taxonomy registry.
     *
     * @return TaxonomyRegistry
     */
    public function registry(): TaxonomyRegistry
    {
        return $this->registry;
    }

    /**
     * Get the definition of the given taxonomy.
     *
     * @param  string  $handle
     * @return TaxonomyDefinition
     */
    public function get(string $handle): TaxonomyDefinition
    {
        return $this->registry->get($handle);
    }

    /**
     * Determine if the given taxonomy is registered.
     *
     * @param  string  $handle
     * @return bool
     */
    public function has(string $handle): bool
    {
        return $this->registry->has($handle);
    }

    /**
     * Register a taxonomy at runtime.
     *
     * @param  string  $handle
     * @param  array  $config
     * @return TaxonomyDefinition
     */
    public function register(string $handle, array $config): TaxonomyDefinition
    {
        return $this->registry->register($handle, $config);
    }

    /**
     * Get the handles of every registered taxonomy.
     *
     * @return list<string>
     */
    public function handles(): array
    {
        return $this->registry->handles();
    }

    /**
     * Set the callback that resolves the scope of any taxable model.
     *
     * @param  Closure|null  $resolver
     * @return $this
     */
    public function resolveScopeUsing(?Closure $resolver): self
    {
        $this->scopeResolver = $resolver;
        return $this;
    }

    /**
     * Get the callback that resolves the scope of taxable models.
     *
     * @return Closure|null
     */
    public function scopeResolver(): ?Closure
    {
        return $this->scopeResolver;
    }

    /**
     * Resolve the scope of the given model with the global resolver.
     *
     * @param  Model  $model
     * @return Scope
     */
    public function scopeFor(Model $model): Scope
    {
        $raw = $this->scopeResolver ? ($this->scopeResolver)($model) : null;
        return Scope::from($raw);
    }
}
