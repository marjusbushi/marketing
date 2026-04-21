<?php

namespace Tests\Unit\Marketing;

use App\Models\DailyBasket;
use App\Models\DailyBasketPost;
use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\Template;
use App\Services\Marketing\CreativeBriefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreativeBriefServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreativeBriefService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreativeBriefService();
    }

    public function test_create_for_post_links_brief_and_post(): void
    {
        $post = $this->makePost();
        $template = Template::query()->create($this->templateAttrs());

        $brief = $this->service->createForPost($post, 'reel', $template, userId: 77);

        $this->assertSame($post->id, $brief->daily_basket_post_id);
        $this->assertSame($template->id, $brief->template_id);
        $this->assertSame('reel', $brief->post_type);
        $this->assertSame('manual', $brief->source);
        $this->assertSame(77, $brief->created_by);
        $this->assertSame($brief->id, $post->refresh()->creative_brief_id);
    }

    public function test_create_for_post_does_not_overwrite_existing_post_link(): void
    {
        $post = $this->makePost();

        $first = $this->service->createForPost($post, 'photo');
        $second = $this->service->createForPost($post->refresh(), 'carousel');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->id, $post->refresh()->creative_brief_id);
    }

    public function test_update_state_and_fields_persist_editor_payload(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'photo', 'source' => 'manual']);

        $this->service->updateState($brief, [
            'engine' => 'polotno',
            'pages' => [['id' => 'page-1']],
        ]);

        $updated = $this->service->updateFields($brief->refresh(), [
            'caption_sq' => 'Drop i ri sot.',
            'hashtags' => ['#zeroabsolute', '#newdrop'],
        ]);

        $this->assertSame('polotno', $updated->state['engine']);
        $this->assertSame('Drop i ri sot.', $updated->caption_sq);
        $this->assertSame(['#zeroabsolute', '#newdrop'], $updated->hashtags);
    }

    public function test_duplicate_keeps_editor_fields_without_post_link(): void
    {
        $brief = CreativeBrief::query()->create([
            'daily_basket_post_id' => $this->makePost()->id,
            'post_type' => 'reel',
            'aspect' => '9:16',
            'duration_sec' => 12,
            'caption_sq' => 'Caption',
            'hashtags' => ['#a'],
            'source' => 'ai-light',
            'state' => ['composition' => 'ReelProduct'],
        ]);

        $copy = $this->service->duplicate($brief);

        $this->assertNull($copy->daily_basket_post_id);
        $this->assertSame('reel', $copy->post_type);
        $this->assertSame('9:16', $copy->aspect);
        $this->assertSame(12, $copy->duration_sec);
        $this->assertSame('Caption', $copy->caption_sq);
        $this->assertSame(['#a'], $copy->hashtags);
        $this->assertSame(['composition' => 'ReelProduct'], $copy->state);
    }

    private function makePost(): DailyBasketPost
    {
        $basket = DailyBasket::query()->create([
            'distribution_week_id' => 100,
            'date' => '2026-04-21',
            'status' => 'active',
        ]);

        return DailyBasketPost::query()->create([
            'daily_basket_id' => $basket->id,
            'post_type' => 'photo',
            'stage' => 'planning',
            'title' => 'Test post',
        ]);
    }

    private function templateAttrs(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Reel Product',
            'slug' => 'reel-product',
            'kind' => 'reel',
            'engine' => 'remotion',
            'source' => ['composition' => 'ReelProduct'],
            'metadata' => [],
            'is_system' => true,
            'is_active' => true,
        ], $overrides);
    }
}
