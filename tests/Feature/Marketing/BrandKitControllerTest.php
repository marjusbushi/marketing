<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandKitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
        $this->buildDisAclTables();
        $this->actingAsMarketingManager();
    }

    public function test_settings_page_renders(): void
    {
        $response = $this->get(route('marketing.settings.brand-kit.index'));

        $response->assertOk();
        $response->assertSee('Brand Kit');
        $response->assertSee('Colors');
    }

    public function test_update_api_persists_brand_kit(): void
    {
        $payload = [
            'colors' => [
                'primary' => '#111827',
                'secondary' => '#f8fafc',
                'accent' => '#e11d48',
                'neutral' => '#64748b',
                'text' => '#0f172a',
            ],
            'typography' => [
                'display' => ['family' => 'Inter', 'weights' => ['700']],
                'body' => ['family' => 'Inter', 'weights' => ['400', '500']],
                'mono' => ['family' => 'ui-monospace', 'weights' => ['400']],
            ],
            'logo_variants' => ['dark' => 'marketing/assets/logo/dark.png'],
            'watermark' => ['path' => 'marketing/assets/watermark/w.png', 'position' => 'bottom-right', 'opacity' => 0.7, 'scale' => 0.18],
            'voice_sq' => 'Tone e shkurter dhe komerciale.',
            'voice_en' => 'Short and direct.',
            'caption_templates' => [
                'hook_patterns' => ['Drop i ri sot'],
                'cta_patterns' => ['Na shkruaj per masen'],
            ],
            'default_hashtags' => ['#zeroabsolute', '#newdrop'],
            'music_library' => [],
            'aspect_defaults' => [
                ['post_type' => 'photo', 'aspect' => '4:5'],
                ['post_type' => 'reel', 'aspect' => '9:16'],
            ],
        ];

        $response = $this->putJson(route('marketing.api.brand-kit.update'), $payload);

        $response->assertOk();
        $response->assertJsonPath('brand_kit.colors.accent', '#e11d48');
        $this->assertDatabaseHas('marketing_brand_kit', [
            'voice_sq' => 'Tone e shkurter dhe komerciale.',
            'updated_by' => 1,
        ]);
    }

    public function test_asset_upload_and_delete_api(): void
    {
        $upload = $this->post(route('marketing.api.brand-kit.assets.store'), [
            'kind' => 'logo',
            'name' => 'Main Logo',
            'file' => UploadedFile::fake()->create('logo.png', 16, 'image/png'),
        ]);

        $upload->assertCreated();
        $assetId = $upload->json('asset.id');
        $path = $upload->json('asset.path');

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('marketing_assets', [
            'id' => $assetId,
            'kind' => 'logo',
            'name' => 'Main Logo',
            'uploaded_by' => 1,
        ]);

        $delete = $this->deleteJson(route('marketing.api.brand-kit.assets.destroy', ['asset' => $assetId]));

        $delete->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('marketing_assets', ['id' => $assetId]);
    }

    private function actingAsMarketingManager(): void
    {
        DB::connection('dis')->table('users')->insert([
            'id' => 1,
            'first_name' => 'Marketing',
            'last_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'secret',
            'locale' => 'sq',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('dis')->table('apps')->insert([
            'id' => 1,
            'slug' => 'marketing',
            'status' => true,
        ]);

        DB::connection('dis')->table('app_roles')->insert([
            'id' => 1,
            'app_id' => 1,
            'status' => true,
        ]);

        DB::connection('dis')->table('user_app_roles')->insert([
            'user_id' => 1,
            'app_role_id' => 1,
        ]);

        DB::connection('dis')->table('app_role_permissions')->insert([
            ['app_role_id' => 1, 'permission' => P::CONTENT_PLANNER_MANAGE->value],
        ]);

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
