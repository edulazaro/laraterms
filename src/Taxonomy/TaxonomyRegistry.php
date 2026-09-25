<?php

namespace EduLazaro\Laraterms\Taxonomy;

use EduLazaro\Laraterms\Exceptions\UnknownTaxonomyException;

/**
 * The taxonomies loaded from config, plus those registered at runtime.
 */
class TaxonomyRegistry
{
    /** @var array<string, TaxonomyDefinition> */
    private array $taxonomies = [];

    /**
     * Create a new registry from the taxonomies config.
     *
     * @param  array  $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $handle => $cfg) {
            $this->taxonomies[$handle] = TaxonomyDefinition::fromConfig($handle, $cfg);
        }
    }

    /**
     * Register a taxonomy at runtime, replacing any with the same handle.
     *
     * @param  string  $handle
     * @param  array  $config
     * @return TaxonomyDefinition
     */
    public function register(string $handle, array $config): TaxonomyDefinition
    {
        $def = TaxonomyDefinition::fromConfig($handle, $config);
        $this->taxonomies[$handle] = $def;
        return $def;
    }

    /**
     * Get the definition of the given taxonomy.
     *
     * @param  string  $handle
     * @return TaxonomyDefinition
     *
     * @throws UnknownTaxonomyException
     */
    public function get(string $handle): TaxonomyDefinition
    {
        if (!isset($this->taxonomies[$handle])) {
            throw new UnknownTaxonomyException("Taxonomy [{$handle}] is not registered. Declare it in config/laraterms.php or call Laraterms::register().");
        }
        return $this->taxonomies[$handle];
    }

    /**
     * Determine if the given taxonomy is registered.
     *
     * @param  string  $handle
     * @return bool
     */
    public function has(string $handle): bool
    {
        return isset($this->taxonomies[$handle]);
    }

    /**
     * Get every registered taxonomy.
     *
     * @return array<string, TaxonomyDefinition>
     */
    public function all(): array
    {
        return $this->taxonomies;
    }

    /**
     * Get the handles of every registered taxonomy.
     *
     * @return list<string>
     */
    public function handles(): array
    {
        return array_keys($this->taxonomies);
    }
}
