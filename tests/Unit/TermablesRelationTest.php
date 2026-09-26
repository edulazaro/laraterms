<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\Support\Models\Post;
use EduLazaro\Laraterms\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Term::termables(): the models of one type tagged with a term.
 */
class TermablesRelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function test_termables_returns_the_models_of_the_given_class(): void
    {
        $term = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->terms()->attach($term->id);

        $found = $term->termables(Post::class)->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($post));
    }

    public function test_termables_accepts_a_morph_map_alias(): void
    {
        Relation::morphMap(['post' => Post::class]);

        $term = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $post = Post::create(['title' => 'Clausula suelo']);
        $post->terms()->attach($term->id);

        $this->assertTrue($term->termables('post')->get()->first()->is($post));
    }

    public function test_termables_only_returns_models_of_that_type(): void
    {
        $term = Term::create(['taxonomy' => 'tags', 'name' => 'Banca']);
        $post = Post::create(['title' => 'Suyo']);
        $post->terms()->attach($term->id);

        // The same term tagging another kind of model.
        DB::table(config('laraterms.tables.termables', 'termables'))->insert([
            'term_id'       => $term->id,
            'termable_type' => 'invoice',
            'termable_id'   => $post->id,
        ]);

        $found = $term->termables(Post::class)->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($post));
    }
}
