<?php

namespace EduLazaro\Laraterms\Taxonomy;

/**
 * Value-object describing one taxonomy as declared in config('laraterms.taxonomies').
 */
final class TaxonomyDefinition
{
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_GLOBAL = 'global';

    /**
     * Create a new taxonomy definition.
     *
     * @param  string  $handle
     * @param  string  $label
     * @param  string  $labelPlural
     * @param  bool  $hierarchical
     * @param  int|null  $maxTermsPerModel
     * @param  bool  $required
     * @param  string  $sort
     * @param  array|null  $models
     * @param  string  $scope
     * @param  string|null  $scopeModel
     */
    public function __construct(
        public readonly string $handle,
        public readonly string $label,
        public readonly string $labelPlural,
        public readonly bool $hierarchical = false,
        public readonly ?int $maxTermsPerModel = null,
        public readonly bool $required = false,
        public readonly string $sort = 'name',
        public readonly ?array $models = null,
        public readonly string $scope = self::SCOPE_TENANT,
        public readonly ?string $scopeModel = null,
    ) {}

    /**
     * Create a definition from its config entry.
     *
     * @param  string  $handle
     * @param  array  $config
     * @return self
     */
    public static function fromConfig(string $handle, array $config): self
    {
        return new self(
            handle: $handle,
            label: $config['label'] ?? ucfirst($handle),
            labelPlural: $config['label_plural'] ?? ucfirst($handle),
            hierarchical: (bool) ($config['hierarchical'] ?? false),
            maxTermsPerModel: isset($config['max_terms_per_model']) ? (int) $config['max_terms_per_model'] : null,
            required: (bool) ($config['required'] ?? false),
            sort: $config['sort'] ?? 'name',
            models: $config['models'] ?? null,
            scope: $config['scope'] ?? self::SCOPE_TENANT,
            scopeModel: $config['scope_model'] ?? $config['owner_model'] ?? null,
        );
    }

    /**
     * Determine if each scope has its own terms.
     *
     * @return bool
     */
    public function isTenantScoped(): bool
    {
        return $this->scope === self::SCOPE_TENANT;
    }

    /**
     * Determine if the terms are shared by every scope.
     *
     * @return bool
     */
    public function isGlobal(): bool
    {
        return $this->scope === self::SCOPE_GLOBAL;
    }

    /**
     * Determine if the given model may use the taxonomy.
     *
     * @param  string  $modelClass
     * @return bool
     */
    public function allowsModel(string $modelClass): bool
    {
        if ($this->models === null) return true;
        return in_array($modelClass, $this->models, true) || in_array(ltrim($modelClass, '\\'), $this->models, true);
    }
}
