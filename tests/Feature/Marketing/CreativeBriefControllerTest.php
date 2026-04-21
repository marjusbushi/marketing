<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\DailyBasket;
use App\Models\DailyBasketPost;
use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the Creative Brief API end-to-end: creation from a daily-basket
 * post, standalone creation, state round-trip (5MB limit), duplicate,
 * filtering by post id, and delete.
 */
class CreativeBriefControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDisAclTables();
        $this->seedMarketingUserWithPermissions([
            P::CONTENT_PLANNER_VIEW->value,
            P::CONTENT_PLANNER_CREATE->value,
            P::CONTENT_PLANNER_EDIT->value,
            P::CONTENT_PLANNER_DELETE->value,
        ]);
    }

    public function test_create_brief_for_daily_basket_post_links_both_sides(): void
    {
        $post = $this->makePost();

        $response = $this->postJson('/marketing/api/creative-briefs', [
            'daily_basket_post_id' => $post->id,
            'post_type'            => 'reel',
            'aspect'               => '9:16',
            'duration_sec'         => 15,
        ]);

        $response->assertCreated();
        $briefId = $response->json('creative_brief.id');

        $this->assertSame($post->id, CreativeBrief::query()->find($briefId)->daily_basket_post_id);
        $this->assertSame($briefId, $post->refresh()->creative_brief_id);
    }

    public function test_standalone_brief_creates_without_post_link(): void
    {
        $response = $this->postJson('/marketing/api/creative-briefs', [
            'post_type' => 'photo',
            'aspect'    => '1:1',
        ]);

        $response->assertCreated();
        $brief = CreativeBrief::query()->findOrFail($response->json('creative_brief.id'));

        $this->assertNull($brief->daily_basket_post_id);
    }

    public function test_brief_with_template_slug_stores_template_id(): void
    {
        $template = Template::query()->create([
            'name'      => 'Seed',
            'slug'      => 'seed-reel',
            'kind'      => 'reel',
            'engine'    => 'remotion',
            'source'    => ['composition' => 'X'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/marketing/api/creative-briefs', [
            'template_slug' => 'seed-reel',
            'post_type'     => 'reel',
        ]);

        $response->assertCreated()
            ->assertJsonPath('creative_brief.template_id', $template->id)
            ->assertJsonPath('creative_brief.template_slug', 'seed-reel');
    }

    public function test_show_returns_state_and_template(): void
    {
        $brief = CreativeBrief::query()->create([
            'post_type' => 'reel',
            'source'    => 'manual',
            'state'     => ['pages' => [['id' => 'p1']]],
        ]);

        $this->getJson("/marketing/api/creative-briefs/{$brief->id}")
            ->assertOk()
            ->assertJsonPath('creative_brief.state.pages.0.id', 'p1');
    }

    public function test_show_exposes_primary_item_group_key_for_ai(): void
    {
        // `primary_item_group_id` / `primary_item_group_name` power the
        // AI "Generate Caption" button in the editor — the presence of the
        // keys in the response is the contract we lock down here. The
        // actual lookup goes cross-DB (item_groups lives in DIS), which
        // the SQLite-per-connection test harness can't faithfully model;
        // end-to-end validation happens on prod/staging MySQL.
        $brief = CreativeBrief::query()->create([
            'post_type' => 'reel',
            'source'    => 'manual',
        ]);

        $response = $this->getJson("/marketing/api/creative-briefs/{$brief->id}")
            ->assertOk();

        $payload = $response->json('creative_brief');
        $this->assertArrayHasKey('primary_item_group_id', $payload);
        $this->assertArrayHasKey('primary_item_group_name', $payload);
        $this->assertNull($payload['primary_item_group_id']);   // no post linked
        $this->assertNull($payload['primary_item_group_name']);
    }

    public function test_update_persists_caption_and_state(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);

        $this->putJson("/marketing/api/creative-briefs/{$brief->id}", [
            'caption_sq' => 'Drop i ri.',
            'caption_en' => 'New drop.',
            'hashtags'   => ['#za', '#drop3'],
            'state'      => ['pages' => [['id' => 'p1', 'children' => []]]],
        ])->assertOk();

        $brief->refresh();
        $this->assertSame('Drop i ri.', $brief->caption_sq);
        $this->assertSame(['#za', '#drop3'], $brief->hashtags);
        $this->assertSame('p1', $brief->state['pages'][0]['id']);
    }

    public function test_state_round_trips_pivot_shape_with_canva_and_capcut(): void
    {
        // The editor hydrates itself from exactly this JSON blob on load,
        // so we lock the shape here. Any schema change ships together
        // with a test update — catching silent-drop bugs early.
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);

        $state = [
            'canva' => [
                'design_id'     => 'design-abc',
                'asset_url'     => 'https://canva.example/asset.png',
                'thumbnail_url' => 'https://canva.example/thumb.jpg',
                'format'        => 'png',
                'attached_at'   => '2026-04-21T10:00:00+00:00',
            ],
            'capcut' => [[
                'kind'             => 'video',
                'source'           => 'capcut',
                'path'             => 'marketing/videos/1/foo.mp4',
                'thumbnail_path'   => 'marketing/videos/1/thumbnails/foo.jpg',
                'duration_seconds' => 15,
                'width'            => 1080,
                'height'           => 1920,
                'mime_type'        => 'video/mp4',
                'size_bytes'       => 2_345_678,
                'media_id'         => null,
                'uploaded_at'      => '2026-04-21T10:05:00+00:00',
            ]],
        ];

        $this->putJson("/marketing/api/creative-briefs/{$brief->id}", ['state' => $state])
            ->assertOk();

        $this->getJson("/marketing/api/creative-briefs/{$brief->id}")
            ->assertOk()
            ->assertJsonPath('creative_brief.state.canva.design_id', 'design-abc')
            ->assertJsonPath('creative_brief.state.canva.format', 'png')
            ->assertJsonPath('creative_brief.state.capcut.0.kind', 'video')
            ->assertJsonPath('creative_brief.state.capcut.0.source', 'capcut')
            ->assertJsonPath('creative_brief.state.capcut.0.duration_seconds', 15);
    }

    public function test_update_rejects_state_larger_than_5mb(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);

        // 6 MB of junk inside a nested field — large enough that encoded
        // payload exceeds the 5MB limit.
        $huge = str_repeat('x', 6 * 1024 * 1024);

        $this->putJson("/marketing/api/creative-briefs/{$brief->id}", [
            'state' => ['blob' => $huge],
        ])->assertStatus(413);
    }

    public function test_duplicate_creates_standalone_copy(): void
    {
        $post = $this->makePost();
        $original = CreativeBrief::query()->create([
            'daily_basket_post_id' => $post->id,
            'post_type'            => 'reel',
            'source'               => 'manual',
            'caption_sq'           => 'Original',
            'state'                => ['k' => 'v'],
        ]);

        $response = $this->postJson("/marketing/api/creative-briefs/{$original->id}/duplicate");

        $response->assertCreated();
        $copy = CreativeBrief::query()->findOrFail($response->json('creative_brief.id'));

        $this->assertNotSame($original->id, $copy->id);
        $this->assertNull($copy->daily_basket_post_id, 'duplicate must not inherit post link');
        $this->assertSame('Original', $copy->caption_sq);
        $this->assertSame(['k' => 'v'], $copy->state);
    }

    public function test_index_filters_by_post_id(): void
    {
        $postA = $this->makePost();
        $postB = $this->makePost();

        CreativeBrief::query()->create(['daily_basket_post_id' => $postA->id, 'post_type' => 'reel', 'source' => 'manual']);
        CreativeBrief::query()->create(['daily_basket_post_id' => $postB->id, 'post_type' => 'reel', 'source' => 'manual']);

        $response = $this->getJson("/marketing/api/creative-briefs?daily_basket_post_id={$postA->id}");

        $response->assertOk();
        $ids = collect($response->json('creative_briefs'))->pluck('daily_basket_post_id')->all();
        $this->assertSame([$postA->id], array_unique($ids));
    }

    public function test_delete_removes_brief(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);

        $this->deleteJson("/marketing/api/creative-briefs/{$brief->id}")->assertOk();

        $this->assertSoftDeleted('marketing_creative_briefs', ['id' => $brief->id]);
    }

    private int $postCounter = 0;

    private function makePost(): DailyBasketPost
    {
        // distribution_week_id references a DIS table; we pass a stable numeric
        // id since tests do not resolve the cross-DB relation. The counter
        // gives each basket a distinct date so multiple posts per test work.
        $this->postCounter++;
        $basket = DailyBasket::query()->create([
            'date'                 => now()->addDays($this->postCounter)->toDateString(),
            'distribution_week_id' => 1,
        ]);

        return DailyBasketPost::query()->create([
            'daily_basket_id' => $basket->id,
            'post_type'       => 'reel',
            'stage'           => 'planning',
            'title'           => 'Test post ' . $this->postCounter,
        ]);
    }

    private function seedMarketingUserWithPermissions(array $permissions): void
    {
        DB::connection('dis')->table('users')->insert([
            'id'         => 1,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'test@example.test',
            'password'   => 'secret',
            'locale'     => 'sq',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('dis')->table('apps')->insert([
            'id'     => 1,
            'slug'   => 'marketing',
            'status' => true,
        ]);

        DB::connection('dis')->table('app_roles')->insert([
            'id'     => 1,
            'app_id' => 1,
            'status' => true,
        ]);

        DB::connection('dis')->table('user_app_roles')->insert([
            'user_id'     => 1,
            'app_role_id' => 1,
        ]);

        foreach ($permissions as $permission) {
            DB::connection('dis')->table('app_role_permissions')->insert([
                'app_role_id' => 1,
                'permission'  => $permission,
            ]);
        }

        $this->actingAs(User::query()->findOrFail(1));
    }

    private function buildDisAclTables(): void
    {
        foreach (['app_role_permissions', 'user_app_roles', 'app_roles', 'apps', 'users'] as $table) {
            Schema::connection('dis')->dropIfExists($table);
        }

        Schema::connection('dis')->create('users', function ($table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('locale')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('dis')->create('apps', function ($table) {
            $table->id();
            $table->string('slug');
            $table->boolean('status')->default(true);
        });

        Schema::connection('dis')->create('app_roles', function ($table) {
            $table->id();
            $table->unsignedBigInteger('app_id');
            $table->boolean('status')->default(true);
        });

        Schema::connection('dis')->create('user_app_roles', function ($table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('app_role_id');
        });

        Schema::connection('dis')->create('app_role_permissions', function ($table) {
            $table->unsignedBigInteger('app_role_id');
            $table->string('permission');
        });
    }
}
