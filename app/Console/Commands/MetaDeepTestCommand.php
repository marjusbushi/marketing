<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class MetaDeepTestCommand extends Command
{
    protected $signature = 'meta:deep-test {--section= : Run specific section (ads,page,ig,messenger,igdm,posts,threads)}';
    protected $description = 'Deep API data test — fetches actual data from Meta APIs to verify what is coming through';

    private string $baseUrl;
    private string $apiVersion;

    public function handle(): int
    {
        Artisan::call('config:clear');

        $this->baseUrl = config('meta.base_url', 'https://graph.facebook.com');
        $this->apiVersion = config('meta.api_version', 'v24.0');

        $section = $this->option('section');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║       META DEEP DATA TEST                           ║');
        $this->info('╚══════════════════════════════════════════════════════╝');

        if (!$section || $section === 'ads') $this->testAdsInsights();
        if (!$section || $section === 'page') $this->testPageInsights();
        if (!$section || $section === 'ig') $this->testIgInsights();
        if (!$section || $section === 'messenger') $this->testMessengerConversations();
        if (!$section || $section === 'igdm') $this->testIgDmConversations();
        if (!$section || $section === 'posts') $this->testPagePosts();
        if (!$section || $section === 'threads') $this->testNewThreads();

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Deep test complete.');
        $this->info('═══════════════════════════════════════════════════════');

        return self::SUCCESS;
    }

    // ─── ADS INSIGHTS ──────────────────────────────────────────────────

    private function testAdsInsights(): void
    {
        $this->section('ADS INSIGHTS (last 7 days)');

        $adAccountId = config('meta.ad_account_id');
        $token = config('meta.token');

        if (!$adAccountId || !$token) {
            $this->warn('   Skipped - missing ad_account_id or token');
            return;
        }

        $since = date('Y-m-d', strtotime('-7 days'));
        $until = date('Y-m-d', strtotime('-1 day'));

        // Account-level daily insights
        try {
            $response = $this->apiGet("{$adAccountId}/insights", [
                'level' => 'account',
                'time_range' => json_encode(['since' => $since, 'until' => $until]),
                'time_increment' => 1,
                'fields' => 'date_start,impressions,reach,clicks,spend,actions,action_values',
                'use_account_attribution_setting' => 'true',
            ], $token, 60);

            $data = $response['data'] ?? [];
            $this->info("   Found {$since} → {$until}: " . count($data) . " daily rows");

            $totalSpend = 0;
            $totalImpressions = 0;
            $totalReach = 0;
            $totalClicks = 0;

            foreach ($data as $row) {
                $spend = (float) ($row['spend'] ?? 0);
                $impressions = (int) ($row['impressions'] ?? 0);
                $reach = (int) ($row['reach'] ?? 0);
                $clicks = (int) ($row['clicks'] ?? 0);
                $totalSpend += $spend;
                $totalImpressions += $impressions;
                $totalReach += $reach;
                $totalClicks += $clicks;

                $actions = $this->parseActions($row['actions'] ?? []);
                $actionValues = $this->parseActions($row['action_values'] ?? []);
                $purchases = $actions['purchase'] ?? ($actions['offsite_conversion.fb_pixel_purchase'] ?? 0);
                $purchaseValue = $actionValues['purchase'] ?? ($actionValues['offsite_conversion.fb_pixel_purchase'] ?? 0);

                $this->info("     {$row['date_start']}: spend=\${$spend} imp={$impressions} reach={$reach} clicks={$clicks} purchases={$purchases} value=\${$purchaseValue}");
            }

            $this->info("   TOTALS: spend=\${$totalSpend} imp={$totalImpressions} reach={$totalReach} clicks={$totalClicks}");

            if (count($data) === 0) {
                $this->warn('   ⚠ No ads data — are there active campaigns?');
            }
        } catch (Exception $e) {
            $this->error("   ✗ Ads insights failed: " . $e->getMessage());
        }

        // Campaign list
        try {
            $response = $this->apiGet("{$adAccountId}/campaigns", [
                'fields' => 'id,name,status,objective',
                'limit' => 10,
            ], $token, 30);

            $campaigns = $response['data'] ?? [];
            $this->info("   Campaigns found: " . count($campaigns));
            foreach ($campaigns as $c) {
                $objective = $c['objective'] ?? 'N/A';
                $this->info("     [{$c['status']}] {$c['name']} ({$objective})");
            }
        } catch (Exception $e) {
            $this->error("   ✗ Campaign list failed: " . $e->getMessage());
        }

        // Platform breakdown
        try {
            $response = $this->apiGet("{$adAccountId}/insights", [
                'level' => 'account',
                'time_range' => json_encode(['since' => $since, 'until' => $until]),
                'breakdowns' => 'publisher_platform',
                'fields' => 'impressions,reach,clicks,spend',
                'use_account_attribution_setting' => 'true',
            ], $token, 60);

            $data = $response['data'] ?? [];
            $this->info("   Platform breakdown:");
            foreach ($data as $row) {
                $platform = $row['publisher_platform'] ?? 'unknown';
                $this->info("     {$platform}: spend=\${$row['spend']} imp={$row['impressions']} reach={$row['reach']}");
            }
        } catch (Exception $e) {
            $this->error("   ✗ Platform breakdown failed: " . $e->getMessage());
        }
    }

    // ─── PAGE INSIGHTS ─────────────────────────────────────────────────

    private function testPageInsights(): void
    {
        $this->section('PAGE INSIGHTS (last 7 days)');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - missing page_id or page_token');
            return;
        }

        $since = date('Y-m-d', strtotime('-7 days'));
        $until = date('Y-m-d', strtotime('-1 day'));

        $metrics = [
            'page_impressions_unique' => 'page_reach',
            'page_views_total' => 'page_views',
            'page_post_engagements' => 'post_engagements',
            'page_posts_impressions' => 'posts_impressions',
            'page_video_views' => 'video_views',
            'page_messages_new_threads' => 'new_msg_threads',
        ];

        foreach ($metrics as $apiMetric => $label) {
            try {
                $response = $this->apiGet("{$pageId}/insights", [
                    'metric' => $apiMetric,
                    'period' => 'day',
                    'since' => $since,
                    'until' => $until,
                ], $pageToken, 30);

                $data = $response['data'] ?? [];
                $values = [];
                foreach ($data as $entry) {
                    foreach ($entry['values'] ?? [] as $v) {
                        $date = substr($v['end_time'] ?? '', 0, 10);
                        $values[] = "{$date}=" . ($v['value'] ?? 0);
                    }
                }

                if (!empty($values)) {
                    $this->info("   ✓ {$label}: " . implode(', ', $values));
                } else {
                    $this->warn("   ⚠ {$label}: 0 data points");
                }
            } catch (Exception $e) {
                $this->error("   ✗ {$label}: " . substr($e->getMessage(), 0, 120));
            }
        }

        // Also test page_impressions (the one that failed in diagnose)
        try {
            $response = $this->apiGet("{$pageId}/insights", [
                'metric' => 'page_impressions',
                'period' => 'day',
                'since' => $since,
                'until' => $until,
            ], $pageToken, 30);

            $data = $response['data'] ?? [];
            $valueCount = 0;
            foreach ($data as $entry) {
                $valueCount += count($entry['values'] ?? []);
            }
            $this->info("   ✓ page_impressions: {$valueCount} data points");
        } catch (Exception $e) {
            $this->warn("   ⚠ page_impressions (deprecated?): " . substr($e->getMessage(), 0, 120));
        }

        // Fan/follower count
        try {
            $response = $this->apiGet($pageId, [
                'fields' => 'fan_count,followers_count',
            ], $pageToken, 15);

            $this->info("   Fan count: " . ($response['fan_count'] ?? 'N/A') . " | Followers: " . ($response['followers_count'] ?? 'N/A'));
        } catch (Exception $e) {
            $this->error("   ✗ Fan count: " . $e->getMessage());
        }
    }

    // ─── IG INSIGHTS ───────────────────────────────────────────────────

    private function testIgInsights(): void
    {
        $this->section('INSTAGRAM INSIGHTS (last 7 days)');

        $igAccountId = config('meta.ig_account_id');
        $pageToken = config('meta.page_token');

        if (!$igAccountId || !$pageToken) {
            $this->warn('   Skipped - missing ig_account_id or page_token');
            return;
        }

        $since = date('Y-m-d', strtotime('-7 days'));
        $until = date('Y-m-d', strtotime('-1 day'));

        // Account info
        try {
            $response = $this->apiGet($igAccountId, [
                'fields' => 'id,username,followers_count,media_count',
            ], $pageToken, 15);

            $this->info("   Account: @{$response['username']} | Followers: {$response['followers_count']} | Media: {$response['media_count']}");
        } catch (Exception $e) {
            $this->error("   ✗ Account info: " . $e->getMessage());
        }

        // total_value metrics (day by day for last 3 days to keep it manageable)
        $totalMetrics = 'reach,views,accounts_engaged,total_interactions,likes,comments,shares,saves,replies,profile_views,website_clicks';

        for ($i = 3; $i >= 1; $i--) {
            $dayStr = date('Y-m-d', strtotime("-{$i} days"));
            $nextDay = date('Y-m-d', strtotime("-" . ($i - 1) . " days"));

            try {
                $response = $this->apiGet("{$igAccountId}/insights", [
                    'metric' => $totalMetrics,
                    'period' => 'day',
                    'since' => $dayStr,
                    'until' => $nextDay,
                    'metric_type' => 'total_value',
                ], $pageToken, 30);

                $data = $response['data'] ?? [];
                $dayData = [];
                foreach ($data as $entry) {
                    $dayData[$entry['name']] = $entry['total_value']['value'] ?? 0;
                }

                $r = $dayData['reach'] ?? 0;
                $v = $dayData['views'] ?? 0;
                $eng = $dayData['accounts_engaged'] ?? 0;
                $int = $dayData['total_interactions'] ?? 0;
                $lik = $dayData['likes'] ?? 0;
                $com = $dayData['comments'] ?? 0;
                $shr = $dayData['shares'] ?? 0;
                $this->info("   {$dayStr}: reach={$r} views={$v} engaged={$eng} interactions={$int} likes={$lik} comments={$com} shares={$shr}");
            } catch (Exception $e) {
                $this->error("   ✗ {$dayStr}: " . substr($e->getMessage(), 0, 120));
            }
        }

        // follower_count (daily net change)
        try {
            $response = $this->apiGet("{$igAccountId}/insights", [
                'metric' => 'follower_count',
                'period' => 'day',
                'since' => $since,
                'until' => $until,
            ], $pageToken, 30);

            $data = $response['data'] ?? [];
            $values = [];
            foreach ($data as $entry) {
                foreach ($entry['values'] ?? [] as $v) {
                    $date = substr($v['end_time'] ?? '', 0, 10);
                    $values[] = "{$date}=" . ($v['value'] ?? 0);
                }
            }
            $this->info("   follower_count (net change): " . implode(', ', $values));
        } catch (Exception $e) {
            $this->error("   ✗ follower_count: " . $e->getMessage());
        }
    }

    // ─── MESSENGER CONVERSATIONS ───────────────────────────────────────

    private function testMessengerConversations(): void
    {
        $this->section('MESSENGER CONVERSATIONS');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - missing page_id or page_token');
            return;
        }

        try {
            $response = $this->apiGet("{$pageId}/conversations", [
                'fields' => 'id,updated_time,message_count',
                'limit' => 10,
            ], $pageToken, 60);

            $data = $response['data'] ?? [];
            $this->info("   Found " . count($data) . " Messenger conversations");

            foreach ($data as $conv) {
                $msgCount = $conv['message_count'] ?? '?';
                $this->info("     [{$conv['id']}] updated={$conv['updated_time']} messages={$msgCount}");

                // Fetch first 3 messages from the most recent conversation
                if ($conv === $data[0]) {
                    try {
                        $convId = $conv['id'];
                        $msgResponse = $this->apiGet("{$convId}/messages", [
                            'fields' => 'from,created_time,message',
                            'limit' => 3,
                        ], $pageToken, 30);

                        $messages = $msgResponse['data'] ?? [];
                        foreach ($messages as $msg) {
                            $from = $msg['from']['name'] ?? ($msg['from']['id'] ?? '?');
                            $text = substr($msg['message'] ?? '(no text)', 0, 50);
                            $this->info("       > [{$msg['created_time']}] {$from}: {$text}");
                        }
                    } catch (Exception $e) {
                        $this->warn("       Could not fetch messages: " . substr($e->getMessage(), 0, 80));
                    }
                }
            }

            $hasPaging = isset($response['paging']['next']);
            $this->info("   Has more pages: " . ($hasPaging ? 'YES' : 'NO'));
        } catch (Exception $e) {
            $this->error("   ✗ Messenger conversations: " . $e->getMessage());
        }
    }

    // ─── IG DM CONVERSATIONS ───────────────────────────────────────────

    private function testIgDmConversations(): void
    {
        $this->section('INSTAGRAM DM CONVERSATIONS');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - missing page_id or page_token');
            return;
        }

        // Test 1: Minimal fields, low limit, longer timeout
        $this->info('   Test 1: Minimal request (limit=5, fields=id,updated_time)');
        try {
            $response = $this->apiGet("{$pageId}/conversations", [
                'fields' => 'id,updated_time',
                'folder' => 'instagram',
                'limit' => 5,
            ], $pageToken, 120);

            $data = $response['data'] ?? [];
            $this->info("   ✓ Found " . count($data) . " IG DM conversations");

            foreach ($data as $conv) {
                $this->info("     [{$conv['id']}] updated={$conv['updated_time']}");
            }

            $hasPaging = isset($response['paging']['next']);
            $this->info("   Has more pages: " . ($hasPaging ? 'YES' : 'NO'));

            // Try fetching messages from first conversation
            if (!empty($data)) {
                $firstConv = $data[0];
                $this->info("   Fetching messages from first conversation ({$firstConv['id']})...");
                try {
                    $msgResponse = $this->apiGet("{$firstConv['id']}/messages", [
                        'fields' => 'from,created_time',
                        'limit' => 5,
                    ], $pageToken, 60);

                    $messages = $msgResponse['data'] ?? [];
                    $this->info("   ✓ Found " . count($messages) . " messages in conversation");

                    foreach ($messages as $msg) {
                        $fromId = $msg['from']['id'] ?? '?';
                        $isPage = $fromId === $pageId;
                        $label = $isPage ? 'SENT' : 'RECEIVED';
                        $this->info("     > [{$msg['created_time']}] {$label} (from: {$fromId})");
                    }
                } catch (Exception $e) {
                    $this->error("   ✗ Messages fetch failed: " . substr($e->getMessage(), 0, 120));
                }
            }
        } catch (Exception $e) {
            $this->error("   ✗ IG DM test 1 failed: " . $e->getMessage());

            // Test 2: Try with user_id filter (different approach)
            $this->info('   Test 2: Without platform filter, using folder=instagram');
            try {
                $response = $this->apiGet("{$pageId}/conversations", [
                    'fields' => 'id,updated_time',
                    'folder' => 'instagram',
                    'limit' => 3,
                ], $pageToken, 120);

                $data = $response['data'] ?? [];
                $this->info("   ✓ folder=instagram found " . count($data) . " conversations");
            } catch (Exception $e2) {
                $this->error("   ✗ IG DM test 2 also failed: " . substr($e2->getMessage(), 0, 120));
            }
        }
    }

    // ─── PAGE POSTS ────────────────────────────────────────────────────

    private function testPagePosts(): void
    {
        $this->section('PAGE POSTS');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - missing page_id or page_token');
            return;
        }

        // Test with minimal fields and small limit
        $this->info('   Test: Minimal fields, limit=5');
        try {
            $response = $this->apiGet("{$pageId}/posts", [
                'fields' => 'id,created_time',
                'limit' => 5,
            ], $pageToken, 30);

            $data = $response['data'] ?? [];
            $this->info("   ✓ Found " . count($data) . " posts");

            foreach ($data as $post) {
                $this->info("     [{$post['id']}] {$post['created_time']}");
            }
        } catch (Exception $e) {
            $this->error("   ✗ Minimal post fetch failed: " . $e->getMessage());
        }

        // Test with message field
        $this->info('   Test: With message, limit=3');
        try {
            $response = $this->apiGet("{$pageId}/posts", [
                'fields' => 'id,message,created_time,shares',
                'limit' => 3,
            ], $pageToken, 30);

            $data = $response['data'] ?? [];
            foreach ($data as $post) {
                $msg = substr($post['message'] ?? '(no text)', 0, 60);
                $shares = $post['shares']['count'] ?? 0;
                $this->info("     [{$post['created_time']}] shares={$shares} | {$msg}");
            }
        } catch (Exception $e) {
            $this->error("   ✗ Post with message failed: " . $e->getMessage());
        }
    }

    // ─── NEW MESSAGE THREADS (page_messages_new_threads) ───────────────

    private function testNewThreads(): void
    {
        $this->section('NEW MESSAGE THREADS METRIC');

        $pageId = config('meta.page_id');
        $pageToken = config('meta.page_token');

        if (!$pageId || !$pageToken) {
            $this->warn('   Skipped - missing page_id or page_token');
            return;
        }

        $since = date('Y-m-d', strtotime('-28 days'));
        $until = date('Y-m-d', strtotime('-1 day'));

        // Test page_messages_new_threads
        $this->info("   Testing page_messages_new_threads ({$since} → {$until})");
        try {
            $response = $this->apiGet("{$pageId}/insights", [
                'metric' => 'page_messages_new_threads',
                'period' => 'day',
                'since' => $since,
                'until' => $until,
            ], $pageToken, 60);

            $data = $response['data'] ?? [];
            $values = [];
            $total = 0;
            foreach ($data as $entry) {
                foreach ($entry['values'] ?? [] as $v) {
                    $date = substr($v['end_time'] ?? '', 0, 10);
                    $val = $v['value'] ?? 0;
                    $total += $val;
                    if ($val > 0) {
                        $values[] = "{$date}={$val}";
                    }
                }
            }

            if (!empty($values)) {
                $this->info("   ✓ New threads total: {$total}");
                $this->info("     Days with threads: " . implode(', ', $values));
            } else {
                $this->warn("   ⚠ page_messages_new_threads returned all zeros for 28 days");
            }
        } catch (Exception $e) {
            $this->error("   ✗ page_messages_new_threads: " . substr($e->getMessage(), 0, 120));
        }

        // Also test the conversation count directly
        $this->info("   Testing direct conversation count (since={$since})");
        try {
            $response = $this->apiGet("{$pageId}/conversations", [
                'fields' => 'id,updated_time',
                'limit' => 100,
                'since' => strtotime($since),
            ], $pageToken, 60);

            $data = $response['data'] ?? [];
            $this->info("   ✓ Messenger conversations since {$since}: " . count($data));

            // Group by date
            $byDate = [];
            foreach ($data as $conv) {
                $date = substr($conv['updated_time'] ?? '', 0, 10);
                $byDate[$date] = ($byDate[$date] ?? 0) + 1;
            }
            ksort($byDate);
            foreach ($byDate as $date => $count) {
                $this->info("     {$date}: {$count} conversations");
            }
        } catch (Exception $e) {
            $this->error("   ✗ Direct conversation count: " . substr($e->getMessage(), 0, 120));
        }

        // IG DM conversation count
        $this->info("   Testing IG DM conversation count (since={$since})");
        try {
            $response = $this->apiGet("{$pageId}/conversations", [
                'fields' => 'id,updated_time',
                'folder' => 'instagram',
                'limit' => 50,
                'since' => strtotime($since),
            ], $pageToken, 120);

            $data = $response['data'] ?? [];
            $this->info("   ✓ IG DM conversations since {$since}: " . count($data));

            $byDate = [];
            foreach ($data as $conv) {
                $date = substr($conv['updated_time'] ?? '', 0, 10);
                $byDate[$date] = ($byDate[$date] ?? 0) + 1;
            }
            ksort($byDate);
            foreach ($byDate as $date => $count) {
                $this->info("     {$date}: {$count} conversations");
            }
        } catch (Exception $e) {
            $this->error("   ✗ IG DM count: " . substr($e->getMessage(), 0, 120));
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function apiGet(string $endpoint, array $params, string $token, int $timeout = 30): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$endpoint}";
        $params['access_token'] = $token;

        $response = Http::timeout($timeout)->connectTimeout(15)->get($url, $params);

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

    private function parseActions(array $actions): array
    {
        $parsed = [];
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;
            $value = $action['value'] ?? 0;
            if ($type) {
                $parsed[$type] = is_numeric($value) ? (float) $value : 0;
            }
        }
        return $parsed;
    }
}
