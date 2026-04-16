<?php

namespace App\Services\Tiktok;

use App\Models\Meta\MetaPeriodTotal;
use App\Models\TikTok\TikTokAdsInsight;
use App\Models\TikTok\TikTokCampaign;
use Exception;
use Illuminate\Support\Facades\Log;

class TiktokAdsSyncService
{
    /**
     * Metrics to fetch for BASIC daily reports.
     */
    private const DAILY_METRICS = [
        'spend',
        'impressions',
        'reach',
        'clicks',
        'ctr',
        'cpc',
        'cpm',
        'video_play_actions',
        'video_watched_2s',
        'video_watched_6s',
        'video_views_p25',
        'video_views_p50',
        'video_views_p75',
        'video_views_p100',
        'average_video_play',
        'likes',
        'comments',
        'shares',
        'follows',
        'profile_visits_rate',
        'conversion',
        'cost_per_conversion',
        'total_purchase',
        'total_purchase_value',
        'total_add_to_cart',
        'total_initiate_checkout',
        'total_registration',
        'total_landing_page_view',
    ];

    /**
     * Metrics for AUDIENCE breakdown reports.
     */
    private const AUDIENCE_METRICS = [
        'spend',
        'impressions',
        'reach',
        'clicks',
        'conversion',
    ];

    public function __construct(private readonly TiktokAdsApiService $api)
    {
    }

    // ─── Campaign sync ────────────────────────────────────

