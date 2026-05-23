<?php

namespace EduLazaro\Laraterms\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight value object representing the "owner" of a Term — i.e. the
 * tenant (Organization, Team, User...) under which the term is scoped.
 *
 * Use Owner::from(...) anywhere you want to accept flexible inputs:
 *
 *   - Eloquent Model           → uses getMorphClass() + getKey()
 *   - Owner instance           → returned as-is
 *   - array ['type' => 'organization', 'id' => 5]
 *   - array ['type' => User::class, 'id' => 42]
 *   - null                     → Owner::global()  (owner_type='', owner_id=0)
 *
 * This means the package doesn't require you to load a full Eloquent Model
 * just to scope a query. If you already have the tenant id + morph alias in
 * memory (session, request attribute, JWT claim), pass the tuple.
 */
final class Owner
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
    ) {}

    /**
     * Normalize anything the user might hand us into a canonical Owner.
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
            type: (string) ($input['type'] ?? $input['owner_type'] ?? ''),
            id: (int) ($input['id'] ?? $input['owner_id'] ?? 0),
        );
    }

    public static function global(): self
    {
        return new self('', 0);
    }

    public function isGlobal(): bool
    {
        return $this->type === '' && $this->id === 0;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
