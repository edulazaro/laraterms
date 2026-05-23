<?php

namespace EduLazaro\Laraterms\Tests\Unit;

use EduLazaro\Laraterms\Facades\Laraterms;
use EduLazaro\Laraterms\Tests\TestCase;

class TaxonomyRegistryTest extends TestCase
{
    public function test_registry_loads_taxonomies_from_config(): void
    {
        $this->assertTrue(Laraterms::has('tags'));
        $this->assertTrue(Laraterms::has('categories'));
        $this->assertFalse(Laraterms::has('nonexistent'));
    }

    public function test_get_returns_taxonomy_definition_with_config_options(): void
    {
        $tags = Laraterms::get('tags');
        $this->assertFalse($tags->hierarchical);
        $this->assertTrue($tags->isTenantScoped());

        $categories = Laraterms::get('categories');
        $this->assertTrue($categories->hierarchical);
    }

    public function test_handles_lists_all_registered_taxonomies(): void
    {
        $handles = Laraterms::handles();

        $this->assertContains('tags', $handles);
        $this->assertContains('categories', $handles);
    }
}
