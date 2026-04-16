<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaIgInsight;
use App\Models\Meta\MetaPageInsight;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MetaPageSyncService
{
    public function __construct(
        private readonly MetaApiService $api,
    ) {}

    /**
     * Sync FB Page Insights for a date range.
     * Uses Page Token which is required for /{page_id}/insights.
     */
    public function syncPageInsights(string $dateFrom, string $dateTo): int
    {
        $pageId = config('meta.page_id');
        if (!$pageId) {
            Log::warning('Meta Page ID not configured. Skipping page insights sync.');
            return 0;
        }

        // Check if page token is available
        if (!config('meta.page_token')) {
            Log::warning('Meta Page Token not configured. Skipping page insights sync. Use OAuth or set META_PAGE_TOKEN in .env.');
            return 0;
        }

        $count = 0;

        // Metrics that work with period=day and Page Token in v21.0
        // Deprecated (removed):
        //   page_daily_unfollows_unique — Sept 2024
        //   page_posts_impressions_paid_unique — Nov 2025
        //   page_posts_impressions_organic_unique — Nov 2025
        // Each metric is fetched individually to avoid one failure blocking all.
        // page_impressions_unique and page_posts_impressions deprecated Nov 2025.
        // Using page_total_media_view_unique (reach) and page_media_view (views).
        // Note: page_daily_follows was marked deprecated Sept 2024 but API still returns it.
        $dayMetrics = [
            'page_reach' => 'page_total_media_view_unique',
            'page_views_total' => 'page_views_total',
            'page_post_engagements' => 'page_post_engagements',
            'page_posts_impressions' => 'page_media_view',
            'page_messages_new_threads' => 'page_messages_new_threads',
            'page_video_views' => 'page_video_views',
            'page_daily_follows' => 'page_daily_follows',
            'page_followers' => 'page_follows',
        ];

        $metricData = [];

        // Meta's `until` is exclusive — add 1 day so the last date is included.
        $untilExclusive = Carbon::parse($dateTo)->addDay()->toDateString();

        foreach ($dayMetrics as $fieldName => $apiMetric) {
            try {
                $response = $this->api->getPageInsights($pageId, $apiMetric, 'day', $dateFrom, $untilExclusive);
                $data = $response['data'] ?? [];

                foreach ($data as $metricEntry) {
                    $values = $metricEntry['values'] ?? [];
                    foreach ($values as $dayValue) {
                        // end_time is the END of the period (start of next day in PST/UTC),
                        // so subtract 1 day to get the actual date the metric belongs to.
                        $date = Carbon::parse($dayValue['end_time'])->subDay()->toDateString();
                        $metricData[$date][$fieldName] = $dayValue['value'] ?? 0;
                    }
                }
            } catch (Exception $e) {
                Log::warning("Failed to fetch page metric {$apiMetric}: " . $e->getMessage());
                // Continue with other metrics - don't let one failure block all
            }
        }

        // Fetch reactions breakdown separately (returns JSON objects, not integers)
        // This gives us the actual "Content Interactions" (reactions only: like, love, wow, etc.)
        // which is much closer to Facebook Pro Dashboard than page_post_engagements (which includes all clicks).
        try {
            $response = $this->api->getPageInsights($pageId, 'page_actions_post_reactions_total', 'day', $dateFrom, $untilExclusive);
            foreach ($response['data'] ?? [] as $metricEntry) {
                foreach ($metricEntry['values'] ?? [] as $dayValue) {
                    $date = Carbon::parse($dayValue['end_time'])->subDay()->toDateString();
                    $val = $dayValue['value'] ?? 0;
                    // Value is a JSON object like {"like":329,"love":14} — sum all reaction types
                    $metricData[$date]['page_reactions_total'] = is_array($val) ? array_sum($val) : (int) $val;
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to fetch page_actions_post_reactions_total: " . $e->getMessage());
        }

        // Also try to get page fan count (lifetime metric)
        try {
            $pageInfo = $this->api->getWithPageToken($pageId, [
                'fields' => 'fan_count,followers_count',
            ]);
            $currentFans = $pageInfo['fan_count'] ?? 0;
            $currentFollowers = $pageInfo['followers_count'] ?? 0;
        } catch (Exception $e) {
            $currentFans = 0;
            $currentFollowers = 0;
            Log::debug("Could not fetch page fan/followers count: " . $e->getMessage());
        }

        // Fetch Reels plays by enumerating /{page-id}/video_reels and summing
        // blue_reels_play_count per reel, grouped by created_time date.
        // This bridges the Views gap since page_posts_impressions excludes Reels.
        $reelsPlaysByDate = $this->syncReelsPlays($pageId, $dateFrom, $dateTo);
        foreach ($reelsPlaysByDate as $date => $plays) {
            $metricData[$date]['page_reels_views'] = $plays;
        }

        // Upsert page insights
        // fan_count/followers_count are lifetime snapshots.
        // Store on the latest date; preserve existing non-zero values for older dates.
        $latestDate = !empty($metricData) ? max(array_keys($metricData)) : null;

        foreach ($metricData as $date => $metrics) {
            $record = [
                'page_posts_impressions' => $metrics['page_posts_impressions'] ?? 0,
                'page_posts_impressions_paid' => $metrics['page_posts_impressions_paid'] ?? 0,
                'page_posts_impressions_organic' => $metrics['page_posts_impressions_organic'] ?? 0,
                'page_reach' => $metrics['page_reach'] ?? 0,
                'page_views_total' => $metrics['page_views_total'] ?? 0,
                'page_post_engagements' => $metrics['page_post_engagements'] ?? 0,
                'page_reactions_total' => $metrics['page_reactions_total'] ?? 0,
                'page_video_views' => $metrics['page_video_views'] ?? 0,
                'page_reels_views' => $metrics['page_reels_views'] ?? 0,
                'page_daily_follows' => $metrics['page_daily_follows'] ?? 0,
                'page_daily_unfollows' => $metrics['page_daily_unfollows'] ?? 0,
                'page_messages_new_threads' => $metrics['page_messages_new_threads'] ?? 0,
                'synced_at' => now(),
            ];

            // page_followers from day metric (page_follows API) gives accurate per-day totals.
            // Fall back to lifetime snapshot from fan_count/followers_count if not available.
            $dayFollowers = (int) ($metrics['page_followers'] ?? 0);
            if ($dayFollowers > 0) {
                $record['page_followers'] = $dayFollowers;
                $record['page_fans'] = $dayFollowers; // Keep page_fans in sync
            } elseif ($date === $latestDate && ($currentFans > 0 || $currentFollowers > 0)) {
                $record['page_fans'] = $currentFans;
                $record['page_followers'] = $currentFollowers;
            } else {
                // For older dates, preserve existing non-zero follower values in DB
                $existing = MetaPageInsight::where('page_id', $pageId)->where('date', $date)->first();
                if ($existing && $existing->page_followers > 0) {
                    // Keep existing snapshot — don't overwrite with 0
                } else if ($currentFollowers > 0) {
                    // No existing data — store current snapshot as best estimate
                    $record['page_followers'] = $currentFollowers;
                    $record['page_fans'] = $currentFans;
                }
            }

            MetaPageInsight::updateOrCreate(
                ['page_id' => $pageId, 'date' => $date],
                $record
            );
            $count++;
        }

        // If no metrics returned any data, log a helpful message
        if ($count === 0) {
            Log::info("Page insights returned 0 records for {$dateFrom} to {$dateTo}. " .
                "This may be normal for old date ranges (Page Insights typically available for last 2 years) " .
                "or the app may need 'pages_read_engagement' permission.");
        }

        return $count;
    }

    /**
     * Sync Instagram Account Insights for a date range.
     * Requires IG Business Account linked to FB Page and Page Token.
     */
    public function syncIgInsights(string $dateFrom, string $dateTo): int
    {
        $igAccountId = config('meta.ig_account_id');
        if (!$igAccountId) {
            // Try to auto-discover IG account from Page
            $igAccountId = $this->discoverIgAccountId();
            if (!$igAccountId) {
                Log::info('Meta IG Account ID not configured and could not be auto-discovered. Skipping IG insights sync.');
                return 0;
            }
        }

        if (!config('meta.page_token')) {
            Log::warning('Meta Page Token not configured. Skipping IG insights sync.');
            return 0;
        }

        $count = 0;
        $metricData = [];

        // IG insights metrics for v21.0+
        // 'impressions' was removed at account level in v21.0 — use 'views' instead.
        // Most metrics require metric_type=total_value and must be fetched day-by-day.
        // We batch ALL metrics into a single comma-separated API call per day to minimize API calls.
        $totalValueMetrics = [
            'reach', 'views', 'accounts_engaged', 'total_interactions',
            'likes', 'comments', 'shares', 'saves', 'replies',
            'profile_views', 'website_clicks',
        ];

        $batchedMetricString = implode(',', $totalValueMetrics);

        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dayStr = $day->toDateString();
            $nextDayStr = $day->copy()->addDay()->toDateString();

            try {
                $response = $this->api->getIgInsights(
                    $igAccountId, $batchedMetricString, 'day', $dayStr, $nextDayStr, 'total_value'
                );

                foreach ($response['data'] ?? [] as $metricEntry) {
                    $metricName = $metricEntry['name'] ?? '';
                    $value = $metricEntry['total_value']['value'] ?? 0;
                    $metricData[$dayStr][$metricName] = $value;
                }
            } catch (Exception $e) {
                Log::debug("Failed to fetch IG metrics for {$dayStr}: " . $e->getMessage());
            }
        }

        // Get current follower count snapshot from account info API (the REAL total)
        $currentFollowers = 0;
        try {
            $accountInfo = $this->api->getIgAccountInfo($igAccountId, ['followers_count']);
            $currentFollowers = $accountInfo['followers_count'] ?? 0;
        } catch (Exception $e) {
            Log::warning('Failed to fetch IG follower count: ' . $e->getMessage());
        }

        // follower_count with period=day returns the DAILY NET CHANGE (not total!)
        // e.g., values like 121, 85, 205 mean "gained X net followers that day"
        // We store these as new_followers and reconstruct total from the snapshot.
        //
        // Meta API limit: follower_count only supports the last 30 days.
        // If our range extends further back, clamp the start date so we still
        // get data for the days that ARE within the 30-day window.
        $dailyFollowerChanges = [];
        try {
            $oldestAllowed = Carbon::today()->subDays(30)->toDateString();
            $followerFrom = max($dateFrom, $oldestAllowed);
            // +2 days: end_time is END of period (shifts dates back by 1) + 1 day reporting lag
            $followerUntil = Carbon::parse($dateTo)->addDays(2)->toDateString();

            // Only attempt if there's a valid window (followerFrom <= dateTo)
            if ($followerFrom <= $dateTo) {
                $response = $this->api->getIgInsights($igAccountId, 'follower_count', 'day', $followerFrom, $followerUntil);
                foreach ($response['data'] ?? [] as $metricEntry) {
                    foreach ($metricEntry['values'] ?? [] as $dayValue) {
                        // end_time is the END of the period — subtract 1 day to get actual date.
                        $date = Carbon::parse($dayValue['end_time'])->subDay()->toDateString();
                        $dailyFollowerChanges[$date] = $dayValue['value'] ?? 0;
                    }
                }
                Log::info("IG follower_count fetched for [{$followerFrom}..{$dateTo}]: " . count($dailyFollowerChanges) . ' days');
            }
        } catch (Exception $e) {
            Log::warning("Failed to fetch IG follower_count via period=day: " . $e->getMessage());
        }

        // Sort dates chronologically
        ksort($metricData);

        // Did we successfully get follower data from the API?
        $hasFollowerData = !empty($dailyFollowerChanges);

        // Reconstruct historical follower totals by working backwards from the snapshot.
        // currentFollowers is the total RIGHT NOW. Each daily change tells us the net gain/loss.
        // We walk backwards from dates that HAVE follower data to calculate totals.
        // Dates outside the 30-day API window are left untouched.
        $followerTotals = [];

        if ($hasFollowerData && $currentFollowers > 0) {
            // Only reconstruct totals for dates that actually have follower change data
            $followerDates = array_keys($dailyFollowerChanges);
            sort($followerDates);

            if (!empty($followerDates)) {
                $runningTotal = $currentFollowers;

                // Walk backwards from the most recent follower date
                for ($i = count($followerDates) - 1; $i >= 0; $i--) {
                    $date = $followerDates[$i];
                    $followerTotals[$date] = $runningTotal;
                    $runningTotal -= ($dailyFollowerChanges[$date] ?? 0);
                }
            }
        }

        // Upsert IG insights
        foreach ($metricData as $date => $metrics) {
            $upsertData = [
                'impressions' => 0,
                'reach' => $metrics['reach'] ?? 0,
                'views' => $metrics['views'] ?? 0,
                'accounts_engaged' => $metrics['accounts_engaged'] ?? 0,
                'total_interactions' => $metrics['total_interactions'] ?? 0,
                'likes' => $metrics['likes'] ?? 0,
                'comments' => $metrics['comments'] ?? 0,
                'shares' => $metrics['shares'] ?? 0,
                'saves' => $metrics['saves'] ?? 0,
                'replies' => $metrics['replies'] ?? 0,
                'profile_views' => $metrics['profile_views'] ?? 0,
                'website_clicks' => $metrics['website_clicks'] ?? 0,
                'synced_at' => now(),
            ];

            // Only set follower fields for dates where the API actually returned data.
            // Dates outside the 30-day window are left untouched in the DB.
            $hasFollowerForDate = isset($dailyFollowerChanges[$date]);

            if ($hasFollowerForDate) {
                $newFollowersValue = $dailyFollowerChanges[$date];

                // Don't overwrite a real non-zero value with 0 from an unfinalized API response.
                // Meta often returns 0 for the most recent 1-2 days before data is settled.
                if ($newFollowersValue === 0) {
                    $existing = MetaIgInsight::where('ig_account_id', $igAccountId)
                        ->where('date', $date)
                        ->first();
                    if ($existing && $existing->new_followers > 0) {
                        // Keep existing non-zero value — skip overwriting with 0
                        Log::debug("IG follower_count: skipping zero overwrite for {$date} (existing={$existing->new_followers})");
                    } else {
                        $upsertData['follower_count'] = $followerTotals[$date] ?? $currentFollowers;
                        $upsertData['new_followers'] = $newFollowersValue;
                    }
                } else {
                    $upsertData['follower_count'] = $followerTotals[$date] ?? $currentFollowers;
                    $upsertData['new_followers'] = $newFollowersValue;
                }
            } elseif ($currentFollowers > 0) {
                // No follower change for this date — only set follower_count
                // if the row doesn't already have a real value.
                $existing = MetaIgInsight::where('ig_account_id', $igAccountId)
                    ->where('date', $date)
                    ->first();
                if (!$existing || $existing->follower_count <= 0) {
                    $upsertData['follower_count'] = $currentFollowers;
                }
                // Don't touch new_followers — keep existing DB value
            }

            MetaIgInsight::updateOrCreate(
                ['ig_account_id' => $igAccountId, 'date' => $date],
                $upsertData
            );
            $count++;
        }

        return $count;
    }

    /**
     * Try to discover the IG Business Account ID from the linked FB Page.
     */
    private function discoverIgAccountId(): ?string
    {
        try {
            $pageId = config('meta.page_id');
            if (!$pageId || !config('meta.page_token')) {
                return null;
            }

            $result = $this->api->getWithPageToken($pageId, [
                'fields' => 'instagram_business_account{id,username}',
            ]);

            $igId = $result['instagram_business_account']['id'] ?? null;
            if ($igId) {
                Log::info("Auto-discovered IG Business Account: {$igId} (@" . ($result['instagram_business_account']['username'] ?? '') . ")");
            }

            return $igId;
        } catch (Exception $e) {
            Log::debug("Could not auto-discover IG account: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Enumerate Facebook Reels via /{page-id}/video_reels and sum
     * blue_reels_play_count per reel, grouped by created_time date.
     *
     * Returns array keyed by date => total plays for that day.
     */
    private function syncReelsPlays(string $pageId, string $dateFrom, string $dateTo): array
    {
        $playsByDate = [];

        try {
            // Paginate manually with small limit (25) to avoid "reduce data" errors.
            // Reels are returned newest-first; stop when we pass dateFrom.
            $reelsInRange = [];
            $response = $this->api->getWithPageToken("{$pageId}/video_reels", [
                'fields' => 'id,created_time',
                'limit' => 25,
            ]);
            $pastRange = false;
            $maxPages = 30;
            $page = 0;

            while (!$pastRange && $page < $maxPages) {
                foreach ($response['data'] ?? [] as $reel) {
                    $created = $reel['created_time'] ?? null;
                    if (!$created) continue;

                    $date = Carbon::parse($created)->toDateString();

                    if ($date < $dateFrom) {
                        $pastRange = true;
                        break;
                    }
                    if ($date <= $dateTo) {
                        $reelsInRange[] = ['id' => $reel['id'], 'date' => $date];
                    }
                }

                // Follow pagination if more pages exist
                $nextCursor = $response['paging']['cursors']['after'] ?? null;
                if (!$nextCursor || $pastRange) break;

                $page++;
                $response = $this->api->getWithPageToken("{$pageId}/video_reels", [
                    'fields' => 'id,created_time',
                    'limit' => 25,
                    'after' => $nextCursor,
                ]);
            }

            Log::info("Found " . count($reelsInRange) . " reels in [{$dateFrom}, {$dateTo}]");

            // Fetch blue_reels_play_count for each reel
            foreach ($reelsInRange as $reel) {
                try {
                    $insights = $this->api->getWithPageToken(
                        $reel['id'] . '/video_insights',
                        ['metric' => 'blue_reels_play_count']
                    );

                    $plays = 0;
                    foreach ($insights['data'] ?? [] as $metric) {
                        $plays = (int) ($metric['values'][0]['value'] ?? 0);
                    }

                    $playsByDate[$reel['date']] = ($playsByDate[$reel['date']] ?? 0) + $plays;
                } catch (Exception $e) {
                    Log::debug("Could not fetch plays for reel {$reel['id']}: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to sync reels plays: " . $e->getMessage());
        }

        return $playsByDate;
    }
}
