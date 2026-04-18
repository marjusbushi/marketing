<?php

namespace App\Services\Meta;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaMarketingV2ChannelService
{
    public function __construct(
        private readonly MetaApiService $api,
        private readonly MetaDataResolverService $resolver,
    ) {}

    private function isDbFirst(): bool
    {
        return (bool) config('meta.features.db_first_mode', false);
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
        if ($this->isDbFirst() && $hasComparableData) {
            $hasComparableData = $this->resolver->hasYoYDataForRange($prevFrom, $prevTo);
        }

        $ads = $current['ads'];
        $prevAds = $previous['ads'];
        $fb = $current['facebook'];
        $prevFb = $previous['facebook'];
        $ig = $current['instagram'];
        $prevIg = $previous['instagram'];

        $totalReach = $ads['reach'] + $fb['reach'] + $ig['reach'];
        $prevTotalReach = $prevAds['reach'] + $prevFb['reach'] + $prevIg['reach'];

        $totalImpressions = $fb['impressions'] + $ig['views'];
        $prevTotalImpressions = $prevFb['impressions'] + $prevIg['views'];

        $totalPageViews = $fb['page_views'] + $ig['profile_views'];
        $prevTotalPageViews = $prevFb['page_views'] + $prevIg['profile_views'];

        $totalEngagement = $fb['post_engagement'] + $ig['engagement'];
        $prevTotalEngagement = $prevFb['post_engagement'] + $prevIg['engagement'];

        $totalOrganicLinkClicks = $fb['link_clicks'] + $ig['link_clicks'];
        $prevTotalOrganicLinkClicks = $prevFb['link_clicks'] + $prevIg['link_clicks'];

        $totalAdsLinkClicks = $ads['link_clicks'];
        $prevTotalAdsLinkClicks = $prevAds['link_clicks'];

        $combinedLinkClicks = $totalOrganicLinkClicks + $totalAdsLinkClicks;
        $prevCombinedLinkClicks = $prevTotalOrganicLinkClicks + $prevTotalAdsLinkClicks;

        $totalThreads = $fb['conversations'] + $ig['conversations'];
        $prevTotalThreads = $prevFb['conversations'] + $prevIg['conversations'];

        $totalConversations = ($fb['received'] + $fb['sent']) + ($ig['received'] + $ig['sent']);
        $prevTotalConversations = ($prevFb['received'] + $prevFb['sent']) + ($prevIg['received'] + $prevIg['sent']);

        $roas = $ads['spend'] > 0 ? round($ads['revenue'] / $ads['spend'], 2) : 0.0;
        $prevRoas = $prevAds['spend'] > 0 ? round($prevAds['revenue'] / $prevAds['spend'], 2) : 0.0;

        return [
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
                'value' => round($ads['spend'], 2),
                'change' => $this->calcWindowChange($ads['spend'], $prevAds['spend'], $hasComparableData),
            ],
            'ads_revenue' => [
                'value' => round($ads['revenue'], 2),
                'change' => $this->calcWindowChange($ads['revenue'], $prevAds['revenue'], $hasComparableData),
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
                Log::info("ChannelService totalDaily: CACHE HIT [{$from}..{$to}]");
                return (array) $cached;
            }
        }

        if ($this->isDbFirst()) {
            Log::info("ChannelService totalDaily: DB-first [{$from}..{$to}]");
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

        return [
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
    }

    public function adsKpis(string $from, string $to, string $platform = 'all', bool $noCache = false): array
    {
        $platform = $this->normalizeAdsPlatform($platform);

        return $this->remember("meta_v2_ads_kpis:{$from}:{$to}:{$platform}", $noCache, function () use ($from, $to, $platform) {
            if ($this->isDbFirst()) {
                $totals = $platform === 'all'
                    ? $this->resolver->resolveAdsTotals($from, $to)
                    : $this->resolver->resolveAdsPlatformTotals($from, $to, $platform);
            } else {
                $totals = $this->runWithV2Version(fn () => $platform === 'all'
                    ? $this->fetchAdsTotals($from, $to)
                    : $this->fetchAdsPlatformTotals($from, $to, $platform));
            }

            $ctr = $totals['impressions'] > 0 ? round(($totals['link_clicks'] / $totals['impressions']) * 100, 2) : 0.0;
            $roas = $totals['spend'] > 0 ? round($totals['revenue'] / $totals['spend'], 2) : 0.0;
            $cpc = $totals['link_clicks'] > 0 ? round($totals['spend'] / $totals['link_clicks'], 2) : 0.0;
            $cpm = $totals['impressions'] > 0 ? round(($totals['spend'] / $totals['impressions']) * 1000, 2) : 0.0;

            return [
                'spend' => ['value' => round($totals['spend'], 2), 'change' => null],
                'impressions' => ['value' => (int) $totals['impressions'], 'change' => null],
                'reach' => ['value' => (int) $totals['reach'], 'change' => null],
                'link_clicks' => ['value' => (int) $totals['link_clicks'], 'change' => null],
                'ctr' => ['value' => $ctr, 'change' => null],
                'purchases' => ['value' => (int) ($totals['purchases'] ?? 0), 'change' => null],
                'revenue' => ['value' => round($totals['revenue'], 2), 'change' => null],
                'roas' => ['value' => $roas, 'change' => null],
                'cpc' => ['value' => $cpc, 'change' => null],
                'cpm' => ['value' => $cpm, 'change' => null],
            ];
        });
    }

    public function adsDailyReport(string $from, string $to, string $platform = 'all', bool $noCache = false): array
    {
        $platform = $this->normalizeAdsPlatform($platform);

        return $this->remember("meta_v2_ads_daily:{$from}:{$to}:{$platform}", $noCache, function () use ($from, $to, $platform) {
            if ($this->isDbFirst()) {
                // DB-first: resolver returns array of rows with date, spend, impressions, etc.
                return $this->resolver->resolveAdsDaily($from, $to);
            }

            return $this->runWithV2Version(function () use ($from, $to, $platform) {
                $daily = $platform === 'all'
                    ? $this->fetchAdsDaily($from, $to)
                    : $this->fetchAdsPlatformDaily($from, $to, $platform);

                $dates = [];
                for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
                    $dates[$d->toDateString()] = [
                        'date' => $d->toDateString(),
                        'spend' => 0.0,
                        'impressions' => 0,
                        'link_clicks' => 0,
                        'reach' => 0,
                        'purchases' => 0,
                        'revenue' => 0.0,
                        'roas' => 0.0,
                    ];
                }

                foreach ($daily as $date => $row) {
                    if (!isset($dates[$date])) {
                        continue;
                    }

                    $dates[$date]['spend'] = round((float) ($row['spend'] ?? 0), 2);
                    $dates[$date]['impressions'] = (int) ($row['impressions'] ?? 0);
                    $dates[$date]['link_clicks'] = (int) ($row['link_clicks'] ?? 0);
                    $dates[$date]['reach'] = (int) ($row['reach'] ?? 0);
                    $dates[$date]['purchases'] = (int) ($row['purchases'] ?? 0);
                    $dates[$date]['revenue'] = round((float) ($row['revenue'] ?? 0), 2);
                    $dates[$date]['roas'] = $dates[$date]['spend'] > 0
                        ? round($dates[$date]['revenue'] / $dates[$date]['spend'], 2)
                        : 0.0;
                }

                return array_values($dates);
            });
        });
    }

    public function adsCampaigns(string $from, string $to, string $platform = 'all', bool $noCache = false): array
    {
        $platform = $this->normalizeAdsPlatform($platform);

        return $this->remember("meta_v2_ads_campaigns:{$from}:{$to}:{$platform}", $noCache, function () use ($from, $to, $platform) {
            if ($this->isDbFirst()) {
                return $this->resolver->resolveAdsCampaigns($from, $to, $platform);
            }

            return $this->runWithV2Version(fn () => $this->fetchAdsCampaigns($from, $to, $platform));
        });
    }

