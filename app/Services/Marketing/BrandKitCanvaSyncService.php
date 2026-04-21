<?php

namespace App\Services\Marketing;

use App\Models\Marketing\BrandKit;
use App\Models\Marketing\CanvaConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One-way sync: push the marketing app's BrandKit into a user's Canva
 * brand kit so their "Open in Canva" sessions start with our colors,
 * typography, and logo assets pre-loaded.
 *
 * The marketing app is source of truth — this service never pulls the
 * Canva brand kit back. To revert, the user re-runs the sync.
 *
 * Implementation notes:
 *   • Canva's brand-kit endpoints are colour palette + fonts + logos.
 *     This service maps our BrandKit JSON into Canva's expected shape.
 *   • Logo uploads go through Canva's /asset-uploads endpoint. Private
 *     files on our side are streamed via Storage::readStream() so we
 *     never expose a public URL.
 *   • Failures on individual assets are collected and returned — the
 *     caller decides whether to surface a partial failure to the UI.
 */
class BrandKitCanvaSyncService
{
    public function __construct(protected CanvaConnectService $canva)
    {
    }

    /**
     * Push the given brand kit into the connection's Canva account.
     *
     * @return array{colors:int, logos:int, errors:array<int,string>}
     */
    public function pushBrandKit(CanvaConnection $connection, ?BrandKit $brandKit): array
    {
        if ($brandKit === null) {
            throw new RuntimeException('BrandKit row does not exist — nothing to sync.');
        }

        $accessToken = $this->canva->getValidAccessToken($connection);

        $errors = [];

        $colorsSynced = $this->syncColorPalette($accessToken, $brandKit->colors ?? [], $errors);
        $logosSynced  = $this->syncLogos($accessToken, $brandKit->logo_variants ?? [], $errors);

        return [
            'colors' => $colorsSynced,
            'logos'  => $logosSynced,
            'errors' => $errors,
        ];
    }

    /**
     * Publish each color in the brand kit as a named palette entry in Canva.
     */
    protected function syncColorPalette(string $accessToken, array $colors, array &$errors): int
    {
        if (empty($colors)) {
            return 0;
        }

        $palette = [];
        foreach ($colors as $name => $hex) {
            if (!is_string($hex)) continue;
            $palette[] = ['name' => (string) $name, 'color' => $hex];
        }

        if (empty($palette)) {
            return 0;
        }

        $response = Http::baseUrl((string) config('canva.base_url'))
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post('/brand/palettes', [
                'name'   => 'Zero Absolute (synced)',
                'colors' => $palette,
            ]);

        if ($response->failed()) {
            $errors[] = 'Color palette sync failed: ' . $response->status() . ' ' . $response->body();
            Log::warning('Canva palette sync failed', ['status' => $response->status(), 'body' => $response->body()]);
            return 0;
        }

        return count($palette);
    }

    /**
     * Upload each logo variant as an asset in Canva (kept in the user's
     * library — Canva brand-kit logos pull from uploaded assets).
     */
    protected function syncLogos(string $accessToken, array $logoVariants, array &$errors): int
    {
        $count = 0;

        foreach ($logoVariants as $variant => $path) {
            if (!is_string($path) || $path === '') continue;

            if (!Storage::disk('public')->exists($path)) {
                $errors[] = "Logo `{$variant}` not found at {$path}";
                continue;
            }

            $stream = Storage::disk('public')->readStream($path);
            if ($stream === null || $stream === false) {
                $errors[] = "Logo `{$variant}` could not be opened at {$path}";
                continue;
            }

            $response = Http::baseUrl((string) config('canva.base_url'))
                ->withToken($accessToken)
                ->withHeaders([
                    'Asset-Upload-Metadata' => json_encode([
                        'name_base64' => base64_encode("ZA Logo {$variant}"),
                    ]),
                    'Content-Type' => 'application/octet-stream',
                ])
                ->timeout(30)
                ->withBody(stream_get_contents($stream), 'application/octet-stream')
                ->post('/asset-uploads');

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($response->failed()) {
                $errors[] = "Logo `{$variant}` upload failed: " . $response->status();
                Log::warning('Canva asset upload failed', [
                    'variant' => $variant,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                continue;
            }

            $count++;
        }

        return $count;
    }
}
