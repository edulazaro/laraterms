<?php

namespace EduLazaro\Laraterms\Tests\Support\Models;

use EduLazaro\Laraterms\Concerns\HasTerms;
use Illuminate\Database\Eloquent\Model;

/**
 * A taxable model scoped to its organization.
 */
class ScopedPost extends Model
{
    use HasTerms;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the scope the model's terms belong to: its organization.
     *
     * @return array{type: string, id: int|null}
     */
    public function termsScope(): array
    {
        return ['type' => 'organization', 'id' => $this->organization_id];
    }
}
