<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Meta\MetaToken;
use App\Services\Meta\MetaTokenResolver;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaAuthController extends Controller
{
    private string $baseUrl;
    private string $apiVersion;
    private string $appId;
    private string $appSecret;

    public function __construct()
    {
        $this->baseUrl = config('meta.base_url', 'https://graph.facebook.com');
        $this->apiVersion = config('meta.api_version', 'v24.0');
        $this->appId = (string) config('meta.app_id', '');
        $this->appSecret = (string) config('meta.app_secret', '');
    }

    /**
     * Show the Meta Auth management page with current token status.
     */
    public function index()
    {
        $tokens = MetaToken::active()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'type' => $token->token_type,
                'page_id' => $token->page_id,
                'ig_account_id' => $token->ig_account_id,
                'expires_at' => $token->expires_at?->format('Y-m-d H:i'),
                'is_expired' => $token->isExpired(),
                'expires_soon' => $token->expiresWithinDays(7),
                'last_used' => $token->last_used_at?->diffForHumans(),
            ];
        });

        return view('meta-marketing.auth', [
            'tokens' => $tokens,
            'appId' => $this->appId,
        ]);
    }

    /**
     * Step 1: Redirect to Meta OAuth dialog.
     */
    public function login(Request $request)
    {
        if (empty($this->appId) || empty($this->appSecret)) {
            return back()->with('error', 'META_APP_ID and META_APP_SECRET must be set in .env');
        }

        $state = Str::random(40);
        session(['meta_oauth_state' => $state]);

        $scopes = implode(',', config('meta.oauth.scopes', []));
        $redirectUri = $this->getRedirectUri();

        $authUrl = "https://www.facebook.com/{$this->apiVersion}/dialog/oauth?" . http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scopes,
            'response_type' => 'code',
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * Step 2: Handle OAuth callback - exchange code for tokens.
     */
    public function callback(Request $request)
    {
        // Verify state
        $storedState = session('meta_oauth_state');
        if (!$storedState || $storedState !== $request->get('state')) {
            return redirect()->route('management.meta-auth.index')->with('error', 'Invalid OAuth state. Please try again.');
        }
        session()->forget('meta_oauth_state');

        // Check for errors
        if ($request->has('error')) {
            $error = $request->get('error_description', $request->get('error'));
            return redirect()->route('management.meta-auth.index')->with('error', "Meta OAuth denied: {$error}");
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('management.meta-auth.index')->with('error', 'No authorization code received.');
        }

        try {
            // Exchange code for short-lived token
            $shortLivedToken = $this->exchangeCodeForToken($code);

            // Exchange short-lived for long-lived token (60 days)
            $longLivedData = $this->exchangeForLongLivedToken($shortLivedToken);

            // Save long-lived user token
            $userToken = MetaToken::create([
                'name' => 'Meta User Token (OAuth)',
                'token_type' => 'long_lived_user',
                'access_token' => $longLivedData['access_token'],
                'scopes' => config('meta.oauth.scopes'),
                'expires_at' => now()->addSeconds($longLivedData['expires_in'] ?? 5184000),
            ]);

            // Fetch user info
            $meInfo = $this->graphGet('me', ['fields' => 'id,name'], $longLivedData['access_token']);
            $userToken->update(['meta_user_id' => $meInfo['id'] ?? null, 'name' => "User: " . ($meInfo['name'] ?? 'Unknown')]);

            // Fetch pages and page tokens
            $pageResults = $this->fetchAndStorePageTokens($longLivedData['access_token']);

            // Drop the resolver cache so sync calls from this request onward
            // see the freshly-saved tokens without waiting for the 5-min TTL.
            MetaTokenResolver::forgetCache();

            $pageCount = count($pageResults);
            $igCount = collect($pageResults)->filter(fn($p) => !empty($p['ig_id']))->count();

            return redirect()->route('management.meta-auth.index')->with('success',
                "OAuth success! Saved {$pageCount} page token(s) and found {$igCount} Instagram account(s)."
            );
        } catch (Exception $e) {
            Log::error('Meta OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('management.meta-auth.index')->with('error', 'OAuth failed: ' . $e->getMessage());
        }
    }

    /**
     * Manually refresh a specific token.
     */
    public function refresh(Request $request, MetaToken $token)
    {
        try {
            if ($token->token_type === 'long_lived_user') {
                $newData = $this->exchangeForLongLivedToken($token->getDecryptedToken());
                $token->update([
                    'access_token' => $newData['access_token'],
                    'expires_at' => now()->addSeconds($newData['expires_in'] ?? 5184000),
                ]);

                // Also refresh page tokens derived from this user token
                $this->fetchAndStorePageTokens($newData['access_token']);

                return back()->with('success', 'Token refreshed successfully. New expiry: ' . $token->fresh()->expires_at->format('Y-m-d H:i'));
            }

            return back()->with('error', 'Only long-lived user tokens can be refreshed.');
        } catch (Exception $e) {
            Log::error('Meta token refresh failed: ' . $e->getMessage());
            return back()->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Revoke/deactivate a token.
     */
    public function revoke(Request $request, MetaToken $token)
    {
        $token->update(['is_active' => false]);
        return back()->with('success', "Token '{$token->name}' deactivated.");
    }

    /**
     * Exchange authorization code for short-lived access token.
     */
    private function exchangeCodeForToken(string $code): string
    {
        $response = Http::get("{$this->baseUrl}/{$this->apiVersion}/oauth/access_token", [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->getRedirectUri(),
            'code' => $code,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new Exception("Failed to exchange code: {$error}");
        }

        return $response->json('access_token');
    }

    /**
     * Exchange short-lived token for long-lived token (60 days).
     */
    private function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get("{$this->baseUrl}/{$this->apiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new Exception("Failed to get long-lived token: {$error}");
        }

        return $response->json();
    }

    /**
     * Fetch user's pages and store page tokens.
     */
    private function fetchAndStorePageTokens(string $userToken): array
    {
        $pages = $this->graphGet('me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,followers_count}',
        ], $userToken);

        $results = [];

        foreach ($pages['data'] ?? [] as $page) {
            $pageId = $page['id'];
            $igAccount = $page['instagram_business_account'] ?? null;

            // Deactivate old page tokens for this page
            MetaToken::where('page_id', $pageId)
                ->where('token_type', 'page')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Store new page token (page tokens from long-lived user tokens don't expire)
            MetaToken::create([
                'name' => "Page: {$page['name']}",
                'token_type' => 'page',
                'access_token' => $page['access_token'],
                'page_id' => $pageId,
                'ig_account_id' => $igAccount['id'] ?? null,
                'scopes' => config('meta.oauth.scopes'),
                'expires_at' => null, // Page tokens from long-lived user tokens don't expire
            ]);

            $results[] = [
                'page_id' => $pageId,
                'page_name' => $page['name'],
                'ig_id' => $igAccount['id'] ?? null,
                'ig_username' => $igAccount['username'] ?? null,
            ];

            Log::info("Stored page token for: {$page['name']} (Page: {$pageId}, IG: " . ($igAccount['id'] ?? 'none') . ")");
        }

        return $results;
    }

    /**
     * Helper: Make a Graph API GET request.
     */
    private function graphGet(string $endpoint, array $params, string $token): array
    {
        $params['access_token'] = $token;
        $response = Http::get("{$this->baseUrl}/{$this->apiVersion}/{$endpoint}", $params);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new Exception("Graph API error on /{$endpoint}: {$error}");
        }

        return $response->json();
    }

    /**
     * Build the full redirect URI.
     */
    private function getRedirectUri(): string
    {
        $uri = config('meta.oauth.redirect_uri');

        // If it's a relative path, prepend base URL
        if (str_starts_with($uri, '/')) {
            return rtrim(config('app.url'), '/') . $uri;
        }

        return $uri;
    }
}
