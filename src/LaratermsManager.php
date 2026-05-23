<?php

namespace EduLazaro\Laraterms;

use Closure;
use EduLazaro\Laraterms\Support\Owner;
use EduLazaro\Laraterms\Taxonomy\TaxonomyDefinition;
use EduLazaro\Laraterms\Taxonomy\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Manager público del paquete. Holds the singleton TaxonomyRegistry and
 * exposes a global owner resolver for tenant-scoped taxonomies.
 *
 *   Laraterms::registry()->all();
 *   Laraterms::get('tags');
 *   Laraterms::has('regions');
 *   Laraterms::register('regions', [...]);
 *
 *   // Define how the package figures out the "tenant" (owner) for any model
 *   // that doesn't override termsOwner() itself:
 *   Laraterms::resolveOwnerUsing(fn (Model $m) => $m->organization ?? null);
 */
class LaratermsManager
{
    /** @var Closure|null */
    private $ownerResolver = null;

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
     * Register a global callback that returns the "owner" model for any
     * taxable model. Useful when most of your models share the same owner
     * (e.g. always the user's current organization) so you don't have to
     * implement termsOwner() on each one.
     *
     *   Laraterms::resolveOwnerUsing(fn (Model $m) => $m->organization);
     */
    public function resolveOwnerUsing(?Closure $resolver): self
    {
        $this->ownerResolver = $resolver;
        return $this;
    }

    public function ownerResolver(): ?Closure
    {
        return $this->ownerResolver;
    }

    /**
     * Resolve the owner for a given taxable model using the global resolver,
     * normalized to an Owner value object. Returns Owner::global() if no
     * resolver registered or the resolver returns null.
     *
     * The resolver itself can return a Model, an Owner, an array
     * ['type'=>..., 'id'=>...] or null — all are normalized here.
     */
    public function ownerFor(Model $model): Owner
    {
        $raw = $this->ownerResolver ? ($this->ownerResolver)($model) : null;
        return Owner::from($raw);
    }
}
