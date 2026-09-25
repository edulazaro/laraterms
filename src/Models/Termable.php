<?php

namespace EduLazaro\Laraterms\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * The pivot between terms and taxable models, with sort order and timestamps.
 */
class Termable extends MorphPivot
{
    public $incrementing = true;

    protected $fillable = ['term_id', 'termable_type', 'termable_id', 'sort_order'];

    /**
     * Get the table associated with the pivot.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('laraterms.tables.termables', 'termables');
    }
}
