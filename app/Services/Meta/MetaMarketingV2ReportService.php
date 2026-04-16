<?php

namespace App\Services\Meta;

use App\Services\Tiktok\TiktokAdsSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaMarketingV2ReportService
{
    private bool $lastCacheHit = false;

    public function __construct(
        private readonly MetaApiService $api,
        private readonly ?MetaDataResolverService $resolver = null,
    ) {}

    private function isDbFirst(): bool
    {
        return config('meta.features.db_first_mode', false) && $this->resolver !== null;
    }

    public function wasCacheHit(): bool
    {
        return $this->lastCacheHit;
    }

    public function getApiVersion(): string
    {
        return (string) config('meta.api_version', 'v24.0');
    }

    /**
     * Return KPI payload with the same schema as totalKpis() from the legacy dashboard.
     */
    public function totalKpis(string $from, string $to, ?string $preset = null, bool $noCache = false): array
    {
        $preset = $this->normalizePreset($preset);

        $currentWindow = $this->safeWindowTotals($from, $to, $preset, $noCache);
        $prevFrom = Carbon::parse($from)->subYear()->toDateString();
        $prevTo = Carbon::parse($to)->subYear()->toDateString();
        // YoY: DB only when db_first_mode is on — never call Meta API for previous year
        $previousWindow = $this->safeWindowTotals($prevFrom, $prevTo, null, $noCache, $this->isDbFirst());

        $current = $currentWindow['data'];
        $previous = $previousWindow['data'];
        $hasComparableData = $currentWindow['available'] && $previousWindow['available'];

        // In DB-first mode, check if previous year has period totals in DB
        if ($this->isDbFirst() && $hasComparableData && $this->resolver) {
            $hasComparableData = $this->resolver->hasYoYDataForRange($prevFrom, $prevTo);
        }

        $ads = $current['ads'];
        $prevAds = $previous['ads'];
        $fb = $current['facebook'];
        $prevFb = $previous['facebook'];
        $ig = $current['instagram'];
        $prevIg = $previous['instagram'];

        $hasTiktok = $this->isTiktokEnabled();
        $tt = $hasTiktok ? ($current['tiktok'] ?? TiktokAdsSyncService::emptyTotals()) : TiktokAdsSyncService::emptyTotals();
        $prevTt = $hasTiktok ? ($previous['tiktok'] ?? TiktokAdsSyncService::emptyTotals()) : TiktokAdsSyncService::emptyTotals();
        $ttEngagement = ($tt['likes'] ?? 0) + ($tt['comments'] ?? 0) + ($tt['shares'] ?? 0);
        $prevTtEngagement = ($prevTt['likes'] ?? 0) + ($prevTt['comments'] ?? 0) + ($prevTt['shares'] ?? 0);

        $totalReach = $ads['reach'] + $fb['reach'] + $ig['reach'] + ($tt['reach'] ?? 0);
        $prevTotalReach = $prevAds['reach'] + $prevFb['reach'] + $prevIg['reach'] + ($prevTt['reach'] ?? 0);

        $totalImpressions = $fb['impressions'] + $ig['views'] + ($tt['impressions'] ?? 0);
        $prevTotalImpressions = $prevFb['impressions'] + $prevIg['views'] + ($prevTt['impressions'] ?? 0);

        $totalPageViews = $fb['page_views'] + $ig['profile_views'];
        $prevTotalPageViews = $prevFb['page_views'] + $prevIg['profile_views'];

        $totalEngagement = $fb['post_engagement'] + $ig['engagement'] + $ttEngagement;
        $prevTotalEngagement = $prevFb['post_engagement'] + $prevIg['engagement'] + $prevTtEngagement;

        $totalOrganicLinkClicks = $fb['link_clicks'] + $ig['link_clicks'];
        $prevTotalOrganicLinkClicks = $prevFb['link_clicks'] + $prevIg['link_clicks'];

        $totalAdsLinkClicks = $ads['link_clicks'] + ($tt['clicks'] ?? 0);
        $prevTotalAdsLinkClicks = $prevAds['link_clicks'] + ($prevTt['clicks'] ?? 0);

        $combinedLinkClicks = $totalOrganicLinkClicks + $totalAdsLinkClicks;
        $prevCombinedLinkClicks = $prevTotalOrganicLinkClicks + $prevTotalAdsLinkClicks;

        $totalThreads = $fb['conversations'] + $ig['conversations'];
        $prevTotalThreads = $prevFb['conversations'] + $prevIg['conversations'];

        $totalConversations = ($fb['received'] + $fb['sent']) + ($ig['received'] + $ig['sent']);
        $prevTotalConversations = ($prevFb['received'] + $prevFb['sent']) + ($prevIg['received'] + $prevIg['sent']);

        $totalAdsSpend = $ads['spend'] + ($tt['spend'] ?? 0);
        $prevTotalAdsSpend = $prevAds['spend'] + ($prevTt['spend'] ?? 0);
        $totalAdsRevenue = ($ads['revenue'] ?? 0) + ($tt['purchase_value'] ?? 0);
        $prevTotalAdsRevenue = ($prevAds['revenue'] ?? 0) + ($prevTt['purchase_value'] ?? 0);

        $roas = $totalAdsSpend > 0 ? round($totalAdsRevenue / $totalAdsSpend, 2) : 0.0;
        $prevRoas = $prevTotalAdsSpend > 0 ? round($prevTotalAdsRevenue / $prevTotalAdsSpend, 2) : 0.0;

        $result = [
            'total_reach' => [
                'value' => (int) $totalReach,
                'change' => $this->calcWindowChange($totalReach, $prevTotalReach, $hasComparableData),
                'note' => 'combined_non_deduplicated',
            ],
            'total_impressions' => [
                'value' => (int) $totalImpressions,
                'change' => $this->calcWindowChange($totalImpressions, $prevTotalImpressions, $hasComparableData),
            ],
            'total_page_views' => [
                'value' => (int) $totalPageViews,
                'change' => $this->calcWindowChange($totalPageViews, $prevTotalPageViews, $hasComparableData),
            ],
            'total_engagement' => [
                'value' => (int) $totalEngagement,
                'change' => $this->calcWindowChange($totalEngagement, $prevTotalEngagement, $hasComparableData),
            ],
            'combined_link_clicks' => [
                'value' => (int) $combinedLinkClicks,
                'change' => $this->calcWindowChange($combinedLinkClicks, $prevCombinedLinkClicks, $hasComparableData),
            ],
            'total_link_clicks' => [
                'value' => (int) $totalOrganicLinkClicks,
                'change' => $this->calcWindowChange($totalOrganicLinkClicks, $prevTotalOrganicLinkClicks, $hasComparableData),
            ],
            'ads_link_clicks' => [
                'value' => (int) $totalAdsLinkClicks,
                'change' => $this->calcWindowChange($totalAdsLinkClicks, $prevTotalAdsLinkClicks, $hasComparableData),
            ],
            'new_threads' => [
                'value' => (int) $totalThreads,
                'change' => $this->calcWindowChange($totalThreads, $prevTotalThreads, $hasComparableData),
            ],
            'conversations' => [
                'value' => (int) $totalConversations,
                'change' => $this->calcWindowChange($totalConversations, $prevTotalConversations, $hasComparableData),
            ],
            'ads_spend' => [
                'value' => round($totalAdsSpend, 2),
                'change' => $this->calcWindowChange($totalAdsSpend, $prevTotalAdsSpend, $hasComparableData),
            ],
            'ads_revenue' => [
                'value' => round($totalAdsRevenue, 2),
                'change' => $this->calcWindowChange($totalAdsRevenue, $prevTotalAdsRevenue, $hasComparableData),
            ],
            'roas' => [
                'value' => $roas,
                'change' => $this->calcWindowChange($roas, $prevRoas, $hasComparableData),
            ],
            'fb_reach' => [
                'value' => (int) $fb['reach'],
                'change' => $this->calcWindowChange($fb['reach'], $prevFb['reach'], $hasComparableData),
            ],
            'ig_reach' => [
                'value' => (int) $ig['reach'],
                'change' => $this->calcWindowChange($ig['reach'], $prevIg['reach'], $hasComparableData),
            ],
        ];

        if ($hasTiktok) {
            $result['tiktok_spend'] = [
                'value' => round($tt['spend'] ?? 0, 2),
                'change' => $this->calcWindowChange($tt['spend'] ?? 0, $prevTt['spend'] ?? 0, $hasComparableData),
            ];
            $result['tiktok_reach'] = [
                'value' => (int) ($tt['reach'] ?? 0),
                'change' => $this->calcWindowChange($tt['reach'] ?? 0, $prevTt['reach'] ?? 0, $hasComparableData),
            ];
            $result['tiktok_video_views'] = [
                'value' => (int) ($tt['video_views'] ?? 0),
                'change' => $this->calcWindowChange($tt['video_views'] ?? 0, $prevTt['video_views'] ?? 0, $hasComparableData),
            ];
        }

        return $result;
    }

    /**
     * Return daily payload with the same schema as totalDaily() from legacy dashboard.
     */
    public function totalDaily(string $from, string $to, bool $noCache = false): array
    {
        $cacheVersion = Cache::get('meta_cache_version', 0);
        $cacheKey = $this->getApiVersion() . ":v{$cacheVersion}:meta_v2_daily:{$from}:{$to}";
        if (!$noCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->lastCacheHit = true;
                Log::info("ReportService totalDaily: CACHE HIT [{$from}..{$to}]");
                return (array) $cached;
            }
        }
        $this->lastCacheHit = false;

        if ($this->isDbFirst()) {
            Log::info("ReportService totalDaily: DB-first [{$from}..{$to}]");
            $hasTiktok = $this->isTiktokEnabled();
            $dates = [];
            for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
                $row = [
                    'date' => $d->toDateString(),
                    'fb_reach' => 0,
                    'fb_impressions' => 0,
                    'ig_reach' => 0,
                    'ig_profile_views' => 0,
                    'ads_spend' => 0,
                    'ads_reach' => 0,
                    'ads_link_clicks' => 0,
                ];
                if ($hasTiktok) {
                    $row['tiktok_spend'] = 0;
                    $row['tiktok_reach'] = 0;
                    $row['tiktok_video_views'] = 0;
                }
                $dates[$d->toDateString()] = $row;
            }

            foreach ($this->resolver->resolveAdsDaily($from, $to) as $row) {
                $day = $row['date'];
                if (isset($dates[$day])) {
                    $dates[$day]['ads_spend'] = round((float) ($row['spend'] ?? 0), 2);
                    $dates[$day]['ads_reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$day]['ads_link_clicks'] = (int) ($row['link_clicks'] ?? 0);
                }
            }

            foreach ($this->resolver->resolveFbDaily($from, $to) as $row) {
                $day = $row['date'];
                if (isset($dates[$day])) {
                    $dates[$day]['fb_reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$day]['fb_impressions'] = (int) ($row['post_impressions'] ?? 0);
                }
            }

            foreach ($this->resolver->resolveIgDaily($from, $to) as $row) {
                $day = $row['date'];
                if (isset($dates[$day])) {
                    $dates[$day]['ig_reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$day]['ig_profile_views'] = (int) ($row['profile_views'] ?? 0);
                }
            }

            if ($hasTiktok) {
                foreach ($this->resolver->resolveTiktokAdsDaily($from, $to) as $row) {
                    $day = $row['date'];
                    if (isset($dates[$day])) {
                        $dates[$day]['tiktok_spend'] = round((float) ($row['spend'] ?? 0), 2);
                        $dates[$day]['tiktok_reach'] = (int) ($row['reach'] ?? 0);
                        $dates[$day]['tiktok_video_views'] = (int) ($row['video_views'] ?? 0);
                    }
                }
            }

            $result = array_values($dates);
        } else {
            $result = $this->runWithV2Version(function () use ($from, $to) {
                $dates = [];
                for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
                    $dates[$d->toDateString()] = [
                        'date' => $d->toDateString(),
                        'fb_reach' => 0,
                        'fb_impressions' => 0,
                        'ig_reach' => 0,
                        'ig_profile_views' => 0,
                        'ads_spend' => 0,
                        'ads_reach' => 0,
                        'ads_link_clicks' => 0,
                    ];
                }

                $adsDaily = $this->fetchAdsDaily($from, $to);
                foreach ($adsDaily as $day => $row) {
                    if (!isset($dates[$day])) {
                        continue;
                    }
                    $dates[$day]['ads_spend'] = round((float) ($row['spend'] ?? 0), 2);
                    $dates[$day]['ads_reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$day]['ads_link_clicks'] = (int) ($row['link_clicks'] ?? 0);
                }

                $fbDaily = $this->fetchFacebookDaily($from, $to);
                foreach ($fbDaily as $day => $row) {
                    if (!isset($dates[$day])) {
                        continue;
                    }
                    $dates[$day]['fb_reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$day]['fb_impressions'] = (int) ($row['impressions'] ?? 0);
                }

                $igDaily = $this->fetchInstagramDaily($from, $to);
                foreach ($igDaily as $day => $row) {
                    if (!isset($dates[$day])) {
                        continue;
                    }
                    $dates[$day]['ig_reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$day]['ig_profile_views'] = (int) ($row['profile_views'] ?? 0);
                }

                return array_values($dates);
            });
        }

        $ttl = Carbon::parse($to)->lt(Carbon::today()) ? 604800 : 1800;
        Cache::put($cacheKey, $result, $ttl);
        return $result;
    }

    /**
     * Return channel comparison payload with the same schema as totalComparison().
     */
    public function totalComparison(string $from, string $to, ?string $preset = null, bool $noCache = false): array
    {
        $preset = $this->normalizePreset($preset);
        $window = $this->windowTotals($from, $to, $preset, $noCache);
        $fb = $window['facebook'];
        $ig = $window['instagram'];
        $ads = $window['ads'];

        $result = [
            'facebook' => [
                'impressions' => (int) $fb['impressions'],
                'reach' => (int) $fb['reach'],
                'engagement' => (int) $fb['post_engagement'],
                'conversations' => (int) $fb['conversations'],
            ],
            'instagram' => [
                'reach' => (int) $ig['reach'],
                'profile_views' => (int) $ig['profile_views'],
                'engagement' => (int) $ig['engagement'],
                'conversations' => (int) $ig['conversations'],
            ],
            'ads' => [
                'impressions' => (int) $ads['impressions'],
                'reach' => (int) $ads['reach'],
                'link_clicks' => (int) $ads['link_clicks'],
                'spend' => round($ads['spend'], 2),
                'revenue' => round($ads['revenue'], 2),
            ],
        ];

        if ($this->isTiktokEnabled() && isset($window['tiktok'])) {
            $tt = $window['tiktok'];
            $result['tiktok'] = [
                'impressions' => (int) ($tt['impressions'] ?? 0),
                'reach' => (int) ($tt['reach'] ?? 0),
                'clicks' => (int) ($tt['clicks'] ?? 0),
                'spend' => round($tt['spend'] ?? 0, 2),
                'revenue' => round($tt['purchase_value'] ?? 0, 2),
                'video_views' => (int) ($tt['video_views'] ?? 0),
            ];
        }

        return $result;
    }

    private function windowTotals(string $from, string $to, ?string $preset = null, bool $noCache = false, bool $dbOnly = false): array
    {
        $preset = $this->normalizePreset($preset);
        $cacheVersion = Cache::get('meta_cache_version', 0);
        $cacheKey = $this->getApiVersion() . ":v{$cacheVersion}:meta_v2_window:{$from}:{$to}:" . ($preset ?? 'none');

        if (!$noCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->lastCacheHit = true;
                return (array) $cached;
            }
        }
        $this->lastCacheHit = false;

        if ($this->isDbFirst()) {
            $mode = $dbOnly ? 'DB-only (YoY)' : 'DB-first';
            Log::info("ReportService windowTotals: {$mode} [{$from}..{$to}]");
            $startTime = microtime(true);

            if ($dbOnly) {
                // YoY: period-level totals from DB (populated by API on first load or Rifresko).
                // Never SUM daily rows — date ranges are fully different from singular dates.
                $fb = $this->resolver->resolveFbPeriodTotalsForYoY($from, $to);
                $ig = $this->resolver->resolveIgPeriodTotalsForYoY($from, $to);
                $ads = $this->resolver->resolveAdsPeriodTotalsForYoY($from, $to);
                $result = [
                    'ads' => $ads ?? $this->emptyAdsTotals(),
                    'facebook' => $fb ?? $this->emptyFacebookTotals(),
                    'instagram' => $ig ?? $this->emptyInstagramTotals(),
                ];
                if ($this->isTiktokEnabled()) {
                    $tiktok = $this->resolver->resolveTiktokAdsPeriodTotalsForYoY($from, $to);
                    $result['tiktok'] = $tiktok ?? TiktokAdsSyncService::emptyTotals();
                }
            } else {
                $result = [
                    'ads' => $this->resolver->resolveAdsTotals($from, $to),
                    'facebook' => $this->resolver->resolveFbTotals($from, $to),
                    'instagram' => $this->resolver->resolveIgTotals($from, $to),
                ];
                if ($this->isTiktokEnabled()) {
                    $result['tiktok'] = $this->resolver->resolveTiktokAdsTotals($from, $to);
                }
            }

            $elapsed = round(microtime(true) - $startTime, 2);
            Log::info("ReportService windowTotals: {$mode} DONE in {$elapsed}s — ads_spend={$result['ads']['spend']}, fb_reach={$result['facebook']['reach']}, ig_reach={$result['instagram']['reach']}");
        } else {
            Log::info("ReportService windowTotals: API mode [{$from}..{$to}]");
            $result = $this->runWithV2Version(function () use ($from, $to, $preset) {
                $ads = $this->safeTotalsSection(
                    fn () => $this->fetchAdsTotals($from, $to),
                    $this->emptyAdsTotals(),
                    'ads',
                    $from,
                    $to
                );

                $fb = $this->safeTotalsSection(
                    fn () => $this->fetchFacebookTotals($from, $to, $preset),
                    $this->emptyFacebookTotals(),
                    'facebook',
                    $from,
                    $to
                );

                $ig = $this->safeTotalsSection(
                    fn () => $this->fetchInstagramTotals($from, $to, (int) ($ads['messaging_conversations'] ?? 0)),
                    $this->emptyInstagramTotals(),
                    'instagram',
                    $from,
                    $to
                );

                $result = [
                    'ads' => $ads,
                    'facebook' => $fb,
                    'instagram' => $ig,
                ];

                // TikTok is always DB-first (no legacy API path)
                if ($this->isTiktokEnabled() && $this->resolver) {
                    $result['tiktok'] = $this->safeTotalsSection(
                        fn () => $this->resolver->resolveTiktokAdsTotals($from, $to),
                        TiktokAdsSyncService::emptyTotals(),
                        'tiktok',
                        $from,
                        $to
                    );
                }

                return $result;
            });
        }

        $ttl = Carbon::parse($to)->lt(Carbon::today()) ? 604800 : 1800;
        Cache::put($cacheKey, $result, $ttl);
        return $result;
    }

    private function safeWindowTotals(string $from, string $to, ?string $preset = null, bool $noCache = false, bool $dbOnly = false): array
    {
        try {
            return [
                'data' => $this->windowTotals($from, $to, $preset, $noCache, $dbOnly),
                'available' => true,
            ];
        } catch (Throwable $e) {
            Log::warning("Meta v2 totals window failed [{$from}..{$to}]: " . $e->getMessage());

            return [
                'data' => $this->emptyWindowTotals(),
                'available' => false,
            ];
        }
    }

    private function safeTotalsSection(
        callable $callback,
        array $fallback,
        string $section,
        string $from,
        string $to
    ): array {
        try {
            $result = $callback();

            return is_array($result) ? $result : $fallback;
        } catch (Throwable $e) {
            Log::warning("Meta v2 {$section} totals failed [{$from}..{$to}]: " . $e->getMessage());
            return $fallback;
        }
    }

    private function isTiktokEnabled(): bool
    {
        return (bool) config('tiktok.features.tiktok_module', false);
    }

    private function emptyWindowTotals(): array
    {
        $totals = [
            'ads' => $this->emptyAdsTotals(),
            'facebook' => $this->emptyFacebookTotals(),
            'instagram' => $this->emptyInstagramTotals(),
        ];

        if ($this->isTiktokEnabled()) {
            $totals['tiktok'] = TiktokAdsSyncService::emptyTotals();
        }

        return $totals;
    }

    private function emptyAdsTotals(): array
    {
        return [
            'spend' => 0.0,
            'impressions' => 0,
            'reach' => 0,
            'link_clicks' => 0,
            'revenue' => 0.0,
            'messaging_conversations' => 0,
            'messaging_conversations_replied' => 0,
        ];
    }

    private function emptyFacebookTotals(): array
    {
        return [
            'impressions' => 0,
            'reach' => 0,
            'page_views' => 0,
            'post_engagement' => 0,
            'link_clicks' => 0,
            'conversations' => 0,
            'received' => 0,
            'sent' => 0,
        ];
    }

    private function emptyInstagramTotals(): array
    {
        return [
            'reach' => 0,
            'views' => 0,
            'profile_views' => 0,
            'new_followers' => 0,
            'engagement' => 0,
            'link_clicks' => 0,
            'conversations' => 0,
            'received' => 0,
            'sent' => 0,
        ];
    }

    private function fetchAdsTotals(string $from, string $to): array
    {
        $adAccountId = (string) config('meta.ad_account_id', '');
        if ($adAccountId === '') {
            return [
                'spend' => 0.0,
                'impressions' => 0,
                'reach' => 0,
                'link_clicks' => 0,
                'revenue' => 0.0,
                'messaging_conversations' => 0,
                'messaging_conversations_replied' => 0,
            ];
        }

        // No time_increment → single de-duplicated row for the entire period.
        // Reach, impressions etc. are properly de-duplicated by Meta.
        $rows = $this->api->getAdsInsights($adAccountId, $this->withAttribution([
            'level' => 'account',
            'time_range' => json_encode(['since' => $from, 'until' => $to]),
            'fields' => 'impressions,reach,clicks,spend,actions,action_values',
        ]));

        if (empty($rows) || !isset($rows[0])) {
            return [
                'spend' => 0.0,
                'impressions' => 0,
                'reach' => 0,
                'link_clicks' => 0,
                'revenue' => 0.0,
                'messaging_conversations' => 0,
                'messaging_conversations_replied' => 0,
            ];
        }

        $row = $rows[0];
        $actions = $this->parseActions($row['actions'] ?? []);
        $actionValues = $this->parseActions($row['action_values'] ?? []);

        return [
            'spend' => (float) ($row['spend'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'reach' => (int) ($row['reach'] ?? 0),
            'link_clicks' => (int) ($actions['link_click'] ?? 0),
            'revenue' => (float) ($actionValues['purchase'] ?? ($actionValues['offsite_conversion.fb_pixel_purchase'] ?? 0)),
            'messaging_conversations' => (int) ($actions['onsite_conversion.total_messaging_connection'] ?? $actions['onsite_conversion.messaging_conversation_started_7d'] ?? 0),
            'messaging_conversations_replied' => (int) ($actions['onsite_conversion.messaging_conversation_replied_7d'] ?? 0),
        ];
    }

    private function fetchAdsDaily(string $from, string $to): array
    {
        $adAccountId = (string) config('meta.ad_account_id', '');
        if ($adAccountId === '') {
            return [];
        }

        $rows = $this->api->getAdsInsights($adAccountId, $this->withAttribution([
            'level' => 'account',
            'time_range' => json_encode(['since' => $from, 'until' => $to]),
            'time_increment' => 1,
            'fields' => 'date_start,reach,spend,actions',
        ]));

        $daily = [];
        foreach ($rows as $row) {
            $date = (string) ($row['date_start'] ?? '');
            if ($date === '') {
                continue;
            }

            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'spend' => 0.0,
                    'reach' => 0,
                    'link_clicks' => 0,
                ];
            }

            $actions = $this->parseActions($row['actions'] ?? []);
            $daily[$date]['spend'] += (float) ($row['spend'] ?? 0);
            $daily[$date]['reach'] += (int) ($row['reach'] ?? 0);
            $daily[$date]['link_clicks'] += (int) ($actions['link_click'] ?? 0);
        }

        return $daily;
    }

    private function fetchFacebookTotals(string $from, string $to, ?string $preset = null): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($pageId === '' || $pageToken === '') {
            return [
                'impressions' => 0,
                'reach' => 0,
                'page_views' => 0,
                'post_engagement' => 0,
                'link_clicks' => 0,
                'conversations' => 0,
                'received' => 0,
                'sent' => 0,
            ];
        }

        // Period-level totals via total_over_range — de-duplicated, matches Meta Insights exactly.
        // Never SUM daily rows for KPI cards.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        $reach = 0;
        $impressions = 0;
        $pageViews = 0;
        $postEngagement = 0;

        try {
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_total_media_view_unique,page_media_view,page_views_total,page_post_engagements',
                'since' => $from,
                'until' => $untilExclusive,
                'period' => 'total_over_range',
            ]);
            $metricMap = [
                'page_total_media_view_unique' => 'reach',
                'page_media_view' => 'impressions',
                'page_views_total' => 'page_views',
                'page_post_engagements' => 'post_engagement',
            ];
            foreach ($response['data'] ?? [] as $entry) {
                $name = $entry['name'] ?? '';
                if (isset($metricMap[$name])) {
                    $val = (int) round($this->normalizeMetricValue(
                        $entry['values'][0]['value'] ?? $entry['total_value']['value'] ?? 0
                    ));
                    match ($metricMap[$name]) {
                        'reach' => $reach = $val,
                        'impressions' => $impressions = $val,
                        'page_views' => $pageViews = $val,
                        'post_engagement' => $postEngagement = $val,
                    };
                }
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB total_over_range failed: " . $e->getMessage());
        }

        $messaging = $this->fetchMessagingTotals('messenger', $from, $to);

        return [
            'impressions' => (int) $impressions,
            'reach' => (int) $reach,
            'page_views' => (int) $pageViews,
            'post_engagement' => (int) $postEngagement,
            'link_clicks' => 0,
            'conversations' => (int) $messaging['conversations'],
            'received' => (int) $messaging['received'],
            'sent' => (int) $messaging['sent'],
        ];
    }

    private function fetchFacebookDaily(string $from, string $to): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($pageId === '' || $pageToken === '') {
            return [];
        }

        $daily = [];

        // Fetch both metrics in a single API call (was 2 separate calls).
        // Meta's `until` is exclusive — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();
        try {
            $response = $this->api->getPageInsights(
                $pageId,
                'page_total_media_view_unique,page_media_view',
                'day',
                $from,
                $untilExclusive
            );
            foreach ($response['data'] ?? [] as $entry) {
                $name = $entry['name'] ?? '';
                $key = match ($name) {
                    'page_total_media_view_unique' => 'reach',
                    'page_media_view' => 'impressions',
                    default => null,
                };
                if ($key === null) {
                    continue;
                }
                foreach ($entry['values'] ?? [] as $value) {
                    // FB Page Insights end_time marks the END of the period — subtract 1 day to get the actual date.
                    $date = Carbon::parse($value['end_time'])->subDay()->toDateString();
                    if (!isset($daily[$date])) {
                        $daily[$date] = ['reach' => 0, 'impressions' => 0];
                    }
                    $daily[$date][$key] = (int) round($this->normalizeMetricValue($value['value'] ?? 0));
                }
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB daily metrics failed: " . $e->getMessage());
        }

        return $daily;
    }

    private function sumPageMetric(string $pageId, string $metric, string $from, string $to): int
    {
        try {
            $response = $this->api->getPageInsights($pageId, $metric, 'day', $from, $to);
            $sum = 0.0;
            foreach ($response['data'] ?? [] as $entry) {
                foreach ($entry['values'] ?? [] as $value) {
                    $sum += $this->normalizeMetricValue($value['value'] ?? 0);
                }
            }

            return (int) round($sum);
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB metric failed [{$metric}]: " . $e->getMessage());
            return 0;
        }
    }

    private function fetchFbPresetReach(string $pageId, string $preset): ?int
    {
        try {
            // page_impressions_unique deprecated Nov 2025; use page_total_media_view_unique.
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_total_media_view_unique',
                'date_preset' => $preset,
                'period' => 'total_over_range',
            ]);

            if (!isset($response['data']) || empty($response['data'][0]['values'])) {
                return null;
            }

            return (int) round($this->normalizeMetricValue($response['data'][0]['values'][0]['value'] ?? 0));
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB preset reach failed [{$preset}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch deduplicated FB reach for a custom date range using total_over_range.
     */
    private function fetchFbDateRangeReach(string $pageId, string $from, string $to): ?int
    {
        try {
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_total_media_view_unique',
                'since' => $from,
                'until' => Carbon::parse($to)->addDay()->toDateString(),
                'period' => 'total_over_range',
            ]);

            if (!isset($response['data']) || empty($response['data'][0]['values'])) {
                return null;
            }

            return (int) round($this->normalizeMetricValue($response['data'][0]['values'][0]['value'] ?? 0));
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB date-range reach failed [{$from}..{$to}]: " . $e->getMessage());
            return null;
        }
    }

    private function fetchInstagramTotals(string $from, string $to, int $paidThreadsFromAds = 0): array
    {
        $igAccountId = (string) config('meta.ig_account_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($igAccountId === '') {
            $igAccountId = $this->discoverIgAccountId() ?? '';
        }

        if ($igAccountId === '' || $pageToken === '') {
            return [
                'reach' => 0,
                'views' => 0,
                'profile_views' => 0,
                'new_followers' => 0,
                'engagement' => 0,
                'link_clicks' => 0,
                'conversations' => 0,
                'received' => 0,
                'sent' => 0,
            ];
        }

        $metricsMap = [
            'reach' => 'reach',
            'views' => 'views',
            'profile_views' => 'profile_views',
            'total_interactions' => 'total_interactions',
            'website_clicks' => 'website_clicks',
        ];

        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        $totals = array_fill_keys(array_keys($metricsMap), 0);
        try {
            $response = $this->api->getIgInsights(
                $igAccountId,
                implode(',', array_values($metricsMap)),
                'day',
                $from,
                $untilExclusive,
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
            Log::debug('Meta v2 IG batched metrics failed: ' . $e->getMessage());

            // Fallback: one metric at a time
            foreach ($metricsMap as $key => $metric) {
                try {
                    $response = $this->api->getIgInsights($igAccountId, $metric, 'day', $from, $untilExclusive, 'total_value');
                    $sum = 0;
                    foreach ($response['data'] ?? [] as $entry) {
                        $sum += (int) round($this->normalizeMetricValue($entry['total_value']['value'] ?? 0));
                    }
                    $totals[$key] = $sum;
                } catch (Throwable $inner) {
                    Log::debug("Meta v2 IG metric failed [{$metric}]: " . $inner->getMessage());
                }
            }
        }

        // follower_count period=day gives daily net change.
        $newFollowers = 0;
        try {
            $followerResponse = $this->api->getIgInsights($igAccountId, 'follower_count', 'day', $from, $untilExclusive);
            foreach ($followerResponse['data'] ?? [] as $entry) {
                foreach ($entry['values'] ?? [] as $value) {
                    $newFollowers += (int) ($value['value'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            Log::debug('Meta v2 IG follower_count failed: ' . $e->getMessage());
        }

        $messaging = $this->fetchMessagingTotals('instagram', $from, $to);

        $organicThreads = (int) $messaging['conversations'];
        $paidThreads = max(0, $paidThreadsFromAds);

        return [
            'reach' => (int) $totals['reach'],
            'views' => (int) $totals['views'],
            'profile_views' => (int) $totals['profile_views'],
            'new_followers' => (int) $newFollowers,
            'engagement' => (int) $totals['total_interactions'],
            'link_clicks' => (int) $totals['website_clicks'],
            'conversations' => $organicThreads + $paidThreads,
            'received' => (int) $messaging['received'] + $paidThreads,
            'sent' => (int) $messaging['sent'],
        ];
    }

    private function fetchInstagramDaily(string $from, string $to): array
    {
        $igAccountId = (string) config('meta.ig_account_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($igAccountId === '') {
            $igAccountId = $this->discoverIgAccountId() ?? '';
        }
        if ($igAccountId === '' || $pageToken === '') {
            return [];
        }

        $daily = [];
        for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $daily[$d->toDateString()] = ['reach' => 0, 'profile_views' => 0];
        }

        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        // Fetch reach with period=day (supports daily breakdown).
        try {
            $response = $this->api->getIgInsights($igAccountId, 'reach', 'day', $from, $untilExclusive);
            foreach ($response['data'] ?? [] as $entry) {
                foreach ($entry['values'] ?? [] as $value) {
                    $date = Carbon::parse($value['end_time'])->toDateString();
                    if (isset($daily[$date])) {
                        $daily[$date]['reach'] = (int) round($this->normalizeMetricValue($value['value'] ?? 0));
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 IG daily reach failed: " . $e->getMessage());
        }

        // profile_views requires metric_type=total_value (can't be combined with reach).
        // Fetch as total for the whole period and distribute evenly across days.
        try {
            $response = $this->api->getIgInsights($igAccountId, 'profile_views', 'day', $from, $untilExclusive, 'total_value');
            $totalPv = 0;
            foreach ($response['data'] ?? [] as $entry) {
                $totalPv = (int) round($this->normalizeMetricValue($entry['total_value']['value'] ?? 0));
            }
            // Distribute total evenly across days for the daily chart.
            $dayCount = count($daily);
            if ($dayCount > 0 && $totalPv > 0) {
                $perDay = (int) round($totalPv / $dayCount);
                foreach ($daily as &$row) {
                    $row['profile_views'] = $perDay;
                }
                unset($row);
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 IG daily profile_views failed: " . $e->getMessage());
        }

        return $daily;
    }

    private function fetchMessagingTotals(string $platform, string $from, string $to): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($pageId === '' || $pageToken === '') {
            return [
                'conversations' => 0,
                'received' => 0,
                'sent' => 0,
            ];
        }

        $isInstagram = $platform === 'instagram';

        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        // Cap at 3 pages (≈1500 items) to keep response time under 5s.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();
        try {
            $all = $this->api->getConversations($pageId, $platform, $from, $untilExclusive, 3);
        } catch (Throwable $e) {
            Log::debug("Meta v2 {$platform} conversations failed: " . $e->getMessage());
            return [
                'conversations' => 0,
                'received' => 0,
                'sent' => 0,
            ];
        }

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();
        $filtered = [];

        foreach ($all as $conversation) {
            $updated = $conversation['updated_time'] ?? null;
            if (!$updated) {
                continue;
            }
            $dt = Carbon::parse($updated);
            if ($dt->lt($fromStart) || $dt->gt($toEnd)) {
                continue;
            }
            $filtered[] = $conversation;
        }

        // Use message_count for Messenger (date-range scoped); for Instagram it's lifetime total
        // so we just use conversation count as a proxy for received messages.
        $totalReceived = 0;
        foreach ($filtered as $conv) {
            if ($isInstagram) {
                $totalReceived++;
            } else {
                $totalReceived += max(1, (int) ($conv['message_count'] ?? 1));
            }
        }

        return [
            'conversations' => count($filtered),
            'received' => $totalReceived > 0 ? $totalReceived : count($filtered),
            'sent' => 0,
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
            $response = $this->api->getWithPageToken($pageId, [
                'fields' => 'instagram_business_account{id,username}',
            ]);

            return $response['instagram_business_account']['id'] ?? null;
        } catch (Throwable $e) {
            Log::debug('Meta v2 discover IG account failed: ' . $e->getMessage());
            return null;
        }
    }

    private function runWithV2Version(callable $callback): mixed
    {
        return $this->api->runWithApiVersion($this->getApiVersion(), $callback);
    }

    private function normalizePreset(?string $preset): ?string
    {
        if (!$preset) {
            return null;
        }

        return preg_match('/^[a-z_]+$/', $preset) ? $preset : null;
    }

    private function parseActions(array $actions): array
    {
        $parsed = [];
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;
            if (!$type) {
                continue;
            }
            $parsed[$type] = ($parsed[$type] ?? 0) + $this->normalizeMetricValue($action['value'] ?? 0);
        }

        return $parsed;
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

    private function withAttribution(array $params): array
    {
        $configured = config('meta.ads_attribution', ['use_account_attribution_setting' => true]);
        if (isset($configured['use_account_attribution_setting'])) {
            $configured['use_account_attribution_setting'] = $configured['use_account_attribution_setting'] ? 'true' : 'false';
        }

        return array_merge($configured, $params);
    }

    private function calcChange(float|int $current, float|int $previous): string|float|int
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous == 0.0) {
            return $current > 0 ? 'new' : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function calcWindowChange(float|int $current, float|int $previous, bool $hasComparableData): string|float|int|null
    {
        if (!$hasComparableData) {
            return null;
        }

        return $this->calcChange($current, $previous);
    }
}
