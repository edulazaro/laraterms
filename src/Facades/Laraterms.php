<?php

namespace EduLazaro\Laraterms\Facades;

use Closure;
use EduLazaro\Laraterms\LaratermsManager;
use EduLazaro\Laraterms\Support\Scope;
use EduLazaro\Laraterms\Taxonomy\TaxonomyDefinition;
use EduLazaro\Laraterms\Taxonomy\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TaxonomyRegistry registry()
 * @method static TaxonomyDefinition get(string $handle)
 * @method static bool has(string $handle)
 * @method static TaxonomyDefinition register(string $handle, array $config)
 * @method static list<string> handles()
 * @method static LaratermsManager resolveScopeUsing(?Closure $resolver)
 * @method static ?Closure scopeResolver()
 * @method static Scope scopeFor(Model $model)
 *
 * @see LaratermsManager
 */
class Laraterms extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laraterms';
    }
}
