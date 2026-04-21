<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\Marketing\CreativeBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end coverage for the Studio page: the Blade shell renders, the
 * React mount node is present, and the initial props JSON carries the
 * exact shape the SPA expects. Actual React mount is not executed here —
 * frontend integration lives in Vitest / Playwright in future tasks.
 */
class StudioControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDisAclTables();
    }

    public function test_studio_page_renders_with_mount_node_and_props(): void
    {
        $this->seedMarketingUserWithPermissions([
            P::CONTENT_PLANNER_VIEW->value,
            P::CONTENT_PLANNER_EDIT->value,
        ]);

        $response = $this->get('/marketing/studio');

        $response->assertOk();
        $response->assertSee('id="studio-app"', false);
        $response->assertSee('data-props=', false);

        // Extract the data-props JSON and verify structure.
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-props="[^"]+"/', $html);
        $this->assertSame(1, preg_match('/data-props="([^"]+)"/', $html, $matches));
        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('brand_kit', $decoded);
        $this->assertArrayHasKey('endpoints', $decoded);
        $this->assertArrayHasKey('csrf_token', $decoded);
    }

    public function test_studio_page_includes_creative_brief_when_slug_route_used(): void
    {
        $this->seedMarketingUserWithPermissions([P::CONTENT_PLANNER_VIEW->value]);

        $brief = CreativeBrief::query()->create([
            'post_type' => 'reel',
            'source'    => 'manual',
        ]);

        $response = $this->get("/marketing/studio/{$brief->id}");

        $response->assertOk();
        // JSON-encoded into data-props, so the id appears as a number.
        $response->assertSee('&quot;creative_brief_id&quot;:' . $brief->id, false);
    }

    public function test_studio_page_rejects_users_without_view_permission(): void
    {
        // A user with marketing access but no content_planner.view.
        $this->seedMarketingUserWithPermissions([
            P::INFLUENCER_VIEW->value,
        ]);

        $this->get('/marketing/studio')->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/marketing/studio');

        $this->assertContains($response->status(), [302, 401, 403], 'expected auth redirect or block');
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
