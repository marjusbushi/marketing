<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Log;
use Throwable;

class MetaMarketingV2SnapshotService
{
    public function __construct(
        private readonly MetaApiService $api,
    ) {}

    /**
     * Fetch a live v24 snapshot for Ads + Facebook + Instagram.
     */
    public function getSummary(string $from, string $to): array
    {
        $apiVersion = (string) config('meta.api_version', 'v24.0');
        $this->api->resetApiCallsCount();

        return $this->api->runWithApiVersion($apiVersion, function () use ($from, $to, $apiVersion) {
            return [
                'api_version' => $apiVersion,
                'date_range' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'generated_at' => now()->toIso8601String(),
                'ads' => $this->getAdsSummary($from, $to),
                'facebook' => $this->getFacebookSummary($from, $to),
                'instagram' => $this->getInstagramSummary($from, $to),
                'api_calls' => $this->api->getApiCallsCount(),
            ];
        });
    }

    private function getAdsSummary(string $from, string $to): array
    {
        $adAccountId = (string) config('meta.ad_account_id', '');
        if ($adAccountId === '') {
            return [
                'available' => false,
                'error' => 'META_AD_ACCOUNT_ID is not configured.',
            ];
        }

        $params = $this->withAttribution([
            'level' => 'account',
            'time_range' => json_encode(['since' => $from, 'until' => $to]),
            'time_increment' => 1,
            'fields' => 'date_start,impressions,reach,clicks,spend,actions,action_values',
        ]);

        $rows = $this->api->getAdsInsights($adAccountId, $params);

        $totals = [
            'spend' => 0.0,
            'impressions' => 0,
            'reach' => 0,
            'clicks' => 0,
            'link_clicks' => 0,
            'outbound_clicks' => 0,
            'landing_page_views' => 0,
            'purchases' => 0,
            'purchase_value' => 0.0,
            'messaging_conversations' => 0,
            'messaging_conversations_replied' => 0,
        ];

        foreach ($rows as $row) {
            $actions = $this->parseActions($row['actions'] ?? []);
            $actionValues = $this->parseActions($row['action_values'] ?? []);

            $totals['spend'] += (float) ($row['spend'] ?? 0);
            $totals['impressions'] += (int) ($row['impressions'] ?? 0);
            $totals['reach'] += (int) ($row['reach'] ?? 0);
            $totals['clicks'] += (int) ($row['clicks'] ?? 0);
            $totals['link_clicks'] += (int) ($actions['link_click'] ?? 0);
            $totals['outbound_clicks'] += (int) ($actions['outbound_click'] ?? 0);
            $totals['landing_page_views'] += (int) ($actions['landing_page_view'] ?? 0);
            $totals['purchases'] += (int) ($actions['purchase'] ?? ($actions['offsite_conversion.fb_pixel_purchase'] ?? 0));
            $totals['purchase_value'] += (float) ($actionValues['purchase'] ?? ($actionValues['offsite_conversion.fb_pixel_purchase'] ?? 0));
            $totals['messaging_conversations'] += (int) ($actions['onsite_conversion.total_messaging_connection'] ?? $actions['onsite_conversion.messaging_conversation_started_7d'] ?? 0);
            $totals['messaging_conversations_replied'] += (int) ($actions['onsite_conversion.messaging_conversation_replied_7d'] ?? 0);
        }

        $roas = $totals['spend'] > 0 ? round($totals['purchase_value'] / $totals['spend'], 2) : 0.0;
        $ctr = $totals['impressions'] > 0 ? round(($totals['link_clicks'] / $totals['impressions']) * 100, 2) : 0.0;
        $cpc = $totals['link_clicks'] > 0 ? round($totals['spend'] / $totals['link_clicks'], 2) : 0.0;
        $cpm = $totals['impressions'] > 0 ? round(($totals['spend'] / $totals['impressions']) * 1000, 2) : 0.0;

        return [
            'available' => true,
            'rows' => count($rows),
            'metrics' => [
                'spend' => round($totals['spend'], 2),
                'impressions' => $totals['impressions'],
                'reach' => $totals['reach'],
                'clicks' => $totals['clicks'],
                'link_clicks' => $totals['link_clicks'],
                'outbound_clicks' => $totals['outbound_clicks'],
                'landing_page_views' => $totals['landing_page_views'],
                'purchases' => $totals['purchases'],
                'purchase_value' => round($totals['purchase_value'], 2),
                'roas' => $roas,
                'ctr' => $ctr,
                'cpc' => $cpc,
                'cpm' => $cpm,
                'messaging_conversations' => $totals['messaging_conversations'],
                'messaging_conversations_replied' => $totals['messaging_conversations_replied'],
            ],
        ];
    }

