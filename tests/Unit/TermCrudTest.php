<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Models\Term;
use EduLazaro\Laraterms\Tests\TestCase;

class TermCrudTest extends TestCase
{
    public function test_creating_a_term_auto_generates_handle_from_name(): void
    {
        $term = Term::create([
            'taxonomy' => 'tags',
            'name'     => 'Banca',
        ]);

        $this->assertNotEmpty($term->handle);
        $this->assertSame('banca', $term->handle);
    }

    public function test_handles_are_unique_per_scope_and_taxonomy(): void
    {
        Term::create([
            'taxonomy'   => 'tags',
            'scope_type' => 'organization',
            'scope_id'   => 1,
            'name'       => 'IRPH',
        ]);

        // Same name in different scope: different row, same handle is fine
        $b = Term::create([
            'taxonomy'   => 'tags',
            'scope_type' => 'organization',
            'scope_id'   => 2,
            'name'       => 'IRPH',
        ]);

        $this->assertSame('irph', $b->handle);
        $this->assertSame(2, Term::where('taxonomy', 'tags')->where('handle', 'irph')->count());
    }

    public function test_search_text_is_auto_built_from_name_and_description(): void
    {
        $term = Term::create([
            'taxonomy'    => 'tags',
            'name'        => 'Cláusula Suelo',
            'description' => 'STJUE Gutiérrez Naranjo C-154/15',
        ]);

        $this->assertStringContainsString('Cláusula Suelo', $term->search_text);
        $this->assertStringContainsString('Gutiérrez Naranjo', $term->search_text);
    }
}
