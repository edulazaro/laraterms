<?php

namespace EduLazaro\Laraterms\Tests\Support\Models;

use EduLazaro\Laraterms\Concerns\HasTerms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftPost extends Model
{
    use HasTerms, SoftDeletes;

    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;
}
