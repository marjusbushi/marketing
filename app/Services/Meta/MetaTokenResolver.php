<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bridges DB-stored Meta tokens (meta_tokens table on DIS) into config('meta.*').
 *
 * Why: OAuth callback writes tokens to the DB, but every sync service reads from
 * config() (which is hydrated from .env). Without this bridge tokens saved via
 * OAuth are invisible to the sync layer and /api/posts/sync-meta returns 0.
 *
 * Runs once at boot. .env values win if set — the bridge only fills blanks so
 * manual overrides (local dev, emergency swap) still work.
 */
class MetaTokenResolver
{
    private const CACHE_KEY = 'meta:active_token_bridge:v1';
    private const CACHE_TTL_SECONDS = 300;

    public static function hydrateConfig(): void
    {
        try {
            $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return self::loadFromDb();
            });
        } catch (Throwable $e) {
            // DB down or meta_tokens table missing (fresh install) — don't break boot.
            return;
        }

        if (! $data) {
            return;
        }

        // .env wins. Only fill when blank — preserves manual overrides.
        if (! Config::get('meta.page_token') && ! empty($data['page_token'])) {
            Config::set('meta.page_token', $data['page_token']);
        }
        if (! Config::get('meta.page_id') && ! empty($data['page_id'])) {
            Config::set('meta.page_id', $data['page_id']);
        }
        if (! Config::get('meta.ig_account_id') && ! empty($data['ig_account_id'])) {
            Config::set('meta.ig_account_id', $data['ig_account_id']);
        }
        if (! Config::get('meta.token') && ! empty($data['user_token'])) {
            Config::set('meta.token', $data['user_token']);
        }
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{page_token:?string,page_id:?string,ig_account_id:?string,user_token:?string}|null
     */
    private static function loadFromDb(): ?array
    {
        // Safety: if DIS connection isn't configured (e.g. running artisan before
        // migrations), bail instead of throwing mid-boot.
        if (! self::disConnectionReady()) {
            return null;
        }

        // Page token: most recent active page token that still works.
        // Unlike long-lived user tokens, page tokens derived from a long-lived
        // user token never expire — but we order by id desc to grab the freshest.
        $pageToken = MetaToken::active()
            ->where('token_type', 'page')
            ->whereNotNull('page_id')
            ->orderByDesc('id')
            ->first();

        // Long-lived user token (for app-level calls not scoped to a page).
        $userToken = MetaToken::active()
            ->where('token_type', 'long_lived_user')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        if (! $pageToken && ! $userToken) {
            return null;
        }

        return [
            'page_token'     => $pageToken?->access_token,
            'page_id'        => $pageToken?->page_id,
            'ig_account_id'  => $pageToken?->ig_account_id,
            'user_token'     => $userToken?->access_token,
        ];
    }

    private static function disConnectionReady(): bool
    {
        try {
            return DB::connection('dis')->getSchemaBuilder()->hasTable('meta_tokens');
        } catch (Throwable $e) {
            return false;
        }
    }
}
