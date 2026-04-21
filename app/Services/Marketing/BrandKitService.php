<?php

namespace App\Services\Marketing;

use App\Models\Marketing\BrandKit;
use Illuminate\Support\Facades\Cache;

/**
 * Canonical read path for the singleton brand kit.
 *
 * The brand kit is hit on nearly every Studio render (Polotno palette,
 * Remotion composition props, Claude prompt building), so the 60-second
 * Redis cache keeps MySQL out of the hot path.
 */
class BrandKitService
{
    private const CACHE_KEY = 'marketing.brand_kit.v1';
    private const CACHE_TTL = 60;

    public function get(): BrandKit
    {
        // Cache the array representation (not the Eloquent object). Serialised
        // Eloquent instances become __PHP_Incomplete_Class whenever two
        // php-fpm workers see different class shapes — one populates the
        // cache under the old class, the next reads under the new class.
        // The array round-trip is immune: no class reference in the payload.
        $data = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => BrandKit::query()->firstOrCreate([])->toArray(),
        );

        $brandKit = new BrandKit();
        $brandKit->exists = true;
        $brandKit->forceFill($data);

        return $brandKit;
    }

    public function update(array $attributes, ?int $userId = null): BrandKit
    {
        $kit = BrandKit::query()->firstOrCreate([]);

        if ($userId !== null) {
            $attributes['updated_by'] = $userId;
        }

        $kit->fill($attributes)->save();

        $this->forget();

        return $kit->refresh();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