    public function syncCampaigns(): int
    {
        $page = 1;
        $total = 0;

        do {
            $result = $this->api->getCampaigns($page);
            $campaigns = $result['data']['list'] ?? [];

            foreach ($campaigns as $c) {
                TikTokCampaign::updateOrCreate(
                    ['campaign_id' => $c['campaign_id']],
                    [
                        'advertiser_id' => $this->api->getAdvertiserId(),
                        'name' => $c['campaign_name'] ?? 'Unnamed',
                        'objective' => $c['objective_type'] ?? null,
                        'status' => $c['status'] ?? 'UNKNOWN',
                        'budget' => $c['budget'] ?? null,
                        'budget_mode' => $c['budget_mode'] ?? null,
                    ]
                );
                $total++;
            }

            $totalPages = $result['data']['page_info']['total_page'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        Log::info("TikTok: synced {$total} campaigns.");

        return $total;
    }

    // ─── Daily insights sync ──────────────────────────────

    /**
     * Sync daily ads insights. Chunks into 30-day windows.
     */
    public function syncInsights(string $from, string $to): int
    {
        $chunks = TiktokAdsApiService::chunkDateRange($from, $to, config('tiktok.max_daily_range', 30));
        $total = 0;

        foreach ($chunks as $chunk) {
            $total += $this->syncInsightsChunk($chunk['from'], $chunk['to']);

            if (count($chunks) > 1) {
                sleep(config('tiktok.pause_between_batches', 3));
            }
        }

        Log::info("TikTok: synced {$total} daily insight rows for {$from} to {$to}.");

        return $total;
    }

    private function syncInsightsChunk(string $from, string $to): int
    {
        $rows = $this->api->getReportAllPages([
            'report_type' => 'BASIC',
            'data_level' => 'AUCTION_CAMPAIGN',
            'dimensions' => ['campaign_id', 'stat_time_day'],
            'metrics' => self::DAILY_METRICS,
            'start_date' => $from,
            'end_date' => $to,
            'page_size' => 1000,
        ]);

        $count = 0;

        foreach ($rows as $row) {
            $dims = $row['dimensions'] ?? [];
            $metrics = $row['metrics'] ?? [];
            $date = substr($dims['stat_time_day'] ?? '', 0, 10); // "2026-03-01 00:00:00" → "2026-03-01"

            if (! $date) {
                continue;
            }

            // Resolve campaign FK
            $campaign = TikTokCampaign::where('campaign_id', $dims['campaign_id'] ?? '')->first();

            TikTokAdsInsight::updateOrCreate(
                [
                    'advertiser_id' => $this->api->getAdvertiserId(),
                    'date' => $date,
                    'tiktok_campaign_id' => $campaign?->id,
                ],
                [
                    'spend' => $this->num($metrics, 'spend'),
                    'impressions' => $this->int($metrics, 'impressions'),
                    'reach' => $this->int($metrics, 'reach'),
                    'clicks' => $this->int($metrics, 'clicks'),
                    'video_views' => $this->int($metrics, 'video_play_actions'),
                    'video_watched_2s' => $this->int($metrics, 'video_watched_2s'),
                    'video_watched_6s' => $this->int($metrics, 'video_watched_6s'),
                    'video_views_p25' => $this->int($metrics, 'video_views_p25'),
                    'video_views_p50' => $this->int($metrics, 'video_views_p50'),
                    'video_views_p75' => $this->int($metrics, 'video_views_p75'),
                    'video_views_p100' => $this->int($metrics, 'video_views_p100'),
                    'average_video_play' => $this->int($metrics, 'average_video_play'),
                    'likes' => $this->int($metrics, 'likes'),
                    'comments' => $this->int($metrics, 'comments'),
                    'shares' => $this->int($metrics, 'shares'),
                    'follows' => $this->int($metrics, 'follows'),
                    'profile_visits' => 0, // Not directly available in this report
                    'conversions' => $this->int($metrics, 'conversion'),
                    'cost_per_conversion' => $this->num($metrics, 'cost_per_conversion'),
                    'purchases' => $this->int($metrics, 'total_purchase'),
                    'purchase_value' => $this->num($metrics, 'total_purchase_value'),
                    'add_to_cart' => $this->int($metrics, 'total_add_to_cart'),
                    'initiate_checkout' => $this->int($metrics, 'total_initiate_checkout'),
                    'registrations' => $this->int($metrics, 'total_registration'),
                    'landing_page_views' => $this->int($metrics, 'total_landing_page_view'),
                    'synced_at' => now(),
                ]
            );

            $count++;
        }

        return $count;
    }

    // ─── Audience breakdowns sync ─────────────────────────

    public function syncBreakdowns(string $from, string $to): int
    {
        $chunks = TiktokAdsApiService::chunkDateRange($from, $to, config('tiktok.max_daily_range', 30));
        $total = 0;

        foreach ($chunks as $chunk) {
            $total += $this->syncBreakdownsChunk($chunk['from'], $chunk['to']);
        }

        return $total;
    }

    private function syncBreakdownsChunk(string $from, string $to): int
    {
        $breakdowns = ['age', 'gender', 'platform'];
        $updated = 0;

        foreach ($breakdowns as $dimension) {
            try {
                $rows = $this->api->getReportAllPages([
                    'report_type' => 'AUDIENCE',
                    'data_level' => 'AUCTION_ADVERTISER',
                    'dimensions' => [$dimension, 'stat_time_day'],
                    'metrics' => self::AUDIENCE_METRICS,
                    'start_date' => $from,
                    'end_date' => $to,
                    'page_size' => 1000,
                ]);

                // Group by date
                $byDate = [];
                foreach ($rows as $row) {
                    $dims = $row['dimensions'] ?? [];
                    $metrics = $row['metrics'] ?? [];
                    $date = substr($dims['stat_time_day'] ?? '', 0, 10);
                    $value = $dims[$dimension] ?? 'unknown';

                    if (! $date) {
                        continue;
                    }

                    $byDate[$date][$value] = [
                        'spend' => $this->num($metrics, 'spend'),
                        'impressions' => $this->int($metrics, 'impressions'),
                        'reach' => $this->int($metrics, 'reach'),
                        'clicks' => $this->int($metrics, 'clicks'),
                        'conversions' => $this->int($metrics, 'conversion'),
                    ];
                }

                // Update insight rows with breakdown JSON
                $column = "{$dimension}_breakdown";
                foreach ($byDate as $date => $breakdown) {
                    TikTokAdsInsight::where('advertiser_id', $this->api->getAdvertiserId())
                        ->where('date', $date)
                        ->update([$column => json_encode($breakdown)]);
                    $updated++;
                }

                sleep(config('tiktok.pause_between_batches', 3));
            } catch (Exception $e) {
                Log::warning("TikTok breakdown sync failed for {$dimension}: {$e->getMessage()}");
            }
        }

        return $updated;
    }

    // ─── Period totals (for KPI cards) ────────────────────

    /**
     * Fetch period-level totals (no time dimension = de-duplicated reach).
     * Used for KPI cards on current period.
     */
    public function fetchPeriodTotals(string $from, string $to): array
    {
        $result = $this->api->getReport([
            'report_type' => 'BASIC',
            'data_level' => 'AUCTION_ADVERTISER',
            'dimensions' => ['advertiser_id'],
            'metrics' => self::DAILY_METRICS,
            'start_date' => $from,
            'end_date' => $to,
            'page_size' => 10,
        ]);

        $row = $result['data']['list'][0] ?? null;

        if (! $row) {
            return $this->emptyTotals();
        }

        $m = $row['metrics'] ?? [];

        return [
            'spend' => $this->num($m, 'spend'),
            'impressions' => $this->int($m, 'impressions'),
            'reach' => $this->int($m, 'reach'),
            'clicks' => $this->int($m, 'clicks'),
            'video_views' => $this->int($m, 'video_play_actions'),
            'video_watched_2s' => $this->int($m, 'video_watched_2s'),
            'video_watched_6s' => $this->int($m, 'video_watched_6s'),
            'likes' => $this->int($m, 'likes'),
            'comments' => $this->int($m, 'comments'),
            'shares' => $this->int($m, 'shares'),
            'follows' => $this->int($m, 'follows'),
            'conversions' => $this->int($m, 'conversion'),
            'cost_per_conversion' => $this->num($m, 'cost_per_conversion'),
            'purchases' => $this->int($m, 'total_purchase'),
            'purchase_value' => $this->num($m, 'total_purchase_value'),
            'add_to_cart' => $this->int($m, 'total_add_to_cart'),
            'initiate_checkout' => $this->int($m, 'total_initiate_checkout'),
        ];
    }

    /**
     * Fetch period totals and store in meta_period_totals for YoY caching.
     */
    public function syncPeriodTotals(string $from, string $to): array
    {
        $totals = $this->fetchPeriodTotals($from, $to);

        MetaPeriodTotal::storeTotals('tiktok', $from, $to, $totals);

        return $totals;
    }

    public static function emptyTotals(): array
    {
        return [
            'spend' => 0.0,
            'impressions' => 0,
            'reach' => 0,
            'clicks' => 0,
            'video_views' => 0,
            'video_watched_2s' => 0,
            'video_watched_6s' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'follows' => 0,
            'conversions' => 0,
            'cost_per_conversion' => 0.0,
            'purchases' => 0,
            'purchase_value' => 0.0,
            'add_to_cart' => 0,
            'initiate_checkout' => 0,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────

    private function num(array $metrics, string $key): float
    {
        return (float) ($metrics[$key] ?? 0);
    }

    private function int(array $metrics, string $key): int
    {
        return (int) ($metrics[$key] ?? 0);
    }
}
