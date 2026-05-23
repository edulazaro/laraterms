<?php

namespace EduLazaro\Laraterms\Support;

use EduLazaro\Laraterms\Models\Term;
use Illuminate\Support\Str;

/**
 * Genera el `handle` único de un término dentro de su scope
 * (owner_type, owner_id, taxonomy). Sluggify con sufijo numérico ante colisión.
 */
class HandleGenerator
{
    public function __construct(
        private readonly string $locale = 'en',
        private readonly string $separator = '-',
        private readonly bool $uniqueWithinScope = true,
    ) {}

    /**
     * Genera handle a partir de $sourceName, scoped a (ownerType, ownerId, taxonomy).
     */
    public function generate(
        string $sourceName,
        string $taxonomy,
        string $ownerType = '',
        int $ownerId = 0,
        ?int $ignoreTermId = null,
    ): string {
        $base = Str::slug($sourceName, $this->separator, $this->locale);
        if ($base === '') $base = 't' . $this->separator . (string) time();

        if (!$this->uniqueWithinScope) return $base;

        $candidate = $base;
        $i = 2;
        while ($this->existsInScope($candidate, $taxonomy, $ownerType, $ownerId, $ignoreTermId)) {
            $candidate = $base . $this->separator . $i;
            $i++;
        }
        return $candidate;
    }

    private function existsInScope(
        string $handle,
        string $taxonomy,
        string $ownerType,
        int $ownerId,
        ?int $ignoreTermId,
    ): bool {
        $q = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('handle', $handle);
        if ($ignoreTermId !== null) $q->where('id', '!=', $ignoreTermId);
        return $q->exists();
    }
}
