<?php

namespace App\Services\Departments;

use App\Models\Meta\MetaAdsInsight;
use App\Models\Meta\MetaCampaign;
use App\Models\Meta\MetaPostInsight;
// use App\Models\Task; // TODO: marketing tasks model
use App\Models\TikTok\TikTokAdsInsight;
use App\Models\TikTok\TikTokVideo;
use App\Services\Meta\MetaMarketingV2ReportService;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketingKpiService implements DepartmentKpiInterface
{
    public function __construct(
        private readonly MetaMarketingV2ReportService $reportService,
    ) {}

    public function getKpis(string $from, string $to): array
    {
        try {
            $totals = $this->reportService->totalKpis($from, $to);
        } catch (Throwable $e) {
            Log::warning('MarketingKpiService: Failed to fetch totalKpis', ['error' => $e->getMessage()]);
            $totals = [];
        }

        return [
            // ─── Row 1: Money & Performance ───
            [
                'key'    => 'ads_spend',
                'label'  => 'Ad Spend',
                'value'  => $this->formatCurrency($totals['ads_spend']['value'] ?? 0),
                'raw'    => $totals['ads_spend']['value'] ?? 0,
                'change' => $totals['ads_spend']['change'] ?? null,
                'icon'   => 'mdi:cash-multiple',
                'color'  => '#E91E63',
            ],
            [
                'key'    => 'ads_revenue',
                'label'  => 'Ad Revenue',
                'value'  => $this->formatCurrency($totals['ads_revenue']['value'] ?? 0),
                'raw'    => $totals['ads_revenue']['value'] ?? 0,
                'change' => $totals['ads_revenue']['change'] ?? null,
                'icon'   => 'mdi:cash-register',
                'color'  => '#43A047',
            ],
            [
                'key'    => 'roas',
                'label'  => 'ROAS',
                'value'  => number_format($totals['roas']['value'] ?? 0, 2) . 'x',
                'raw'    => $totals['roas']['value'] ?? 0,
                'change' => $totals['roas']['change'] ?? null,
                'icon'   => 'mdi:trending-up',
                'color'  => '#2E7D32',
            ],
            // ─── Row 2: Audience & Visibility ───
            [
                'key'    => 'total_reach',
                'label'  => 'Total Reach',
                'value'  => $this->formatNumber($totals['total_reach']['value'] ?? 0),
                'raw'    => $totals['total_reach']['value'] ?? 0,
                'change' => $totals['total_reach']['change'] ?? null,
                'icon'   => 'mdi:eye-outline',
                'color'  => '#1E88E5',
            ],
            [
                'key'    => 'total_impressions',
                'label'  => 'Impressions',
                'value'  => $this->formatNumber($totals['total_impressions']['value'] ?? 0),
                'raw'    => $totals['total_impressions']['value'] ?? 0,
                'change' => $totals['total_impressions']['change'] ?? null,
                'icon'   => 'mdi:chart-bar',
                'color'  => '#00897B',
            ],
            [
                'key'    => 'total_page_views',
                'label'  => 'Page Views',
                'value'  => $this->formatNumber($totals['total_page_views']['value'] ?? 0),
                'raw'    => $totals['total_page_views']['value'] ?? 0,
                'change' => $totals['total_page_views']['change'] ?? null,
                'icon'   => 'mdi:web',
                'color'  => '#5C6BC0',
            ],
            // ─── Row 3: Engagement & Interaction ───
            [
                'key'    => 'total_engagement',
                'label'  => 'Engagement',
                'value'  => $this->formatNumber($totals['total_engagement']['value'] ?? 0),
                'raw'    => $totals['total_engagement']['value'] ?? 0,
                'change' => $totals['total_engagement']['change'] ?? null,
                'icon'   => 'mdi:heart-outline',
                'color'  => '#FB8C00',
            ],
            [
                'key'    => 'combined_link_clicks',
                'label'  => 'Link Clicks',
                'value'  => $this->formatNumber($totals['combined_link_clicks']['value'] ?? 0),
                'raw'    => $totals['combined_link_clicks']['value'] ?? 0,
                'change' => $totals['combined_link_clicks']['change'] ?? null,
                'icon'   => 'mdi:cursor-default-click-outline',
                'color'  => '#7B1FA2',
            ],
            [
                'key'    => 'new_threads',
                'label'  => 'New Threads',
                'value'  => $this->formatNumber($totals['new_threads']['value'] ?? 0),
                'raw'    => $totals['new_threads']['value'] ?? 0,
                'change' => $totals['new_threads']['change'] ?? null,
                'icon'   => 'mdi:message-outline',
                'color'  => '#0097A7',
            ],
            // ─── Row 4: Platform Breakdown ───
            [
                'key'    => 'fb_reach',
                'label'  => 'Facebook Reach',
                'value'  => $this->formatNumber($totals['fb_reach']['value'] ?? 0),
                'raw'    => $totals['fb_reach']['value'] ?? 0,
                'change' => $totals['fb_reach']['change'] ?? null,
                'icon'   => 'mdi:facebook',
                'color'  => '#1877F2',
            ],
            [
                'key'    => 'ig_reach',
                'label'  => 'Instagram Reach',
                'value'  => $this->formatNumber($totals['ig_reach']['value'] ?? 0),
                'raw'    => $totals['ig_reach']['value'] ?? 0,
                'change' => $totals['ig_reach']['change'] ?? null,
                'icon'   => 'mdi:instagram',
                'color'  => '#E1306C',
            ],
            [
                'key'    => 'conversations',
                'label'  => 'Messages',
                'value'  => $this->formatNumber($totals['conversations']['value'] ?? 0),
                'raw'    => $totals['conversations']['value'] ?? 0,
                'change' => $totals['conversations']['change'] ?? null,
                'icon'   => 'mdi:chat-processing-outline',
                'color'  => '#546E7A',
            ],
        ];
    }

    public function getTaskImpact(Task $task): ?array
    {
        if (! $task->relationLoaded('references')) {
            $task->load('references');
        }

        if ($task->references->isEmpty()) {
            return null;
        }

        $impact = [];

        foreach ($task->references as $ref) {
            $snapshot = $ref->snapshot_data;
            $model = $ref->getReferenceable();

            if (! $model || ! $snapshot) {
                continue;
            }

            $current = match ($ref->reference_type) {
                'meta_post' => [
                    'reach'  => $model->reach,
                    'likes'  => $model->likes,
                    'shares' => $model->shares,
                ],
                'tiktok_video' => [
                    'view_count' => $model->view_count,
                    'like_count' => $model->like_count,
                    'share_count'=> $model->share_count,
                ],
                default => null,
            };

            if ($current) {
                $impact[] = [
                    'type'     => $ref->reference_type,
                    'label'    => $ref->label,
                    'snapshot' => $snapshot,
                    'current'  => $current,
                ];
            }
        }

        return $impact ?: null;
    }

    private function formatNumber(int|float $value): string
    {
        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return number_format($value / 1_000, 1) . 'K';
        }
        return number_format($value);
    }

    private function formatCurrency(float $value): string
    {
        if ($value >= 1_000_000) {
            return '$' . number_format($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return '$' . number_format($value / 1_000, 1) . 'K';
        }
        return '$' . number_format($value, 2);
    }
}
