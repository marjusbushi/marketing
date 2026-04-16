<?php

namespace App\Services\Meta;

use App\Http\Middleware\MetaMarketingCache;
use App\Models\Meta\MetaAdsInsight;
use App\Models\Meta\MetaCampaign;
use App\Models\Meta\MetaAdSet;
use App\Models\Meta\MetaIgInsight;
use App\Models\Meta\MetaMessagingStat;
use App\Models\Meta\MetaPageInsight;
use App\Models\Meta\MetaPeriodTotal;
use App\Models\Meta\MetaPostInsight;
use App\Models\TikTok\TikTokAdsInsight;
use App\Models\TikTok\TikTokCampaign;
use App\Models\TikTok\TikTokVideo;
use App\Services\Tiktok\TiktokAdsSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DB-first data resolver for Meta Marketing dashboard.
 *
 * Rules:
 * - Ngarko (Load): DB only. Zero API calls. Serve whatever's in the DB.
 * - Rifresko (Refresh): Pull everything fresh from Meta API, replace DB data.
 * - YoY: DB only. Never call API for previous year.
 * - Data is populated by nightly cron sync + Rifresko.
 */
class MetaDataResolverService
{
    public function __construct(
        private readonly MetaPageSyncService $pageSyncService,
        private readonly MetaAdsSyncService $adsSyncService,
        private readonly MetaPostSyncService $postSyncService,
        private readonly MetaMessagingSyncService $messagingSyncService,
        private readonly MetaApiService $api,
    ) {}

    // ─── Gap Detection & Helpers ──────────────────────────

    /**
     * Find dates within [from, to] that have no row in the given DB table.
     */
    private function getMissingDates(string $table, string $dateColumn, string $from, string $to, array $extraWhere = []): array
    {
        $allDates = [];
        for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $allDates[] = $d->toDateString();
        }