    public function adsBreakdowns(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_ads_breakdowns:{$from}:{$to}", $noCache, function () use ($from, $to) {
            if ($this->isDbFirst()) {
                return $this->resolver->resolveAdsBreakdowns($from, $to);
            }

            return $this->runWithV2Version(function () use ($from, $to) {
                return [
                    'age' => $this->fetchAdsBreakdown($from, $to, 'age'),
                    'gender' => $this->fetchAdsBreakdown($from, $to, 'gender'),
                    'platform' => $this->fetchAdsBreakdown($from, $to, 'publisher_platform'),
                    'placement' => $this->fetchAdsBreakdown($from, $to, 'publisher_platform,platform_position'),
                ];
            });
        });
    }

    public function igKpis(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_ig_kpis:{$from}:{$to}", $noCache, function () use ($from, $to) {
            if ($this->isDbFirst()) {
                $adsIg = $this->resolver->resolveAdsPlatformTotals($from, $to, 'instagram');
                $adsFb = $this->resolver->resolveAdsPlatformTotals($from, $to, 'facebook');
                $ig = $this->resolver->resolveIgTotals($from, $to);

                $paidThreads = (int) ($adsIg['messaging_conversations'] ?? 0);

                // DM Kontakte: sum facebook + instagram paid messaging connections (matches Meta Business Suite)
                $dmKontakte = (int) ($adsIg['messaging_conversations'] ?? 0) + (int) ($adsFb['messaging_conversations'] ?? 0);

                return [
                    'reach' => ['value' => (int) $ig['reach'], 'change' => null],
                    'views' => ['value' => (int) $ig['views'], 'change' => null],
                    'profile_views' => ['value' => (int) $ig['profile_views'], 'change' => null],
                    'content_interactions' => ['value' => (int) $ig['engagement'], 'change' => null],
                    // Link Clicks: bio taps + FB-platform ads link_click (matches Meta Business Suite)
                    'combined_link_clicks' => ['value' => (int) ($ig['link_clicks'] + $adsFb['link_clicks']), 'change' => null],
                    'link_clicks' => ['value' => (int) $ig['link_clicks'], 'change' => null],
                    'ads_link_clicks' => ['value' => (int) $adsFb['link_clicks'], 'change' => null],
                    'new_threads' => ['value' => $dmKontakte, 'change' => null],
                    'conversations' => ['value' => (int) ($ig['received'] + $ig['sent']) + $paidThreads, 'change' => null],
                ];
            }

            return $this->runWithV2Version(function () use ($from, $to) {
                $adsIg = $this->safeTotalsSection(
                    fn () => $this->fetchAdsPlatformTotals($from, $to, 'instagram'),
                    $this->emptyAdsTotals(),
                    'ads_instagram',
                    $from,
                    $to
                );

                $adsFb = $this->safeTotalsSection(
                    fn () => $this->fetchAdsPlatformTotals($from, $to, 'facebook'),
                    $this->emptyAdsTotals(),
                    'ads_facebook',
                    $from,
                    $to
                );

                $ig = $this->safeTotalsSection(
                    fn () => $this->fetchInstagramTotals($from, $to, (int) ($adsIg['messaging_conversations'] ?? 0)),
                    $this->emptyInstagramTotals(),
                    'instagram',
                    $from,
                    $to
                );

                // DM Kontakte: sum facebook + instagram paid messaging connections (matches Meta Business Suite)
                $dmKontakte = (int) ($adsIg['messaging_conversations'] ?? 0) + (int) ($adsFb['messaging_conversations'] ?? 0);

                return [
                    'reach' => ['value' => (int) $ig['reach'], 'change' => null],
                    'views' => ['value' => (int) $ig['views'], 'change' => null],
                    'profile_views' => ['value' => (int) $ig['profile_views'], 'change' => null],
                    'content_interactions' => ['value' => (int) $ig['engagement'], 'change' => null],
                    // Link Clicks: bio taps + FB-platform ads link_click (matches Meta Business Suite)
                    'combined_link_clicks' => ['value' => (int) ($ig['link_clicks'] + $adsFb['link_clicks']), 'change' => null],
                    'link_clicks' => ['value' => (int) $ig['link_clicks'], 'change' => null],
                    'ads_link_clicks' => ['value' => (int) $adsFb['link_clicks'], 'change' => null],
                    'new_threads' => ['value' => $dmKontakte, 'change' => null],
                    'conversations' => ['value' => (int) ($ig['received'] + $ig['sent']), 'change' => null],
                ];
            });
        });
    }

    public function igDailyReport(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_ig_daily:{$from}:{$to}", $noCache, function () use ($from, $to) {
            if ($this->isDbFirst()) {
                $daily = $this->resolver->resolveIgDaily($from, $to);
                $paidDaily = $this->getAdsPlatformMessagingDaily($from, $to, 'instagram');

                foreach ($daily as &$row) {
                    $date = $row['date'] ?? '';
                    $paid = (int) ($paidDaily[$date] ?? 0);
                    $row['conversations'] = (int) ($row['conversations'] ?? 0) + $paid;
                    $row['messages_received'] = (int) ($row['messages_received'] ?? 0) + $paid;
                }
                unset($row);

                return $daily;
            }

            return $this->runWithV2Version(function () use ($from, $to) {
                $daily = $this->fetchInstagramRichDaily($from, $to);
                $messagingDaily = $this->fetchMessagingDaily('instagram', $from, $to);
                $adsDaily = [];

                try {
                    $adsDaily = $this->fetchAdsPlatformDaily($from, $to, 'instagram');
                } catch (Throwable $e) {
                    Log::debug('Meta v2 IG ads daily failed: ' . $e->getMessage());
                }

                foreach ($daily as $date => &$row) {
                    $msg = $messagingDaily[$date] ?? ['conversations' => 0, 'messages_received' => 0, 'messages_sent' => 0];
                    $row['conversations'] = (int) $msg['conversations'];
                    $row['messages_received'] = (int) $msg['messages_received'];
                    $row['messages_sent'] = (int) $msg['messages_sent'];

                    $paidStarted = (int) ($adsDaily[$date]['messaging_conversations'] ?? 0);
                    $row['conversations'] += $paidStarted;
                    $row['messages_received'] += $paidStarted;
                }
                unset($row);

                return array_values($daily);
            });
        });
    }

    public function igTopPosts(string $from, string $to, int $limit = 12, ?string $type = null, bool $noCache = false): array
    {
        $type = $type !== null && preg_match('/^[a-z_]+$/', $type) ? $type : null;

        return $this->remember("meta_v2_ig_posts:{$from}:{$to}:{$limit}:" . ($type ?? 'all'), $noCache, function () use ($from, $to, $limit, $type) {
            if ($this->isDbFirst()) {
                return $this->resolver->resolveIgTopPosts($from, $to, $limit, $type);
            }

            return $this->runWithV2Version(function () use ($from, $to, $limit, $type) {
                // Pass actual display limit — fetchInstagramPosts now only fetches
                // insights for the top N posts (ranked by likes+comments).
                $posts = $this->fetchInstagramPosts($from, $to, $limit);
                if ($type) {
                    $posts = array_values(array_filter($posts, fn (array $post) => ($post['post_type'] ?? null) === $type));
                }

                usort($posts, fn (array $a, array $b) => ($b['engagement'] ?? 0) <=> ($a['engagement'] ?? 0));
                return array_slice($posts, 0, $limit);
            });
        });
    }

    public function igMessaging(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_ig_messaging:{$from}:{$to}", $noCache, function () use ($from, $to) {
            if ($this->isDbFirst()) {
                $daily = $this->resolver->resolveMessagingDaily('instagram', $from, $to);
                $paidDaily = $this->getAdsPlatformMessagingDaily($from, $to, 'instagram');

                $totals = ['conversations' => 0, 'received' => 0, 'sent' => 0];
                foreach ($daily as &$row) {
                    $date = $row['date'] ?? '';
                    $paid = (int) ($paidDaily[$date] ?? 0);
                    $row['new_conversations'] = (int) ($row['new_conversations'] ?? $row['conversations'] ?? 0) + $paid;
                    $row['total_messages_received'] = (int) ($row['total_messages_received'] ?? $row['messages_received'] ?? 0) + $paid;
                    $row['total_messages_sent'] = (int) ($row['total_messages_sent'] ?? $row['messages_sent'] ?? 0);
                    $totals['conversations'] += $row['new_conversations'];
                    $totals['received'] += $row['total_messages_received'];
                    $totals['sent'] += $row['total_messages_sent'];
                }
                unset($row);

                return ['totals' => $totals, 'daily' => array_values($daily)];
            }

            return $this->runWithV2Version(function () use ($from, $to) {
                $daily = $this->fetchMessagingDaily('instagram', $from, $to);
                $adsDaily = [];

                try {
                    $adsDaily = $this->fetchAdsPlatformDaily($from, $to, 'instagram');
                } catch (Throwable $e) {
                    Log::debug('Meta v2 IG ads messaging daily failed: ' . $e->getMessage());
                }

                $totals = [
                    'conversations' => 0,
                    'received' => 0,
                    'sent' => 0,
                ];

                foreach ($daily as $date => &$row) {
                    $paidReplied = (int) ($adsDaily[$date]['messaging_conversations_replied'] ?? 0);
                    $row['new_conversations'] = (int) ($row['conversations'] ?? 0) + $paidReplied;
                    $row['total_messages_received'] = (int) ($row['messages_received'] ?? 0) + $paidReplied;
                    $row['total_messages_sent'] = (int) ($row['messages_sent'] ?? 0);

                    $totals['conversations'] += $row['new_conversations'];
                    $totals['received'] += $row['total_messages_received'];
                    $totals['sent'] += $row['total_messages_sent'];
                }
                unset($row);

                return [
                    'totals' => $totals,
                    'daily' => array_values($daily),
                ];
            });
        });
    }

    public function fbKpis(string $from, string $to, ?string $preset = null, bool $noCache = false): array
    {
        $preset = $this->normalizePreset($preset);

        return $this->remember("meta_v2_fb_kpis:{$from}:{$to}:" . ($preset ?? 'none'), $noCache, function () use ($from, $to, $preset, $noCache) {
            if ($this->isDbFirst()) {
                // ALL KPI cards use period-level API totals, NOT SUM(daily).
                $fb = $this->resolver->resolveFbTotals($from, $to);
                $adsFb = $this->resolver->resolveAdsPlatformTotals($from, $to, 'facebook');

                // Messenger: page_messages_new_conversations_unique already includes paid
                // conversations — do NOT add paidThreads or it double-counts.
                // (IG is different: Conversations API is organic-only, so paid must be added there.)
                return [
                    'reach' => ['value' => (int) $fb['reach'], 'change' => null],
                    'post_impressions' => ['value' => (int) $fb['impressions'], 'change' => null],
                    'page_views' => ['value' => (int) $fb['page_views'], 'change' => null],
                    'page_engagements' => ['value' => (int) $fb['content_interactions'], 'change' => null],
                    'ads_link_clicks' => ['value' => (int) $adsFb['link_clicks'], 'change' => null],
                    'new_threads' => ['value' => (int) $fb['conversations'], 'change' => null],
                ];
            }

            return $this->runWithV2Version(function () use ($from, $to, $preset) {
                $fb = $this->safeTotalsSection(
                    fn () => $this->fetchFacebookTotals($from, $to, $preset),
                    $this->emptyFacebookTotals(),
                    'facebook',
                    $from,
                    $to
                );

                $adsFb = $this->safeTotalsSection(
                    fn () => $this->fetchAdsPlatformTotals($from, $to, 'facebook'),
                    $this->emptyAdsTotals(),
                    'ads_facebook',
                    $from,
                    $to
                );

                // All KPI values come from period-level API calls (total_over_range / no time_increment).
                // Never SUM daily rows for KPI cards.
                return [
                    'reach' => ['value' => (int) $fb['reach'], 'change' => null],
                    'post_impressions' => ['value' => (int) $fb['impressions'], 'change' => null],
                    'page_views' => ['value' => (int) $fb['page_views'], 'change' => null],
                    'page_engagements' => ['value' => (int) $fb['content_interactions'], 'change' => null],
                    'ads_link_clicks' => ['value' => (int) $adsFb['link_clicks'], 'change' => null],
                    'new_threads' => ['value' => (int) $fb['conversations'], 'change' => null],
                ];
            });
        });
    }

    public function fbDailyReport(string $from, string $to, ?string $preset = null, bool $noCache = false): array
    {
        $preset = $this->normalizePreset($preset);

        return $this->remember("meta_v2_fb_daily:{$from}:{$to}:" . ($preset ?? 'none'), $noCache, function () use ($from, $to) {
            if ($this->isDbFirst()) {
                $daily = $this->resolver->resolveFbDaily($from, $to);
                $paidDaily = $this->getAdsPlatformMessagingDaily($from, $to, 'facebook');

                foreach ($daily as &$row) {
                    $date = $row['date'] ?? '';
                    $paid = (int) ($paidDaily[$date] ?? 0);
                    // new_threads from page_messages_new_threads already includes paid — don't double-count
                    $row['messages_received'] = (int) ($row['messages_received'] ?? 0) + $paid;
                }
                unset($row);

                return $daily;
            }

            return $this->runWithV2Version(function () use ($from, $to) {
                $pageId = (string) config('meta.page_id', '');
                $base = $this->fetchFacebookDaily($from, $to);
                $pageViews = $this->fetchPageMetricDaily($pageId, 'page_views_total', $from, $to);
                // page_actions_post_reactions_total = date-scoped reactions on posts (excludes Reels).
                $pageReactions = $this->fetchPageMetricDaily($pageId, 'page_actions_post_reactions_total', $from, $to);
                // Supplement with Reel interactions (likes + comments) to match Meta's "Content interactions".
                $reelInteractions = $this->fetchFbReelInteractionsDaily($from, $to);
                $pageEngagements = $this->fetchPageMetricDaily($pageId, 'page_post_engagements', $from, $to);
                // page_fans deprecated Nov 2025; use page_follows instead.
                $pageFans = $this->fetchPageMetricDaily($pageId, 'page_follows', $from, $to);
                $pageDailyFollows = $this->fetchPageMetricDaily($pageId, 'page_daily_follows', $from, $to);
                $messagingDaily = $this->fetchMessagingDaily('messenger', $from, $to);

                $dates = [];
                for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
                    $date = $d->toDateString();
                    $dates[$date] = [
                        'date' => $date,
                        'reach' => (int) ($base[$date]['reach'] ?? 0),
                        // page_media_view already includes video+reels — don't add them again.
                        'post_impressions' => (int) ($base[$date]['impressions'] ?? 0),
                        'page_views' => (int) ($pageViews[$date] ?? 0),
                        // Content interactions = post reactions (date-scoped) + reel interactions.
                        'page_engagements' => (int) ($pageReactions[$date] ?? 0) + (int) ($reelInteractions[$date] ?? 0),
                        'post_engagement' => (int) ($pageEngagements[$date] ?? 0),
                        'new_threads' => (int) ($messagingDaily[$date]['conversations'] ?? 0),
                        'messages_received' => (int) ($messagingDaily[$date]['messages_received'] ?? 0),
                        'messages_sent' => (int) ($messagingDaily[$date]['messages_sent'] ?? 0),
                        'page_fans' => (int) ($pageFans[$date] ?? 0),
                        'page_daily_follows' => (int) ($pageDailyFollows[$date] ?? 0),
                    ];
                }

                return array_values($dates);
            });
        });
    }

    public function fbTopPosts(string $from, string $to, int $limit = 12, bool $noCache = false): array
    {
        return $this->remember("meta_v2_fb_posts:{$from}:{$to}:{$limit}", $noCache, function () use ($from, $to, $limit) {
            if ($this->isDbFirst()) {
                return $this->resolver->resolveFbTopPosts($from, $to, $limit);
            }

            return $this->runWithV2Version(function () use ($from, $to, $limit) {
                $posts = $this->fetchFacebookPosts($from, $to, $limit);
                usort($posts, fn (array $a, array $b) => ($b['engagement'] ?? 0) <=> ($a['engagement'] ?? 0));
                return array_slice($posts, 0, $limit);
            });
        });
    }

    public function fbMessaging(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_fb_messaging:{$from}:{$to}", $noCache, function () use ($from, $to) {
            if ($this->isDbFirst()) {
                $daily = $this->resolver->resolveMessagingDaily('messenger', $from, $to);
                $paidDaily = $this->getAdsPlatformMessagingDaily($from, $to, 'facebook');

                $totals = ['conversations' => 0, 'received' => 0, 'sent' => 0];
                foreach ($daily as &$row) {
                    $date = $row['date'] ?? '';
                    $paid = (int) ($paidDaily[$date] ?? 0);
                    $row['new_conversations'] = (int) ($row['new_conversations'] ?? $row['conversations'] ?? 0) + $paid;
                    $row['total_messages_received'] = (int) ($row['total_messages_received'] ?? $row['messages_received'] ?? 0) + $paid;
                    $row['total_messages_sent'] = (int) ($row['total_messages_sent'] ?? $row['messages_sent'] ?? 0);
                    $totals['conversations'] += $row['new_conversations'];
                    $totals['received'] += $row['total_messages_received'];
                    $totals['sent'] += $row['total_messages_sent'];
                }
                unset($row);

                return ['totals' => $totals, 'daily' => array_values($daily)];
            }

            return $this->runWithV2Version(function () use ($from, $to) {
                $daily = $this->fetchMessagingDaily('messenger', $from, $to);

                $totals = [
                    'conversations' => 0,
                    'received' => 0,
                    'sent' => 0,
                ];

                foreach ($daily as &$row) {
                    $row['new_conversations'] = (int) ($row['conversations'] ?? 0);
                    $row['total_messages_received'] = (int) ($row['messages_received'] ?? 0);
                    $row['total_messages_sent'] = (int) ($row['messages_sent'] ?? 0);
                    $totals['conversations'] += $row['new_conversations'];
                    $totals['received'] += $row['total_messages_received'];
                    $totals['sent'] += $row['total_messages_sent'];
                }
                unset($row);

                return [
                    'totals' => $totals,
                    'daily' => array_values($daily),
                ];
            });
        });
    }

    private function windowTotals(string $from, string $to, ?string $preset = null, bool $noCache = false, bool $dbOnly = false): array
    {
        $preset = $this->normalizePreset($preset);
        $cacheVersion = Cache::get('meta_cache_version', 0);
        $cacheKey = $this->getApiVersion() . ":v{$cacheVersion}:meta_v2_window:{$from}:{$to}:" . ($preset ?? 'none');

        if (!$noCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::info("ChannelService windowTotals: CACHE HIT [{$from}..{$to}]");
                return (array) $cached;
            }
        }

        if ($this->isDbFirst()) {
            $mode = $dbOnly ? 'DB-only (YoY)' : 'DB-first';
            Log::info("ChannelService windowTotals: {$mode} [{$from}..{$to}]");
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
            } else {
                $result = [
                    'ads' => $this->resolver->resolveAdsTotals($from, $to),
                    'facebook' => $this->resolver->resolveFbTotals($from, $to),
                    'instagram' => $this->resolver->resolveIgTotals($from, $to),
                ];
            }

            $elapsed = round(microtime(true) - $startTime, 2);
            Log::info("ChannelService windowTotals: {$mode} DONE in {$elapsed}s — ads_spend={$result['ads']['spend']}, fb_reach={$result['facebook']['reach']}, ig_reach={$result['instagram']['reach']}");
        } else {
            Log::info("ChannelService windowTotals: API mode [{$from}..{$to}]");
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
                    fn () => $this->fetchInstagramTotals($from, $to, (int) ($ads['messaging_conversations_replied'] ?? 0)),
                    $this->emptyInstagramTotals(),
                    'instagram',
                    $from,
                    $to
                );

                return [
                    'ads' => $ads,
                    'facebook' => $fb,
                    'instagram' => $ig,
                ];
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

    private function emptyWindowTotals(): array
    {
        return [
            'ads' => $this->emptyAdsTotals(),
            'facebook' => $this->emptyFacebookTotals(),
            'instagram' => $this->emptyInstagramTotals(),
        ];
    }

    private function emptyAdsTotals(): array
    {
        return [
            'spend' => 0.0,
            'impressions' => 0,
            'reach' => 0,
            'link_clicks' => 0,
            'purchases' => 0,
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
            'content_interactions' => 0,
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
            return $this->emptyAdsTotals();
        }

        // No time_increment → single de-duplicated row for the entire period.
        // Reach, impressions etc. are properly de-duplicated by Meta.
        $rows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'account',
            'fields' => 'impressions,reach,spend,actions,action_values',
        ]);

        if (empty($rows) || !isset($rows[0])) {
            return $this->emptyAdsTotals();
        }

        $totals = $this->emptyAdsTotals();
        $this->accumulateAdsRow($totals, $rows[0]);

        return [
            'spend' => (float) $totals['spend'],
            'impressions' => (int) $totals['impressions'],
            'reach' => (int) $totals['reach'],
            'link_clicks' => (int) $totals['link_clicks'],
            'purchases' => (int) $totals['purchases'],
            'revenue' => (float) $totals['revenue'],
            'messaging_conversations' => (int) $totals['messaging_conversations'],
            'messaging_conversations_replied' => (int) $totals['messaging_conversations_replied'],
        ];
    }

    private function fetchAdsDaily(string $from, string $to): array
    {
        $adAccountId = (string) config('meta.ad_account_id', '');
        if ($adAccountId === '') {
            return [];
        }

        $rows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'account',
            'time_increment' => 1,
            'fields' => 'date_start,impressions,reach,spend,actions,action_values',
        ]);

        $daily = [];
        foreach ($rows as $row) {
            $date = (string) ($row['date_start'] ?? '');
            if ($date === '') {
                continue;
            }

            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'spend' => 0.0,
                    'impressions' => 0,
                    'reach' => 0,
                    'link_clicks' => 0,
                    'purchases' => 0,
                    'revenue' => 0.0,
                    'messaging_conversations' => 0,
                    'messaging_conversations_replied' => 0,
                ];
            }

            $this->accumulateAdsRow($daily[$date], $row);
        }

        return $daily;
    }

    private function normalizeAdsPlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));

        return in_array($platform, ['all', 'facebook', 'instagram'], true) ? $platform : 'all';
    }

    private function fetchAdsPlatformTotals(string $from, string $to, string $platform): array
    {
        if ($platform === 'all') {
            return $this->fetchAdsTotals($from, $to);
        }

        // No time_increment → single de-duplicated row per platform breakdown.
        $rows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'account',
            'breakdowns' => 'publisher_platform',
            'fields' => 'impressions,reach,spend,actions,action_values',
        ]);

        foreach ($rows as $row) {
            if (strtolower((string) ($row['publisher_platform'] ?? '')) !== $platform) {
                continue;
            }

            $totals = $this->emptyAdsTotals();
            $this->accumulateAdsRow($totals, $row);
            return $totals;
        }

        return $this->emptyAdsTotals();
    }

    /**
     * Read daily paid messaging conversations from meta_ads_insights DB
     * using the platform_breakdown JSON column.
     * Returns ['2026-03-01' => 5, '2026-03-02' => 3, ...].
     */
    private function getAdsPlatformMessagingDaily(string $from, string $to, string $platform): array
    {
        $jsonPath = '$."' . $platform . '".messaging_conversations';

        $rows = \DB::table('meta_ads_insights')
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('platform_breakdown')
            ->selectRaw('date, SUM(JSON_EXTRACT(platform_breakdown, ?)) as mc', [$jsonPath])
            ->groupBy('date')
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            $daily[$row->date instanceof \DateTimeInterface ? $row->date->format('Y-m-d') : (string) $row->date] = (int) ($row->mc ?? 0);
        }

        return $daily;
    }

    private function fetchAdsPlatformDaily(string $from, string $to, string $platform): array
    {
        if ($platform === 'all') {
            return $this->fetchAdsDaily($from, $to);
        }

        $rows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'account',
            'time_increment' => 1,
            'breakdowns' => 'publisher_platform',
            'fields' => 'date_start,impressions,reach,spend,actions,action_values',
        ]);

        $daily = [];
        foreach ($rows as $row) {
            if (strtolower((string) ($row['publisher_platform'] ?? '')) !== $platform) {
                continue;
            }

            $date = (string) ($row['date_start'] ?? '');
            if ($date === '') {
                continue;
            }

            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'spend' => 0.0,
                    'impressions' => 0,
                    'reach' => 0,
                    'link_clicks' => 0,
                    'purchases' => 0,
                    'revenue' => 0.0,
                    'messaging_conversations' => 0,
                    'messaging_conversations_replied' => 0,
                ];
            }

            $this->accumulateAdsRow($daily[$date], $row);
        }

        return $daily;
    }

    private function fetchAdsCampaigns(string $from, string $to, string $platform): array
    {
        $adAccountId = (string) config('meta.ad_account_id', '');
        if ($adAccountId === '') {
            return [];
        }

        $campaignRows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'campaign',
            'breakdowns' => $platform === 'all' ? null : 'publisher_platform',
            'fields' => 'campaign_id,campaign_name,impressions,reach,spend,actions,action_values',
        ]);

        $adSetRows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'adset',
            'breakdowns' => $platform === 'all' ? null : 'publisher_platform',
            'fields' => 'campaign_id,adset_id,adset_name,impressions,spend,actions,action_values',
        ]);

        $campaignMeta = [];
        $adSetMeta = [];

        try {
            foreach ($this->api->getPaginated("{$adAccountId}/campaigns", [
                'fields' => 'id,name,status,objective',
            ], 100) as $campaign) {
                $campaignMeta[(string) ($campaign['id'] ?? '')] = $campaign;
            }
        } catch (Throwable $e) {
            Log::debug('Meta v2 ads campaign metadata failed: ' . $e->getMessage());
        }

        try {
            foreach ($this->api->getPaginated("{$adAccountId}/adsets", [
                'fields' => 'id,name,status,optimization_goal,campaign_id',
            ], 100) as $adSet) {
                $adSetMeta[(string) ($adSet['id'] ?? '')] = $adSet;
            }
        } catch (Throwable $e) {
            Log::debug('Meta v2 ads ad set metadata failed: ' . $e->getMessage());
        }

        $campaigns = [];
        foreach ($campaignRows as $row) {
            if ($platform !== 'all' && strtolower((string) ($row['publisher_platform'] ?? '')) !== $platform) {
                continue;
            }

            $campaignId = (string) ($row['campaign_id'] ?? '');
            if ($campaignId === '') {
                continue;
            }

            if (!isset($campaigns[$campaignId])) {
                $meta = $campaignMeta[$campaignId] ?? [];
                $campaigns[$campaignId] = array_merge($this->emptyAdsTotals(), [
                    'id' => $campaignId,
                    'name' => (string) ($meta['name'] ?? $row['campaign_name'] ?? 'Unnamed campaign'),
                    'objective' => (string) ($meta['objective'] ?? ''),
                    'status' => (string) ($meta['status'] ?? 'UNKNOWN'),
                    'ad_sets' => [],
                ]);
            }

            $this->accumulateAdsRow($campaigns[$campaignId], $row);
        }

        $adSetsByCampaign = [];
        foreach ($adSetRows as $row) {
            if ($platform !== 'all' && strtolower((string) ($row['publisher_platform'] ?? '')) !== $platform) {
                continue;
            }

            $campaignId = (string) ($row['campaign_id'] ?? '');
            $adSetId = (string) ($row['adset_id'] ?? '');
            if ($campaignId === '' || $adSetId === '') {
                continue;
            }

            if (!isset($adSetsByCampaign[$campaignId])) {
                $adSetsByCampaign[$campaignId] = [];
            }

            if (!isset($adSetsByCampaign[$campaignId][$adSetId])) {
                $meta = $adSetMeta[$adSetId] ?? [];
                $adSetsByCampaign[$campaignId][$adSetId] = array_merge($this->emptyAdsTotals(), [
                    'id' => $adSetId,
                    'name' => (string) ($meta['name'] ?? $row['adset_name'] ?? 'Unnamed ad set'),
                    'status' => (string) ($meta['status'] ?? 'UNKNOWN'),
                    'optimization_goal' => (string) ($meta['optimization_goal'] ?? ''),
                ]);
            }

            $this->accumulateAdsRow($adSetsByCampaign[$campaignId][$adSetId], $row);
        }

        foreach ($campaigns as &$campaign) {
            $campaign['ad_sets'] = [];
            foreach ($adSetsByCampaign[$campaign['id']] ?? [] as $adSet) {
                if (($adSet['spend'] ?? 0) <= 0 && ($adSet['impressions'] ?? 0) <= 0 && ($adSet['link_clicks'] ?? 0) <= 0) {
                    continue;
                }

                $campaign['ad_sets'][] = [
                    'name' => $adSet['name'],
                    'status' => $adSet['status'],
                    'optimization_goal' => $adSet['optimization_goal'],
                    'spend' => round((float) $adSet['spend'], 2),
                    'impressions' => (int) $adSet['impressions'],
                    'link_clicks' => (int) $adSet['link_clicks'],
                    'purchases' => (int) $adSet['purchases'],
                    'revenue' => round((float) $adSet['revenue'], 2),
                    'roas' => (float) $adSet['spend'] > 0 ? round((float) $adSet['revenue'] / (float) $adSet['spend'], 2) : 0.0,
                ];
            }

            usort($campaign['ad_sets'], fn (array $a, array $b) => ($b['spend'] ?? 0) <=> ($a['spend'] ?? 0));
        }
        unset($campaign);

        $campaigns = array_values(array_map(function (array $campaign) {
            $spend = (float) ($campaign['spend'] ?? 0);
            $impressions = (int) ($campaign['impressions'] ?? 0);
            $linkClicks = (int) ($campaign['link_clicks'] ?? 0);
            $revenue = (float) ($campaign['revenue'] ?? 0);

            return [
                'id' => $campaign['id'],
                'name' => $campaign['name'],
                'objective' => $campaign['objective'],
                'status' => $campaign['status'],
                'spend' => round($spend, 2),
                'impressions' => $impressions,
                'reach' => (int) ($campaign['reach'] ?? 0),
                'link_clicks' => $linkClicks,
                'ctr' => $impressions > 0 ? round(($linkClicks / $impressions) * 100, 2) : 0.0,
                'purchases' => (int) ($campaign['purchases'] ?? 0),
                'revenue' => round($revenue, 2),
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0.0,
                'cpc' => $linkClicks > 0 ? round($spend / $linkClicks, 2) : 0.0,
                'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 2) : 0.0,
                'ad_sets' => $campaign['ad_sets'],
            ];
        }, array_filter($campaigns, fn (array $campaign) => ($campaign['spend'] ?? 0) > 0 || ($campaign['impressions'] ?? 0) > 0 || ($campaign['link_clicks'] ?? 0) > 0)));

        usort($campaigns, fn (array $a, array $b) => ($b['spend'] ?? 0) <=> ($a['spend'] ?? 0));

        return $campaigns;
    }

    private function fetchAdsBreakdown(string $from, string $to, string $breakdown): array
    {
        $rows = $this->fetchAdsInsightsRows($from, $to, [
            'level' => 'account',
            'time_increment' => 1,
            'breakdowns' => $breakdown,
            'fields' => 'date_start,reach,impressions,spend,actions',
        ]);

        $result = [];
        foreach ($rows as $row) {
            $key = match ($breakdown) {
                'publisher_platform,platform_position' => trim(
                    strtolower((string) ($row['publisher_platform'] ?? 'unknown'))
                    . '_' .
                    strtolower((string) ($row['platform_position'] ?? 'unknown')),
                    '_'
                ),
                default => strtolower((string) ($row[$breakdown] ?? 'unknown')),
            };

            if ($key === '') {
                $key = 'unknown';
            }

            if (!isset($result[$key])) {
                $result[$key] = [
                    'spend' => 0.0,
                    'impressions' => 0,
                    'clicks' => 0,
                    'reach' => 0,
                ];
            }

            $actions = $this->parseActions($row['actions'] ?? []);
            $result[$key]['spend'] += (float) ($row['spend'] ?? 0);
            $result[$key]['impressions'] += (int) ($row['impressions'] ?? 0);
            $result[$key]['clicks'] += (int) $this->extractLinkClicks($actions);
            $result[$key]['reach'] += (int) ($row['reach'] ?? 0);
        }

        uasort($result, fn (array $a, array $b) => ($b['spend'] ?? 0) <=> ($a['spend'] ?? 0));
        return $result;
    }

    private function fetchAdsInsightsRows(string $from, string $to, array $params = []): array
    {
        $adAccountId = (string) config('meta.ad_account_id', '');
        if ($adAccountId === '') {
            return [];
        }

        $payload = array_filter(array_merge([
            'time_range' => json_encode(['since' => $from, 'until' => $to]),
        ], $params), fn ($value) => $value !== null && $value !== '');

        return $this->api->getAdsInsights($adAccountId, $this->withAttribution($payload));
    }

    private function accumulateAdsRow(array &$bucket, array $row): void
    {
        $actions = $this->parseActions($row['actions'] ?? []);
        $actionValues = $this->parseActions($row['action_values'] ?? []);

        $bucket['spend'] = (float) ($bucket['spend'] ?? 0) + (float) ($row['spend'] ?? 0);
        $bucket['impressions'] = (int) ($bucket['impressions'] ?? 0) + (int) ($row['impressions'] ?? 0);
        $bucket['reach'] = (int) ($bucket['reach'] ?? 0) + (int) ($row['reach'] ?? 0);
        $bucket['link_clicks'] = (int) ($bucket['link_clicks'] ?? 0) + (int) $this->extractLinkClicks($actions);
        $bucket['purchases'] = (int) ($bucket['purchases'] ?? 0) + (int) $this->extractPurchases($actions);
        $bucket['revenue'] = (float) ($bucket['revenue'] ?? 0) + (float) $this->extractRevenue($actionValues);
        $bucket['messaging_conversations'] = (int) ($bucket['messaging_conversations'] ?? 0) + (int) $this->extractMessagingStarted($actions);
        $bucket['messaging_conversations_replied'] = (int) ($bucket['messaging_conversations_replied'] ?? 0) + (int) $this->extractMessagingReplied($actions);
    }

    private function extractLinkClicks(array $actions): float
    {
        foreach (['link_click', 'outbound_click'] as $key) {
            if (isset($actions[$key])) {
                return $this->normalizeMetricValue($actions[$key]);
            }
        }

        return 0.0;
    }

    private function extractPurchases(array $actions): float
    {
        foreach ([
            'purchase',
            'omni_purchase',
            'offsite_conversion.fb_pixel_purchase',
            'onsite_web_purchase',
            'app_custom_event.fb_mobile_purchase',
        ] as $key) {
            if (isset($actions[$key])) {
                return $this->normalizeMetricValue($actions[$key]);
            }
        }

        return 0.0;
    }

    private function extractRevenue(array $actionValues): float
    {
        foreach ([
            'purchase',
            'omni_purchase',
            'offsite_conversion.fb_pixel_purchase',
            'onsite_web_purchase',
            'app_custom_event.fb_mobile_purchase',
        ] as $key) {
            if (isset($actionValues[$key])) {
                return $this->normalizeMetricValue($actionValues[$key]);
            }
        }

        return 0.0;
    }

    private function extractMessagingStarted(array $actions): float
    {
        foreach ([
            'onsite_conversion.total_messaging_connection',
            'onsite_conversion.messaging_conversation_started_7d',
            'onsite_conversion.messaging_first_reply',
        ] as $key) {
            if (isset($actions[$key])) {
                return $this->normalizeMetricValue($actions[$key]);
            }
        }

        return 0.0;
    }

    private function extractMessagingReplied(array $actions): float
    {
        foreach ([
            'onsite_conversion.messaging_conversation_replied_7d',
            'onsite_conversion.messaging_conversation_started_7d',
        ] as $key) {
            if (isset($actions[$key])) {
                return $this->normalizeMetricValue($actions[$key]);
            }
        }

        return 0.0;
    }

    private function fetchFacebookTotals(string $from, string $to, ?string $preset = null): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');
        $defaults = [
            'impressions' => 0,
            'reach' => 0,
            'page_views' => 0,
            'post_engagement' => 0,
            'content_interactions' => 0,
            'link_clicks' => 0,
            'conversations' => 0,
            'received' => 0,
            'sent' => 0,
        ];

        if ($pageId === '' || $pageToken === '') {
            return $defaults;
        }

        // Period-level totals via total_over_range — de-duplicated, matches Meta Insights exactly.
        // Never SUM daily rows for KPI cards.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        $reach = 0;
        $impressions = 0;
        $pageViews = 0;
        $postEngagement = 0;

        // Batch 1: numeric metrics via total_over_range
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

        // Batch 2: Content Interactions (reactions breakdown) via total_over_range
        $contentInteractions = 0;
        try {
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_actions_post_reactions_total',
                'since' => $from,
                'until' => $untilExclusive,
                'period' => 'total_over_range',
            ]);
            foreach ($response['data'] ?? [] as $entry) {
                if (($entry['name'] ?? '') === 'page_actions_post_reactions_total') {
                    $val = $entry['values'][0]['value'] ?? $entry['total_value']['value'] ?? [];
                    if (is_array($val)) {
                        $contentInteractions = (int) array_sum($val);
                    } else {
                        $contentInteractions = (int) $val;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB content_interactions total_over_range failed: " . $e->getMessage());
        }

        $messaging = $this->fetchMessagingTotals('messenger', $from, $to);

        return [
            'impressions' => (int) $impressions,
            'reach' => (int) $reach,
            'page_views' => (int) $pageViews,
            'post_engagement' => (int) $postEngagement,
            'content_interactions' => (int) $contentInteractions,
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

        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        // Fetch both metrics in a single API call (was 2 separate calls).
        // page_impressions_unique and page_posts_impressions deprecated Nov 2025.
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
        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();
        try {
            $response = $this->api->getPageInsights($pageId, $metric, 'day', $from, $untilExclusive);
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
        // API enforces a hard 30-day rolling window — clamp $from to avoid error 100.
        $newFollowers = 0;
        try {
            $followerFrom = max($from, Carbon::yesterday()->subDays(29)->toDateString());
            if ($followerFrom <= $to) {
                $followerResponse = $this->api->getIgInsights($igAccountId, 'follower_count', 'day', $followerFrom, $untilExclusive);
                foreach ($followerResponse['data'] ?? [] as $entry) {
                    foreach ($entry['values'] ?? [] as $value) {
                        $newFollowers += (int) ($value['value'] ?? 0);
                    }
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

        // Only `reach` supports plain period=day with daily breakdown.
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

        // profile_views requires metric_type=total_value — fetch total and distribute evenly.
        try {
            $response = $this->api->getIgInsights($igAccountId, 'profile_views', 'day', $from, $untilExclusive, 'total_value');
            $totalPv = 0;
            foreach ($response['data'] ?? [] as $entry) {
                $totalPv = (int) round($this->normalizeMetricValue($entry['total_value']['value'] ?? 0));
            }
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

    private function fetchInstagramRichDaily(string $from, string $to): array
    {
        $igAccountId = (string) config('meta.ig_account_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($igAccountId === '') {
            $igAccountId = $this->discoverIgAccountId() ?? '';
        }

        $dates = [];
        for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $dates[$d->toDateString()] = [
                'date' => $d->toDateString(),
                'views' => 0,
                'reach' => 0,
                'profile_views' => 0,
                'website_clicks' => 0,
                'content_interactions' => 0,
                'follower_count' => 0,
                'new_followers' => 0,
                'conversations' => 0,
                'messages_received' => 0,
                'messages_sent' => 0,
            ];
        }

        if ($igAccountId === '' || $pageToken === '') {
            return $dates;
        }

        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        // Only `reach` supports plain period=day with daily breakdown.
        // All other IG metrics require metric_type=total_value.
        try {
            $response = $this->api->getIgInsights($igAccountId, 'reach', 'day', $from, $untilExclusive);
            foreach ($response['data'] ?? [] as $entry) {
                foreach ($entry['values'] ?? [] as $value) {
                    $date = Carbon::parse($value['end_time'])->toDateString();
                    if (isset($dates[$date])) {
                        $dates[$date]['reach'] = (int) round($this->normalizeMetricValue($value['value'] ?? 0));
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 IG rich daily reach failed: " . $e->getMessage());
        }

        // Fetch views, profile_views, website_clicks, total_interactions per-day with metric_type=total_value.
        $totalValueMetrics = [
            'views' => 'views',
            'profile_views' => 'profile_views',
            'website_clicks' => 'website_clicks',
            'content_interactions' => 'total_interactions',
        ];
        $tvCsv = implode(',', array_values($totalValueMetrics));
        $tvReverse = array_flip($totalValueMetrics);

        for ($day = Carbon::parse($from)->copy(); $day->lte(Carbon::parse($to)); $day->addDay()) {
            $dayStr = $day->toDateString();
            $nextDay = $day->copy()->addDay()->toDateString();
            try {
                $response = $this->api->getIgInsights($igAccountId, $tvCsv, 'day', $dayStr, $nextDay, 'total_value');
                foreach ($response['data'] ?? [] as $entry) {
                    $name = $entry['name'] ?? '';
                    $key = $tvReverse[$name] ?? null;
                    if ($key !== null && isset($dates[$dayStr])) {
                        $dates[$dayStr][$key] = (int) round($this->normalizeMetricValue($entry['total_value']['value'] ?? 0));
                    }
                }
            } catch (Throwable $e) {
                Log::debug("Meta v2 IG rich daily total_value failed [{$dayStr}]: " . $e->getMessage());
            }
        }

        // follower_count API enforces a hard 30-day rolling window — clamp $from.
        $dailyFollowerChanges = [];
        try {
            $followerFrom = max($from, Carbon::yesterday()->subDays(29)->toDateString());
            if ($followerFrom <= $to) {
                $followerUntil = Carbon::parse($to)->copy()->addDays(2)->toDateString();
                $response = $this->api->getIgInsights($igAccountId, 'follower_count', 'day', $followerFrom, $followerUntil);
                foreach ($response['data'] ?? [] as $metricEntry) {
                    foreach ($metricEntry['values'] ?? [] as $dayValue) {
                        $date = Carbon::parse($dayValue['end_time'] ?? $followerFrom)->subDay()->toDateString();
                        $dailyFollowerChanges[$date] = (int) ($dayValue['value'] ?? 0);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug('Meta v2 IG follower_count daily failed: ' . $e->getMessage());
        }

        foreach ($dailyFollowerChanges as $date => $value) {
            if (isset($dates[$date])) {
                $dates[$date]['new_followers'] = $value;
            }
        }

        $currentFollowers = $this->fetchCurrentIgFollowers($igAccountId);
        if ($currentFollowers > 0) {
            $running = $currentFollowers;
            foreach (array_reverse(array_keys($dates)) as $date) {
                $dates[$date]['follower_count'] = max(0, $running);
                $running -= (int) ($dates[$date]['new_followers'] ?? 0);
            }
        }

        return $dates;
    }

    private function fetchIgMetricDailySeries(string $igAccountId, string $metric, string $from, string $to): array
    {
        $series = [];

        // Most IG metrics need metric_type=total_value. Only `reach` and `follower_count` support
        // plain period=day. Go straight to per-day total_value to avoid failed retries.
        for ($day = Carbon::parse($from)->copy(); $day->lte(Carbon::parse($to)); $day->addDay()) {
            $dayStr = $day->toDateString();
            $nextDay = $day->copy()->addDay()->toDateString();

            try {
                $response = $this->api->getIgInsights($igAccountId, $metric, 'day', $dayStr, $nextDay, 'total_value');
                foreach ($response['data'] ?? [] as $entry) {
                    $series[$dayStr] = (int) round($this->normalizeMetricValue($entry['total_value']['value'] ?? 0));
                }
            } catch (Throwable $e) {
                Log::debug("Meta v2 IG daily series failed [{$metric} {$dayStr}]: " . $e->getMessage());
            }
        }

        return $series;
    }

    private function fetchInstagramPosts(string $from, string $to, int $limit = 50): array
    {
        $igAccountId = (string) config('meta.ig_account_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($igAccountId === '') {
            $igAccountId = $this->discoverIgAccountId() ?? '';
        }
        if ($igAccountId === '' || $pageToken === '') {
            return [];
        }

        // Fetch media with date bounds so past-month queries find the right posts.
        try {
            $sinceTs = Carbon::parse($from)->startOfDay()->timestamp;
            $untilTs = Carbon::parse($to)->endOfDay()->timestamp;
            $response = $this->api->getWithPageToken("{$igAccountId}/media", [
                'fields' => 'id,caption,media_type,media_product_type,permalink,thumbnail_url,media_url,timestamp,like_count,comments_count',
                'since' => $sinceTs,
                'until' => $untilTs,
                'limit' => 50,
            ]);
            $media = $response['data'] ?? [];
        } catch (Throwable $e) {
            Log::debug('Meta v2 IG media fetch failed: ' . $e->getMessage());
            return [];
        }

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();

        // Phase 1: Build candidates from listing (no per-post insights yet).
        $candidates = [];
        foreach ($media as $item) {
            $mediaId = (string) ($item['id'] ?? '');
            $createdAt = isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null;
            if ($mediaId === '' || $createdAt === null || $createdAt->lt($fromStart) || $createdAt->gt($toEnd)) {
                continue;
            }
            $likes = (int) ($item['like_count'] ?? 0);
            $comments = (int) ($item['comments_count'] ?? 0);
            $candidates[] = [
                'item' => $item,
                'created_at' => $createdAt,
                'quick_engagement' => $likes + $comments,
            ];
        }

        // Phase 2: Sort by likes+comments, take top N, then fetch insights only for those.
        usort($candidates, fn ($a, $b) => $b['quick_engagement'] <=> $a['quick_engagement']);
        $candidates = array_slice($candidates, 0, $limit);

        $posts = [];
        foreach ($candidates as $candidate) {
            try {
                $item = $candidate['item'];
                $createdAt = $candidate['created_at'];
                $mediaId = (string) $item['id'];
                $postType = $this->mapIgMediaType((string) ($item['media_type'] ?? ''), (string) ($item['media_product_type'] ?? ''));
                $insights = $this->fetchIgMediaInsights($mediaId, $postType);
                $likes = (int) ($item['like_count'] ?? 0);
                $comments = (int) ($item['comments_count'] ?? 0);
                $shares = (int) ($insights['shares'] ?? 0);
                $saves = (int) ($insights['saved'] ?? 0);

                $posts[] = [
                    'post_id' => $mediaId,
                    'post_type' => $postType,
                    'message' => mb_substr((string) ($item['caption'] ?? ''), 0, 100),
                    'permalink_url' => $item['permalink'] ?? null,
                    'media_url' => $item['thumbnail_url'] ?? ($item['media_url'] ?? null),
                    'created_at' => $createdAt->format('Y-m-d H:i'),
                    'impressions' => (int) ($insights['impressions'] ?? ($insights['views'] ?? 0)),
                    'reach' => (int) ($insights['reach'] ?? 0),
                    'engagement' => $likes + $comments + $shares + $saves,
                    'likes' => $likes,
                    'comments' => $comments,
                    'shares' => $shares,
                    'saves' => $saves,
                    'video_views' => (int) ($insights['views'] ?? 0),
                    'plays' => (int) ($insights['views'] ?? 0),
                ];
            } catch (Throwable $e) {
                Log::debug('Meta v2 IG media item skipped: ' . $e->getMessage(), [
                    'media_id' => $item['id'] ?? null,
                ]);
            }
        }

        return $posts;
    }

    private function fetchFacebookPosts(string $from, string $to, int $limit = 50): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($pageId === '' || $pageToken === '') {
            return [];
        }

        try {
            $posts = $this->api->getPagePosts($pageId, [
                'id',
                'message',
                'created_time',
                'permalink_url',
                'attachments{type,media_type,url,media,subattachments}',
            ], max($limit, 50), $from);
        } catch (Throwable $e) {
            Log::debug('Meta v2 FB posts fetch failed: ' . $e->getMessage());
            return [];
        }

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();
        $result = [];

        foreach ($posts as $post) {
            // Stop once we have enough posts with insights.
            if (count($result) >= $limit) {
                break;
            }

            try {
                $postId = (string) ($post['id'] ?? '');
                $createdAt = isset($post['created_time']) ? Carbon::parse($post['created_time']) : null;
                if ($postId === '' || $createdAt === null || $createdAt->lt($fromStart) || $createdAt->gt($toEnd)) {
                    continue;
                }

                $attachment = $post['attachments']['data'][0] ?? [];
                $postType = $this->mapFbPostType((string) ($attachment['type'] ?? ($attachment['media_type'] ?? 'status')));
                $insights = $this->fetchFbPostInsights($postId);
                $likes = (int) ($insights['likes'] ?? 0);
                $comments = (int) ($insights['comments'] ?? 0);
                $shares = (int) ($insights['shares'] ?? 0);

                $result[] = [
                    'post_id' => $postId,
                    'post_type' => $postType,
                    'message' => mb_substr((string) ($post['message'] ?? ''), 0, 100),
                    'permalink_url' => $post['permalink_url'] ?? null,
                    'media_url' => $this->extractFbMediaUrl($attachment),
                    'created_at' => $createdAt->format('Y-m-d H:i'),
                    'impressions' => (int) ($insights['impressions'] ?? 0),
                    'reach' => (int) ($insights['reach'] ?? 0),
                    'engagement' => $likes + $comments + $shares,
                    'likes' => $likes,
                    'comments' => $comments,
                    'shares' => $shares,
                    'video_views' => (int) ($insights['video_views'] ?? 0),
                ];
            } catch (Throwable $e) {
                Log::debug('Meta v2 FB post item skipped: ' . $e->getMessage(), [
                    'post_id' => $post['id'] ?? null,
                ]);
            }
        }

        return $result;
    }

    private function fetchPageMetricDaily(string $pageId, string $metric, string $from, string $to): array
    {
        if ($pageId === '') {
            return [];
        }

        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();
        try {
            $response = $this->api->getPageInsights($pageId, $metric, 'day', $from, $untilExclusive);
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB metric daily failed [{$metric}]: " . $e->getMessage());
            return [];
        }

        $daily = [];
        foreach ($response['data'] ?? [] as $entry) {
            foreach ($entry['values'] ?? [] as $value) {
                // FB Page Insights end_time marks the END of the period — subtract 1 day to get the actual date.
                $date = Carbon::parse($value['end_time'] ?? $from)->subDay()->toDateString();
                $daily[$date] = (int) round($this->normalizeMetricValue($value['value'] ?? 0));
            }
        }

        return $daily;
    }

    /**
     * Fetch Reel interactions (likes + comments) bucketed by creation date.
     * page_actions_post_reactions_total covers post reactions but excludes Reels entirely.
     * This supplements it with Reel data to approximate Meta's "Content interactions".
     */
    private function fetchFbReelInteractionsDaily(string $from, string $to): array
    {
        $pageId = (string) config('meta.page_id', '');
        if ($pageId === '') {
            return [];
        }

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();
        $daily = [];

        try {
            $reels = $this->api->getPaginatedWithPageToken("{$pageId}/video_reels", [
                'fields' => 'id,created_time,likes.summary(total_count).limit(0),comments.summary(total_count).limit(0)',
                'since' => $fromStart->timestamp,
                'until' => $toEnd->timestamp,
            ], 100, 5);

            foreach ($reels as $reel) {
                $createdAt = isset($reel['created_time']) ? Carbon::parse($reel['created_time']) : null;
                if ($createdAt === null || $createdAt->lt($fromStart) || $createdAt->gt($toEnd)) {
                    continue;
                }
                $date = $createdAt->toDateString();
                $likes = (int) ($reel['likes']['summary']['total_count'] ?? 0);
                $comments = (int) ($reel['comments']['summary']['total_count'] ?? 0);
                $daily[$date] = ($daily[$date] ?? 0) + $likes + $comments;
            }
        } catch (Throwable $e) {
            Log::debug('Meta v2 FB reel interactions failed: ' . $e->getMessage());
        }

        return $daily;
    }

    private function fetchMessagingDaily(string $platform, string $from, string $to): array
    {
        // Cache messaging daily to avoid duplicate conversations fetch across endpoints.
        return $this->remember("meta_v2_msg_daily:{$platform}:{$from}:{$to}", false, function () use ($platform, $from, $to) {
            return $this->fetchMessagingDailyRaw($platform, $from, $to);
        });
    }

    private function fetchMessagingDailyRaw(string $platform, string $from, string $to): array
    {
        $pageId = (string) config('meta.page_id', '');
        $pageToken = (string) config('meta.page_token', '');

        $daily = [];
        for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $daily[$d->toDateString()] = [
                'date' => $d->toDateString(),
                'conversations' => 0,
                'messages_received' => 0,
                'messages_sent' => 0,
            ];
        }

        if ($pageId === '' || $pageToken === '') {
            return $daily;
        }

        $isInstagram = $platform === 'instagram';
        // Meta's `until` parameter is EXCLUSIVE — add 1 day to include the user's end date.
        // 3 pages × 500 limit = ~1500 conversations per fetch, sufficient for organic.
        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();
        try {
            $all = $this->api->getConversations($pageId, $platform, $from, $untilExclusive, 3);
        } catch (Throwable $e) {
            Log::debug("Meta v2 {$platform} conversations daily failed: " . $e->getMessage());
            return $daily;
        }

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();
        $filtered = [];

        foreach ($all as $conversation) {
            $updated = $conversation['updated_time'] ?? null;
            if (!$updated) {
                continue;
            }

            $updatedAt = Carbon::parse($updated);
            if ($updatedAt->lt($fromStart) || $updatedAt->gt($toEnd)) {
                continue;
            }

            $filtered[] = $conversation;
            $date = $updatedAt->toDateString();
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'conversations' => 0,
                    'messages_received' => 0,
                    'messages_sent' => 0,
                ];
            }
            $daily[$date]['conversations']++;
        }

        // Use message_count from conversations API instead of per-conversation message fetch.
        // This eliminates up to 60+ API calls per dashboard load.
        foreach ($filtered as $conversation) {
            $updatedAt = Carbon::parse((string) ($conversation['updated_time'] ?? $from));
            $day = $updatedAt->toDateString();
            $messageCount = $isInstagram ? 1 : max(1, (int) ($conversation['message_count'] ?? 1));

            if (!isset($daily[$day])) {
                $daily[$day] = [
                    'date' => $day,
                    'conversations' => 0,
                    'messages_received' => 0,
                    'messages_sent' => 0,
                ];
            }

            $daily[$day]['messages_received'] += $messageCount;
        }

        ksort($daily);
        return $daily;
    }

    private function fetchCurrentIgFollowers(string $igAccountId): int
    {
        try {
            $response = $this->api->getWithPageToken($igAccountId, [
                'fields' => 'followers_count',
            ]);

            return (int) ($response['followers_count'] ?? 0);
        } catch (Throwable $e) {
            Log::debug('Meta v2 IG followers_count fetch failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function sumPostEngagement(array $posts): int
    {
        return array_sum(array_map(fn (array $post) => (int) ($post['engagement'] ?? 0), $posts));
    }

    /**
     * Fast post engagement sum using only the media listing (likes+comments).
     * Avoids 50+ per-post insights API calls. Used by igKpis().
     */
    private function sumIgMediaEngagementFast(string $from, string $to): int
    {
        $igAccountId = (string) config('meta.ig_account_id', '');
        $pageToken = (string) config('meta.page_token', '');
        if ($igAccountId === '') {
            $igAccountId = $this->discoverIgAccountId() ?? '';
        }
        if ($igAccountId === '' || $pageToken === '') {
            return 0;
        }

        // Fetch media with date bounds so past-month queries return correct data.
        $sinceTs = Carbon::parse($from)->startOfDay()->timestamp;
        $untilTs = Carbon::parse($to)->endOfDay()->timestamp;
        $response = $this->api->getWithPageToken("{$igAccountId}/media", [
            'fields' => 'id,timestamp,like_count,comments_count',
            'since' => $sinceTs,
            'until' => $untilTs,
            'limit' => 50,
        ]);
        $media = $response['data'] ?? [];

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();
        $total = 0;

        foreach ($media as $item) {
            $createdAt = isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null;
            if ($createdAt === null || $createdAt->lt($fromStart) || $createdAt->gt($toEnd)) {
                continue;
            }
            $total += (int) ($item['like_count'] ?? 0) + (int) ($item['comments_count'] ?? 0);
        }

        return $total;
    }

    private function fetchFbPostInsights(string $postId): array
    {
        $insights = [];

        try {
            $response = $this->api->getPostInsights($postId, 'post_media_view,post_clicks');
            foreach ($response['data'] ?? [] as $metric) {
                $name = (string) ($metric['name'] ?? '');
                $value = $metric['values'][0]['value'] ?? 0;
                if ($name === 'post_media_view') {
                    $insights['impressions'] = (int) round($this->normalizeMetricValue($value));
                } elseif ($name === 'post_clicks') {
                    $insights['clicks'] = (int) round($this->normalizeMetricValue($value));
                }
            }
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB post insights failed [{$postId}]: " . $e->getMessage());
        }

        try {
            $postData = $this->api->getWithPageToken($postId, [
                'fields' => 'shares,comments.summary(true),reactions.summary(true)',
            ]);
            $insights['shares'] = (int) ($postData['shares']['count'] ?? 0);
            $insights['comments'] = (int) ($postData['comments']['summary']['total_count'] ?? 0);
            $insights['likes'] = (int) ($postData['reactions']['summary']['total_count'] ?? 0);
        } catch (Throwable $e) {
            Log::debug("Meta v2 FB post object failed [{$postId}]: " . $e->getMessage());
        }

        return $insights;
    }

    private function fetchIgMediaInsights(string $mediaId, string $postType): array
    {
        try {
            $metrics = match ($postType) {
                'story' => 'views,reach,replies',
                'reel' => 'views,reach,saved,shares',
                default => 'views,reach,saved,shares',
            };

            $response = $this->api->getPostInsights($mediaId, $metrics);
            $insights = [];
            foreach ($response['data'] ?? [] as $metric) {
                $name = (string) ($metric['name'] ?? '');
                $value = $metric['values'][0]['value'] ?? 0;
                $insights[$name] = (int) round($this->normalizeMetricValue($value));
            }

            if (isset($insights['views']) && !isset($insights['impressions'])) {
                $insights['impressions'] = $insights['views'];
            }

            return $insights;
        } catch (Throwable $e) {
            Log::debug("Meta v2 IG post insights failed [{$mediaId}]: " . $e->getMessage());
            return [];
        }
    }

    private function mapFbPostType(string $type): string
    {
        return match (strtolower($type)) {
            'photo' => 'photo',
            'video' => 'video',
            'link' => 'link',
            default => 'status',
        };
    }

    private function mapIgMediaType(string $mediaType, string $productType): string
    {
        $productType = strtolower($productType);
        if ($productType === 'reels' || $productType === 'reel') {
            return 'reel';
        }
        if ($productType === 'story') {
            return 'story';
        }

        return match (strtoupper($mediaType)) {
            'IMAGE' => 'photo',
            'VIDEO' => 'video',
            'CAROUSEL_ALBUM' => 'carousel_album',
            default => 'photo',
        };
    }

    private function extractFbMediaUrl(array $attachment): ?string
    {
        $url = $attachment['media']['image']['src'] ?? null;
        if (is_string($url) && $url !== '') {
            return $url;
        }

        $sub = $attachment['subattachments']['data'][0]['media']['image']['src'] ?? null;
        if (is_string($sub) && $sub !== '') {
            return $sub;
        }

        $fallback = $attachment['url'] ?? null;
        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    private function fetchMessagingTotals(string $platform, string $from, string $to): array
    {
        // Cache messaging totals to avoid duplicate conversations fetch across endpoints.
        return $this->remember("meta_v2_msg_totals:{$platform}:{$from}:{$to}", false, function () use ($platform, $from, $to) {
            return $this->fetchMessagingTotalsRaw($platform, $from, $to);
        });
    }

    private function fetchMessagingTotalsRaw(string $platform, string $from, string $to): array
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
        // 3 pages × 500 limit = ~1500 conversations per fetch, sufficient for organic.
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

    private bool $lastCacheHit = false;

    public function wasCacheHit(): bool
    {
        return $this->lastCacheHit;
    }

    private function remember(string $cacheKey, bool $noCache, callable $callback): array
    {
        // Include API version AND cache version in key so both version upgrades
        // and Rifresko cache busts invalidate stale data.
        $cacheVersion = Cache::get('meta_cache_version', 0);
        $versionedKey = $this->getApiVersion() . ':v' . $cacheVersion . ':' . $cacheKey;

        if (!$noCache) {
            $cached = Cache::get($versionedKey);
            if ($cached !== null) {
                $this->lastCacheHit = true;
                Log::info("ChannelService: CACHE HIT [{$cacheKey}]");
                return (array) $cached;
            }
        }

        $this->lastCacheHit = false;
        $source = $this->isDbFirst() ? 'DB-first' : 'API';
        Log::info("ChannelService: CACHE MISS [{$cacheKey}] — resolving via {$source}");
        $startTime = microtime(true);
        $result = $callback();
        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info("ChannelService: resolved [{$cacheKey}] in {$elapsed}s via {$source}");

        // Historical data (date range fully in the past) can be cached much longer.
        // Current/live data uses shorter TTL since it may update during the day.
        // Cache keys contain dates in format meta_v2_*:{from}:{to}[:extra].
        $ttl = 1800; // 30 minutes default
        if (preg_match('/:\d{4}-\d{2}-\d{2}:(\d{4}-\d{2}-\d{2})/', $cacheKey, $m)) {
            $toDate = $m[1];
            if (Carbon::parse($toDate)->lt(Carbon::today())) {
                $ttl = 604800; // 7 days for fully historical ranges (data won't change)
            }
        }

        Cache::put($versionedKey, $result, $ttl);

        return $result;
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

    // ─── TikTok Channel Methods ──────────────────────────

    public function tiktokKpis(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_tiktok_kpis:{$from}:{$to}", $noCache, function () use ($from, $to) {
            $totals = $this->resolver->resolveTiktokAdsTotals($from, $to);

            $ctr = $totals['impressions'] > 0 ? round(($totals['clicks'] / $totals['impressions']) * 100, 2) : 0.0;
            $roas = $totals['spend'] > 0 ? round($totals['purchase_value'] / $totals['spend'], 2) : 0.0;
            $cpc = $totals['clicks'] > 0 ? round($totals['spend'] / $totals['clicks'], 2) : 0.0;
            $cpm = $totals['impressions'] > 0 ? round(($totals['spend'] / $totals['impressions']) * 1000, 2) : 0.0;
            $engagement = ($totals['likes'] ?? 0) + ($totals['comments'] ?? 0) + ($totals['shares'] ?? 0);

            return [
                'spend' => ['value' => round($totals['spend'], 2), 'change' => null],
                'impressions' => ['value' => (int) $totals['impressions'], 'change' => null],
                'reach' => ['value' => (int) $totals['reach'], 'change' => null],
                'clicks' => ['value' => (int) $totals['clicks'], 'change' => null],
                'ctr' => ['value' => $ctr, 'change' => null],
                'video_views' => ['value' => (int) ($totals['video_views'] ?? 0), 'change' => null],
                'purchases' => ['value' => (int) ($totals['purchases'] ?? 0), 'change' => null],
                'revenue' => ['value' => round($totals['purchase_value'] ?? 0, 2), 'change' => null],
                'roas' => ['value' => $roas, 'change' => null],
                'cpc' => ['value' => $cpc, 'change' => null],
                'cpm' => ['value' => $cpm, 'change' => null],
                'engagement' => ['value' => $engagement, 'change' => null],
                'likes' => ['value' => (int) ($totals['likes'] ?? 0), 'change' => null],
                'comments' => ['value' => (int) ($totals['comments'] ?? 0), 'change' => null],
                'shares' => ['value' => (int) ($totals['shares'] ?? 0), 'change' => null],
                'follows' => ['value' => (int) ($totals['follows'] ?? 0), 'change' => null],
                'conversions' => ['value' => (int) ($totals['conversions'] ?? 0), 'change' => null],
                'cost_per_conversion' => ['value' => round($totals['cost_per_conversion'] ?? 0, 2), 'change' => null],
                'add_to_cart' => ['value' => (int) ($totals['add_to_cart'] ?? 0), 'change' => null],
                'initiate_checkout' => ['value' => (int) ($totals['initiate_checkout'] ?? 0), 'change' => null],
            ];
        });
    }

    public function tiktokDailyReport(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_tiktok_daily:{$from}:{$to}", $noCache, function () use ($from, $to) {
            return $this->resolver->resolveTiktokAdsDaily($from, $to);
        });
    }

    public function tiktokCampaigns(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_tiktok_campaigns:{$from}:{$to}", $noCache, function () use ($from, $to) {
            return $this->resolver->resolveTiktokCampaigns($from, $to);
        });
    }

    public function tiktokBreakdowns(string $from, string $to, bool $noCache = false): array
    {
        return $this->remember("meta_v2_tiktok_breakdowns:{$from}:{$to}", $noCache, function () use ($from, $to) {
            return $this->resolver->resolveTiktokBreakdowns($from, $to);
        });
    }

    public function tiktokTopVideos(string $from, string $to, int $limit = 10, bool $noCache = false): array
    {
        return $this->remember("meta_v2_tiktok_top_videos:{$from}:{$to}:{$limit}", $noCache, function () use ($from, $to, $limit) {
            return $this->resolver->resolveTiktokTopVideos($from, $to, $limit);
        });
    }
}
