<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\DailyBasket;
use App\Models\DailyBasketPost;
use App\Models\Marketing\CanvaConnection;
use App\Models\Marketing\CreativeBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the design flow on top of a connected Canva account:
 *   • create design from brand template (autofill)
 *   • poll export job
 *   • attach the exported asset to a creative brief
 *   • auto-refresh expired access tokens
 *   • brand-kit one-way sync
 */
class CanvaDesignControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('canva.features.canva_connect', true);
        config()->set('canva.client_id', 'test-client-id');
        config()->set('canva.client_secret', 'test-client-secret');
        config()->set('canva.base_url', 'https://api.canva.com/rest/v1');
        config()->set('canva.token_url', 'https://api.canva.com/rest/v1/oauth/token');

        $this->buildDisAclTables();
        $this->seedMarketingUserWithPermissions([
            P::MODULE_MARKETING_ACCESS->value,
            P::CONTENT_PLANNER_EDIT->value,
            P::CONTENT_PLANNER_CREATE->value,
            P::CONTENT_PLANNER_MANAGE->value,
        ]);

        $this->connectCanva();
    }

    public function test_create_design_autofills_brand_template(): void
    {
        Http::fake([
            'api.canva.com/rest/v1/brand-templates/*/autofills' => Http::response([
                'design' => [
                    'id'   => 'design-001',
                    'urls' => ['edit_url' => 'https://www.canva.com/design/design-001/edit'],
                ],
            ]),
        ]);

        $response = $this->postJson('/marketing/api/canva/designs', [
            'brand_template_id' => 'tmpl-123',
            'fields'            => ['headline' => 'Drop i ri'],
        ]);

        $response->assertOk()->assertJsonPath('design.id', 'design-001');

        Http::assertSent(function ($req) {
            return str_ends_with($req->url(), '/brand-templates/tmpl-123/autofills')
                && $req->hasHeader('Authorization', 'Bearer access-aaa')
                && $req['data']['headline'] === 'Drop i ri';
        });
    }

    public function test_export_flow_forwards_canva_job_payload(): void
    {
        Http::fake([
            'api.canva.com/rest/v1/exports' => Http::response([
                'job' => ['id' => 'job-001', 'status' => 'in_progress'],
            ]),
            'api.canva.com/rest/v1/exports/job-001' => Http::response([
                'job' => [
                    'id'     => 'job-001',
                    'status' => 'success',
                    'urls'   => ['https://canva.example/asset.png'],
                ],
            ]),
        ]);

        $this->postJson('/marketing/api/canva/designs/design-001/export', ['format' => 'png'])
            ->assertOk()
            ->assertJsonPath('job.id', 'job-001');

        $this->getJson('/marketing/api/canva/exports/job-001')
            ->assertOk()
            ->assertJsonPath('job.status', 'success')
            ->assertJsonPath('job.urls.0', 'https://canva.example/asset.png');
    }

    public function test_attach_to_brief_persists_design_reference_and_media_slot(): void
    {
        $brief = $this->makeBrief();

        $response = $this->postJson("/marketing/api/creative-briefs/{$brief->id}/attach-canva-design", [
            'design_id'  => 'design-001',
            'asset_url'  => 'https://canva.example/asset.png',
            'format'     => 'png',
        ]);

        $response->assertOk();
        $fresh = $brief->refresh();

        $this->assertSame('design-001', $fresh->state['canva']['design_id']);
        $this->assertSame('https://canva.example/asset.png', $fresh->state['canva']['asset_url']);
        $this->assertCount(1, $fresh->media_slots);
        $this->assertSame('canva', $fresh->media_slots[0]['kind']);
    }

    public function test_create_design_refreshes_expired_token_transparently(): void
    {
        CanvaConnection::query()->where('user_id', 1)->update([
            'expires_at' => now()->subMinutes(1), // already expired
        ]);

        Http::fake([
            'api.canva.com/rest/v1/oauth/token' => Http::response([
                'access_token'  => 'refreshed-zzz',
                'refresh_token' => 'new-refresh',
                'expires_in'    => 3600,
            ]),
            'api.canva.com/rest/v1/brand-templates/*/autofills' => Http::response([
                'design' => ['id' => 'design-002'],
            ]),
        ]);

        $this->postJson('/marketing/api/canva/designs', [
            'brand_template_id' => 'tmpl-999',
        ])->assertOk();

        Http::assertSent(function ($req) {
            return str_ends_with($req->url(), '/brand-templates/tmpl-999/autofills')
                && $req->hasHeader('Authorization', 'Bearer refreshed-zzz');
        });

        $this->assertSame('refreshed-zzz', CanvaConnection::query()->where('user_id', 1)->first()->access_token);
    }

    public function test_returns_428_when_no_active_connection(): void
    {
        CanvaConnection::query()->delete();

        $this->postJson('/marketing/api/canva/designs', ['brand_template_id' => 'x'])
            ->assertStatus(428);
    }

    // ─── helpers ────────────────────────────────────────────────

    private function connectCanva(): void
    {
        CanvaConnection::query()->create([
            'user_id'       => 1,
            'access_token'  => 'access-aaa',
            'refresh_token' => 'refresh-bbb',
            'expires_at'    => now()->addHour(),
            'is_active'     => true,
        ]);
    }

    private function makeBrief(): CreativeBrief
    {
        $basket = DailyBasket::query()->create([
            'date'                 => now()->toDateString(),
            'distribution_week_id' => 1,
        ]);

        $post = DailyBasketPost::query()->create([
            'daily_basket_id' => $basket->id,
            'post_type'       => 'photo',
            'stage'           => 'planning',
            'title'           => 'Canva test post',
        ]);

        return CreativeBrief::query()->create([
            'daily_basket_post_id' => $post->id,
            'post_type'            => 'photo',
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
