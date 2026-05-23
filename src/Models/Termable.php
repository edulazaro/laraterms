<?php

namespace EduLazaro\Laraterms\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Pivot between Term and any taxable model. Custom MorphPivot lets us add
 * `sort_order` and timestamps on the relationship itself (so you can show
 * terms in the order they were attached).
 */
class Termable extends MorphPivot
{
    public $incrementing = true;

    protected $fillable = ['term_id', 'termable_type', 'termable_id', 'sort_order'];

    public function getTable(): string
    {
        return config('laraterms.tables.termables', 'termables');
    }
}
