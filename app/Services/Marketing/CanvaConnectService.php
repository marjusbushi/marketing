<?php

namespace App\Services\Marketing;

use App\Models\Marketing\CanvaConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin client for Canva Connect API.
 *
 * Responsibilities:
 *   • Build the authorize URL with PKCE (code_challenge = S256(code_verifier))
 *   • Exchange authorization code for access_token + refresh_token
 *   • Refresh the access token near expiry
 *   • Revoke a token on disconnect
 *   • Create a design from a brand template (autofill with brand kit values)
 *   • Read a design's metadata
 *   • Start an export job and poll for completion
 *
 * HTTP transport is injected via the Http facade so Http::fake() works in
 * feature tests. All methods that hit Canva will throw a RuntimeException
 * on a non-2xx response — callers decide how to surface that.
 */
class CanvaConnectService
{
    /**
     * Build the OAuth authorize URL and the PKCE code_verifier the caller
     * must persist in the session under the given `state` key.
     *
     * @return array{url: string, state: string, code_verifier: string}
     */
    public function authorizeUrl(): array
    {
        $state         = Str::random(40);
        $codeVerifier  = $this->generateCodeVerifier();
        $codeChallenge = $this->deriveCodeChallenge($codeVerifier);

        $params = [
            'client_id'             => $this->clientId(),
            'redirect_uri'          => $this->redirectUri(),
            'response_type'         => 'code',
            'scope'                 => implode(' ', config('canva.oauth.scopes', [])),
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        $url = rtrim(config('canva.auth_url'), '?') . '?' . http_build_query($params);

        return [
            'url'           => $url,
            'state'         => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    /**
     * Exchange an authorization code for access_token + refresh_token.
     *
     * @return array{access_token:string, refresh_token:string, expires_in:int, scope?:string, token_type?:string}
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $response = $this->tokenRequest()->asForm()->post(config('canva.token_url'), [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
        ]);

        return $this->decodeTokenResponse($response, 'exchange authorization code');
    }

    /**
     * Refresh an access token using the stored refresh_token.
     *
     * @return array{access_token:string, refresh_token:string, expires_in:int, scope?:string, token_type?:string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->tokenRequest()->asForm()->post(config('canva.token_url'), [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        return $this->decodeTokenResponse($response, 'refresh access token');
    }

    /**
     * Revoke a token (access or refresh). Canva's revoke endpoint returns
     * 200 with an empty body on success.
     */
    public function revokeToken(string $token): void
    {
        $response = $this->tokenRequest()->asForm()->post(config('canva.revoke_url'), [
            'token' => $token,
        ]);

        if ($response->failed()) {
            // Revocation failures are non-fatal — we still deactivate locally.
            Log::warning('Canva token revoke returned non-2xx', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }
    }

    /**
     * Fetch the authenticated user's profile. Used to populate canva_user_id
     * and canva_display_name on the connection row.
     *
     * @return array{user_id:string, display_name?:string}
     */
    public function getCurrentUser(string $accessToken): array
    {
        $response = $this->apiRequest($accessToken)->get('/users/me');
        $this->throwIfFailed($response, 'fetch Canva profile');

        $payload = $response->json();

        return [
            'user_id'      => $payload['user']['id'] ?? $payload['id'] ?? 'unknown',
            'display_name' => $payload['user']['display_name'] ?? $payload['display_name'] ?? null,
        ];
    }

    /**
     * Autofill a brand template with the given field values, creating a new
     * design owned by the authenticated user. `fields` is an array keyed by
     * the template's field names (defined inside Canva).
     *
     * @param  array<string, mixed>  $fields
     * @return array  Canva's raw JSON payload (contains design id + edit_url).
     */
    public function createDesignFromBrandTemplate(string $accessToken, string $brandTemplateId, array $fields = []): array
    {
        $response = $this->apiRequest($accessToken)->post(
            "/brand-templates/{$brandTemplateId}/autofills",
            ['data' => $fields]
        );

        $this->throwIfFailed($response, 'autofill brand template');

        return $response->json();
    }

    /**
     * Read a design's metadata (status, title, thumbnail, edit url).
     */
    public function getDesign(string $accessToken, string $designId): array
    {
        $response = $this->apiRequest($accessToken)->get("/designs/{$designId}");
        $this->throwIfFailed($response, 'get design');

        return $response->json();
    }

    /**
     * Start an export job for a design. Returns Canva's job payload — the
     * caller polls `getExportJob()` until status is `success` and the
     * `urls` array is populated.
     */
    public function startExport(string $accessToken, string $designId, string $format = 'png'): array
    {
        $allowed = config('canva.export.allowed_formats', ['png', 'jpg', 'pdf']);
        if (!in_array($format, $allowed, true)) {
            throw new RuntimeException("Unsupported Canva export format: {$format}");
        }

        $response = $this->apiRequest($accessToken)->post('/exports', [
            'design_id' => $designId,
            'format'    => [
                'type' => $format,
            ],
        ]);

        $this->throwIfFailed($response, 'start export');

        return $response->json();
    }

    /**
     * Poll an export job. When `status = success`, `urls` will contain the
     * signed download URLs for each exported asset.
     */
    public function getExportJob(string $accessToken, string $jobId): array
    {
        $response = $this->apiRequest($accessToken)->get("/exports/{$jobId}");
        $this->throwIfFailed($response, 'get export job');

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────

    protected function clientId(): string
    {
        $id = (string) config('canva.client_id', '');
        if ($id === '') {
            throw new RuntimeException('CANVA_CLIENT_ID is not configured.');
        }
        return $id;
    }

    protected function clientSecret(): string
    {
        $secret = (string) config('canva.client_secret', '');
        if ($secret === '') {
            throw new RuntimeException('CANVA_CLIENT_SECRET is not configured.');
        }
        return $secret;
    }

    protected function redirectUri(): string
    {
        $uri = (string) config('canva.oauth.redirect_uri');

        if (str_starts_with($uri, '/')) {
            return rtrim((string) config('app.url'), '/') . $uri;
        }

        return $uri;
    }

    protected function tokenRequest(): PendingRequest
    {
        // Canva's token endpoint accepts HTTP Basic auth with client creds.
        return Http::withBasicAuth($this->clientId(), $this->clientSecret())
            ->acceptJson()
            ->timeout(15)
            ->retry(
                (int) config('canva.max_retries', 3),
                fn (int $attempt) => ((array) config('canva.retry_delay_seconds', [1, 4, 10]))[$attempt - 1] ?? 10,
                fn ($exception, $request) => true, // retry on connection errors
                throw: false
            );
    }

    protected function apiRequest(string $accessToken): PendingRequest
    {
        return Http::baseUrl((string) config('canva.base_url'))
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(
                (int) config('canva.max_retries', 3),
                fn (int $attempt) => ((array) config('canva.retry_delay_seconds', [1, 4, 10]))[$attempt - 1] ?? 10,
                fn ($exception, $request) => true,
                throw: false
            );
    }

    protected function decodeTokenResponse(Response $response, string $operation): array
    {
        $this->throwIfFailed($response, $operation);

        $payload = $response->json();

        foreach (['access_token', 'refresh_token', 'expires_in'] as $required) {
            if (!array_key_exists($required, $payload)) {
                throw new RuntimeException("Canva token response missing `{$required}` ({$operation})");
            }
        }

        return $payload;
    }

    protected function throwIfFailed(Response $response, string $operation): void
    {
        if (!$response->failed()) {
            return;
        }

        $error = $response->json('message')
            ?? $response->json('error_description')
            ?? $response->json('error')
            ?? 'Unknown error';

        Log::warning('Canva API call failed', [
            'operation' => $operation,
            'status'    => $response->status(),
            'body'      => $response->body(),
        ]);

        throw new RuntimeException("Canva API failed to {$operation}: {$error} (HTTP {$response->status()})");
    }

    // ─── PKCE helpers ─────────────────────────────────────────────

    protected function generateCodeVerifier(): string
    {
        // RFC 7636 allows 43-128 chars from the unreserved set.
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    protected function deriveCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    // ─── Connection helpers ───────────────────────────────────────

    /**
     * Persist (or update) a connection row from a freshly issued token payload.
     */
    public function storeConnection(int $userId, array $tokenPayload, array $profile = []): CanvaConnection
    {
        $scopeString = $tokenPayload['scope'] ?? '';
        $scopes = $scopeString === '' ? null : preg_split('/\s+/', trim($scopeString));

        return CanvaConnection::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token'       => $tokenPayload['access_token'],
                'refresh_token'      => $tokenPayload['refresh_token'],
                'scopes'             => $scopes,
                'expires_at'         => now()->addSeconds((int) $tokenPayload['expires_in']),
                'canva_user_id'      => $profile['user_id'] ?? null,
                'canva_display_name' => $profile['display_name'] ?? null,
                'connected_at'       => now(),
                'is_active'          => true,
            ],
        );
    }

    /**
     * Return a usable access token for the given connection, refreshing
     * transparently if it has expired or is close to expiring.
     */
    public function getValidAccessToken(CanvaConnection $connection): string
    {
        if (!$connection->isExpired() && !$connection->expiresSoon()) {
            $connection->forceFill(['last_used_at' => now()])->save();
            return $connection->access_token;
        }

        $refreshed = $this->refreshAccessToken($connection->refresh_token);

        $connection->forceFill([
            'access_token'  => $refreshed['access_token'],
            'refresh_token' => $refreshed['refresh_token'],
            'expires_at'    => now()->addSeconds((int) $refreshed['expires_in']),
            'last_used_at'  => now(),
        ])->save();

        return $connection->access_token;
    }
}
