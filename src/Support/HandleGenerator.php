<?php

namespace EduLazaro\Laraterms\Support;

use EduLazaro\Laraterms\Models\Term;
use Illuminate\Support\Str;

/**
 * Genera el `handle` único de un término dentro de su scope
 * (scope_type, scope_id, taxonomy). Sluggify con sufijo numérico ante colisión.
 */
class HandleGenerator
{
    public function __construct(
        private readonly string $locale = 'en',
        private readonly string $separator = '-',
        private readonly bool $uniqueWithinScope = true,
    ) {}

    /**
     * Genera handle a partir de $sourceName, scoped a (scopeType, scopeId, taxonomy).
     */
    public function generate(
        string $sourceName,
        string $taxonomy,
        string $scopeType = '',
        int $scopeId = 0,
        ?int $ignoreTermId = null,
    ): string {
        $base = Str::slug($sourceName, $this->separator, $this->locale);
        if ($base === '') $base = 't' . $this->separator . (string) time();

        if (!$this->uniqueWithinScope) return $base;

        $candidate = $base;
        $i = 2;
        while ($this->existsInScope($candidate, $taxonomy, $scopeType, $scopeId, $ignoreTermId)) {
            $candidate = $base . $this->separator . $i;
            $i++;
        }
        return $candidate;
    }

    private function existsInScope(
        string $handle,
        string $taxonomy,
        string $scopeType,
        int $scopeId,
        ?int $ignoreTermId,
    ): bool {
        $q = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('handle', $handle);
        if ($ignoreTermId !== null) $q->where('id', '!=', $ignoreTermId);
        return $q->exists();
    }
}
