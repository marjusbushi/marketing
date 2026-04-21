<?php

namespace Tests\Feature\Marketing;

use App\Enums\MarketingPermissionEnum as P;
use App\Models\Marketing\Template;
use App\Models\User;
use Database\Seeders\MarketingTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Coverage for the read side of the templates API consumed by the editor,
 * and the write side used by Manager+ admins. System templates are
 * protected from edit/delete by 403 assertions below.
 */
class TemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDisAclTables();
        $this->seed(MarketingTemplatesSeeder::class);
    }

    public function test_seeder_creates_seven_system_templates(): void
    {
        $count = Template::query()->where('is_system', true)->count();

        $this->assertSame(7, $count);
    }

    public function test_index_filters_by_kind(): void
    {
        $this->actingAsMarketingUser();

        $response = $this->getJson('/marketing/api/templates?kind=reel');

        $response->assertOk();
        $templates = $response->json('templates');
        $this->assertNotEmpty($templates);
        foreach ($templates as $t) {
            $this->assertSame('reel', $t['kind']);
        }
    }

    public function test_index_filters_by_engine(): void
    {
        $this->actingAsMarketingUser();

        $response = $this->getJson('/marketing/api/templates?engine=polotno');

        $response->assertOk();
        foreach ($response->json('templates') as $t) {
            $this->assertSame('polotno', $t['engine']);
        }
    }

    public function test_show_by_slug_returns_source(): void
    {
        $this->actingAsMarketingUser();

        $response = $this->getJson('/marketing/api/templates/reel-product-showcase');

        $response->assertOk();
        $response->assertJsonPath('template.slug', 'reel-product-showcase');
        $response->assertJsonPath('template.engine', 'remotion');
        $this->assertArrayHasKey('source', $response->json('template'));
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->actingAsMarketingUser();

        $this->getJson('/marketing/api/templates/does-not-exist')
            ->assertNotFound();
    }

    public function test_system_templates_cannot_be_updated(): void
    {
        $this->actingAsMarketingManager();
        $systemTemplate = Template::query()->where('is_system', true)->first();

        $this->putJson("/marketing/api/templates/{$systemTemplate->id}", ['name' => 'New name'])
            ->assertForbidden();
    }

    public function test_system_templates_cannot_be_deleted(): void
    {
        $this->actingAsMarketingManager();
        $systemTemplate = Template::query()->where('is_system', true)->first();

        $this->deleteJson("/marketing/api/templates/{$systemTemplate->id}")
            ->assertForbidden();
    }

    public function test_user_created_template_lifecycle(): void
    {
        $this->actingAsMarketingManager();

        $create = $this->postJson('/marketing/api/templates', [
            'name'   => 'My Custom',
            'kind'   => 'photo',
            'engine' => 'polotno',
            'source' => ['polotno' => ['width' => 1080, 'height' => 1080, 'pages' => []]],
        ]);
        $create->assertCreated();
        $id = $create->json('template.id');

        $this->putJson("/marketing/api/templates/{$id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('template.name', 'Renamed');

        $this->deleteJson("/marketing/api/templates/{$id}")
            ->assertOk();

        $this->assertFalse(Template::query()->find($id)->is_active);
    }

    private function actingAsMarketingUser(): void
    {
        $this->seedMarketingUserWithPermissions([P::CONTENT_PLANNER_VIEW->value]);
    }

    private function actingAsMarketingManager(): void
    {
        $this->seedMarketingUserWithPermissions([
            P::CONTENT_PLANNER_VIEW->value,
            P::CONTENT_PLANNER_MANAGE->value,
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
