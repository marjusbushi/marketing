<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\DailyBasket;
use App\Models\DailyBasketPost;
use App\Models\DailyBasketPostMedia;
use App\Models\Marketing\CreativeBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CapCut video upload endpoint: validates happy path + rejection of
 * non-video / oversized / missing-file requests, and that the record
 * lands on the post + the brief's media_slots + state.capcut.
 */
class CreativeBriefVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        config()->set('content-planner.video_max_size_mb', 500);

        $this->buildDisAclTables();
        $this->seedMarketingUserWithPermissions([
            P::MODULE_MARKETING_ACCESS->value,
            P::CONTENT_PLANNER_EDIT->value,
            P::CONTENT_PLANNER_CREATE->value,
        ]);
    }

    public function test_upload_stores_file_and_creates_media_record(): void
    {
        $brief = $this->makeBrief();
        $video = UploadedFile::fake()->create('drop.mp4', 2048, 'video/mp4');
        $thumb = UploadedFile::fake()->image('thumb.jpg', 400, 225);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/marketing/api/creative-briefs/{$brief->id}/upload-video",
            [
                'file'             => $video,
                'thumbnail'        => $thumb,
                'duration_seconds' => 15,
                'width'            => 1080,
                'height'           => 1920,
            ]
        );

        $response->assertCreated();
        $payload = $response->json();

        $this->assertNotNull($payload['media']);
        $this->assertSame(15, $payload['media']['duration_seconds']);
        $this->assertSame(1080, $payload['media']['width']);

        $media = DailyBasketPostMedia::query()->firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        Storage::disk('public')->assertExists($media->thumbnail_path);

        $freshBrief = $brief->refresh();
        $this->assertCount(1, $freshBrief->media_slots);
        $this->assertSame('video', $freshBrief->media_slots[0]['kind']);
        $this->assertSame('capcut', $freshBrief->media_slots[0]['source']);
        $this->assertSame(15, $freshBrief->duration_sec);
        $this->assertSame($media->id, $freshBrief->media_slots[0]['media_id']);
        $this->assertSame($media->id, $freshBrief->state['capcut'][0]['media_id']);
    }

    public function test_upload_rejects_non_video_file(): void
    {
        $brief = $this->makeBrief();
        $notVideo = UploadedFile::fake()->image('cover.jpg', 400, 400);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/marketing/api/creative-briefs/{$brief->id}/upload-video",
            ['file' => $notVideo],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
        $this->assertSame(0, DailyBasketPostMedia::query()->count());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        config()->set('content-planner.video_max_size_mb', 1); // 1MB ceiling

        $brief = $this->makeBrief();
        $tooBig = UploadedFile::fake()->create('huge.mp4', 4096, 'video/mp4'); // 4MB

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/marketing/api/creative-briefs/{$brief->id}/upload-video",
            ['file' => $tooBig],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_upload_requires_file_parameter(): void
    {
        $brief = $this->makeBrief();

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/marketing/api/creative-briefs/{$brief->id}/upload-video",
            [],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_upload_without_linked_post_still_populates_slots(): void
    {
        $brief = CreativeBrief::query()->create([
            'daily_basket_post_id' => null,
            'post_type'            => 'reel',
            'source'               => 'manual',
            'state'                => [],
            'media_slots'          => [],
            'created_by'           => 1,
        ]);

        $video = UploadedFile::fake()->create('drop.mp4', 1024, 'video/mp4');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/marketing/api/creative-briefs/{$brief->id}/upload-video",
            ['file' => $video, 'duration_seconds' => 8, 'width' => 720, 'height' => 1280],
        );

        $response->assertCreated()->assertJsonPath('media', null);

        $this->assertSame(0, DailyBasketPostMedia::query()->count());

        $freshBrief = $brief->refresh();
        $this->assertCount(1, $freshBrief->media_slots);
        $this->assertSame('capcut', $freshBrief->media_slots[0]['source']);
        $this->assertNull($freshBrief->media_slots[0]['media_id']);
    }

    // ─── helpers ────────────────────────────────────────────────

    private function makeBrief(): CreativeBrief
    {
        $basket = DailyBasket::query()->create([
            'date'                 => now()->toDateString(),
            'distribution_week_id' => 1,
        ]);

        $post = DailyBasketPost::query()->create([
            'daily_basket_id' => $basket->id,
            'post_type'       => 'reel',
            'stage'           => 'planning',
            'title'           => 'CapCut test post',
        ]);

        return CreativeBrief::query()->create([
            'daily_basket_post_id' => $post->id,
            'post_type'            => 'reel',
            'source'               => 'manual',
            'state'                => [],
            'media_slots'          => [],
            'created_by'           => 1,
        ]);
    }

    private function seedMarketingUserWithPermissions(array $permissions): void
    {
        DB::connection('dis')->table('users')->insert([
            'id' => 1, 'first_name' => 'Test', 'last_name' => 'User',
            'email' => 'test@example.test', 'password' => 'secret', 'locale' => 'sq',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('dis')->table('apps')->insert(['id' => 1, 'slug' => 'marketing', 'status' => true]);
        DB::connection('dis')->table('app_roles')->insert(['id' => 1, 'app_id' => 1, 'status' => true]);
        DB::connection('dis')->table('user_app_roles')->insert(['user_id' => 1, 'app_role_id' => 1]);

        foreach ($permissions as $p) {
            DB::connection('dis')->table('app_role_permissions')->insert([
                'app_role_id' => 1, 'permission' => $p,
            ]);
        }

        $this->actingAs(User::query()->findOrFail(1));
    }

    private function buildDisAclTables(): void
    {
        foreach (['app_role_permissions', 'user_app_roles', 'app_roles', 'apps', 'users'] as $table) {
            Schema::connection('dis')->dropIfExists($table);
        }

        Schema::connection('dis')->create('users', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('locale')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('banned_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::connection('dis')->create('apps', function ($t) {
            $t->id();
            $t->string('slug');
            $t->boolean('status')->default(true);
        });
        Schema::connection('dis')->create('app_roles', function ($t) {
            $t->id();
            $t->unsignedBigInteger('app_id');
            $t->boolean('status')->default(true);
        });
        Schema::connection('dis')->create('user_app_roles', function ($t) {
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('app_role_id');
        });
        Schema::connection('dis')->create('app_role_permissions', function ($t) {
            $t->unsignedBigInteger('app_role_id');
            $t->string('permission');
        });
    }
}
