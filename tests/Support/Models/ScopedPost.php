<?php

namespace EduLazaro\Laraterms\Tests\Support\Models;

use EduLazaro\Laraterms\Concerns\HasTerms;
use Illuminate\Database\Eloquent\Model;

class ScopedPost extends Model
{
    use HasTerms;

    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;

    public function termsScope(): array
    {
        return ['type' => 'organization', 'id' => $this->organization_id];
    }
}