        $query = DB::table($table)->whereBetween($dateColumn, [$from, $to]);
        foreach ($extraWhere as $col => $val) {
            $query->where($col, $val);
        }
        $existingDates = $query->pluck($dateColumn)
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        return array_values(array_diff($allDates, $existingDates));
    }

    /**
     * Group consecutive dates into [from, to] ranges for batch API calls.
     * E.g. ['02-25','02-26','02-28'] → [['02-25','02-26'], ['02-28','02-28']]
     */
    private function groupConsecutiveDates(array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        sort($dates);
        $ranges = [];
        $start = $dates[0];
        $prev = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            if (Carbon::parse($dates[$i])->diffInDays(Carbon::parse($prev)) > 1) {
                $ranges[] = [$start, $prev];
                $start = $dates[$i];
            }
            $prev = $dates[$i];
        }

        $ranges[] = [$start, $prev];

        return $ranges;
    }

    // ─── Ensure Methods (Ngarko = DB only, zero API calls) ─

    /**
     * No-op for Ngarko. DB is the source of truth.
     * Data is populated by nightly sync or Rifresko.
     */
    public function ensureFbData(string $from, string $to): void
    {
        // Ngarko: serve from DB only. Zero API calls.
    }

    public function ensureIgData(string $from, string $to): void
    {
        // Ngarko: serve from DB only. Zero API calls.
    }

    public function ensureAdsData(string $from, string $to): void
    {
        // Ngarko: serve from DB only. Zero API calls.
    }

    public function ensureMessagingData(string $platform, string $from, string $to): void
    {
        // Ngarko: serve from DB only. Zero API calls.
    }

    public function ensurePostsData(): void
    {
        // Ngarko: serve from DB only. Zero API calls.
    }

    // ─── Resolve: Facebook ────────────────────────────────

    /**
     * Return daily FB page data from DB (gap-fills first).
     * Returns array of rows keyed by date string.
     */
    public function resolveFbDaily(string $from, string $to): array
    {
        $this->ensureFbData($from, $to);
        $this->ensureMessagingData('messenger', $from, $to);

        $pageId = (string) config('meta.page_id');
        $fbRows = MetaPageInsight::where('page_id', $pageId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $msgRows = MetaMessagingStat::messenger()
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        $result = [];
        foreach ($fbRows as $row) {
            $dateStr = $row->date->toDateString();
            $msg = $msgRows[$dateStr] ?? null;

            $result[] = [
                'date' => $dateStr,
                'reach' => (int) $row->page_reach,
                'post_impressions' => (int) $row->page_posts_impressions,
                'page_views' => (int) $row->page_views_total,
                'page_engagements' => (int) $row->page_reactions_total,
                'post_engagement' => (int) $row->page_post_engagements,
                'new_threads' => (int) $row->page_messages_new_threads,
                'messages_received' => $msg ? (int) $msg->total_messages_received : 0,
                'messages_sent' => $msg ? (int) $msg->total_messages_sent : 0,
                'page_fans' => (int) $row->page_fans,
                'page_daily_follows' => (int) $row->page_daily_follows,
            ];
        }

        return $result;
    }

    /**
     * Return FB period totals from Meta API (de-duplicated).
     * KPI cards use period-level API calls, NOT SUM(daily DB rows).
     * Shape matches windowTotals()['facebook'].
     */
    public function resolveFbTotals(string $from, string $to): array
    {
        $this->ensureMessagingData('messenger', $from, $to);

        $pageId = (string) config('meta.page_id');

        // Period totals from API (de-duplicated, matches Meta Insights exactly)
        $fb = $this->fetchFbPeriodTotals($pageId, $from, $to);

        // Messaging from DB (additive counts, SUM is correct)
        $msg = MetaMessagingStat::messenger()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'impressions' => $fb['impressions'],
            'reach' => $fb['reach'],
            'page_views' => $fb['page_views'],
            'post_engagement' => $fb['post_engagement'],
            'content_interactions' => $fb['content_interactions'],
            'link_clicks' => 0, // FB deprecated page-level link clicks
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    // ─── Resolve: Instagram ───────────────────────────────

    /**
     * Return daily IG data from DB (gap-fills first).
     */
    public function resolveIgDaily(string $from, string $to): array
    {
        $this->ensureIgData($from, $to);
        $this->ensureMessagingData('instagram', $from, $to);

        $igAccountId = (string) config('meta.ig_account_id');
        $igRows = MetaIgInsight::where('ig_account_id', $igAccountId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $msgRows = MetaMessagingStat::instagram()
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        $result = [];
        foreach ($igRows as $row) {
            $dateStr = $row->date->toDateString();
            $msg = $msgRows[$dateStr] ?? null;

            $result[] = [
                'date' => $dateStr,
                'views' => (int) $row->views,
                'reach' => (int) $row->reach,
                'profile_views' => (int) $row->profile_views,
                'website_clicks' => (int) $row->website_clicks,
                'content_interactions' => (int) $row->total_interactions,
                'follower_count' => (int) $row->follower_count,
                'new_followers' => (int) $row->new_followers,
                'conversations' => $msg ? (int) $msg->new_conversations : 0,
                'messages_received' => $msg ? (int) $msg->total_messages_received : 0,
                'messages_sent' => $msg ? (int) $msg->total_messages_sent : 0,
            ];
        }

        return $result;
    }

    /**
     * Return IG period totals from Meta API (de-duplicated).
     * KPI cards use period-level API calls, NOT SUM(daily DB rows).
     * Shape matches windowTotals()['instagram'].
     */
    public function resolveIgTotals(string $from, string $to): array
    {
        $this->ensureMessagingData('instagram', $from, $to);

        $igAccountId = (string) config('meta.ig_account_id');

        // Period totals from API (de-duplicated, matches Meta Insights exactly)
        $ig = $this->fetchIgPeriodTotals($igAccountId, $from, $to);

        // Messaging from DB (additive counts, SUM is correct)
        $msg = MetaMessagingStat::instagram()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'reach' => $ig['reach'],
            'views' => $ig['views'],
            'profile_views' => $ig['profile_views'],
            'new_followers' => $ig['new_followers'],
            'engagement' => $ig['engagement'],
            'link_clicks' => $ig['link_clicks'],
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    // ─── Resolve: Ads ─────────────────────────────────────

    /**
     * Return ads period totals from Meta API (de-duplicated).
     * KPI cards use period-level API calls, NOT SUM(daily DB rows).
     * Shape matches windowTotals()['ads'].
     */
    public function resolveAdsTotals(string $from, string $to): array
    {
        // Period totals from API (de-duplicated, matches Meta Ads Manager exactly)
        return $this->fetchAdsPeriodTotals($from, $to);
    }

    /**
     * Return daily ads data from DB.
     */
    public function resolveAdsDaily(string $from, string $to): array
    {
        $this->ensureAdsData($from, $to);

        $rows = MetaAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                date,
                SUM(spend) as spend,
                SUM(impressions) as impressions,
                SUM(reach) as reach,
                SUM(link_clicks) as link_clicks,
                SUM(purchases) as purchases,
                SUM(purchase_value) as revenue
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(function ($row) {
            $spend = (float) $row->spend;
            $revenue = (float) $row->revenue;

            return [
                'date' => Carbon::parse($row->date)->toDateString(),
                'spend' => $spend,
                'impressions' => (int) $row->impressions,
                'link_clicks' => (int) $row->link_clicks,
                'reach' => (int) $row->reach,
                'purchases' => (int) $row->purchases,
                'revenue' => $revenue,
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0.0,
            ];
        })->toArray();
    }

    /**
     * Return campaign tree with metrics from DB.
     */
    public function resolveAdsCampaigns(string $from, string $to, string $platform = 'all'): array
    {
        $this->ensureAdsData($from, $to);

        // Campaign-level aggregation
        $campaignRows = MetaAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                meta_campaign_id,
                SUM(spend) as spend,
                SUM(impressions) as impressions,
                SUM(reach) as reach,
                SUM(link_clicks) as link_clicks,
                SUM(purchases) as purchases,
                SUM(purchase_value) as revenue
            ')
            ->groupBy('meta_campaign_id')
            ->get()
            ->keyBy('meta_campaign_id');

        // Ad set-level aggregation
        $adSetRows = MetaAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                meta_campaign_id, meta_ad_set_id,
                SUM(spend) as spend,
                SUM(impressions) as impressions,
                SUM(link_clicks) as link_clicks,
                SUM(purchases) as purchases,
                SUM(purchase_value) as revenue
            ')
            ->groupBy('meta_campaign_id', 'meta_ad_set_id')
            ->get();

        // Build ad set index by campaign
        $adSetsByCampaign = [];
        foreach ($adSetRows as $row) {
            $adSetsByCampaign[$row->meta_campaign_id][] = $row;
        }

        // Load metadata
        $campaigns = MetaCampaign::all()->keyBy('id');
        $adSets = MetaAdSet::all()->keyBy('id');

        $result = [];
        foreach ($campaignRows as $campaignId => $metrics) {
            $campaign = $campaigns[$campaignId] ?? null;
            $spend = (float) $metrics->spend;
            $impressions = (int) $metrics->impressions;
            $linkClicks = (int) $metrics->link_clicks;
            $revenue = (float) $metrics->revenue;

            if ($spend <= 0 && $impressions <= 0 && $linkClicks <= 0) {
                continue;
            }

            $campaignAdSets = [];
            foreach ($adSetsByCampaign[$campaignId] ?? [] as $asRow) {
                $as = $adSets[$asRow->meta_ad_set_id] ?? null;
                $asSpend = (float) $asRow->spend;
                $asRevenue = (float) $asRow->revenue;

                $campaignAdSets[] = [
                    'name' => $as->name ?? 'Unknown',
                    'status' => $as->status ?? 'UNKNOWN',
                    'optimization_goal' => $as->optimization_goal ?? '',
                    'spend' => $asSpend,
                    'impressions' => (int) $asRow->impressions,
                    'link_clicks' => (int) $asRow->link_clicks,
                    'purchases' => (int) $asRow->purchases,
                    'revenue' => $asRevenue,
                    'roas' => $asSpend > 0 ? round($asRevenue / $asSpend, 2) : 0.0,
                ];
            }

            usort($campaignAdSets, fn ($a, $b) => $b['spend'] <=> $a['spend']);

            $result[] = [
                'id' => $campaign->campaign_id ?? '',
                'name' => $campaign->name ?? 'Unknown',
                'objective' => $campaign->objective ?? '',
                'status' => $campaign->status ?? 'UNKNOWN',
                'spend' => $spend,
                'impressions' => $impressions,
                'reach' => (int) $metrics->reach,
                'link_clicks' => $linkClicks,
                'ctr' => $impressions > 0 ? round(($linkClicks / $impressions) * 100, 2) : 0.0,
                'purchases' => (int) $metrics->purchases,
                'revenue' => $revenue,
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0.0,
                'cpc' => $linkClicks > 0 ? round($spend / $linkClicks, 2) : 0.0,
                'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 2) : 0.0,
                'ad_sets' => $campaignAdSets,
            ];
        }

        usort($result, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $result;
    }

    /**
     * Return merged breakdowns from JSON columns in ads insights.
     */
    public function resolveAdsBreakdowns(string $from, string $to): array
    {
        $this->ensureAdsData($from, $to);

        $rows = MetaAdsInsight::whereBetween('date', [$from, $to])->get();

        $age = [];
        $gender = [];
        $platformBd = [];
        $placement = [];

        foreach ($rows as $row) {
            $this->mergeBreakdown($age, $row->age_breakdown, ['spend', 'impressions', 'clicks', 'reach']);
            $this->mergeBreakdown($gender, $row->gender_breakdown, ['spend', 'impressions', 'clicks', 'reach']);
            $this->mergeBreakdown($platformBd, $row->platform_breakdown, [
                'spend', 'impressions', 'clicks', 'reach',
                'link_clicks', 'purchases', 'purchase_value',
                'add_to_cart', 'initiate_checkout', 'leads',
                'messaging_conversations', 'messaging_conversations_replied',
            ]);
            $this->mergeBreakdown($placement, $row->placement_breakdown, ['spend', 'impressions', 'clicks', 'reach']);
        }

        // Sort each breakdown by spend desc
        $sortBySpend = function (&$bd) {
            uasort($bd, fn ($a, $b) => ($b['spend'] ?? 0) <=> ($a['spend'] ?? 0));
        };

        $sortBySpend($age);
        $sortBySpend($gender);
        $sortBySpend($platformBd);
        $sortBySpend($placement);

        return [
            'age' => $age,
            'gender' => $gender,
            'platform' => $platformBd,
            'placement' => $placement,
        ];
    }

    /**
     * Merge a single row's breakdown JSON into the accumulator.
     */
    private function mergeBreakdown(array &$accumulator, ?array $breakdown, array $fields): void
    {
        if (! $breakdown) {
            return;
        }

        foreach ($breakdown as $key => $values) {
            if (! isset($accumulator[$key])) {
                $accumulator[$key] = array_fill_keys($fields, 0);
            }
            foreach ($fields as $field) {
                $accumulator[$key][$field] += (float) ($values[$field] ?? 0);
            }
        }
    }

    // ─── Resolve: Messaging ───────────────────────────────

    /**
     * Return aggregated messaging totals from DB.
     */
    public function resolveMessagingTotals(string $platform, string $from, string $to): array
    {
        $this->ensureMessagingData($platform, $from, $to);

        $msg = MetaMessagingStat::where('platform', $platform)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    /**
     * Return daily messaging data from DB.
     */
    public function resolveMessagingDaily(string $platform, string $from, string $to): array
    {
        $this->ensureMessagingData($platform, $from, $to);

        $dbRows = MetaMessagingStat::where('platform', $platform)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        // Build a row for EVERY date in [from, to] — missing days default to zero.
        $result = [];
        for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $dateStr = $d->toDateString();
            $row = $dbRows[$dateStr] ?? null;

            $result[] = [
                'date' => $dateStr,
                'conversations' => $row ? (int) $row->new_conversations : 0,
                'messages_received' => $row ? (int) $row->total_messages_received : 0,
                'messages_sent' => $row ? (int) $row->total_messages_sent : 0,
                'new_conversations' => $row ? (int) $row->new_conversations : 0,
                'total_messages_received' => $row ? (int) $row->total_messages_received : 0,
                'total_messages_sent' => $row ? (int) $row->total_messages_sent : 0,
            ];
        }

        return $result;
    }

    // ─── Resolve: Top Posts ───────────────────────────────

    /**
     * Return top FB posts from DB sorted by engagement.
     */
    public function resolveFbTopPosts(string $from, string $to, int $limit = 12): array
    {
        $this->ensurePostsData();

        return MetaPostInsight::facebook()
            ->whereBetween('created_at_meta', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->orderByRaw('(COALESCE(likes,0) + COALESCE(comments,0) + COALESCE(shares,0)) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => $this->shapePost($p))
            ->toArray();
    }

    /**
     * Return top IG posts from DB sorted by engagement.
     */
    public function resolveIgTopPosts(string $from, string $to, int $limit = 12, ?string $type = null): array
    {
        $this->ensurePostsData();

        $query = MetaPostInsight::instagram()
            ->whereBetween('created_at_meta', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);

        if ($type) {
            $query->where('post_type', $type);
        }

        return $query
            ->orderByRaw('(COALESCE(likes,0) + COALESCE(comments,0) + COALESCE(shares,0) + COALESCE(saves,0)) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => $this->shapePost($p))
            ->toArray();
    }

    /**
     * Shape a MetaPostInsight model into the expected frontend format.
     */
    private function shapePost(MetaPostInsight $p): array
    {
        return [
            'post_id' => $p->post_id,
            'post_type' => strtoupper($p->post_type ?? 'unknown'),
            'message' => $p->message ? mb_substr($p->message, 0, 100) : '',
            'permalink_url' => $p->permalink_url,
            'media_url' => $p->media_url,
            'created_at' => $p->created_at_meta ? $p->created_at_meta->format('Y-m-d H:i') : null,
            'impressions' => (int) $p->impressions,
            'reach' => (int) $p->reach,
            'engagement' => (int) $p->likes + (int) $p->comments + (int) $p->shares + (int) $p->saves,
            'likes' => (int) $p->likes,
            'comments' => (int) $p->comments,
            'shares' => (int) $p->shares,
            'saves' => (int) $p->saves,
            'video_views' => (int) $p->video_views,
            'plays' => (int) $p->plays,
        ];
    }

    // ─── Resolve: Ads Platform Totals ─────────────────────

    /**
     * Return ads totals filtered by platform (from JSON breakdown).
     */
    public function resolveAdsPlatformTotals(string $from, string $to, string $platform): array
    {
        if ($platform === 'all') {
            return $this->resolveAdsTotals($from, $to);
        }

        // Period totals with platform breakdown — single de-duplicated row per platform
        return $this->fetchAdsPlatformPeriodTotals($from, $to, $platform);
    }

    /**
     * Fetch ads period totals filtered by publisher_platform (no time_increment → de-duplicated).
     */
    private function fetchAdsPlatformPeriodTotals(string $from, string $to, string $platform): array
    {
        $defaults = [
            'spend' => 0.0,
            'impressions' => 0,
            'reach' => 0,
            'link_clicks' => 0,
            'purchases' => 0,
            'revenue' => 0.0,
            'messaging_conversations' => 0,
            'messaging_conversations_replied' => 0,
        ];

        $accountId = config('meta.ad_account_id');
        if (!$accountId) {
            return $defaults;
        }

        try {
            $insights = $this->api->getAdsInsights($accountId, [
                'level' => 'account',
                'time_range' => json_encode(['since' => $from, 'until' => $to]),
                'breakdowns' => 'publisher_platform',
                'fields' => 'spend,impressions,reach,actions,action_values',
                ...$this->adsSyncService->getAttributionParams(),
            ]);

            foreach ($insights as $row) {
                if (strtolower((string) ($row['publisher_platform'] ?? '')) !== $platform) {
                    continue;
                }

                $result = $defaults;
                $result['spend'] = (float) ($row['spend'] ?? 0);
                $result['impressions'] = (int) ($row['impressions'] ?? 0);
                $result['reach'] = (int) ($row['reach'] ?? 0);

                $actionsByType = [];
                foreach ($row['actions'] ?? [] as $action) {
                    $type = $action['action_type'] ?? '';
                    $val = (int) ($action['value'] ?? 0);
                    $actionsByType[$type] = $val;
                    match ($type) {
                        'link_click' => $result['link_clicks'] = $val,
                        'purchase' => $result['purchases'] = $val,
                        'onsite_conversion.messaging_conversation_replied_7d' => $result['messaging_conversations_replied'] = $val,
                        default => null,
                    };
                }

                // Prefer total_messaging_connection (broader, matches Meta Business Suite)
                // over messaging_conversation_started_7d (7-day attribution window only).
                $result['messaging_conversations'] =
                    $actionsByType['onsite_conversion.total_messaging_connection']
                    ?? $actionsByType['onsite_conversion.messaging_conversation_started_7d']
                    ?? 0;

                foreach ($row['action_values'] ?? [] as $action) {
                    if (($action['action_type'] ?? '') === 'purchase') {
                        $result['revenue'] = (float) ($action['value'] ?? 0);
                    }
                }

                return $result;
            }
        } catch (Throwable $e) {
            Log::debug("Ads platform period totals ({$platform}) failed: {$e->getMessage()}");
        }

        // Fallback: DB SUM of daily platform_breakdown (inflates reach but better than 0)
        Log::warning("Ads platform period totals ({$platform}): API failed, using DB SUM fallback");
        $this->ensureAdsData($from, $to);
        $rows = MetaAdsInsight::whereBetween('date', [$from, $to])
            ->whereNotNull('platform_breakdown')
            ->get();

        foreach ($rows as $row) {
            $bd = $row->platform_breakdown;
            if (!$bd || !isset($bd[$platform])) {
                continue;
            }
            $p = $bd[$platform];
            $defaults['spend'] += (float) ($p['spend'] ?? 0);
            $defaults['impressions'] += (int) ($p['impressions'] ?? 0);
            $defaults['reach'] += (int) ($p['reach'] ?? 0);
            $defaults['link_clicks'] += (int) ($p['link_clicks'] ?? 0);
            $defaults['purchases'] += (int) ($p['purchases'] ?? 0);
            $defaults['revenue'] += (float) ($p['purchase_value'] ?? 0);
            $defaults['messaging_conversations'] += (int) ($p['messaging_conversations'] ?? 0);
            $defaults['messaging_conversations_replied'] += (int) ($p['messaging_conversations_replied'] ?? 0);
        }

        return $defaults;
    }

    // ─── Resolve: DB-only (YoY, no gap-fill) ──────────────

    /**
     * Read FB totals from DB without gap-filling.
     * For YoY where we never call Meta API for previous year.
     */
    public function resolveFbTotalsDbOnly(string $from, string $to): array
    {
        $pageId = (string) config('meta.page_id');
        $fb = MetaPageInsight::where('page_id', $pageId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(page_reach), 0) as reach,
                COALESCE(SUM(page_posts_impressions), 0) as impressions,
                COALESCE(SUM(page_views_total), 0) as page_views,
                COALESCE(SUM(page_post_engagements), 0) as post_engagement
            ')
            ->first();

        $msg = MetaMessagingStat::messenger()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'impressions' => (int) $fb->impressions,
            'reach' => (int) $fb->reach,
            'page_views' => (int) $fb->page_views,
            'post_engagement' => (int) $fb->post_engagement,
            'link_clicks' => 0, // FB deprecated page-level link clicks
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    /**
     * Read IG totals from DB without gap-filling. For YoY.
     */
    public function resolveIgTotalsDbOnly(string $from, string $to): array
    {
        $igAccountId = (string) config('meta.ig_account_id');
        $ig = MetaIgInsight::where('ig_account_id', $igAccountId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(reach), 0) as reach,
                COALESCE(SUM(views), 0) as views,
                COALESCE(SUM(profile_views), 0) as profile_views,
                COALESCE(SUM(total_interactions), 0) as engagement,
                COALESCE(SUM(website_clicks), 0) as link_clicks,
                COALESCE(SUM(new_followers), 0) as new_followers
            ')
            ->first();

        $msg = MetaMessagingStat::instagram()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'reach' => (int) $ig->reach,
            'views' => (int) $ig->views,
            'profile_views' => (int) $ig->profile_views,
            'new_followers' => (int) $ig->new_followers,
            'engagement' => (int) $ig->engagement,
            'link_clicks' => (int) $ig->link_clicks,
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    /**
     * Read ads totals from DB without gap-filling. For YoY.
     */
    public function resolveAdsTotalsDbOnly(string $from, string $to): array
    {
        $ads = MetaAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(reach), 0) as reach,
                COALESCE(SUM(link_clicks), 0) as link_clicks,
                COALESCE(SUM(purchases), 0) as purchases,
                COALESCE(SUM(purchase_value), 0) as revenue,
                COALESCE(SUM(messaging_conversations), 0) as messaging_conversations,
                COALESCE(SUM(messaging_conversations_replied), 0) as messaging_conversations_replied
            ')
            ->first();

        return [
            'spend' => (float) ($ads->spend ?? 0),
            'impressions' => (int) ($ads->impressions ?? 0),
            'reach' => (int) ($ads->reach ?? 0),
            'link_clicks' => (int) ($ads->link_clicks ?? 0),
            'purchases' => (int) ($ads->purchases ?? 0),
            'revenue' => (float) ($ads->revenue ?? 0),
            'messaging_conversations' => (int) ($ads->messaging_conversations ?? 0),
            'messaging_conversations_replied' => (int) ($ads->messaging_conversations_replied ?? 0),
        ];
    }

    /**
     * Check if DB has any data for a date range (used for YoY availability).
     */
    public function hasDataForRange(string $from, string $to): bool
    {
        $hasFb = MetaPageInsight::where('page_id', (string) config('meta.page_id'))
            ->whereBetween('date', [$from, $to])
            ->exists();

        $hasAds = MetaAdsInsight::whereBetween('date', [$from, $to])->exists();

        return $hasFb || $hasAds;
    }

    /**
     * Check if period totals exist in DB for a date range (YoY).
     */
    public function hasYoYDataForRange(string $from, string $to): bool
    {
        return MetaPeriodTotal::where('date_from', $from)
            ->where('date_to', $to)
            ->exists();
    }

    // ─── YoY Period Totals (de-duplicated, never SUM daily) ──

    /**
     * Get FB period totals for YoY. DB-first with API fallback.
     *
     * @param bool $forceApi  Rifresko = true (always call API). Ngarko = false (DB first).
     * @return array|null  null = API returned nothing AND nothing in DB.
     */
    public function resolveFbPeriodTotalsForYoY(string $from, string $to, bool $forceApi = false): ?array
    {
        if ($forceApi) {
            // Rifresko: call API. Save if data returned, don't overwrite with nothing.
            $apiResult = $this->fetchFbPeriodTotalsForYoY($from, $to);
            if ($apiResult !== null) {
                MetaPeriodTotal::storeTotals('facebook', $from, $to, $apiResult);
                return $apiResult;
            }
            // API returned nothing → keep whatever is in DB
            return MetaPeriodTotal::getCached('facebook', $from, $to);
        }

        // Ngarko: try DB first
        $cached = MetaPeriodTotal::getCached('facebook', $from, $to);
        if ($cached !== null) {
            return $cached;
        }

        // Not in DB → fetch from API → save
        $apiResult = $this->fetchFbPeriodTotalsForYoY($from, $to);
        if ($apiResult !== null) {
            MetaPeriodTotal::storeTotals('facebook', $from, $to, $apiResult);
        }
        return $apiResult;
    }

    /**
     * Get IG period totals for YoY. DB-first with API fallback.
     */
    public function resolveIgPeriodTotalsForYoY(string $from, string $to, bool $forceApi = false): ?array
    {
        if ($forceApi) {
            $apiResult = $this->fetchIgPeriodTotalsForYoY($from, $to);
            if ($apiResult !== null) {
                MetaPeriodTotal::storeTotals('instagram', $from, $to, $apiResult);
                return $apiResult;
            }
            return MetaPeriodTotal::getCached('instagram', $from, $to);
        }

        $cached = MetaPeriodTotal::getCached('instagram', $from, $to);
        if ($cached !== null) {
            return $cached;
        }

        $apiResult = $this->fetchIgPeriodTotalsForYoY($from, $to);
        if ($apiResult !== null) {
            MetaPeriodTotal::storeTotals('instagram', $from, $to, $apiResult);
        }
        return $apiResult;
    }

    /**
     * Get Ads period totals for YoY. DB-first with API fallback.
     */
    public function resolveAdsPeriodTotalsForYoY(string $from, string $to, bool $forceApi = false): ?array
    {
        if ($forceApi) {
            $apiResult = $this->fetchAdsPeriodTotalsForYoY($from, $to);
            if ($apiResult !== null) {
                MetaPeriodTotal::storeTotals('ads', $from, $to, $apiResult);
                return $apiResult;
            }
            return MetaPeriodTotal::getCached('ads', $from, $to);
        }

        $cached = MetaPeriodTotal::getCached('ads', $from, $to);
        if ($cached !== null) {
            return $cached;
        }

        $apiResult = $this->fetchAdsPeriodTotalsForYoY($from, $to);
        if ($apiResult !== null) {
            MetaPeriodTotal::storeTotals('ads', $from, $to, $apiResult);
        }
        return $apiResult;
    }

    /**
     * Fetch FB period totals from Meta API for YoY.
     * Reuses fetchFbPeriodTotals() + messaging from DB.
     * Returns null if API returned nothing meaningful.
     */
    private function fetchFbPeriodTotalsForYoY(string $from, string $to): ?array
    {
        $pageId = (string) config('meta.page_id');
        if (!$pageId) {
            return null;
        }

        try {
            $fb = $this->fetchFbPeriodTotals($pageId, $from, $to);
        } catch (Throwable $e) {
            Log::debug("YoY FB period totals failed: {$e->getMessage()}");
            return null;
        }

        // If all core metrics are zero, API likely returned no data
        if ($fb['reach'] === 0 && $fb['impressions'] === 0 && $fb['post_engagement'] === 0 && $fb['page_views'] === 0) {
            // Check if daily rows exist (fallback indicator that data exists)
            $hasDailyData = MetaPageInsight::where('page_id', $pageId)
                ->whereBetween('date', [$from, $to])
                ->exists();
            if (!$hasDailyData) {
                return null;
            }
        }

        // Messaging: SUM is correct (additive counts). For >90 days ago, no API data exists.
        $msg = MetaMessagingStat::messenger()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'impressions' => (int) $fb['impressions'],
            'reach' => (int) $fb['reach'],
            'page_views' => (int) $fb['page_views'],
            'post_engagement' => (int) $fb['post_engagement'],
            'content_interactions' => (int) ($fb['content_interactions'] ?? 0),
            'link_clicks' => 0,
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    /**
     * Fetch IG period totals from Meta API for YoY.
     * Reuses fetchIgPeriodTotals() + messaging from DB.
     */
    private function fetchIgPeriodTotalsForYoY(string $from, string $to): ?array
    {
        $igAccountId = (string) config('meta.ig_account_id');
        if (!$igAccountId) {
            return null;
        }

        try {
            $ig = $this->fetchIgPeriodTotals($igAccountId, $from, $to);
        } catch (Throwable $e) {
            Log::debug("YoY IG period totals failed: {$e->getMessage()}");
            return null;
        }

        // If all core metrics are zero, likely no data for this period
        if ($ig['reach'] === 0 && $ig['views'] === 0 && $ig['engagement'] === 0) {
            $hasDailyData = MetaIgInsight::where('ig_account_id', $igAccountId)
                ->whereBetween('date', [$from, $to])
                ->exists();
            if (!$hasDailyData) {
                return null;
            }
        }

        // Messaging
        $msg = MetaMessagingStat::instagram()
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(new_conversations), 0) as conversations,
                COALESCE(SUM(total_messages_received), 0) as received,
                COALESCE(SUM(total_messages_sent), 0) as sent
            ')
            ->first();

        return [
            'reach' => (int) $ig['reach'],
            'views' => (int) $ig['views'],
            'profile_views' => (int) $ig['profile_views'],
            'new_followers' => (int) $ig['new_followers'],
            'engagement' => (int) $ig['engagement'],
            'link_clicks' => (int) $ig['link_clicks'],
            'conversations' => (int) ($msg->conversations ?? 0),
            'received' => (int) ($msg->received ?? 0),
            'sent' => (int) ($msg->sent ?? 0),
        ];
    }

    /**
     * Fetch Ads period totals from Meta API for YoY.
     * Reuses fetchAdsPeriodTotals().
     */
    private function fetchAdsPeriodTotalsForYoY(string $from, string $to): ?array
    {
        try {
            $ads = $this->fetchAdsPeriodTotals($from, $to);
        } catch (Throwable $e) {
            Log::debug("YoY Ads period totals failed: {$e->getMessage()}");
            return null;
        }

        // If all metrics zero, check if daily data exists
        if ((float) $ads['spend'] === 0.0 && (int) $ads['impressions'] === 0 && (int) $ads['reach'] === 0) {
            $hasDailyData = MetaAdsInsight::whereBetween('date', [$from, $to])->exists();
            if (!$hasDailyData) {
                return null;
            }
        }

        return $ads;
    }

    // ─── Force Refresh (Rifresko) ─────────────────────────

    /**
     * Force-refresh fast data sources (FB, IG, Ads, Posts).
     * Deletes existing rows, re-syncs from API.
     * Designed to complete within 30s (web request timeout).
     */
    public function forceRefreshFast(string $from, string $to): array
    {
        // Extend PHP timeout — Rifresko can take 60-90s for large date ranges
        set_time_limit(180);

        $startTime = microtime(true);
        Log::info("ForceRefresh: START fast refresh [{$from}..{$to}]");

        $refreshed = [];
        $pageId = (string) config('meta.page_id');
        $igAccountId = (string) config('meta.ig_account_id');

        // 1. Facebook Page Insights — delete + re-sync
        if ($pageId) {
            try {
                $deleted = MetaPageInsight::where('page_id', $pageId)
                    ->whereBetween('date', [$from, $to])
                    ->delete();
                Log::info("ForceRefresh: FB deleted {$deleted} rows, re-syncing...");
                $this->pageSyncService->syncPageInsights($from, $to);
                $refreshed[] = 'facebook';
                Log::info('ForceRefresh: FB OK');
            } catch (Throwable $e) {
                Log::warning("ForceRefresh: FB failed [{$from}..{$to}]: {$e->getMessage()}");
            }
        }

        // 2. Instagram Insights — NO delete, use updateOrCreate only.
        // This preserves follower_count/new_followers for >30-day ranges
        // where the API can't provide follower data anymore.
        if ($igAccountId) {
            try {
                Log::info("ForceRefresh: IG re-syncing (upsert, preserving follower data)...");
                $this->pageSyncService->syncIgInsights($from, $to);
                $refreshed[] = 'instagram';
                Log::info('ForceRefresh: IG OK');
            } catch (Throwable $e) {
                Log::warning("ForceRefresh: IG failed [{$from}..{$to}]: {$e->getMessage()}");
            }
        }

        // 3. Ads Insights — delete + re-sync
        try {
            $deleted = MetaAdsInsight::whereBetween('date', [$from, $to])->delete();
            Log::info("ForceRefresh: Ads deleted {$deleted} rows, re-syncing...");
            $this->adsSyncService->syncInsights($from, $to);
            $refreshed[] = 'ads';
            Log::info('ForceRefresh: Ads OK');
        } catch (Throwable $e) {
            Log::warning("ForceRefresh: Ads failed [{$from}..{$to}]: {$e->getMessage()}");
        }

        // 4. Posts
        try {
            $this->postSyncService->syncFacebookPosts();
            $this->postSyncService->syncInstagramPosts();
            $refreshed[] = 'posts';
            Log::info('ForceRefresh: Posts OK');
        } catch (Throwable $e) {
            Log::warning("ForceRefresh: Posts failed: {$e->getMessage()}");
        }

        // 5. Bust caches
        MetaMarketingCache::bustCache();

        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info("ForceRefresh: DONE in {$elapsed}s — refreshed: " . implode(', ', $refreshed));

        return $refreshed;
    }

    /**
     * Force-refresh messaging data (SLOW — called from queue job only).
     */
    public function forceRefreshMessaging(string $from, string $to): void
    {
        $startTime = microtime(true);
        Log::info("ForceRefresh: START messaging refresh [{$from}..{$to}]");

        $oldestAllowed = Carbon::today()->subDays(60)->toDateString();
        $effectiveFrom = max($from, $oldestAllowed);
        if ($effectiveFrom > $to) {
            Log::info("ForceRefresh: messaging range [{$from}..{$to}] too old (>60 days), skipping");
            return;
        }

        // Delete existing messaging rows
        $deleted = MetaMessagingStat::whereBetween('date', [$effectiveFrom, $to])->delete();
        Log::info("ForceRefresh: messaging deleted {$deleted} rows [{$effectiveFrom}..{$to}]");

        try {
            $this->messagingSyncService->syncMessengerStats($effectiveFrom, $to);
            Log::info('ForceRefresh: Messenger stats OK');
        } catch (Throwable $e) {
            Log::warning("ForceRefresh: Messenger failed [{$effectiveFrom}..{$to}]: {$e->getMessage()}");
        }

        try {
            $this->messagingSyncService->syncIgDmStats($effectiveFrom, $to);
            Log::info('ForceRefresh: IG DMs OK');
        } catch (Throwable $e) {
            Log::warning("ForceRefresh: IG DMs failed [{$effectiveFrom}..{$to}]: {$e->getMessage()}");
        }

        MetaMarketingCache::bustCache();

        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info("ForceRefresh: messaging DONE in {$elapsed}s");
    }

    // ─── Period Totals (API-fetched, de-duplicated) ─────────
    //
    // ARCHITECTURE RULE: KPI cards (date range totals) ALWAYS come from
    // period-level API calls — NEVER from SUM(daily DB rows).
    // Daily data (charts) is a separate concept and reads from DB per-day rows.
    // SUM(daily) inflates unique metrics (reach, etc.) and should never be used
    // for period totals. DB SUM is only used as a last-resort fallback if API fails.

    /**
     * Fetch ALL FB period totals from Meta API.
     * Uses total_over_range period for de-duplicated metrics.
     * Returns the exact numbers Meta Insights shows.
     *
     * content_interactions = page_actions_post_reactions_total (reactions breakdown summed).
     * NOT page_reels_views — that's views/impressions, not interactions.
     */
    private function fetchFbPeriodTotals(string $pageId, string $from, string $to): array
    {
        $defaults = [
            'reach' => 0,
            'impressions' => 0,
            'page_views' => 0,
            'post_engagement' => 0,
            'content_interactions' => 0,
        ];

        if (!$pageId || !config('meta.page_token')) {
            return $defaults;
        }

        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();
        $result = $defaults;

        // Batch 1: numeric metrics via total_over_range
        try {
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_total_media_view_unique,page_media_view,page_views_total,page_post_engagements',
                'since' => $from,
                'until' => $untilExclusive,
                'period' => 'total_over_range',
            ]);

            foreach ($response['data'] ?? [] as $entry) {
                $name = $entry['name'] ?? '';
                $value = (int) ($entry['values'][0]['value'] ?? 0);
                match ($name) {
                    'page_total_media_view_unique' => $result['reach'] = $value,
                    'page_media_view' => $result['impressions'] = $value,
                    'page_views_total' => $result['page_views'] = $value,
                    'page_post_engagements' => $result['post_engagement'] = $value,
                    default => null,
                };
            }
        } catch (Throwable $e) {
            Log::debug("FB total_over_range (batch 1) failed: {$e->getMessage()}");

            // Fallback: reach-only total_over_range + period=day for others
            try {
                $resp = $this->api->getWithPageToken("{$pageId}/insights", [
                    'metric' => 'page_total_media_view_unique',
                    'since' => $from,
                    'until' => $untilExclusive,
                    'period' => 'total_over_range',
                ]);
                foreach ($resp['data'] ?? [] as $entry) {
                    if (($entry['name'] ?? '') === 'page_total_media_view_unique') {
                        $result['reach'] = (int) ($entry['values'][0]['value'] ?? 0);
                    }
                }
            } catch (Throwable $inner) {
                Log::debug("FB reach fallback failed: {$inner->getMessage()}");
            }

            try {
                $resp = $this->api->getPageInsights(
                    $pageId,
                    'page_media_view,page_views_total,page_post_engagements',
                    'day', $from, $untilExclusive
                );
                foreach ($resp['data'] ?? [] as $entry) {
                    $name = $entry['name'] ?? '';
                    $sum = 0;
                    foreach ($entry['values'] ?? [] as $v) {
                        $sum += (int) ($v['value'] ?? 0);
                    }
                    match ($name) {
                        'page_media_view' => $result['impressions'] = $sum,
                        'page_views_total' => $result['page_views'] = $sum,
                        'page_post_engagements' => $result['post_engagement'] = $sum,
                        default => null,
                    };
                }
            } catch (Throwable $inner) {
                Log::debug("FB period=day fallback failed: {$inner->getMessage()}");
            }
        }

        // Batch 2: Content Interactions (reactions breakdown — returns JSON object, needs array_sum)
        try {
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_actions_post_reactions_total',
                'since' => $from,
                'until' => $untilExclusive,
                'period' => 'total_over_range',
            ]);

            foreach ($response['data'] ?? [] as $entry) {
                if (($entry['name'] ?? '') === 'page_actions_post_reactions_total') {
                    $breakdown = $entry['values'][0]['value'] ?? [];
                    $result['content_interactions'] = is_array($breakdown)
                        ? (int) array_sum($breakdown)
                        : (int) $breakdown;
                }
            }
        } catch (Throwable $e) {
            Log::debug("FB content_interactions (reactions) failed: {$e->getMessage()}");
            // Fallback: page_reactions_total from DB (no reel views!)
            $result['content_interactions'] = (int) MetaPageInsight::where('page_id', $pageId)
                ->whereBetween('date', [$from, $to])
                ->sum('page_reactions_total');
        }

        // Last resort: DB SUM (only if numeric metrics are all zero = API failed)
        if ($result['reach'] === 0 && $result['impressions'] === 0 && $result['post_engagement'] === 0) {
            $fb = MetaPageInsight::where('page_id', $pageId)
                ->whereBetween('date', [$from, $to])
                ->selectRaw('
                    COALESCE(SUM(page_reach), 0) as reach,
                    COALESCE(SUM(page_posts_impressions), 0) as impressions,
                    COALESCE(SUM(page_views_total), 0) as page_views,
                    COALESCE(SUM(page_post_engagements), 0) as post_engagement,
                    COALESCE(SUM(page_reactions_total), 0) as content_interactions
                ')
                ->first();

            if ($fb) {
                $result['reach'] = (int) $fb->reach;
                $result['impressions'] = (int) $fb->impressions;
                $result['page_views'] = (int) $fb->page_views;
                $result['post_engagement'] = (int) $fb->post_engagement;
                $result['content_interactions'] = (int) $fb->content_interactions;
            }

            Log::warning("FB period totals: all API calls failed, using DB SUM fallback");
        }

        return $result;
    }

    /**
     * Fetch ALL IG period totals from Meta API.
     * Uses total_value metric type for de-duplicated period metrics.
     * Returns the exact numbers Meta Insights shows.
     */
    private function fetchIgPeriodTotals(string $igAccountId, string $from, string $to): array
    {
        $defaults = [
            'reach' => 0,
            'views' => 0,
            'profile_views' => 0,
            'engagement' => 0,
            'link_clicks' => 0,
            'new_followers' => 0,
        ];

        if (!$igAccountId || !config('meta.page_token')) {
            return $defaults;
        }

        $untilExclusive = Carbon::parse($to)->addDay()->toDateString();

        // Map API metric names → our result keys
        $metricsMap = [
            'reach' => 'reach',
            'views' => 'views',
            'profile_views' => 'profile_views',
            'total_interactions' => 'engagement',
            'website_clicks' => 'link_clicks',
        ];

        $result = $defaults;

        // Fetch all 5 metrics in one call using total_value
        try {
            $response = $this->api->getIgInsights(
                $igAccountId,
                implode(',', array_keys($metricsMap)),
                'day',
                $from,
                $untilExclusive,
                'total_value'
            );

            foreach ($response['data'] ?? [] as $entry) {
                $name = $entry['name'] ?? '';
                if (isset($metricsMap[$name])) {
                    $result[$metricsMap[$name]] = (int) ($entry['total_value']['value'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            Log::debug("IG total_value (all metrics) failed: {$e->getMessage()}");

            // Fallback: one metric at a time
            foreach ($metricsMap as $metric => $key) {
                try {
                    $resp = $this->api->getIgInsights($igAccountId, $metric, 'day', $from, $untilExclusive, 'total_value');
                    foreach ($resp['data'] ?? [] as $entry) {
                        $result[$key] = (int) ($entry['total_value']['value'] ?? 0);
                    }
                } catch (Throwable $inner) {
                    Log::debug("IG total_value [{$metric}] failed: {$inner->getMessage()}");
                }
            }
        }

        // Followers: period=day gives daily net change. SUM is correct (additive count).
        // API enforces 30-day rolling window — clamp $from.
        try {
            $followerFrom = max($from, Carbon::yesterday()->subDays(29)->toDateString());
            if ($followerFrom <= $to) {
                $followerResponse = $this->api->getIgInsights(
                    $igAccountId, 'follower_count', 'day', $followerFrom, $untilExclusive
                );
                foreach ($followerResponse['data'] ?? [] as $entry) {
                    foreach ($entry['values'] ?? [] as $value) {
                        $result['new_followers'] += (int) ($value['value'] ?? 0);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug("IG follower_count failed: {$e->getMessage()}");
            // Fallback to DB for followers only
            $result['new_followers'] = (int) MetaIgInsight::where('ig_account_id', $igAccountId)
                ->whereBetween('date', [$from, $to])
                ->sum('new_followers');
        }

        // If all API calls failed (all zeros except maybe followers), try DB fallback
        if ($result['reach'] === 0 && $result['views'] === 0 && $result['engagement'] === 0) {
            $ig = MetaIgInsight::where('ig_account_id', $igAccountId)
                ->whereBetween('date', [$from, $to])
                ->selectRaw('
                    COALESCE(SUM(reach), 0) as reach,
                    COALESCE(SUM(views), 0) as views,
                    COALESCE(SUM(profile_views), 0) as profile_views,
                    COALESCE(SUM(total_interactions), 0) as engagement,
                    COALESCE(SUM(website_clicks), 0) as link_clicks
                ')
                ->first();

            if ($ig && (int) $ig->views > 0) {
                $result['reach'] = (int) $ig->reach;
                $result['views'] = (int) $ig->views;
                $result['profile_views'] = (int) $ig->profile_views;
                $result['engagement'] = (int) $ig->engagement;
                $result['link_clicks'] = (int) $ig->link_clicks;
                Log::warning("IG period totals: all API calls failed, using DB SUM fallback");
            }
        }

        return $result;
    }

    /**
     * Fetch ALL ads period totals from Meta API in one call.
     * No time_increment = de-duplicated period totals for all metrics.
     * Returns the exact numbers Meta Ads Manager shows.
     */
    private function fetchAdsPeriodTotals(string $from, string $to): array
    {
        $defaults = [
            'spend' => 0.0,
            'impressions' => 0,
            'reach' => 0,
            'link_clicks' => 0,
            'purchases' => 0,
            'revenue' => 0.0,
            'messaging_conversations' => 0,
            'messaging_conversations_replied' => 0,
        ];

        $accountId = config('meta.ad_account_id');
        if (!$accountId) {
            return $defaults;
        }

        try {
            $insights = $this->api->getAdsInsights($accountId, [
                'level' => 'account',
                'time_range' => json_encode(['since' => $from, 'until' => $to]),
                'fields' => 'spend,impressions,reach,actions,action_values',
                ...$this->adsSyncService->getAttributionParams(),
            ]);

            if (!empty($insights) && isset($insights[0])) {
                $row = $insights[0];
                $result = $defaults;
                $result['spend'] = (float) ($row['spend'] ?? 0);
                $result['impressions'] = (int) ($row['impressions'] ?? 0);
                $result['reach'] = (int) ($row['reach'] ?? 0);

                $actionsByType = [];
                foreach ($row['actions'] ?? [] as $action) {
                    $type = $action['action_type'] ?? '';
                    $val = (int) ($action['value'] ?? 0);
                    $actionsByType[$type] = $val;
                    match ($type) {
                        'link_click' => $result['link_clicks'] = $val,
                        'purchase' => $result['purchases'] = $val,
                        'onsite_conversion.messaging_conversation_replied_7d' => $result['messaging_conversations_replied'] = $val,
                        default => null,
                    };
                }

                // Prefer total_messaging_connection (broader, matches Meta Business Suite)
                // over messaging_conversation_started_7d (7-day attribution window only).
                $result['messaging_conversations'] =
                    $actionsByType['onsite_conversion.total_messaging_connection']
                    ?? $actionsByType['onsite_conversion.messaging_conversation_started_7d']
                    ?? 0;

                foreach ($row['action_values'] ?? [] as $action) {
                    if (($action['action_type'] ?? '') === 'purchase') {
                        $result['revenue'] = (float) ($action['value'] ?? 0);
                    }
                }

                return $result;
            }
        } catch (Throwable $e) {
            Log::debug("Ads period totals (no time_increment) failed: {$e->getMessage()}");
        }

        // Fallback: DB SUM (only if API failed)
        Log::warning("Ads period totals: API failed, using DB SUM fallback");
        $ads = MetaAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(reach), 0) as reach,
                COALESCE(SUM(link_clicks), 0) as link_clicks,
                COALESCE(SUM(purchases), 0) as purchases,
                COALESCE(SUM(purchase_value), 0) as revenue,
                COALESCE(SUM(messaging_conversations), 0) as messaging_conversations,
                COALESCE(SUM(messaging_conversations_replied), 0) as messaging_conversations_replied
            ')
            ->first();

        return [
            'spend' => (float) ($ads->spend ?? 0),
            'impressions' => (int) ($ads->impressions ?? 0),
            'reach' => (int) ($ads->reach ?? 0),
            'link_clicks' => (int) ($ads->link_clicks ?? 0),
            'purchases' => (int) ($ads->purchases ?? 0),
            'revenue' => (float) ($ads->revenue ?? 0),
            'messaging_conversations' => (int) ($ads->messaging_conversations ?? 0),
            'messaging_conversations_replied' => (int) ($ads->messaging_conversations_replied ?? 0),
        ];
    }

    // ═══════════════════════════════════════════════════════
    // TIKTOK ADS — resolve methods
    // ═══════════════════════════════════════════════════════

    /**
     * No-op in DB-first mode. TikTok daily data is populated by sync jobs.
     */
    public function ensureTiktokAdsData(string $from, string $to): void
    {
        // DB is source of truth. Zero API calls.
    }

    /**
     * Daily TikTok ads data from DB (for charts).
     */
    public function resolveTiktokAdsDaily(string $from, string $to): array
    {
        return TikTokAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                date,
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(reach), 0) as reach,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(video_views), 0) as video_views,
                COALESCE(SUM(likes), 0) as likes,
                COALESCE(SUM(comments), 0) as comments,
                COALESCE(SUM(shares), 0) as shares,
                COALESCE(SUM(purchases), 0) as purchases,
                COALESCE(SUM(purchase_value), 0) as revenue
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->format('Y-m-d'),
                'spend' => (float) $row->spend,
                'impressions' => (int) $row->impressions,
                'reach' => (int) $row->reach,
                'clicks' => (int) $row->clicks,
                'video_views' => (int) $row->video_views,
                'likes' => (int) $row->likes,
                'comments' => (int) $row->comments,
                'shares' => (int) $row->shares,
                'purchases' => (int) $row->purchases,
                'revenue' => (float) $row->revenue,
            ])
            ->toArray();
    }

    /**
     * Period totals for TikTok ads (KPI cards).
     * Current period: call API (no time dimension = de-duplicated reach).
     */
    public function resolveTiktokAdsTotals(string $from, string $to): array
    {
        try {
            $sync = app(TiktokAdsSyncService::class);

            return $sync->fetchPeriodTotals($from, $to);
        } catch (Throwable $e) {
            Log::warning("resolveTiktokAdsTotals API failed, falling back to DB: {$e->getMessage()}");

            return $this->resolveTiktokAdsTotalsDbOnly($from, $to);
        }
    }

    /**
     * Period totals from DB SUM (for YoY or as fallback).
     */
    public function resolveTiktokAdsTotalsDbOnly(string $from, string $to): array
    {
        $ads = TikTokAdsInsight::whereBetween('date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(reach), 0) as reach,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(video_views), 0) as video_views,
                COALESCE(SUM(likes), 0) as likes,
                COALESCE(SUM(comments), 0) as comments,
                COALESCE(SUM(shares), 0) as shares,
                COALESCE(SUM(conversions), 0) as conversions,
                COALESCE(SUM(purchases), 0) as purchases,
                COALESCE(SUM(purchase_value), 0) as purchase_value
            ')
            ->first();

        return [
            'spend' => (float) ($ads->spend ?? 0),
            'impressions' => (int) ($ads->impressions ?? 0),
            'reach' => (int) ($ads->reach ?? 0),
            'clicks' => (int) ($ads->clicks ?? 0),
            'video_views' => (int) ($ads->video_views ?? 0),
            'likes' => (int) ($ads->likes ?? 0),
            'comments' => (int) ($ads->comments ?? 0),
            'shares' => (int) ($ads->shares ?? 0),
            'conversions' => (int) ($ads->conversions ?? 0),
            'purchases' => (int) ($ads->purchases ?? 0),
            'purchase_value' => (float) ($ads->purchase_value ?? 0),
        ];
    }

    /**
     * YoY period totals from meta_period_totals cache.
     */
    public function resolveTiktokAdsPeriodTotalsForYoY(string $from, string $to, bool $forceApi = false): ?array
    {
        // Check DB cache first
        if (! $forceApi) {
            $cached = MetaPeriodTotal::getCached('tiktok', $from, $to);
            if ($cached) {
                return $cached;
            }
        }

        // Try API (only if force or no cache)
        try {
            $sync = app(TiktokAdsSyncService::class);

            return $sync->syncPeriodTotals($from, $to);
        } catch (Throwable $e) {
            Log::warning("resolveTiktokAdsPeriodTotalsForYoY failed: {$e->getMessage()}");

            // Fall back to DB SUM
            if ($this->hasTiktokDataForRange($from, $to)) {
                return $this->resolveTiktokAdsTotalsDbOnly($from, $to);
            }

            return null;
        }
    }

    /**
     * TikTok campaign breakdown from DB.
     */
    public function resolveTikTokCampaigns(string $from, string $to): array
    {
        return TikTokAdsInsight::whereBetween('date', [$from, $to])
            ->whereNotNull('tiktok_campaign_id')
            ->selectRaw('
                tiktok_campaign_id,
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(reach), 0) as reach,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(video_views), 0) as video_views,
                COALESCE(SUM(purchases), 0) as purchases,
                COALESCE(SUM(purchase_value), 0) as revenue
            ')
            ->groupBy('tiktok_campaign_id')
            ->orderByDesc(DB::raw('SUM(spend)'))
            ->get()
            ->map(function ($row) {
                $campaign = TikTokCampaign::find($row->tiktok_campaign_id);
                $spend = (float) $row->spend;
                $revenue = (float) $row->revenue;

                return [
                    'campaign_id' => $campaign?->campaign_id,
                    'name' => $campaign?->name ?? 'Unknown',
                    'objective' => $campaign?->objective,
                    'status' => $campaign?->status ?? 'UNKNOWN',
                    'spend' => $spend,
                    'impressions' => (int) $row->impressions,
                    'reach' => (int) $row->reach,
                    'clicks' => (int) $row->clicks,
                    'video_views' => (int) $row->video_views,
                    'purchases' => (int) $row->purchases,
                    'revenue' => $revenue,
                    'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * TikTok audience breakdowns from JSON columns.
     */
    public function resolveTiktokBreakdowns(string $from, string $to): array
    {
        $rows = TikTokAdsInsight::whereBetween('date', [$from, $to])->get();

        $age = [];
        $gender = [];
        $platform = [];

        $fields = ['spend', 'impressions', 'reach', 'clicks', 'conversions'];

        foreach ($rows as $row) {
            $this->mergeBreakdown($age, $row->age_breakdown, $fields);
            $this->mergeBreakdown($gender, $row->gender_breakdown, $fields);
            $this->mergeBreakdown($platform, $row->platform_breakdown, $fields);
        }

        return [
            'age' => $this->sortBreakdownBySpend($age),
            'gender' => $this->sortBreakdownBySpend($gender),
            'platform' => $this->sortBreakdownBySpend($platform),
        ];
    }

    private function sortBreakdownBySpend(array $breakdown): array
    {
        $result = [];
        foreach ($breakdown as $key => $metrics) {
            $result[] = array_merge(['label' => $key], $metrics);
        }
        usort($result, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $result;
    }

    /**
     * Top TikTok videos (organic) from DB.
     */
    public function resolveTiktokTopVideos(string $from, string $to, int $limit = 12): array
    {
        return TikTokVideo::whereBetween('created_at_tiktok', [$from, $to])
            ->orderByRaw('(like_count + comment_count + share_count) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($v) => [
                'video_id' => $v->video_id,
                'title' => $v->title ?: mb_substr($v->video_description ?? '', 0, 80),
                'cover_image_url' => $v->cover_image_url,
                'share_url' => $v->share_url,
                'duration' => $v->duration,
                'view_count' => $v->view_count,
                'like_count' => $v->like_count,
                'comment_count' => $v->comment_count,
                'share_count' => $v->share_count,
                'engagement_rate' => $v->engagement_rate,
                'created_at' => $v->created_at_tiktok?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    /**
     * Check if TikTok daily data exists for a date range.
     */
    public function hasTiktokDataForRange(string $from, string $to): bool
    {
        return TikTokAdsInsight::whereBetween('date', [$from, $to])->exists();
    }
}
