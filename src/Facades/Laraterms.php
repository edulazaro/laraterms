<?php

namespace EduLazaro\Laraterms\Facades;

use Closure;
use EduLazaro\Laraterms\LaratermsManager;
use EduLazaro\Laraterms\Support\Owner;
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
 * @method static LaratermsManager resolveOwnerUsing(?Closure $resolver)
 * @method static ?Closure ownerResolver()
 * @method static Owner ownerFor(Model $model)
 *
 * @see LaratermsManager
 */
class Laraterms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laraterms';
    }
}
