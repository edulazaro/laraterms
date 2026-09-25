<?php

namespace EduLazaro\Laraterms\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesPosts
{
    protected function createPostsTable(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->softDeletes();
        });
    }

    protected function globalTaxonomies(): void
    {
        config([
            'laraterms.taxonomies.tags.scope' => 'global',
            'laraterms.taxonomies.categories.scope' => 'global',
        ]);
    }
}
