<?php

namespace EduLazaro\Laraterms\Support;

use EduLazaro\Laraterms\Models\Term;
use Illuminate\Support\Str;

/**
 * Generates a term's handle, unique within its taxonomy and scope.
 */
class HandleGenerator
{
    /**
     * Create a new handle generator instance.
     *
     * @param  string  $locale
     * @param  string  $separator
     * @param  bool  $uniqueWithinScope
     */
    public function __construct(
        private readonly string $locale = 'en',
        private readonly string $separator = '-',
        private readonly bool $uniqueWithinScope = true,
    ) {}

    /**
     * Generate a handle for the name, unique within its taxonomy and scope.
     *
     * @param  string  $sourceName
     * @param  string  $taxonomy
     * @param  string  $scopeType
     * @param  int  $scopeId
     * @param  int|null  $ignoreTermId
     * @return string
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

    /**
     * Determine if the handle is already taken in the taxonomy and scope.
     *
     * @param  string  $handle
     * @param  string  $taxonomy
     * @param  string  $scopeType
     * @param  int  $scopeId
     * @param  int|null  $ignoreTermId
     * @return bool
     */
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
