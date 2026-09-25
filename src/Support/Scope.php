<?php

namespace EduLazaro\Laraterms\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The entity a term catalog belongs to, or the global catalog.
 *
 * Two terms with the same name in different scopes are different rows.
 */
final class Scope
{
    /**
     * Create a new scope instance.
     *
     * @param  string  $type
     * @param  int  $id
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
    ) {}

    /**
     * Normalize a model, a scope, an array or null into a scope.
     *
     * @param  Model|self|array|null  $input
     * @return self
     */
    public static function from(Model|self|array|null $input): self
    {
        if ($input === null) return self::global();
        if ($input instanceof self) return $input;

        if ($input instanceof Model) {
            return new self(
                type: $input->getMorphClass(),
                id: (int) $input->getKey(),
            );
        }

        // array — accept either ['type' => ..., 'id' => ...] or [type, id] tuple
        if (array_is_list($input)) {
            return new self(
                type: (string) ($input[0] ?? ''),
                id: (int) ($input[1] ?? 0),
            );
        }

        return new self(
            type: (string) ($input['type'] ?? $input['scope_type'] ?? $input['owner_type'] ?? ''),
            id: (int) ($input['id'] ?? $input['scope_id'] ?? $input['owner_id'] ?? 0),
        );
    }

    /**
     * Get the global scope.
     *
     * @return self
     */
    public static function global(): self
    {
        return new self('', 0);
    }

    /**
     * Determine if this is the global scope.
     *
     * @return bool
     */
    public function isGlobal(): bool
    {
        return $this->type === '' && $this->id === 0;
    }

    /**
     * Determine if the scope is the same as another one.
     *
     * @param  self  $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    /**
     * Get the scope as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
