<?php

namespace App\Jobs;

use App\Http\Middleware\MetaMarketingCache;
use App\Models\Meta\MetaMessagingStat;
use App\Services\Meta\MetaAdsSyncService;
use App\Services\Meta\MetaApiService;
use App\Services\Meta\MetaDataResolverService;
use App\Services\Meta\MetaPageSyncService;
use App\Services\Meta\MetaPostSyncService;
use App\Services\Tiktok\TiktokAdsSyncService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Full Rifresko: refreshes ALL Meta data in the background.
 *
 * Runs as a queue job so no PHP timeout issues.
 * FB, IG, Ads sync first (~6 min), then messaging via fast conversation counting (~2 min).
 * Posts are excluded (synced by nightly cron, too slow for on-demand refresh).
 * Sets a cache flag so the frontend can poll for completion.
 */
class MetaForceRefreshJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public const STATUS_KEY = 'meta_rifresko_status';

    public function __construct(
        protected string $from,
        protected string $to,
        protected ?string $channel = null, // null = all, 'instagram', 'facebook', 'ads', 'tiktok'
    ) {}

    public function handle(
        MetaPageSyncService $pageSyncService,
        MetaAdsSyncService $adsSyncService,
        MetaPostSyncService $postSyncService,
        MetaApiService $api,
    ): void {
        $startTime = microtime(true);
        $ch = $this->channel;
        Log::info("MetaForceRefreshJob: START [{$this->from}..{$this->to}]" . ($ch ? " channel={$ch}" : ' channel=all'));

        Cache::put(self::STATUS_KEY, [
            'status' => 'syncing',
            'started_at' => now()->toIso8601String(),
            'from' => $this->from,
            'to' => $this->to,
            'channel' => $ch,
        ], 1800);

        $refreshed = [];
        $pageId = (string) config('meta.page_id');
        $igAccountId = (string) config('meta.ig_account_id');
        $syncFb = ! $ch || $ch === 'facebook' || $ch === 'total';
        $syncIg = ! $ch || $ch === 'instagram' || $ch === 'total';
        $syncAds = ! $ch || $ch === 'ads' || $ch === 'total';
        $syncTiktok = (! $ch || $ch === 'tiktok' || $ch === 'total') && config('tiktok.features.tiktok_module', false);

        // 1. Facebook Page Insights
        if ($syncFb && $pageId) {
            try {
                Log::info("MetaForceRefreshJob: FB re-syncing (upsert)...");
                $pageSyncService->syncPageInsights($this->from, $this->to);
                $refreshed[] = 'facebook';
                Log::info('MetaForceRefreshJob: FB OK');
            } catch (Throwable $e) {
                Log::warning("MetaForceRefreshJob: FB failed: {$e->getMessage()}");
            }
        }

        // 2. Instagram Insights
        if ($syncIg && $igAccountId) {
            try {
                Log::info('MetaForceRefreshJob: IG re-syncing (upsert, preserving follower data)...');
                $pageSyncService->syncIgInsights($this->from, $this->to);
                $refreshed[] = 'instagram';
                Log::info('MetaForceRefreshJob: IG OK');
            } catch (Throwable $e) {
                Log::warning("MetaForceRefreshJob: IG failed: {$e->getMessage()}");
            }
        }

        // 3. Ads Insights — always sync (needed for messaging_conversations breakdown on FB/IG tabs)
        if ($syncAds || $syncFb || $syncIg) {
            try {
                Log::info("MetaForceRefreshJob: Ads re-syncing (upsert)...");
                $adsSyncService->syncInsights($this->from, $this->to);
                $refreshed[] = 'ads';
                Log::info('MetaForceRefreshJob: Ads OK');
            } catch (Throwable $e) {
                Log::warning("MetaForceRefreshJob: Ads failed: {$e->getMessage()}");
            }
        }

        // 4. Posts
        if ($syncFb) {
            try {
                Log::info('MetaForceRefreshJob: FB posts syncing...');
                $fbPosts = $postSyncService->syncFacebookPosts();
                $refreshed[] = 'fb_posts';
                Log::info("MetaForceRefreshJob: FB posts OK — {$fbPosts} posts");
            } catch (Throwable $e) {
                Log::warning("MetaForceRefreshJob: FB posts failed: {$e->getMessage()}");
            }
        }

        if ($syncIg) {
            try {
                Log::info('MetaForceRefreshJob: IG posts syncing...');
                $igPosts = $postSyncService->syncInstagramPosts();
                $refreshed[] = 'ig_posts';
                Log::info("MetaForceRefreshJob: IG posts OK — {$igPosts} posts");
            } catch (Throwable $e) {
                Log::warning("MetaForceRefreshJob: IG posts failed: {$e->getMessage()}");
            }
        }

        // 5. Messaging
        // NOTE: Conversations API folder param is broken — unified inbox regardless.
        // Instagram uses Conversations API, Messenger uses Page Insights.
        $oldestAllowed = Carbon::today()->subDays(90)->toDateString();
        $effectiveFrom = max($this->from, $oldestAllowed);

        if ($effectiveFrom <= $this->to && $pageId) {
            if ($syncIg) {
                try {
                    $msgStart = microtime(true);
                    $count = $this->refreshMessagingFast($api, $pageId, 'instagram', $effectiveFrom, $this->to);
                    $elapsed = round(microtime(true) - $msgStart, 2);
                    Log::info("MetaForceRefreshJob: instagram messaging OK — {$count} rows in {$elapsed}s");
                    $refreshed[] = 'instagram_dms';
                } catch (Throwable $e) {
                    Log::warning("MetaForceRefreshJob: instagram messaging failed: {$e->getMessage()}");
                }
            }

            if ($syncFb) {
                try {
                    $msgStart = microtime(true);
                    $count = $this->refreshMessengerFromInsights($api, $pageId, $effectiveFrom, $this->to);
                    $elapsed = round(microtime(true) - $msgStart, 2);
                    Log::info("MetaForceRefreshJob: messenger insights OK — {$count} rows in {$elapsed}s");
                    $refreshed[] = 'messenger_dms';
                } catch (Throwable $e) {
                    Log::warning("MetaForceRefreshJob: messenger insights failed: {$e->getMessage()}");
                }
            }
        }

        // 6. TikTok Ads Insights
        if ($syncTiktok) {
            try {
                Log::info('MetaForceRefreshJob: TikTok ads re-syncing...');
                $tiktokSync = app(TiktokAdsSyncService::class);
                $tiktokSync->syncCampaigns();
                $tiktokSync->syncInsights($this->from, $this->to);
                $tiktokSync->syncBreakdowns($this->from, $this->to);
                $refreshed[] = 'tiktok_ads';
                Log::info('MetaForceRefreshJob: TikTok ads OK');
            } catch (Throwable $e) {
                Log::warning("MetaForceRefreshJob: TikTok ads failed: {$e->getMessage()}");
            }

            // TikTok organic (videos + account snapshot) if enabled
            if (config('tiktok.features.tiktok_organic', false)) {
                try {
                    $tiktokOrganic = app(\App\Services\Tiktok\TiktokSyncService::class);
                    $tiktokOrganic->syncAll();
                    $refreshed[] = 'tiktok_organic';
                    Log::info('MetaForceRefreshJob: TikTok organic OK');
                } catch (Throwable $e) {
                    Log::warning("MetaForceRefreshJob: TikTok organic failed: {$e->getMessage()}");
                }
            }
        }

        // 7. YoY Period Totals — try to refresh from API for previous year.
        //    If API returns data → save to DB. If not → keep existing DB data.
        try {
            $prevFrom = Carbon::parse($this->from)->subYear()->toDateString();
            $prevTo = Carbon::parse($this->to)->subYear()->toDateString();
            Log::info("MetaForceRefreshJob: YoY period totals [{$prevFrom}..{$prevTo}]");

            $resolver = app(MetaDataResolverService::class);
            $resolver->resolveFbPeriodTotalsForYoY($prevFrom, $prevTo, forceApi: true);
            $resolver->resolveIgPeriodTotalsForYoY($prevFrom, $prevTo, forceApi: true);
            $resolver->resolveAdsPeriodTotalsForYoY($prevFrom, $prevTo, forceApi: true);

            // Also refresh current period totals (for the current-year YoY counterpart)
            $resolver->resolveFbPeriodTotalsForYoY($this->from, $this->to, forceApi: true);
            $resolver->resolveIgPeriodTotalsForYoY($this->from, $this->to, forceApi: true);
            $resolver->resolveAdsPeriodTotalsForYoY($this->from, $this->to, forceApi: true);

            // TikTok YoY period totals
            if ($syncTiktok) {
                try {
                    $tiktokSync = app(TiktokAdsSyncService::class);
                    $tiktokSync->syncPeriodTotals($prevFrom, $prevTo);
                    $tiktokSync->syncPeriodTotals($this->from, $this->to);
                } catch (Throwable $e) {
                    Log::warning("MetaForceRefreshJob: TikTok YoY totals failed: {$e->getMessage()}");
                }
            }

            $refreshed[] = 'yoy_period_totals';
            Log::info('MetaForceRefreshJob: YoY period totals OK');
        } catch (Throwable $e) {
            Log::warning("MetaForceRefreshJob: YoY period totals failed: {$e->getMessage()}");
        }

        // 7. Bust caches
        MetaMarketingCache::bustCache();

        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info("MetaForceRefreshJob: DONE in {$elapsed}s — refreshed: " . implode(', ', $refreshed));

        // Signal completion to frontend
        Cache::put(self::STATUS_KEY, [
            'status' => 'done',
            'finished_at' => now()->toIso8601String(),
            'elapsed' => $elapsed,
            'refreshed' => $refreshed,
            'from' => $this->from,
            'to' => $this->to,
        ], 1800);
    }

    /**
     * Fast messaging refresh: paginate conversations with early termination.
     *
     * Conversations API returns newest-first. We paginate page by page,
     * count conversations that fall within [from, to], and STOP when
     * all conversations on a page are older than $from.
     *
     * Skips individual message fetching (too slow). Uses conversation count
     * as messages_received estimate — nightly sync provides detailed breakdown.
     *
     * ~60-120s per platform for a 28-day range.
     */
    private function refreshMessagingFast(MetaApiService $api, string $pageId, string $platform, string $from, string $to): int
    {
        $isInstagram = $platform === 'instagram';

        // Build daily buckets
        $daily = [];
        for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $daily[$d->toDateString()] = [
                'new_conversations' => 0,
                'total_messages_received' => 0,
                'total_messages_sent' => 0,
            ];
        }

        $fromStart = Carbon::parse($from)->startOfDay();
        $toEnd = Carbon::parse($to)->endOfDay();

        // Paginate conversations page by page, stopping when we pass the date range
        $endpoint = "{$pageId}/conversations";
        $limit = $isInstagram ? 25 : 50;
        $params = [
            'fields' => 'id,updated_time,message_count',
            'limit' => $limit,
        ];
        if ($isInstagram) {
            $params['folder'] = 'instagram';
        }

        $maxPages = 100;
        $totalFetched = 0;
        $inRangeCount = 0;

        $response = $api->getWithPageToken($endpoint, $params);
        $page = 0;

        while (true) {
            $conversations = $response['data'] ?? [];
            if (empty($conversations)) {
                break;
            }

            $totalFetched += count($conversations);
            $allOlderThanRange = true;

            foreach ($conversations as $conv) {
                $updated = $conv['updated_time'] ?? null;
                if (! $updated) {
                    continue;
                }

                $updatedAt = Carbon::parse($updated);

                // If any conversation is within or newer than range, we haven't passed it yet
                if ($updatedAt->gte($fromStart)) {
                    $allOlderThanRange = false;
                }

                // Only count conversations within our target range
                if ($updatedAt->lt($fromStart) || $updatedAt->gt($toEnd)) {
                    continue;
                }

                $date = $updatedAt->toDateString();
                if (! isset($daily[$date])) {
                    continue;
                }

                $daily[$date]['new_conversations']++;
                $inRangeCount++;

                // Use message_count from API (available for both Messenger and Instagram)
                $messageCount = max(1, (int) ($conv['message_count'] ?? 1));
                $daily[$date]['total_messages_received'] += $messageCount;
            }

            // Early termination: all conversations on this page are older than our range
            if ($allOlderThanRange) {
                Log::info("MetaForceRefreshJob: {$platform} early stop at page {$page} — all conversations older than {$from}");
                break;
            }

            // Check pagination
            $page++;
            if ($page >= $maxPages || ! isset($response['paging']['next'])) {
                break;
            }

            // Follow next page (re-injects page token)
            $response = $api->fetchNextPageUrl($response['paging']['next']);
        }

        Log::info("MetaForceRefreshJob: {$platform} fetched {$totalFetched} conversations, {$inRangeCount} in range [{$from}..{$to}], {$page} pages");

        // Upsert daily stats
        $upserted = 0;
        foreach ($daily as $date => $counts) {
            MetaMessagingStat::updateOrCreate(
                ['platform' => $platform, 'date' => $date],
                [
                    'new_conversations' => $counts['new_conversations'],
                    'total_messages_received' => $counts['total_messages_received'],
                    'total_messages_sent' => $counts['total_messages_sent'],
                    'synced_at' => now(),
                ]
            );
            $upserted++;
        }

        return $upserted;
    }

    /**
     * Messenger conversations from Page Insights (page_messages_new_conversations_unique).
     *
     * The Conversations API folder parameter is broken (returns unified inbox),
     * so we use this Messenger-specific Page Insight metric instead.
     * Only provides conversation count — no total messages metric exists.
     */
    private function refreshMessengerFromInsights(MetaApiService $api, string $pageId, string $from, string $to): int
    {
        $response = $api->getWithPageToken("{$pageId}/insights", [
            'metric' => 'page_messages_new_conversations_unique',
            'period' => 'day',
            'since' => $from,
            'until' => Carbon::parse($to)->addDay()->toDateString(),
        ]);

        $values = $response['data'][0]['values'] ?? [];

        $upserted = 0;
        foreach ($values as $v) {
            $date = Carbon::parse($v['end_time'])->subDay()->toDateString();

            // Only upsert within our requested range
            if ($date < $from || $date > $to) {
                continue;
            }

            MetaMessagingStat::updateOrCreate(
                ['platform' => 'messenger', 'date' => $date],
                [
                    'new_conversations' => (int) ($v['value'] ?? 0),
                    'total_messages_received' => 0,
                    'total_messages_sent' => 0,
                    'synced_at' => now(),
                ]
            );
            $upserted++;
        }

        return $upserted;
    }

    public function failed(Throwable $exception): void
    {
        Log::error("MetaForceRefreshJob: FAILED — {$exception->getMessage()}");

        Cache::put(self::STATUS_KEY, [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'from' => $this->from,
            'to' => $this->to,
        ], 1800);
    }
}
