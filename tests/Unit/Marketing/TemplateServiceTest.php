<?php

namespace Tests\Unit\Marketing;

use App\Models\Marketing\Template;
use App\Services\Marketing\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService();
    }

    public function test_list_by_kind_returns_only_active_of_that_kind(): void
    {
        Template::query()->create($this->templateAttrs(['kind' => 'reel', 'slug' => 'reel-a', 'is_active' => true]));
        Template::query()->create($this->templateAttrs(['kind' => 'reel', 'slug' => 'reel-b', 'is_active' => false]));
        Template::query()->create($this->templateAttrs(['kind' => 'photo', 'slug' => 'photo-a', 'is_active' => true]));

        $reels = $this->service->listByKind('reel');

        $this->assertCount(1, $reels);
        $this->assertSame('reel-a', $reels->first()->slug);
    }

    public function test_for_engine_filters_by_engine(): void
    {
        Template::query()->create($this->templateAttrs(['engine' => 'polotno', 'slug' => 'p-1']));
        Template::query()->create($this->templateAttrs(['engine' => 'remotion', 'slug' => 'r-1']));

        $this->assertCount(1, $this->service->forEngine('polotno'));
        $this->assertCount(1, $this->service->forEngine('remotion'));
    }

    public function test_system_templates_come_first(): void
    {
        Template::query()->create($this->templateAttrs(['slug' => 'a', 'is_system' => false, 'name' => 'A']));
        Template::query()->create($this->templateAttrs(['slug' => 'b', 'is_system' => true, 'name' => 'B']));

        $list = $this->service->listByKind('reel');

        $this->assertSame('B', $list->first()->name);
    }

    private function templateAttrs(array $overrides = []): array
    {
        return array_merge([
            'name'      => 'Test',
            'slug'      => 'test-' . uniqid(),
            'kind'      => 'reel',
            'engine'    => 'remotion',
            'source'    => ['composition' => 'stub'],
            'metadata'  => [],
            'is_system' => false,
            'is_active' => true,
        ], $overrides);
    }
}