    private function getFacebookSummary(string $from, string $to): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');

        if ($pageId === '' || $pageToken === '') {
            return [
                'available' => false,
                'error' => 'META_PAGE_ID or META_PAGE_TOKEN is not configured.',
            ];
        }

        $metricsMap = [
            'reach' => 'page_total_media_view_unique',
            'page_views' => 'page_views_total',
            'post_engagements' => 'page_post_engagements',
            'post_impressions' => 'page_media_view',
            'new_threads' => 'page_messages_new_threads',
            'video_views' => 'page_video_views',
        ];

        $totals = [];
        $errors = [];

        foreach ($metricsMap as $label => $apiMetric) {
            try {
                $response = $this->api->getPageInsights($pageId, $apiMetric, 'day', $from, $to);
                $totals[$label] = (int) round($this->sumInsightsResponse($response['data'] ?? []));
            } catch (Throwable $e) {
                $totals[$label] = 0;
                $errors[] = "{$apiMetric}: {$e->getMessage()}";
            }
        }

        $pageInfo = [
            'fan_count' => 0,
            'followers_count' => 0,
        ];

        try {
            $pageMeta = $this->api->getWithPageToken($pageId, ['fields' => 'fan_count,followers_count,name']);
            $pageInfo['fan_count'] = (int) ($pageMeta['fan_count'] ?? 0);
            $pageInfo['followers_count'] = (int) ($pageMeta['followers_count'] ?? 0);
            $pageInfo['name'] = (string) ($pageMeta['name'] ?? '');
        } catch (Throwable $e) {
            $errors[] = "page_info: {$e->getMessage()}";
        }

