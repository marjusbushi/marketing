<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\TikTok\TikTokToken;
use App\Services\Tiktok\TiktokAdsApiService;
use App\Services\Tiktok\TiktokApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TiktokAuthController extends Controller
{
    /**
     * Show the TikTok Auth management page with current token status.
     */
    public function index()
    {
        $tokens = TikTokToken::active()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'token_type' => $token->token_type ?? 'organic',
                'open_id' => $token->open_id,
                'advertiser_id' => $token->advertiser_id,
                'access_expires_at' => $token->access_token_expires_at?->format('Y-m-d H:i'),
                'refresh_expires_at' => $token->refresh_token_expires_at?->format('Y-m-d H:i'),
                'is_access_expired' => $token->isAccessTokenExpired(),
                'is_refresh_expired' => $token->isRefreshTokenExpired(),
                'last_used' => $token->last_used_at?->diffForHumans(),
            ];
        });

        return view('tiktok-marketing.auth', [
            'tokens' => $tokens,
            'clientKey' => config('tiktok.client_key'),
            'appId' => config('tiktok.app_id'),
        ]);
    }

    /**
     * Step 1: Redirect to TikTok OAuth dialog.
     */
    public function login()
    {
        $clientKey = config('tiktok.client_key');
        $clientSecret = config('tiktok.client_secret');

        if (empty($clientKey) || empty($clientSecret)) {
            return back()->with('error', 'TIKTOK_CLIENT_KEY and TIKTOK_CLIENT_SECRET must be set in .env');
        }

        $state = Str::random(40);
        session(['tiktok_oauth_state' => $state]);

        $scopes = implode(',', config('tiktok.oauth.organic_scopes', []));
        $redirectUri = $this->getRedirectUri();

        $authUrl = config('tiktok.oauth.organic_auth_url') . '?' . http_build_query([
            'client_key' => $clientKey,
            'response_type' => 'code',
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * Step 2: Handle OAuth callback - exchange code for tokens.
     */
    public function callback(Request $request)
    {
        // Verify state
        $storedState = session('tiktok_oauth_state');
        if (!$storedState || $storedState !== $request->get('state')) {
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }
        session()->forget('tiktok_oauth_state');

        // Check for errors
        if ($request->has('error')) {
            $error = $request->get('error_description', $request->get('error'));
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', "TikTok OAuth denied: {$error}");
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', 'No authorization code received.');
        }

        try {
            $api = app(TiktokApiService::class);
            $data = $api->exchangeCodeForToken($code);

            // Deactivate old tokens
            TikTokToken::where('is_active', true)->update(['is_active' => false]);

            // Save new token
            $token = TikTokToken::create([
                'name' => 'TikTok Account (OAuth)',
                'open_id' => $data['open_id'] ?? '',
                'union_id' => $data['union_id'] ?? null,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'scopes' => $data['scope'] ?? config('tiktok.oauth.organic_scopes'),
                'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 86400),
                'refresh_token_expires_at' => now()->addSeconds($data['refresh_expires_in'] ?? 86400 * 365),
            ]);

            // Try to fetch user info to update token name
            try {
                $api->setToken($token);
                $userInfo = $api->getUserInfo(['display_name', 'username']);
                $userName = $userInfo['data']['user']['display_name']
                    ?? $userInfo['data']['user']['username']
                    ?? 'Unknown';
                $token->update(['name' => "TikTok: {$userName}"]);
            } catch (Exception $e) {
                Log::warning('Could not fetch TikTok user info after auth: ' . $e->getMessage());
            }

            return redirect()->route('management.tiktok-auth.index')
                ->with('success', 'TikTok OAuth success! Token saved.');
        } catch (Exception $e) {
            Log::error('TikTok OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', 'OAuth failed: ' . $e->getMessage());
        }
    }

    /**
     * Refresh access token.
     */
    public function refresh(TikTokToken $token)
    {
        try {
            $api = app(TiktokApiService::class);
            $api->refreshAccessToken($token);

            return back()->with('success', 'Token refreshed. New expiry: ' . $token->fresh()->access_token_expires_at->format('Y-m-d H:i'));
        } catch (Exception $e) {
            Log::error('TikTok token refresh failed: ' . $e->getMessage());
            return back()->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Revoke/deactivate a token.
     */
    public function revoke(TikTokToken $token)
    {
        $token->update(['is_active' => false]);
        return back()->with('success', "Token '{$token->name}' deactivated.");
    }

    // ─── Marketing API OAuth ─────────────────────────────

    /**
     * Step 1: Redirect to TikTok Marketing API OAuth.
     */
    public function loginMarketing()
    {
        $appId = config('tiktok.app_id');
        $appSecret = config('tiktok.app_secret');

        if (empty($appId) || empty($appSecret)) {
            return back()->with('error', 'TIKTOK_APP_ID and TIKTOK_APP_SECRET must be set in .env');
        }

        $state = Str::random(40);
        session(['tiktok_marketing_oauth_state' => $state]);

        $redirectUri = $this->getRedirectUri('marketing');

        $authUrl = config('tiktok.oauth.marketing_auth_url') . '?' . http_build_query([
            'app_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * Step 2: Handle Marketing API OAuth callback.
     */
    public function callbackMarketing(Request $request)
    {
        $storedState = session('tiktok_marketing_oauth_state');
        if (! $storedState || $storedState !== $request->get('state')) {
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', 'Invalid Marketing OAuth state. Please try again.');
        }
        session()->forget('tiktok_marketing_oauth_state');

        $authCode = $request->get('auth_code');
        if (! $authCode) {
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', 'No authorization code received from TikTok Marketing API.');
        }

        try {
            $adsApi = app(TiktokAdsApiService::class);
            $data = $adsApi->exchangeCodeForToken($authCode);

            // Deactivate old marketing tokens
            TikTokToken::marketing()->where('is_active', true)->update(['is_active' => false]);

            $advertiserIds = $data['advertiser_ids'] ?? [];
            $advertiserId = $advertiserIds[0] ?? config('tiktok.advertiser_id');

            $token = TikTokToken::create([
                'name' => 'TikTok Ads (Marketing API)',
                'token_type' => 'marketing',
                'open_id' => '',
                'advertiser_id' => $advertiserId,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? '',
                'scopes' => $data['scope'] ?? [],
                'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 86400),
                'refresh_token_expires_at' => now()->addDays(365),
            ]);

            return redirect()->route('management.tiktok-auth.index')
                ->with('success', "TikTok Marketing API connected! Advertiser ID: {$advertiserId}");
        } catch (Exception $e) {
            Log::error('TikTok Marketing OAuth failed: ' . $e->getMessage());
            return redirect()->route('management.tiktok-auth.index')
                ->with('error', 'Marketing OAuth failed: ' . $e->getMessage());
        }
    }

    private function getRedirectUri(string $type = 'organic'): string
    {
        if ($type === 'marketing') {
            $uri = '/management/tiktok-auth/callback-marketing';
        } else {
            $uri = config('tiktok.oauth.redirect_uri');
        }

        if (str_starts_with($uri, '/')) {
            return rtrim(config('app.url'), '/') . $uri;
        }

        return $uri;
    }
}
