<?php

namespace App\Services\Tiktok;

use App\Models\TikTok\TikTokToken;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the TikTok Marketing API (ads data).
 *
 * Auth: Custom `Access-Token` header (NOT Bearer).
 * Base URL: https://business-api.tiktok.com/open_api/v1.3
 * Sandbox:  https://sandbox-ads.tiktok.com/open_api/v1.3
 */
class TiktokAdsApiService
{
    private string $baseUrl;
    private ?string $advertiserId;
    private int $maxRetries;
    private array $retryDelays;
    private int $apiCallsCount = 0;

    public function __construct()
    {
        $this->baseUrl = config('tiktok.use_sandbox')
            ? config('tiktok.ads_sandbox_url')
            : config('tiktok.ads_base_url');
        $this->advertiserId = config('tiktok.advertiser_id') ?? '';
        $this->maxRetries = config('tiktok.max_retries', 3);
        $this->retryDelays = config('tiktok.retry_delay_seconds', [2, 5, 15]);
    }

    public function getApiCallsCount(): int
    {
        return $this->apiCallsCount;
    }

    public function resetApiCallsCount(): void
    {
        $this->apiCallsCount = 0;
    }

    public function getAdvertiserId(): string
    {
        return $this->advertiserId;
    }

    // ─── Token management ─────────────────────────────────

    private function getAccessToken(): string
    {
        $token = TikTokToken::getActiveMarketingToken();

        if (! $token) {
            throw new Exception('No active TikTok Marketing API token. Authenticate via TikTok Ads OAuth.');
        }

        // Refresh if expiring within 2 hours
        if ($token->needsRefresh() && ! $token->isRefreshTokenExpired()) {
            $this->refreshAccessToken($token);
            $token = $token->fresh();
        }

        $token->markUsed();

        return $token->getDecryptedAccessToken();
    }

    public function refreshAccessToken(TikTokToken $token): array
    {
        $response = Http::post("{$this->baseUrl}/oauth2/refresh_token/", [
            'app_id' => config('tiktok.app_id'),
            'secret' => config('tiktok.app_secret'),
            'refresh_token' => $token->getDecryptedRefreshToken(),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new Exception('TikTok Marketing token refresh failed: ' . $response->body());
        }

        $data = $response->json('data', []);

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->getDecryptedRefreshToken(),
            'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 86400),
        ]);

        Log::info('TikTok Marketing token refreshed.', ['advertiser_id' => $token->advertiser_id]);

