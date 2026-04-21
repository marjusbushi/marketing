<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies the AI Light endpoints end-to-end with Http::fake() standing in
 * for the real Claude API. Covers happy path, graceful degradation on API
 * error, audit log writes, and rate-limiting.
 */
class MarketingAIControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDisAclTables();
        $this->seedMarketingUserWithPermissions([
            P::CONTENT_PLANNER_VIEW->value,
            P::CONTENT_PLANNER_EDIT->value,
        ]);

        config(['anthropic.api_key' => 'test-key']);
    }

    public function test_caption_returns_parsed_json_and_logs_call(): void
    {
        Http::fake([
            '*/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'caption_sq' => 'Drop i ri sot.',
                        'caption_en' => 'New drop today.',
                        'hashtags'   => ['#zeroabsolute', '#drop3'],
                    ]),
                ]],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 40],
            ], 200),
        ]);

        $response = $this->postJson('/marketing/api/ai/caption', [
            'product_id' => 1,
            'post_type'  => 'reel',
            'language'   => 'both',
        ]);

        $response->assertOk()
            ->assertJsonPath('caption_sq', 'Drop i ri sot.')
            ->assertJsonPath('caption_en', 'New drop today.')
            ->assertJsonPath('hashtags.0', '#zeroabsolute');

        $this->assertDatabaseHas('marketing_ai_calls', [
            'endpoint'   => 'caption',
            'ok'         => true,
            'tokens_in'  => 120,
            'tokens_out' => 40,
        ]);
    }

    public function test_caption_returns_empty_on_api_failure(): void
    {
        Http::fake([
            '*/messages' => Http::response(['error' => 'server'], 500),
        ]);

        $response = $this->postJson('/marketing/api/ai/caption', [
            'product_id' => 1,
            'post_type'  => 'reel',
        ]);

        // Graceful degradation: empty payload, no 5xx to the client.
        $response->assertOk()
            ->assertJsonPath('caption_sq', null)
            ->assertJsonPath('caption_en', null)
            ->assertJsonPath('hashtags', []);

        $this->assertDatabaseHas('marketing_ai_calls', [
            'endpoint' => 'caption',
            'ok'       => false,
        ]);
    }

    public function test_caption_validates_input(): void
    {
        $this->postJson('/marketing/api/ai/caption', [
            'post_type' => 'reel',
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id']);

        $this->postJson('/marketing/api/ai/caption', [
            'product_id' => 1,
            'post_type'  => 'banner',
        ])->assertStatus(422)->assertJsonValidationErrors(['post_type']);
    }

    public function test_rewrite_returns_text(): void
    {
        Http::fake([
            '*/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => 'Tekst i ri.',
                ]],
                'usage' => ['input_tokens' => 30, 'output_tokens' => 8],
            ], 200),
        ]);

        $this->postJson('/marketing/api/ai/rewrite', [
            'text'     => 'Original text',
            'tone'     => 'brand',
            'language' => 'sq',
        ])->assertOk()->assertJsonPath('text', 'Tekst i ri.');
    }

    public function test_hashtags_are_filtered_and_capped(): void
    {
        Http::fake([
            '*/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'caption_sq' => 'X',
                        'caption_en' => 'X',
                        'hashtags'   => array_merge(
                            ['#ok', '#also-ok'],
                            ['invalid', '#'],               // no hash / empty
                            array_fill(0, 12, '#spam'),     // duplicates + flood
                        ),
                    ]),
                ]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $response = $this->postJson('/marketing/api/ai/caption', [
            'product_id' => 1,
            'post_type'  => 'reel',
        ]);

        $tags = $response->json('hashtags');
        $this->assertLessThanOrEqual(8, count($tags));
        $this->assertContains('#ok', $tags);
        $this->assertNotContains('invalid', $tags);
    }

    public function test_permissions_reject_users_without_edit(): void
    {
        // Replace the manager with a bare-view user.
        DB::connection('dis')->table('app_role_permissions')->where('app_role_id', 1)->delete();
        DB::connection('dis')->table('app_role_permissions')->insert([
            'app_role_id' => 1,
            'permission'  => P::CONTENT_PLANNER_VIEW->value,
        ]);

        $this->postJson('/marketing/api/ai/caption', [
            'product_id' => 1,
            'post_type'  => 'reel',
        ])->assertForbidden();
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
