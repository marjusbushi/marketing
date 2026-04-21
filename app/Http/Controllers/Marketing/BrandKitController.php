<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Asset;
use App\Models\Marketing\BrandKit;
use App\Services\Marketing\AssetService;
use App\Services\Marketing\BrandKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandKitController extends Controller
{
    public function __construct(
        private readonly BrandKitService $brandKit,
        private readonly AssetService $assets,
    ) {
    }

    public function index(): View
    {
        return view('marketing.brand-kit', [
            'title' => 'Brand Kit',
            'pageTitle' => 'Brand Kit',
            'kitPayload' => $this->serializeKit($this->brandKit->get()),
            'assetPayload' => $this->serializeAssets(),
        ]);
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'brand_kit' => $this->serializeKit($this->brandKit->get()),
            'assets' => $this->serializeAssets(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'colors' => ['nullable', 'array'],
            'colors.primary' => ['nullable', 'string', 'max:20'],
            'colors.secondary' => ['nullable', 'string', 'max:20'],
            'colors.accent' => ['nullable', 'string', 'max:20'],
            'colors.neutral' => ['nullable', 'string', 'max:20'],
            'colors.text' => ['nullable', 'string', 'max:20'],

            'typography' => ['nullable', 'array'],
            'typography.display' => ['nullable', 'array'],
            'typography.display.family' => ['nullable', 'string', 'max:120'],
            'typography.display.weights' => ['nullable', 'array'],
            'typography.display.weights.*' => ['string', 'max:10'],
            'typography.body' => ['nullable', 'array'],
            'typography.body.family' => ['nullable', 'string', 'max:120'],
            'typography.body.weights' => ['nullable', 'array'],
            'typography.body.weights.*' => ['string', 'max:10'],
            'typography.mono' => ['nullable', 'array'],
            'typography.mono.family' => ['nullable', 'string', 'max:120'],
            'typography.mono.weights' => ['nullable', 'array'],
            'typography.mono.weights.*' => ['string', 'max:10'],

            'logo_variants' => ['nullable', 'array'],
            'logo_variants.dark' => ['nullable', 'string', 'max:500'],
            'logo_variants.light' => ['nullable', 'string', 'max:500'],
            'logo_variants.transparent' => ['nullable', 'string', 'max:500'],
            'logo_variants.icon' => ['nullable', 'string', 'max:500'],

            'watermark' => ['nullable', 'array'],
            'watermark.path' => ['nullable', 'string', 'max:500'],
            'watermark.position' => ['nullable', 'string', 'max:40'],
            'watermark.opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'watermark.scale' => ['nullable', 'numeric', 'min:0.01', 'max:1'],

            'voice_sq' => ['nullable', 'string', 'max:5000'],
            'voice_en' => ['nullable', 'string', 'max:5000'],

            'caption_templates' => ['nullable', 'array'],
            'caption_templates.hook_patterns' => ['nullable', 'array'],
            'caption_templates.hook_patterns.*' => ['string', 'max:300'],
            'caption_templates.cta_patterns' => ['nullable', 'array'],
            'caption_templates.cta_patterns.*' => ['string', 'max:300'],

            'default_hashtags' => ['nullable', 'array'],
            'default_hashtags.*' => ['string', 'max:80'],

            'music_library' => ['nullable', 'array'],
            'music_library.*' => ['array'],

            'aspect_defaults' => ['nullable', 'array'],
            'aspect_defaults.*.post_type' => ['required_with:aspect_defaults', 'string', 'max:30'],
            'aspect_defaults.*.aspect' => ['required_with:aspect_defaults', 'string', 'max:10'],
        ]);

        $kit = $this->brandKit->update($validated, $request->user()?->id);

        return response()->json([
            'message' => 'Brand kit saved.',
            'brand_kit' => $this->serializeKit($kit),
        ]);
    }

    public function uploadAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:sticker,music,font,logo,watermark,template-asset'],
            'name' => ['required', 'string', 'max:180'],
            'file' => ['required', 'file', 'max:51200'],
            'metadata' => ['nullable', 'array'],
        ]);

        $asset = $this->assets->upload(
            $request->file('file'),
            $validated['kind'],
            $validated['name'],
            $validated['metadata'] ?? [],
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Asset uploaded.',
            'asset' => $this->serializeAsset($asset),
        ], 201);
    }

    public function deleteAsset(Asset $asset): JsonResponse
    {
        $this->assets->delete($asset);

        return response()->json([
            'message' => 'Asset deleted.',
        ]);
    }

    private function serializeKit(BrandKit $kit): array
    {
        return [
            'id' => $kit->id,
            'colors' => $kit->colors ?: [
                'primary' => '#111827',
                'secondary' => '#f5f5f4',
                'accent' => '#e11d48',
                'neutral' => '#64748b',
                'text' => '#0f172a',
            ],
            'typography' => $kit->typography ?: [
                'display' => ['family' => 'Inter', 'weights' => ['600', '700']],
                'body' => ['family' => 'Inter', 'weights' => ['400', '500', '600']],
                'mono' => ['family' => 'ui-monospace', 'weights' => ['400', '500']],
            ],
            'logo_variants' => $kit->logo_variants ?: [
                'dark' => null,
                'light' => null,
                'transparent' => null,
                'icon' => null,
            ],
            'watermark' => $kit->watermark ?: [
                'path' => null,
                'position' => 'bottom-right',
                'opacity' => 0.72,
                'scale' => 0.18,
            ],
            'voice_sq' => $kit->voice_sq,
            'voice_en' => $kit->voice_en,
            'caption_templates' => $kit->caption_templates ?: [
                'hook_patterns' => [],
                'cta_patterns' => [],
            ],
            'default_hashtags' => $kit->default_hashtags ?: [],
            'music_library' => $kit->music_library ?: [],
            'aspect_defaults' => $kit->aspect_defaults ?: [
                ['post_type' => 'photo', 'aspect' => '4:5'],
                ['post_type' => 'carousel', 'aspect' => '4:5'],
                ['post_type' => 'story', 'aspect' => '9:16'],
                ['post_type' => 'reel', 'aspect' => '9:16'],
                ['post_type' => 'video', 'aspect' => '16:9'],
            ],
            'updated_by' => $kit->updated_by,
            'updated_at' => $kit->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeAssets(): array
    {
        return Asset::query()
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (Asset $asset) => $this->serializeAsset($asset))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAsset(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'kind' => $asset->kind,
            'name' => $asset->name,
            'path' => $asset->path,
            'url' => Storage::disk('public')->url($asset->path),
            'mime_type' => $asset->mime_type,
            'duration_seconds' => $asset->duration_seconds,
            'metadata' => $asset->metadata ?: [],
            'uploaded_by' => $asset->uploaded_by,
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }
}
