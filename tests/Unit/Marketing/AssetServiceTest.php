<?php

namespace Tests\Unit\Marketing;

use App\Models\Marketing\Asset;
use App\Services\Marketing\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssetService();
        Storage::fake('public');
    }

    public function test_upload_stores_file_and_creates_asset(): void
    {
        $file = UploadedFile::fake()->create('logo.png', 24, 'image/png');

        $asset = $this->service->upload(
            file: $file,
            kind: 'logo',
            name: 'Dark Logo',
            metadata: ['variant' => 'dark'],
            userId: 88,
        );

        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame('logo', $asset->kind);
        $this->assertSame('Dark Logo', $asset->name);
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertSame(['variant' => 'dark'], $asset->metadata);
        $this->assertSame(88, $asset->uploaded_by);
    }

    public function test_by_kind_returns_assets_ordered_by_name(): void
    {
        Asset::query()->create($this->assetAttrs(['kind' => 'music', 'name' => 'Z Track']));
        Asset::query()->create($this->assetAttrs(['kind' => 'music', 'name' => 'A Track']));
        Asset::query()->create($this->assetAttrs(['kind' => 'logo', 'name' => 'Logo']));

        $music = $this->service->byKind('music');

        $this->assertCount(2, $music);
        $this->assertSame(['A Track', 'Z Track'], $music->pluck('name')->all());
    }

    public function test_delete_removes_file_and_record(): void
    {
        $asset = $this->service->upload(
            UploadedFile::fake()->create('watermark.png', 12, 'image/png'),
            'watermark',
            'Watermark',
        );

        $path = $asset->path;

        $this->service->delete($asset);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('marketing_assets', ['id' => $asset->id]);
    }

    private function assetAttrs(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'logo',
            'name' => 'Asset',
            'path' => 'marketing/assets/logo/file.png',
            'mime_type' => 'image/png',
            'metadata' => [],
        ], $overrides);
    }
}
