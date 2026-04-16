<?php

namespace App\Services\Tiktok;

use App\Models\TikTok\TikTokToken;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiktokApiService
{
    private string $baseUrl;
    private int $maxRetries;
    private array $retryDelays;
    private int $apiCallsCount = 0;
    private ?TikTokToken $token = null;

    public function __construct()
    {
        $this->baseUrl = config('tiktok.organic_base_url');
        $this->maxRetries = config('tiktok.max_retries', 3);
        $this->retryDelays = config('tiktok.retry_delay_seconds', [1, 4, 16]);
    }

    public function getApiCallsCount(): int
    {
        return $this->apiCallsCount;
    }

    public function resetApiCallsCount(): void
    {
        $this->apiCallsCount = 0;
    }

    /**
     * Set the token to use for API calls.
     */
    public function setToken(TikTokToken $token): void
    {
        $this->token = $token;
    }

    /**
     * Get the active token, refreshing if needed.
     */
    private function getAccessToken(): string
    {
        if (!$this->token) {
            $this->token = TikTokToken::getActiveToken();
        }

        if (!$this->token) {
            throw new Exception('No active TikTok token found. Please authenticate first.');
        }

        // Refresh if expired
        if ($this->token->isAccessTokenExpired() && !$this->token->isRefreshTokenExpired()) {
            $this->refreshAccessToken();
        }

        $this->token->markUsed();

        return $this->token->getDecryptedAccessToken();
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken(?TikTokToken $token = null): array
    {
        $token = $token ?? $this->token;

        if (!$token) {
            throw new Exception('No token to refresh.');
        }

        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token/", [
            'client_key' => config('tiktok.client_key'),
            'client_secret' => config('tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->getDecryptedRefreshToken(),
        ]);

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception('TikTok token refresh failed: ' . ($error['error_description'] ?? $error['message'] ?? 'Unknown error'));
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 86400),
            'refresh_token_expires_at' => now()->addSeconds($data['refresh_expires_in'] ?? 86400 * 365),
        ]);

        $this->token = $token->fresh();

        return $data;
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token/", [
            'client_key' => config('tiktok.client_key'),
            'client_secret' => config('tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => url(config('tiktok.oauth.redirect_uri')),
        ]);

        if ($response->failed()) {
            $error = $response->json();
            throw new Exception('TikTok token exchange failed: ' . ($error['error_description'] ?? $error['message'] ?? 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Get user info from TikTok.
     */
    public function getUserInfo(array $fields = ['open_id', 'union_id', 'avatar_url', 'display_name', 'bio_description', 'is_verified', 'username', 'profile_deep_link', 'follower_count', 'following_count', 'likes_count', 'video_count']): array
    {
        return $this->get('/user/info/', [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * Get list of user's videos.
     */
    public function getVideoList(?int $cursor = null, int $maxCount = 20): array
    {
        $body = ['max_count' => min($maxCount, 20)];

        if ($cursor) {
            $body['cursor'] = $cursor;
        }

        return $this->post('/video/list/', $body, [
            'fields' => 'id,title,video_description,duration,cover_image_url,share_url,embed_link,like_count,comment_count,share_count,view_count,create_time,width,height',
        ]);
    }

    /**
     * Query specific videos by IDs.
     */
    public function queryVideos(array $videoIds): array
    {
        return $this->post('/video/query/', [
            'filters' => [
                'video_ids' => array_slice($videoIds, 0, 20), // Max 20 per request
            ],
        ], [
            'fields' => 'id,title,video_description,duration,cover_image_url,share_url,embed_link,like_count,comment_count,share_count,view_count,create_time,width,height',
        ]);
    }

    /**
     * Make a GET request to TikTok API.
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $accessToken = $this->getAccessToken();

        return $this->requestWithRetry('GET', $url, $queryParams, $accessToken);
    }

    /**
     * Make a POST request to TikTok API.
     */
    public function post(string $endpoint, array $body = [], array $queryParams = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $accessToken = $this->getAccessToken();

        return $this->requestWithRetry('POST', $url, $queryParams, $accessToken, $body);
    }

    /**
     * HTTP request with retry logic.
     */
    private function requestWithRetry(string $method, string $url, array $queryParams, string $accessToken, array $body = []): array
    {
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            try {
                $this->apiCallsCount++;

                $request = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                    ]);

                if ($method === 'GET') {
                    $response = $request->get($url, $queryParams);
                } else {
                    // For POST, query params go in URL, body goes as JSON
                    if (!empty($queryParams)) {
                        $url .= '?' . http_build_query($queryParams);
                    }
                    $response = $request->post($url, $body);
                }

                // Rate limit
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After', $this->getRetryDelay($attempt));
                    Log::warning("TikTok API rate limit hit. Waiting {$retryAfter}s.", [
                        'attempt' => $attempt + 1,
                        'url' => $this->sanitizeUrl($url),
                    ]);
                    sleep((int) $retryAfter);
                    $attempt++;
                    continue;
                }

                if ($response->failed()) {
                    $errorBody = $response->json();
                    $errorCode = $errorBody['error']['code'] ?? 'unknown';
                    $errorMessage = $errorBody['error']['message'] ?? 'Unknown TikTok API error';

                    Log::error('TikTok API Error', [
                        'url' => $this->sanitizeUrl($url),
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ]);

                    throw new Exception("TikTok API Error [{$errorCode}]: {$errorMessage}");
                }

                $json = $response->json();

                // TikTok returns errors in the body with "ok" HTTP status sometimes
                if (isset($json['error']['code']) && $json['error']['code'] !== 'ok') {
                    $errorCode = $json['error']['code'];
                    $errorMessage = $json['error']['message'] ?? 'Unknown error';

                    Log::error('TikTok API Body Error', [
                        'url' => $this->sanitizeUrl($url),
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ]);

                    throw new Exception("TikTok API Error [{$errorCode}]: {$errorMessage}");
                }

                return $json;
            } catch (Exception $e) {
                if ($attempt >= $this->maxRetries) {
                    Log::error('TikTok API request failed after all retries.', [
                        'url' => $this->sanitizeUrl($url),
                        'error' => $e->getMessage(),
                        'attempts' => $attempt + 1,
                    ]);
                    throw $e;
                }

                sleep($this->getRetryDelay($attempt));
                $attempt++;
            }
        }

        throw new Exception('TikTok API request failed after max retries.');
    }

    private function getRetryDelay(int $attempt): int
    {
        return $this->retryDelays[$attempt] ?? end($this->retryDelays);
    }

    private function sanitizeUrl(string $url): string
    {
        return preg_replace('/access_token=[^&]+/', 'access_token=***', $url);
    }
}
