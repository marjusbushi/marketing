<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caches Meta Marketing dashboard API responses.
 *
 * Cache strategy:
 * - Historical date ranges (to < today): cached 7 days (data won't change)
 * - Current date ranges (to = today):    cached until midnight (re-cached next day)
 * - Sync endpoint is never cached
 *
 * Cache invalidation:
 * - After Meta sync: bump `meta_cache_version` → all old keys become stale
 * - Old keys expire naturally via TTL
 */
class MetaMarketingCache
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET API requests (not page views, not sync)
        if (!$request->isMethod('GET') || str_contains($request->path(), '/api/sync')) {
            return $next($request);
        }

        $to = $request->get('to');
        if (!$to) {
            return $next($request);
        }

        // nocache=1 → skip reading cache, but still write the fresh result back
        $noCache = $request->boolean('nocache');

        $version = (int) Cache::get('meta_cache_version', 0);
        $params = $request->query();
        unset($params['nocache']); // exclude from cache key so cached/non-cached share the same key
        ksort($params);
        $key = "meta_v{$version}:" . md5($request->path() . '|' . json_encode($params));

        // Determine TTL
        $toDate = Carbon::parse($to)->startOfDay();
        $today = Carbon::today();

        if ($toDate->gte($today)) {
            // Current period — cache until midnight (min 10 minutes)
            $ttl = max(600, (int) now()->diffInSeconds($today->copy()->endOfDay()));
        } else {
            // Historical data — cache for 7 days
            $ttl = 7 * 24 * 3600;
        }

        // Try cache hit (skip if nocache requested)
        $cached = $noCache ? null : Cache::get($key);
        if ($cached !== null) {
            return response()->json($cached)
                ->header('X-Meta-Cache', 'HIT')
                ->header('X-Meta-Cache-Key', substr($key, 0, 60));
        }

        // Cache miss — execute the request
        $response = $next($request);

        // Only cache successful JSON responses
        if ($response->getStatusCode() === 200) {
            try {
                $data = json_decode($response->getContent(), true);
                if ($data !== null) {
                    Cache::put($key, $data, $ttl);
                }
            } catch (\Throwable $e) {
                Log::debug('Meta cache write failed: ' . $e->getMessage());
            }
        }

        return $response->header('X-Meta-Cache', 'MISS');
    }

    /**
     * Bust all Meta Marketing dashboard caches by bumping the version.
     * Called after sync completes.
     */
    public static function bustCache(): void
    {
        // Use timestamp so version is always unique — even after Cache::flush()
        // (increment on a flushed key returns 1, colliding with the default)
        $version = (int) round(microtime(true) * 1000);
        Cache::put('meta_cache_version', $version, 86400 * 30);
        Log::info("Meta Marketing cache busted (version set to {$version})");
    }
}
