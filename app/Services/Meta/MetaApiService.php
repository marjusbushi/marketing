<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaRawEvent;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaApiService
{
    private string $baseUrl;
    private string $apiVersion;
    private ?string $token;
    private ?string $pageToken;
    private ?string $appSecret;
    private int $maxRetries;
    private array $retryDelays;
    private int $apiCallsCount = 0;
    private string $correlationId;

    public function __construct()
    {
        $this->baseUrl = config('meta.base_url');
        $this->apiVersion = config('meta.api_version');
        $this->token = config('meta.token');
        $this->pageToken = config('meta.page_token');
        $this->appSecret = config('meta.app_secret');
        $this->maxRetries = config('meta.max_retries', 3);
        $this->retryDelays = config('meta.retry_delay_seconds', [1, 4, 16]);
        $this->correlationId = Str::uuid()->toString();
    }

    /**
     * Set a correlation ID to group related API calls.
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /**
     * Get the current correlation ID.
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Get currently active Graph API version for this service instance.
     */
    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    /**
     * Temporarily execute API calls with a different Graph API version.
     */
    public function runWithApiVersion(string $apiVersion, callable $callback): mixed
    {
        $original = $this->apiVersion;
        $this->apiVersion = $apiVersion;

        try {
            return $callback();
        } finally {
            $this->apiVersion = $original;
        }
    }

    /**
     * Generate appsecret_proof for secure API calls.
     */
    private function getAppSecretProof(bool $forPageToken = false): ?string
    {
        if (!$this->appSecret) {
            return null;
        }
        $token = $forPageToken && $this->pageToken ? $this->pageToken : $this->token;
        return hash_hmac('sha256', $token, $this->appSecret);
    }

    /**
     * Get the number of API calls made in this session.
     */
    public function getApiCallsCount(): int
    {
        return $this->apiCallsCount;
    }

    /**
     * Reset the API calls counter.
     */
    public function resetApiCallsCount(): void
    {
        $this->apiCallsCount = 0;
    }

    /**
     * Make a GET request to Meta Graph API with retry and rate limit handling.
     */
    public function get(string $endpoint, array $params = [], bool $usePageToken = false): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$endpoint}";
        $tokenType = $usePageToken ? 'page' : 'system_user';
        $params['access_token'] = $usePageToken && $this->pageToken ? $this->pageToken : $this->token;

        Log::debug('Meta API Request', [
            'url' => $this->sanitizeUrl($url),
            'endpoint' => $endpoint,
            'token_type' => $tokenType,
        ]);

        return $this->requestWithRetry('GET', $url, $params, $tokenType);
    }

    /**
     * Make a GET request using the Page Access Token (for Page/IG insights).
     */
    public function getWithPageToken(string $endpoint, array $params = []): array
    {
        return $this->get($endpoint, $params, true);
    }

    /**
     * Fetch all pages of a paginated endpoint.
     */
    public function getPaginated(string $endpoint, array $params = [], int $limit = 100): array
    {
        $params['limit'] = $limit;
        $allData = [];
        $response = $this->get($endpoint, $params);
        $allData = array_merge($allData, $response['data'] ?? []);

        // Follow pagination — re-inject token to avoid auth issues
        $maxPages = 20;
        $page = 0;
        while (isset($response['paging']['next']) && $page < $maxPages) {
            $nextUrl = $response['paging']['next'];
            $parsedUrl = parse_url($nextUrl);
            parse_str($parsedUrl['query'] ?? '', $queryParams);
            $queryParams['access_token'] = $this->token;
            $cleanUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '');

            $response = $this->requestWithRetry('GET', $cleanUrl, $queryParams);
            $allData = array_merge($allData, $response['data'] ?? []);
            $page++;
        }

        return $allData;
    }

    /**
     * Fetch insights with date range for Ads.
     */
    public function getAdsInsights(string $adAccountId, array $params = []): array
    {
        $endpoint = "{$adAccountId}/insights";

        return $this->getPaginated($endpoint, $params);
    }

    /**
     * Fetch Page Insights metrics (uses Page Token).
     */
    public function getPageInsights(string $pageId, string $metric, string $period, string $since, string $until): array
    {
        $endpoint = "{$pageId}/insights";
        $params = [
            'metric' => $metric,
            'period' => $period,
            'since' => $since,
            'until' => $until,
        ];

        return $this->getWithPageToken($endpoint, $params);
    }

    /**
     * Fetch IG User Insights (uses Page Token).
     * Some metrics in v21.0+ require metric_type=total_value parameter.
     */
    public function getIgInsights(string $igAccountId, string $metric, string $period, string $since, string $until, ?string $metricType = null): array
    {
        $endpoint = "{$igAccountId}/insights";
        $params = [
            'metric' => $metric,
            'period' => $period,
            'since' => $since,
            'until' => $until,
        ];

        if ($metricType) {
            $params['metric_type'] = $metricType;
        }

        return $this->getWithPageToken($endpoint, $params);
    }

    /**
     * Fetch IG account info (followers_count, etc.) - uses Page Token.
     */
    public function getIgAccountInfo(string $igAccountId, array $fields = ['followers_count', 'media_count']): array
    {
        return $this->getWithPageToken($igAccountId, ['fields' => implode(',', $fields)]);
    }

    /**
     * Fetch page posts (uses Page Token).
     * Uses /published_posts with a since filter to avoid "reduce data" errors
     * on pages with many thousands of posts.
     */
    public function getPagePosts(string $pageId, array $fields, int $limit = 50, ?string $since = null): array
    {
        $params = ['fields' => implode(',', $fields)];
        if ($since) {
            $params['since'] = $since;
        }

        return $this->getPaginatedWithPageToken("{$pageId}/published_posts", $params, $limit);
    }

    /**
     * Fetch IG media (uses Page Token).
     */
    public function getIgMedia(string $igAccountId, array $fields, int $limit = 50): array
    {
        return $this->getPaginatedWithPageToken("{$igAccountId}/media", ['fields' => implode(',', $fields)], $limit);
    }

    /**
     * Fetch insights for a specific post/media (uses Page Token).
     */
    public function getPostInsights(string $postId, string $metric): array
    {
        return $this->getWithPageToken("{$postId}/insights", ['metric' => $metric]);
    }

    /**
     * Fetch conversations (Messenger or IG DMs) - uses Page Token.
     *
     * IG DMs use folder=instagram instead of platform=instagram to avoid
     * timeouts on large accounts. The platform filter causes Meta to scan
     * all conversations server-side; folder is pre-indexed and much faster.
     */
    public function getConversations(string $pageOrIgId, string $platform = 'messenger', ?string $since = null, ?string $until = null, int $maxPages = 20): array
    {
        $endpoint = "{$pageOrIgId}/conversations";
        $isInstagram = $platform === 'instagram';

        $params = [
            'fields' => 'id,updated_time,message_count',
        ];

        if ($isInstagram) {
            $params['folder'] = 'instagram';
        }

        if ($since) {
            $params['since'] = $since;
        }

        // Bound the fetch to prevent fetching unbounded conversation history.
        if ($until) {
            $params['until'] = $until;
        }

        return $this->getPaginatedWithPageToken($endpoint, $params, 500, $maxPages);
    }

    /**
     * Fetch all pages of a paginated endpoint using Page Token.
     * Re-injects token on pagination to avoid "Provide valid app ID" errors.
     */
    public function getPaginatedWithPageToken(string $endpoint, array $params = [], int $limit = 100, int $maxPages = 20): array
    {
        $params['limit'] = $limit;
        $allData = [];
        $response = $this->getWithPageToken($endpoint, $params);
        $allData = array_merge($allData, $response['data'] ?? []);

        // Follow pagination — re-inject page token to avoid auth issues
        $page = 0;
        while (isset($response['paging']['next']) && $page < $maxPages) {
            $nextUrl = $response['paging']['next'];
            // Re-inject the access token in case Meta strips it from the next URL
            $parsedUrl = parse_url($nextUrl);
            parse_str($parsedUrl['query'] ?? '', $queryParams);
            $queryParams['access_token'] = $this->pageToken;
            $cleanUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '');

            $response = $this->requestWithRetry('GET', $cleanUrl, $queryParams, 'page');
            $allData = array_merge($allData, $response['data'] ?? []);
            $page++;
        }

        return $allData;
    }

    /**
     * Follow a pagination "next" URL, re-injecting the page token.
     * Used by jobs that need manual page-by-page pagination with early termination.
     */
    public function fetchNextPageUrl(string $nextUrl): array
    {
        $parsedUrl = parse_url($nextUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $queryParams['access_token'] = $this->pageToken;
        $cleanUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '') . ($parsedUrl['path'] ?? '');

        return $this->requestWithRetry('GET', $cleanUrl, $queryParams, 'page');
    }

    /**
     * Make an HTTP request with retry logic and rate limit handling.
     * Logs raw request/response to meta_raw_events for audit.
     */
    private function requestWithRetry(string $method, string $url, array $params, string $tokenType = 'system_user'): array
    {
        $attempt = 0;
        $endpoint = $this->extractEndpoint($url);

        while ($attempt <= $this->maxRetries) {
            $startTime = microtime(true);

            try {
                $this->apiCallsCount++;

                $response = Http::timeout(120)
                    ->connectTimeout(30)
                    ->retry(0)
                    ->get($url, $params);

                $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                // Check for rate limiting
                if ($response->status() === 429) {
                    $this->logRawEvent($endpoint, $method, $params, $response->body(), $response->status(), $durationMs, $tokenType, true, 'Rate limit hit (429)');
                    $retryAfter = $response->header('Retry-After', $this->getRetryDelay($attempt));
                    Log::warning("Meta API rate limit hit. Waiting {$retryAfter}s before retry.", [
                        'attempt' => $attempt + 1,
                        'url' => $this->sanitizeUrl($url),
                    ]);
                    sleep((int) $retryAfter);
                    $attempt++;
                    continue;
                }

                // Check for business use case rate limit
                $usageHeader = $response->header('x-business-use-case-usage');
                if ($usageHeader) {
                    $this->handleBusinessRateLimit($usageHeader);
                }

                if ($response->failed()) {
                    $errorBody = $response->json();
                    $errorMessage = $errorBody['error']['message'] ?? 'Unknown Meta API error';
                    $errorCode = $errorBody['error']['code'] ?? 0;

                    $this->logRawEvent($endpoint, $method, $params, $response->body(), $response->status(), $durationMs, $tokenType, true, "[{$errorCode}] {$errorMessage}");

                    Log::error('Meta API Error Response', [
                        'url' => $this->sanitizeUrl($url),
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ]);

                    // "Reduce data" is NOT transient — don't waste time retrying
                    if ($errorCode === 1 && str_contains($errorMessage, 'reduce the amount of data')) {
                        throw new Exception("Meta API Error [{$errorCode}]: {$errorMessage}");
                    }

                    // Transient errors - retry
                    if (in_array($errorCode, [1, 2, 4, 17, 341])) {
                        sleep($this->getRetryDelay($attempt));
                        $attempt++;
                        continue;
                    }

                    throw new Exception("Meta API Error [{$errorCode}]: {$errorMessage}");
                }

                // Log successful response (truncate body to avoid huge JSON)
                $this->logRawEvent($endpoint, $method, $params, $response->body(), $response->status(), $durationMs, $tokenType, false);

                return $response->json();
            } catch (Exception $e) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                // Non-transient API errors (e.g. code 100 "invalid metric") should not be retried.
                // They contain "Meta API Error [" prefix from the throw above.
                $isNonTransient = str_contains($e->getMessage(), 'Meta API Error [');

                if ($isNonTransient || $attempt >= $this->maxRetries) {
                    $this->logRawEvent($endpoint, $method, $params, null, null, $durationMs, $tokenType, true, $e->getMessage());
                    Log::error('Meta API request failed' . ($isNonTransient ? ' (non-transient).' : ' after all retries.'), [
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

        throw new Exception('Meta API request failed after max retries.');
    }

    /**
     * Log a raw API call to meta_raw_events table.
     */
    private function logRawEvent(string $endpoint, string $method, array $params, ?string $responseBody, ?int $httpStatus, int $durationMs, string $tokenType, bool $isError, ?string $errorMessage = null): void
    {
        try {
            // Remove access_token from logged params
            $safeParams = $params;
            if (isset($safeParams['access_token'])) {
                $safeParams['access_token'] = '***REDACTED***';
            }
            if (isset($safeParams['appsecret_proof'])) {
                $safeParams['appsecret_proof'] = '***REDACTED***';
            }

            // Truncate response body to 64KB max
            $truncatedBody = $responseBody;
            if ($truncatedBody && strlen($truncatedBody) > 65536) {
                $truncatedBody = substr($truncatedBody, 0, 65536) . '...[TRUNCATED]';
            }

            MetaRawEvent::create([
                'correlation_id' => $this->correlationId,
                'endpoint' => substr($endpoint, 0, 500),
                'method' => $method,
                'token_type' => $tokenType,
                'request_params' => $safeParams,
                'response_body' => $truncatedBody,
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'is_error' => $isError,
                'error_message' => $errorMessage ? substr($errorMessage, 0, 1000) : null,
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            // Never let audit logging crash the sync
            Log::debug('Failed to log meta raw event: ' . $e->getMessage());
        }
    }

    /**
     * Extract the endpoint path from a full URL.
     */
    private function extractEndpoint(string $url): string
    {
        $parsed = parse_url($url, PHP_URL_PATH);
        return $parsed ? ltrim($parsed, '/') : $url;
    }

    /**
     * Get the retry delay for the given attempt.
     */
    private function getRetryDelay(int $attempt): int
    {
        return $this->retryDelays[$attempt] ?? end($this->retryDelays);
    }

    /**
     * Handle business use case rate limiting from Meta response headers.
     */
    private function handleBusinessRateLimit(string $usageHeader): void
    {
        try {
            $usage = json_decode($usageHeader, true);
            if (!$usage) return;

            foreach ($usage as $accountId => $usageData) {
                foreach ($usageData as $item) {
                    $callCount = $item['call_count'] ?? 0;
                    $estimatedTimeToReset = $item['estimated_time_to_regain_access'] ?? 0;

                    // If we're at 80%+ usage, slow down
                    if ($callCount >= 80) {
                        $pauseSeconds = max(5, $estimatedTimeToReset * 60);
                        Log::info("Meta API usage at {$callCount}%. Pausing {$pauseSeconds}s.", [
                            'account' => $accountId,
                        ]);
                        sleep(min($pauseSeconds, 300)); // Max 5 minutes
                    }
                }
            }
        } catch (Exception $e) {
            // Don't let rate limit parsing crash the sync
            Log::debug('Could not parse Meta rate limit header: ' . $e->getMessage());
        }
    }

    /**
     * Remove access_token from URL for logging.
     */
    private function sanitizeUrl(string $url): string
    {
        return preg_replace('/access_token=[^&]+/', 'access_token=***', $url);
    }
}