        return $data;
    }

    public function exchangeCodeForToken(string $authCode): array
    {
        $response = Http::post("{$this->baseUrl}/oauth2/access_token/", [
            'app_id' => config('tiktok.app_id'),
            'secret' => config('tiktok.app_secret'),
            'auth_code' => $authCode,
        ]);

        if ($response->failed()) {
            throw new Exception('TikTok Marketing token exchange failed: ' . $response->body());
        }

        $json = $response->json();

        if (($json['code'] ?? -1) !== 0) {
            throw new Exception('TikTok Marketing API error: ' . ($json['message'] ?? 'Unknown'));
        }

        return $json['data'] ?? [];
    }

    // ─── Reporting ────────────────────────────────────────

    /**
     * Synchronous integrated report.
     * GET /report/integrated/get/
     *
     * @param array $params Keys: report_type, data_level, dimensions, metrics, start_date, end_date, page, page_size, filtering
     */
    public function getReport(array $params): array
    {
        $params['advertiser_id'] = $this->advertiserId;

        // Encode arrays to JSON strings (TikTok requires this for GET params)
        foreach (['dimensions', 'metrics', 'filtering'] as $key) {
            if (isset($params[$key]) && is_array($params[$key])) {
                $params[$key] = json_encode($params[$key]);
            }
        }

        return $this->get('/report/integrated/get/', $params);
    }

    /**
     * Fetch all pages of a report.
     */
    public function getReportAllPages(array $params): array
    {
        $allRows = [];
        $page = 1;
        $pageSize = $params['page_size'] ?? 1000;

        do {
            $params['page'] = $page;
            $params['page_size'] = $pageSize;

            $result = $this->getReport($params);
            $list = $result['data']['list'] ?? [];
            $allRows = array_merge($allRows, $list);

            $totalPages = $result['data']['page_info']['total_page'] ?? 1;
            $page++;

            if ($page <= $totalPages) {
                usleep(config('tiktok.pause_between_batches', 3) * 1000000);
            }
        } while ($page <= $totalPages);

        return $allRows;
    }

    // ─── Entity endpoints ─────────────────────────────────

    public function getCampaigns(int $page = 1, int $pageSize = 1000): array
    {
        return $this->get('/campaign/get/', [
            'advertiser_id' => $this->advertiserId,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function getAdGroups(int $page = 1, int $pageSize = 1000): array
    {
        return $this->get('/adgroup/get/', [
            'advertiser_id' => $this->advertiserId,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function getAdvertiserInfo(): array
    {
        return $this->get('/advertiser/info/', [
            'advertiser_ids' => json_encode([$this->advertiserId]),
        ]);
    }

    // ─── Date range chunking ──────────────────────────────

    /**
     * Split a date range into chunks of $maxDays.
     * TikTok limits daily granularity to 30-day ranges.
     *
     * @return array<array{from: string, to: string}>
     */
    public static function chunkDateRange(string $from, string $to, int $maxDays = 30): array
    {
        $chunks = [];
        $start = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);

        while ($start->lte($end)) {
            $chunkEnd = $start->copy()->addDays($maxDays - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            $chunks[] = [
                'from' => $start->format('Y-m-d'),
                'to' => $chunkEnd->format('Y-m-d'),
            ];

            $start = $chunkEnd->copy()->addDay();
        }

        return $chunks;
    }

    // ─── HTTP transport ───────────────────────────────────

    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $body = []): array
    {
        return $this->request('POST', $endpoint, [], $body);
    }

    private function request(string $method, string $endpoint, array $params = [], array $body = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;
        $accessToken = $this->getAccessToken();
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            try {
                $this->apiCallsCount++;

                $http = Http::timeout(60)->withHeaders([
                    'Access-Token' => $accessToken,
                ]);

                $response = $method === 'GET'
                    ? $http->get($url, $params)
                    : $http->post($url, $body);

                // Rate limit
                if ($response->status() === 429) {
                    $delay = $this->retryDelays[$attempt] ?? end($this->retryDelays);
                    Log::warning("TikTok Ads API rate limit hit. Waiting {$delay}s.", [
                        'attempt' => $attempt + 1,
                        'endpoint' => $endpoint,
                    ]);
                    sleep($delay);
                    $attempt++;
                    continue;
                }

                if ($response->failed()) {
                    throw new Exception("TikTok Ads API HTTP {$response->status()}: {$response->body()}");
                }

                $json = $response->json();

                // TikTok returns code=0 on success
                if (($json['code'] ?? -1) !== 0) {
                    $code = $json['code'] ?? 'unknown';
                    $msg = $json['message'] ?? 'Unknown error';

                    // Auth errors should not retry
                    if (in_array($code, [40100, 40104, 40105])) {
                        throw new Exception("TikTok Ads API Auth Error [{$code}]: {$msg}");
                    }

                    throw new Exception("TikTok Ads API Error [{$code}]: {$msg}");
                }

                return $json;
            } catch (Exception $e) {
                if ($attempt >= $this->maxRetries) {
                    Log::error('TikTok Ads API failed after retries.', [
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                        'attempts' => $attempt + 1,
                    ]);
                    throw $e;
                }

                sleep($this->retryDelays[$attempt] ?? end($this->retryDelays));
                $attempt++;
            }
        }

        throw new Exception('TikTok Ads API request failed after max retries.');
    }
}
