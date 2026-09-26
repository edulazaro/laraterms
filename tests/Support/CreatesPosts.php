<?php

namespace EduLazaro\Laraterms\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A posts table to tag, and a switch to make the example taxonomies global.
 */
trait CreatesPosts
{
    /**
     * Create the posts table, with an organization and soft deletes.
     *
     * @return void
     */
    protected function createPostsTable(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Make `tags` and `categories` global, for tests that are not about scopes.
     *
     * @return void
     */
    protected function globalTaxonomies(): void
    {
        config([
            'laraterms.taxonomies.tags.scope' => 'global',
            'laraterms.taxonomies.categories.scope' => 'global',
        ]);
    }
}
