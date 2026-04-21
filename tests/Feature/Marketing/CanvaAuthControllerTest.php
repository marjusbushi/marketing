<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\Marketing\CanvaConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the Canva Connect OAuth flow — authorize redirect with PKCE,
 * callback token exchange, disconnect revocation, and status endpoint.
 */
class CanvaAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('canva.features.canva_connect', true);
        config()->set('canva.client_id', 'test-client-id');
        config()->set('canva.client_secret', 'test-client-secret');
        config()->set('canva.auth_url', 'https://www.canva.com/api/oauth/authorize');
        config()->set('canva.token_url', 'https://api.canva.com/rest/v1/oauth/token');
        config()->set('canva.revoke_url', 'https://api.canva.com/rest/v1/oauth/revoke');
        config()->set('canva.base_url', 'https://api.canva.com/rest/v1');
        config()->set('canva.oauth.redirect_uri', '/marketing/canva/callback');
        config()->set('app.url', 'https://app.test');

        $this->buildDisAclTables();
        $this->seedMarketingUserWithPermissions([
            P::MODULE_MARKETING_ACCESS->value,
            P::CONTENT_PLANNER_EDIT->value,
        ]);
    }

    public function test_authorize_redirects_with_pkce_and_stores_state(): void
    {
        $response = $this->get('/marketing/canva/authorize');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://www.canva.com/api/oauth/authorize', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $params);

        $this->assertSame('test-client-id', $params['client_id']);
        $this->assertSame('https://app.test/marketing/canva/callback', $params['redirect_uri']);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertNotEmpty($params['code_challenge']);
        $this->assertNotEmpty($params['state']);

        $this->assertSame($params['state'], session('canva_oauth_state'));
        $this->assertNotEmpty(session('canva_oauth_verifier'));
    }

    public function test_authorize_returns_404_when_feature_disabled(): void
    {
        config()->set('canva.features.canva_connect', false);

        $this->get('/marketing/canva/authorize')->assertNotFound();
    }

    public function test_authorize_returns_503_when_client_credentials_missing(): void
    {
        config()->set('canva.client_id', '');

        $this->get('/marketing/canva/authorize')->assertStatus(503);
    }

    public function test_callback_exchanges_code_and_saves_encrypted_connection(): void
    {
        Http::fake([
            'api.canva.com/rest/v1/oauth/token' => Http::response([
                'access_token'  => 'access-aaa',
                'refresh_token' => 'refresh-bbb',
                'expires_in'    => 3600,
                'scope'         => 'design:content:read design:content:write',
                'token_type'    => 'Bearer',
            ]),
            'api.canva.com/rest/v1/users/me' => Http::response([
                'user' => ['id' => 'canva-user-123', 'display_name' => 'Marjus B.'],
            ]),
        ]);

        $this->withSession([
            'canva_oauth_state'    => 'abc123',
            'canva_oauth_verifier' => 'verify-xyz',
        ]);

        $response = $this->get('/marketing/canva/callback?code=the-code&state=abc123');

        $response->assertRedirect(route('marketing.settings.brand-kit.index'));
        $response->assertSessionHas('success');

        $connection = CanvaConnection::query()->firstOrFail();
        $this->assertSame(1, $connection->user_id);
        $this->assertSame('access-aaa', $connection->access_token);   // decrypted via cast
        $this->assertSame('refresh-bbb', $connection->refresh_token);
        $this->assertSame('canva-user-123', $connection->canva_user_id);
        $this->assertSame('Marjus B.', $connection->canva_display_name);

        // And the ciphertext in the DB is NOT the plaintext.
        $raw = DB::table('marketing_canva_connections')->where('id', $connection->id)->first();
        $this->assertNotSame('access-aaa', $raw->access_token);
        $this->assertNotSame('refresh-bbb', $raw->refresh_token);
    }

    public function test_callback_rejects_tampered_state(): void
    {
        $this->withSession([
            'canva_oauth_state'    => 'real-state',
            'canva_oauth_verifier' => 'verifier',
        ]);

        $response = $this->get('/marketing/canva/callback?code=foo&state=attacker-state');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, CanvaConnection::query()->count());
    }

    public function test_disconnect_deactivates_connection_and_clears_tokens(): void
    {
        $connection = CanvaConnection::query()->create([
            'user_id'       => 1,
            'access_token'  => 'access',
            'refresh_token' => 'refresh',
            'expires_at'    => now()->addHour(),
            'is_active'     => true,
        ]);

        Http::fake([
            'api.canva.com/rest/v1/oauth/revoke' => Http::response([], 200),
        ]);

        $response = $this->postJson('/marketing/canva/disconnect');

        $response->assertOk()->assertJsonPath('status', 'disconnected');

        $connection->refresh();
        $this->assertFalse($connection->is_active);
        $this->assertSame('', $connection->access_token);
        $this->assertSame('', $connection->refresh_token);
    }

    public function test_status_returns_disconnected_when_no_row(): void
    {
        $response = $this->getJson('/marketing/api/canva/status');

        $response->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('feature_enabled', true);
    }

    public function test_status_reports_connection_metadata(): void
    {
        CanvaConnection::query()->create([
            'user_id'             => 1,
            'access_token'        => 'access',
            'refresh_token'       => 'refresh',
            'canva_user_id'       => 'cu-777',
            'canva_display_name'  => 'ZA Studio',
            'expires_at'          => now()->addHour(),
            'is_active'           => true,
        ]);

        $response = $this->getJson('/marketing/api/canva/status');

        $response->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('canva_user_id', 'cu-777')
            ->assertJsonPath('canva_display_name', 'ZA Studio')
            ->assertJsonPath('expired', false);
    }

    // ─── test scaffolding (mirrors CreativeBriefControllerTest) ──

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

        DB::connection('dis')->table('apps')->insert(['id' => 1, 'slug' => 'marketing', 'status' => true]);
        DB::connection('dis')->table('app_roles')->insert(['id' => 1, 'app_id' => 1, 'status' => true]);
        DB::connection('dis')->table('user_app_roles')->insert(['user_id' => 1, 'app_role_id' => 1]);

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