        return [
            'available' => true,
            'metrics' => array_merge($totals, $pageInfo),
            'errors' => $errors,
        ];
    }

    private function getInstagramSummary(string $from, string $to): array
    {
        $igAccountId = (string) config('meta.ig_account_id', '');
        $pageToken = (string) config('meta.page_token', '');

        if ($igAccountId === '') {
            $igAccountId = $this->discoverIgAccountId() ?? '';
        }

        if ($igAccountId === '' || $pageToken === '') {
            return [
                'available' => false,
                'error' => 'META_IG_ACCOUNT_ID (or discovery) / META_PAGE_TOKEN is not configured.',
            ];
        }

        $metricsMap = [
            'reach' => 'reach',
            'views' => 'views',
            'accounts_engaged' => 'accounts_engaged',
            'total_interactions' => 'total_interactions',
            'profile_views' => 'profile_views',
            'website_clicks' => 'website_clicks',
            'likes' => 'likes',
            'comments' => 'comments',
            'shares' => 'shares',
            'saves' => 'saves',
            'replies' => 'replies',
        ];

        $totals = array_fill_keys(array_keys($metricsMap), 0);
        $errors = [];

        try {
            $response = $this->api->getIgInsights(
                $igAccountId,
                implode(',', array_values($metricsMap)),
                'day',
                $from,
                $to,
                'total_value'
            );

            foreach ($response['data'] ?? [] as $entry) {
                $name = (string) ($entry['name'] ?? '');
                $key = array_search($name, $metricsMap, true);
                if ($key === false) {
                    continue;
                }
                $totals[$key] = (int) round($this->normalizeMetricValue($entry['total_value']['value'] ?? 0));
            }
        } catch (Throwable $e) {
            $errors[] = "batched_metrics: {$e->getMessage()}";

            // Fallback to per-metric calls if the batched request is rejected.
            foreach ($metricsMap as $key => $apiMetric) {
                try {
                    $response = $this->api->getIgInsights($igAccountId, $apiMetric, 'day', $from, $to, 'total_value');
                    $metricValue = 0;
                    foreach ($response['data'] ?? [] as $entry) {
                        $metricValue += $this->normalizeMetricValue($entry['total_value']['value'] ?? 0);
                    }
                    $totals[$key] = (int) round($metricValue);
                } catch (Throwable $inner) {
                    $errors[] = "{$apiMetric}: {$inner->getMessage()}";
                }
            }
        }

        $netNewFollowers = 0;
        try {
            $followerResponse = $this->api->getIgInsights($igAccountId, 'follower_count', 'day', $from, $to);
            foreach ($followerResponse['data'] ?? [] as $entry) {
                foreach ($entry['values'] ?? [] as $value) {
                    $netNewFollowers += (int) ($value['value'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            $errors[] = "follower_count: {$e->getMessage()}";
        }

        $accountInfo = [
            'username' => null,
            'followers_count' => 0,
            'media_count' => 0,
        ];

        try {
            $account = $this->api->getIgAccountInfo($igAccountId, ['username', 'followers_count', 'media_count']);
            $accountInfo = [
                'username' => $account['username'] ?? null,
                'followers_count' => (int) ($account['followers_count'] ?? 0),
                'media_count' => (int) ($account['media_count'] ?? 0),
            ];
        } catch (Throwable $e) {
            $errors[] = "account_info: {$e->getMessage()}";
        }

        return [
            'available' => true,
            'ig_account_id' => $igAccountId,
            'metrics' => array_merge($totals, [
                'net_new_followers' => $netNewFollowers,
                'followers_snapshot' => $accountInfo['followers_count'],
                'media_count' => $accountInfo['media_count'],
                'username' => $accountInfo['username'],
            ]),
            'errors' => $errors,
        ];
    }

    private function discoverIgAccountId(): ?string
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');

        if ($pageId === '' || $pageToken === '') {
            return null;
        }

        try {
            $result = $this->api->getWithPageToken($pageId, [
                'fields' => 'instagram_business_account{id,username}',
            ]);

            $igId = $result['instagram_business_account']['id'] ?? null;
            if ($igId) {
                Log::info("Meta v2 snapshot auto-discovered IG account: {$igId}");
            }

            return $igId;
        } catch (Throwable $e) {
            Log::debug('Meta v2 snapshot could not auto-discover IG account: ' . $e->getMessage());
            return null;
        }
    }

    private function parseActions(array $actions): array
    {
        $parsed = [];

        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;
            if (!$type) {
                continue;
            }

            $value = $this->normalizeMetricValue($action['value'] ?? 0);
            $parsed[$type] = ($parsed[$type] ?? 0) + $value;
        }

        return $parsed;
    }

    private function withAttribution(array $params): array
    {
        $configured = config('meta.ads_attribution', ['use_account_attribution_setting' => true]);

        if (isset($configured['use_account_attribution_setting'])) {
            $configured['use_account_attribution_setting'] = $configured['use_account_attribution_setting'] ? 'true' : 'false';
        }

        return array_merge($configured, $params);
    }

    private function sumInsightsResponse(array $data): float
    {
        $sum = 0.0;

        foreach ($data as $entry) {
            foreach ($entry['values'] ?? [] as $value) {
                $sum += $this->normalizeMetricValue($value['value'] ?? 0);
            }
        }

        return $sum;
    }

    private function normalizeMetricValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            $sum = 0.0;
            foreach ($value as $item) {
                $sum += $this->normalizeMetricValue($item);
            }
            return $sum;
        }

        return 0.0;
    }
}
