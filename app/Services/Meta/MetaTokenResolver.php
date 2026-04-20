<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaToken;
use Illuminate\Encryption\Encrypter;
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
     * Token sources, in priority order:
     *   1. meta_tokens (OAuth flow) — plaintext access_token, our native format
     *   2. hrms_meta_credentials (HRMS-managed) — Crypt::encryptString'd with HRMS's APP_KEY
     *
     * The HRMS fallback exists because the HRMS app seeded this table before
     * the Marketing OAuth flow existed; a production instance may still rely
     * on HRMS as the single source of truth for the Meta page token.
     *
     * @return array{page_token:?string,page_id:?string,ig_account_id:?string,user_token:?string}|null
     */
    private static function loadFromDb(): ?array
    {
        if (! self::disConnectionReady()) {
            return null;
        }

        $fromMetaTokens = self::loadFromMetaTokens();
        if ($fromMetaTokens) {
            return $fromMetaTokens;
        }

        return self::loadFromHrmsCredentials();
    }

    /**
     * @return array{page_token:?string,page_id:?string,ig_account_id:?string,user_token:?string}|null
     */
    private static function loadFromMetaTokens(): ?array
    {
        // Page token: most recent active page token. Page tokens derived from
        // a long-lived user token never expire — we still order by id desc to
        // grab the freshest (most recently refreshed) one.
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
            'page_token'    => $pageToken?->access_token,
            'page_id'       => $pageToken?->page_id,
            'ig_account_id' => $pageToken?->ig_account_id,
            'user_token'    => $userToken?->access_token,
        ];
    }

    /**
     * HRMS stores one active credential row with the page access token
     * encrypted via Laravel's Crypt facade (HRMS's APP_KEY, AES-256-CBC).
     *
     * We decrypt with an Encrypter instantiated from config('meta.hrms_credentials_key'),
     * which the operator sets to HRMS's APP_KEY in this app's .env. Falling
     * back to the local APP_KEY only works when the two apps share a key.
     *
     * @return array{page_token:?string,page_id:?string,ig_account_id:?string,user_token:?string}|null
     */
    private static function loadFromHrmsCredentials(): ?array
    {
        if (! self::disConnectionReady('hrms_meta_credentials')) {
            return null;
        }

        $row = DB::connection('dis')
            ->table('hrms_meta_credentials')
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('token_expires_at')->orWhere('token_expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        if (! $row || empty($row->access_token)) {
            return null;
        }

        $decrypted = self::decryptHrmsToken($row->access_token);
        if (! $decrypted) {
            return null;
        }

        return [
            'page_token'    => $decrypted,
            'page_id'       => $row->page_id ?? null,
            'ig_account_id' => $row->instagram_id ?? null,
            'user_token'    => null,
        ];
    }

    private static function decryptHrmsToken(string $payload): ?string
    {
        $rawKey = (string) (config('meta.hrms_credentials_key') ?: config('app.key'));
        if ($rawKey === '') {
            return null;
        }

        // Laravel keys are stored base64-prefixed (base64:...); strip + decode.
        $binaryKey = str_starts_with($rawKey, 'base64:')
            ? base64_decode(substr($rawKey, 7))
            : $rawKey;

        try {
            $encrypter = new Encrypter($binaryKey, (string) config('app.cipher', 'AES-256-CBC'));
            return $encrypter->decryptString($payload);
        } catch (Throwable $e) {
            // Wrong key, corrupted payload, or plaintext row — skip silently
            // so the resolver doesn't break boot.
            return null;
        }
    }

    private static function disConnectionReady(string $table = 'meta_tokens'): bool
    {
        try {
            return DB::connection('dis')->getSchemaBuilder()->hasTable($table);
        } catch (Throwable $e) {
            return false;
        }
    }
}
