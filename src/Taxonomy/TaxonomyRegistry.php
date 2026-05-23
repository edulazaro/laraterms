<?php

namespace EduLazaro\Laraterms\Taxonomy;

use EduLazaro\Laraterms\Exceptions\UnknownTaxonomyException;

/**
 * Singleton registry of taxonomies loaded from config('laraterms.taxonomies').
 * Also lets you register taxonomies at runtime (handy for tests or feature
 * toggles) via TaxonomyRegistry::register().
 */
class TaxonomyRegistry
{
    /** @var array<string, TaxonomyDefinition> */
    private array $taxonomies = [];

    public function __construct(array $config = [])
    {
        foreach ($config as $handle => $cfg) {
            $this->taxonomies[$handle] = TaxonomyDefinition::fromConfig($handle, $cfg);
        }
    }

    /**
     * Register a taxonomy at runtime. Overwrites if it exists.
     */
    public function register(string $handle, array $config): TaxonomyDefinition
    {
        $def = TaxonomyDefinition::fromConfig($handle, $config);
        $this->taxonomies[$handle] = $def;
        return $def;
    }

    /**
     * @throws UnknownTaxonomyException
     */
    public function get(string $handle): TaxonomyDefinition
    {
        if (!isset($this->taxonomies[$handle])) {
            throw new UnknownTaxonomyException("Taxonomy [{$handle}] is not registered. Declare it in config/laraterms.php or call Laraterms::register().");
        }
        return $this->taxonomies[$handle];
    }

    public function has(string $handle): bool
    {
        return isset($this->taxonomies[$handle]);
    }

    /** @return array<string, TaxonomyDefinition> */
    public function all(): array
    {
        return $this->taxonomies;
    }

    /** @return list<string> */
    public function handles(): array
    {
        return array_keys($this->taxonomies);
    }
}
