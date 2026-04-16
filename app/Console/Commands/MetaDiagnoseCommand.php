<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class MetaDiagnoseCommand extends Command
{
    protected $signature = 'meta:diagnose';
    protected $description = 'Diagnose Meta API configuration, tokens, permissions, and IG linkage';

    private string $baseUrl;
    private string $apiVersion;

    public function handle(): int
    {
        Artisan::call('config:clear');

        $this->baseUrl = config('meta.base_url', 'https://graph.facebook.com');
        $this->apiVersion = config('meta.api_version', 'v24.0');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║          META API DIAGNOSTIC REPORT                 ║');
        $this->info('╚══════════════════════════════════════════════════════╝');
        $this->info('');

        // 1. Check .env configuration
        $this->checkEnvConfig();

        // 2. Verify System User Token
        $this->checkSystemUserToken();

        // 3. Verify Page Token
        $this->checkPageToken();

        // 4. Check Page Info + IG Linkage
        $this->checkPageAndIgLinkage();

        // 5. Check App permissions / scopes on tokens
        $this->checkTokenPermissions();

        // 6. Check Page Insights accessibility
        $this->checkPageInsights();

        // 7. Check IG Insights accessibility
        $this->checkIgInsights();

        // 8. Check Page Posts accessibility
        $this->checkPagePosts();

        // 9. Check Messaging accessibility
        $this->checkMessaging();

        // 10. Check Ad Account accessibility
        $this->checkAdAccount();

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Diagnostic complete.');
        $this->info('═══════════════════════════════════════════════════════');

        return self::SUCCESS;
    }

    private function checkEnvConfig(): void
    {
        $this->section('1. ENV CONFIGURATION');

        $keys = [
            'META_SYSTEM_USER_TOKEN' => config('meta.token'),
            'META_PAGE_TOKEN'        => config('meta.page_token'),
            'META_APP_ID'            => config('meta.app_id'),
            'META_APP_SECRET'        => config('meta.app_secret'),
            'META_AD_ACCOUNT_ID'     => config('meta.ad_account_id'),
            'META_PAGE_ID'           => config('meta.page_id'),
            'META_IG_ACCOUNT_ID'     => config('meta.ig_account_id'),
            'META_BUSINESS_ID'       => config('meta.business_id'),
            'META_API_VERSION'       => config('meta.api_version'),
        ];

        foreach ($keys as $key => $value) {
            if (!$value) {
                $this->warn("   ✗ {$key}: NOT SET");
            } elseif (in_array($key, ['META_SYSTEM_USER_TOKEN', 'META_PAGE_TOKEN', 'META_APP_SECRET'])) {
                $this->info("   ✓ {$key}: " . substr($value, 0, 10) . '...' . substr($value, -6));
            } else {
                $this->info("   ✓ {$key}: {$value}");
            }
        }
    }

    private function checkSystemUserToken(): void
    {
        $this->section('2. SYSTEM USER TOKEN');

        $token = config('meta.token');
        if (!$token) {
            $this->error('   System User Token not set. Cannot proceed.');
            return;
        }

        try {
            $response = $this->apiGet('me', ['fields' => 'id,name'], $token);
            $this->info("   ✓ Token valid. User: {$response['name']} (ID: {$response['id']})");
        } catch (Exception $e) {
            $this->error("   ✗ Token INVALID: " . $e->getMessage());
        }
    }

    private function checkPageToken(): void
    {
        $this->section('3. PAGE TOKEN');

        $token = config('meta.page_token');
        if (!$token) {
            $this->error('   Page Token not set. Page/IG features will not work.');
            return;
        }

        try {
            $response = $this->apiGet('me', ['fields' => 'id,name,category'], $token);
            $this->info("   ✓ Token valid. Page: {$response['name']} (ID: {$response['id']})");
            if (isset($response['category'])) {
                $this->info("     Category: {$response['category']}");
            }

            // Check token debug info
            $appId = config('meta.app_id');
            $appSecret = config('meta.app_secret');
            if ($appId && $appSecret) {
                $appToken = "{$appId}|{$appSecret}";
                try {
                    $debugInfo = $this->apiGet('debug_token', ['input_token' => $token], $appToken);
                    $data = $debugInfo['data'] ?? [];
                    $this->info("     App: " . ($data['app_id'] ?? 'N/A') . " | Type: " . ($data['type'] ?? 'N/A'));
                    $this->info("     Expires: " . (isset($data['expires_at']) && $data['expires_at'] > 0 ? date('Y-m-d H:i:s', $data['expires_at']) : 'Never'));
                    $scopes = $data['scopes'] ?? [];
                    $this->info("     Scopes (" . count($scopes) . "): " . implode(', ', $scopes));

                    // Check for critical missing scopes
                    $required = ['pages_show_list', 'pages_read_engagement', 'read_insights', 'instagram_basic', 'instagram_manage_insights'];
                    $missing = array_diff($required, $scopes);
                    if (!empty($missing)) {
                        $this->warn("     ⚠ MISSING SCOPES: " . implode(', ', $missing));
                    } else {
                        $this->info("     ✓ All critical scopes present");
                    }
                } catch (Exception $e) {
                    $this->warn("   Could not debug token: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $this->error("   ✗ Token INVALID: " . $e->getMessage());
        }
    }

    private function checkPageAndIgLinkage(): void
    {
        $this->section('4. PAGE → INSTAGRAM BUSINESS LINKAGE');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->error('   Page ID or Page Token missing. Cannot check IG linkage.');
            return;
        }

        try {
            // Check page info
            $pageInfo = $this->apiGet($pageId, [
                'fields' => 'id,name,fan_count,followers_count,instagram_business_account{id,username,followers_count,media_count,profile_picture_url}',
            ], $pageToken);

            $this->info("   Page: {$pageInfo['name']} (ID: {$pageInfo['id']})");
            $this->info("   Fans: " . ($pageInfo['fan_count'] ?? 'N/A') . " | Followers: " . ($pageInfo['followers_count'] ?? 'N/A'));

            $igBusiness = $pageInfo['instagram_business_account'] ?? null;

            if ($igBusiness) {
                $this->info("   ✓ IG BUSINESS ACCOUNT LINKED!");
                $this->info("     IG ID: {$igBusiness['id']}");
                $this->info("     Username: @" . ($igBusiness['username'] ?? 'N/A'));
                $this->info("     Followers: " . ($igBusiness['followers_count'] ?? 'N/A'));
                $this->info("     Media: " . ($igBusiness['media_count'] ?? 'N/A'));

                $configIgId = config('meta.ig_account_id');
                if ($configIgId && $configIgId !== $igBusiness['id']) {
                    $this->warn("     ⚠ META_IG_ACCOUNT_ID in .env ({$configIgId}) does NOT match discovered ID ({$igBusiness['id']})!");
                    $this->warn("       Update .env: META_IG_ACCOUNT_ID={$igBusiness['id']}");
                } elseif (!$configIgId) {
                    $this->warn("     ⚠ META_IG_ACCOUNT_ID not set in .env. Add: META_IG_ACCOUNT_ID={$igBusiness['id']}");
                } else {
                    $this->info("     ✓ META_IG_ACCOUNT_ID matches.");
                }
            } else {
                $this->error('   ✗ NO IG BUSINESS ACCOUNT LINKED TO THIS PAGE!');
                $this->warn('     This means IG insights, IG media, and IG DMs will NOT work.');
                $this->warn('');
                $this->warn('     TO FIX:');
                $this->warn('     1. Go to Instagram App → Settings → Account → Switch to Professional Account → Business');
                $this->warn('     2. In Instagram, link to Facebook Page "Zero Absolute"');
                $this->warn('     3. Or in Meta Business Suite → Settings → Instagram Accounts → Connect');
                $this->warn('     4. Re-run: php artisan meta:diagnose');
            }
        } catch (Exception $e) {
            $this->error("   ✗ Failed to check page/IG linkage: " . $e->getMessage());
        }
    }

    private function checkTokenPermissions(): void
    {
        $this->section('5. TOKEN PERMISSIONS (debug_token)');

        $systemToken = config('meta.token');
        $appId = config('meta.app_id');
        $appSecret = config('meta.app_secret');

        if (!$appId || !$appSecret) {
            $this->warn('   App ID or Secret not set. Cannot debug tokens.');
            return;
        }

        $appToken = "{$appId}|{$appSecret}";

        // Debug system user token
        if ($systemToken) {
            try {
                $debug = $this->apiGet('debug_token', ['input_token' => $systemToken], $appToken);
                $data = $debug['data'] ?? [];
                $scopes = $data['scopes'] ?? [];
                $this->info("   System User Token Scopes (" . count($scopes) . "):");
                foreach ($scopes as $scope) {
                    $this->info("     - {$scope}");
                }
            } catch (Exception $e) {
                $this->error("   ✗ Cannot debug system token: " . $e->getMessage());
            }
        }
    }

    private function checkPageInsights(): void
    {
        $this->section('6. PAGE INSIGHTS TEST');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - Page ID or Token missing.');
            return;
        }

        // page_impressions was removed in Graph API v21.0 — use page_impressions_unique (reach) instead
        $metrics = ['page_impressions_unique', 'page_views_total', 'page_post_engagements', 'page_messages_new_threads'];

        foreach ($metrics as $metric) {
            try {
                $response = $this->apiGet("{$pageId}/insights", [
                    'metric' => $metric,
                    'period' => 'day',
                    'since' => date('Y-m-d', strtotime('-3 days')),
                    'until' => date('Y-m-d', strtotime('-1 day')),
                ], $pageToken);

                $data = $response['data'] ?? [];
                $valueCount = 0;
                foreach ($data as $entry) {
                    $valueCount += count($entry['values'] ?? []);
                }

                if ($valueCount > 0) {
                    $this->info("   ✓ {$metric}: {$valueCount} data points");
                } else {
                    $this->warn("   ⚠ {$metric}: 200 OK but 0 data points (may need pages_read_engagement)");
                }
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, '(#100)')) {
                    $this->error("   ✗ {$metric}: Permission denied or metric unavailable");
                } else {
                    $this->error("   ✗ {$metric}: " . substr($msg, 0, 120));
                }
            }
        }
    }

    private function checkIgInsights(): void
    {
        $this->section('7. IG INSIGHTS TEST');

        $igAccountId = config('meta.ig_account_id');
        $pageToken = config('meta.page_token');

        if (!$igAccountId || !$pageToken) {
            // Try auto-discover
            $pageId = config('meta.page_id');
            if ($pageId && $pageToken) {
                try {
                    $result = $this->apiGet($pageId, ['fields' => 'instagram_business_account{id}'], $pageToken);
                    $igAccountId = $result['instagram_business_account']['id'] ?? null;
                    if ($igAccountId) {
                        $this->info("   Auto-discovered IG ID: {$igAccountId}");
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }

            if (!$igAccountId) {
                $this->warn('   Skipped - IG Account ID not configured and auto-discover failed.');
                return;
            }
        }

        // Test IG account info
        try {
            $info = $this->apiGet($igAccountId, [
                'fields' => 'id,username,followers_count,media_count,biography',
            ], $pageToken);
            $this->info("   ✓ IG Account: @" . ($info['username'] ?? 'N/A') . " | Followers: " . ($info['followers_count'] ?? 'N/A'));
        } catch (Exception $e) {
            $this->error("   ✗ Cannot access IG account info: " . $e->getMessage());
            return;
        }

        // Test IG insights metrics (v21.0+ compatible)
        // [metric, period, extra_params]
        $igMetrics = [
            ['reach', 'day', []],
            ['follower_count', 'day', []],
            ['profile_views', 'day', ['metric_type' => 'total_value']],
            ['website_clicks', 'day', ['metric_type' => 'total_value']],
        ];

        foreach ($igMetrics as [$metric, $period, $extraParams]) {
            try {
                $params = array_merge([
                    'metric' => $metric,
                    'period' => $period,
                    'since' => date('Y-m-d', strtotime('-3 days')),
                    'until' => date('Y-m-d', strtotime('-1 day')),
                ], $extraParams);

                $response = $this->apiGet("{$igAccountId}/insights", $params, $pageToken);

                $data = $response['data'] ?? [];
                $valueCount = 0;
                foreach ($data as $entry) {
                    $valueCount += count($entry['values'] ?? []);
                    // total_value metrics have different structure
                    if (isset($entry['total_value'])) {
                        $valueCount++;
                    }
                }

                if ($valueCount > 0) {
                    $this->info("   ✓ IG {$metric}: {$valueCount} data points");
                } else {
                    $this->warn("   ⚠ IG {$metric}: 200 OK but 0 data points");
                }
            } catch (Exception $e) {
                $this->error("   ✗ IG {$metric}: " . substr($e->getMessage(), 0, 120));
            }
        }
    }

    private function checkPagePosts(): void
    {
        $this->section('8. PAGE POSTS TEST');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - Page ID or Token missing.');
            return;
        }

        try {
            $response = $this->apiGet("{$pageId}/published_posts", [
                'fields' => 'id,message,created_time',
                'limit' => 3,
                'since' => date('Y-m-d', strtotime('-7 days')),
            ], $pageToken);

            $data = $response['data'] ?? [];
            if (count($data) > 0) {
                $this->info("   ✓ Can access page posts. First " . count($data) . " posts:");
                foreach ($data as $post) {
                    $msg = substr($post['message'] ?? '(no message)', 0, 60);
                    $this->info("     - [{$post['id']}] {$msg}");
                }
            } else {
                $this->warn("   ⚠ No posts returned (page may have no posts or insufficient permissions)");
            }
        } catch (Exception $e) {
            $this->error("   ✗ Cannot access page posts: " . substr($e->getMessage(), 0, 150));
        }
    }

    private function checkMessaging(): void
    {
        $this->section('9. MESSAGING TEST');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - Page ID or Token missing.');
            return;
        }

        // Test Messenger conversations
        try {
            $response = $this->apiGet("{$pageId}/conversations", [
                'fields' => 'id,updated_time',
                'limit' => 3,
            ], $pageToken);

            $data = $response['data'] ?? [];
            $this->info("   ✓ Messenger conversations accessible. Found: " . count($data));
        } catch (Exception $e) {
            $this->error("   ✗ Messenger: " . substr($e->getMessage(), 0, 120));
        }

        // Test IG DM conversations (folder=instagram avoids timeout on large accounts)
        $igAccountId = config('meta.ig_account_id');
        if ($igAccountId) {
            try {
                $response = $this->apiGet("{$pageId}/conversations", [
                    'fields' => 'id,updated_time',
                    'folder' => 'instagram',
                    'limit' => 3,
                ], $pageToken);

                $data = $response['data'] ?? [];
                $this->info("   ✓ IG DM conversations accessible. Found: " . count($data));
            } catch (Exception $e) {
                $this->error("   ✗ IG DMs: " . substr($e->getMessage(), 0, 120));
            }
        }
    }

    private function checkAdAccount(): void
    {
        $this->section('10. AD ACCOUNT TEST');

        $adAccountId = config('meta.ad_account_id');
        $token = config('meta.token');

        if (!$adAccountId || !$token) {
            $this->warn('   Skipped - Ad Account ID or System Token missing.');
            return;
        }

        try {
            $response = $this->apiGet($adAccountId, [
                'fields' => 'id,name,account_status,currency,timezone_name,amount_spent',
            ], $token);

            $this->info("   ✓ Ad Account: {$response['name']} (ID: {$response['id']})");
            $this->info("     Status: " . $this->mapAdAccountStatus($response['account_status'] ?? 0));
            $this->info("     Currency: " . ($response['currency'] ?? 'N/A') . " | Timezone: " . ($response['timezone_name'] ?? 'N/A'));
            $amountSpent = ($response['amount_spent'] ?? 0) / 100;
            $this->info("     Amount Spent (lifetime): €" . number_format($amountSpent, 2));
        } catch (Exception $e) {
            $this->error("   ✗ Cannot access ad account: " . $e->getMessage());
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function apiGet(string $endpoint, array $params, string $token): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$endpoint}";
        $params['access_token'] = $token;

        $response = Http::timeout(15)->get($url, $params);

        if ($response->failed()) {
            $error = $response->json()['error'] ?? [];
            throw new Exception(($error['message'] ?? 'Unknown error') . ' [code: ' . ($error['code'] ?? 'N/A') . ']');
        }

        return $response->json();
    }

    private function section(string $title): void
    {
        $this->info('');
        $this->info("── {$title} " . str_repeat('─', max(0, 50 - strlen($title))));
    }

    private function mapAdAccountStatus(int $status): string
    {
        return match ($status) {
            1 => 'ACTIVE',
            2 => 'DISABLED',
            3 => 'UNSETTLED',
            7 => 'PENDING_RISK_REVIEW',
            8 => 'PENDING_SETTLEMENT',
            9 => 'IN_GRACE_PERIOD',
            100 => 'PENDING_CLOSURE',
            101 => 'CLOSED',
            201 => 'ANY_ACTIVE',
            202 => 'ANY_CLOSED',
            default => "UNKNOWN ({$status})",
        };
    }
}
