<?php

namespace EduLazaro\Laraterms\Tests\Support\Models;

use EduLazaro\Laraterms\Concerns\HasTerms;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasTerms;

    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;
}
