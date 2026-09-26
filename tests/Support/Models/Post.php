<?php

namespace EduLazaro\Laraterms\Tests\Support\Models;

use EduLazaro\Laraterms\Concerns\HasTerms;
use Illuminate\Database\Eloquent\Model;

/**
 * A taxable model with no scope of its own.
 */
class Post extends Model
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
}
