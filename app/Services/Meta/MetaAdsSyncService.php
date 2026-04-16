<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaAdAccount;
use App\Models\Meta\MetaAdSet;
use App\Models\Meta\MetaAdsInsight;
use App\Models\Meta\MetaAdsPeriodReach;
use App\Models\Meta\MetaCampaign;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MetaAdsSyncService
{
    private const ATTRIBUTION_PARAMS = [
        // Keep one attribution contract across all ads endpoints for consistency.
        'use_account_attribution_setting' => 'true',
    ];

    public function __construct(
        private readonly MetaApiService $api,
    ) {}

    /**
     * Sync ad account info.
     */
    public function syncAdAccount(): MetaAdAccount
    {
        $accountId = config('meta.ad_account_id');
        $data = $this->api->get($accountId, [
            'fields' => 'account_id,name,currency,timezone_name,account_status',
        ]);

        return MetaAdAccount::updateOrCreate(
            ['account_id' => $data['account_id'] ?? $accountId],
            [
                'name' => $data['name'] ?? 'Unknown',
                'currency' => $data['currency'] ?? 'EUR',
                'timezone' => $data['timezone_name'] ?? 'Europe/Tirane',
                'status' => ($data['account_status'] ?? 1) == 1 ? 'ACTIVE' : 'DISABLED',
            ]
        );
    }

    /**
     * Sync all campaigns for the ad account.
     */
    public function syncCampaigns(MetaAdAccount $adAccount): int
    {
        $accountId = config('meta.ad_account_id');
        $campaigns = $this->api->getPaginated("{$accountId}/campaigns", [
            'fields' => 'id,name,objective,status,daily_budget,lifetime_budget,start_time,stop_time',
        ]);

        $count = 0;
        foreach ($campaigns as $campaign) {
            MetaCampaign::updateOrCreate(
                ['campaign_id' => $campaign['id']],
                [
                    'meta_ad_account_id' => $adAccount->id,
                    'name' => $campaign['name'] ?? '',
                    'objective' => $campaign['objective'] ?? null,
                    'status' => $campaign['status'] ?? 'ACTIVE',
                    'daily_budget' => isset($campaign['daily_budget']) ? $campaign['daily_budget'] / 100 : null,
                    'lifetime_budget' => isset($campaign['lifetime_budget']) ? $campaign['lifetime_budget'] / 100 : null,
                    'start_date' => isset($campaign['start_time']) ? Carbon::parse($campaign['start_time'])->toDateString() : null,
                    'end_date' => isset($campaign['stop_time']) ? Carbon::parse($campaign['stop_time'])->toDateString() : null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync all ad sets for all campaigns.
     */
    public function syncAdSets(): int
    {
        $accountId = config('meta.ad_account_id');
        $adSets = $this->api->getPaginated("{$accountId}/adsets", [
            'fields' => 'id,name,campaign_id,status,daily_budget,targeting,optimization_goal',
        ]);

        $count = 0;
        foreach ($adSets as $adSet) {
            $campaign = MetaCampaign::where('campaign_id', $adSet['campaign_id'] ?? '')->first();
            if (!$campaign) {
                continue;
            }

            MetaAdSet::updateOrCreate(
                ['adset_id' => $adSet['id']],
                [
                    'meta_campaign_id' => $campaign->id,
                    'name' => $adSet['name'] ?? '',
                    'status' => $adSet['status'] ?? 'ACTIVE',
                    'daily_budget' => isset($adSet['daily_budget']) ? $adSet['daily_budget'] / 100 : null,
                    'targeting_summary' => $adSet['targeting'] ?? null,
                    'optimization_goal' => $adSet['optimization_goal'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync ads insights for a date range.
     */
    public function syncInsights(string $dateFrom, string $dateTo): int
    {
        $accountId = config('meta.ad_account_id');
        $count = 0;

        // Get the ad account for creating missing campaigns
        $adAccount = MetaAdAccount::first();
        if (!$adAccount) {
            return 0;
        }

        // Main insights at adset level - include campaign and adset names
        $insights = $this->api->getAdsInsights($accountId, $this->withAttribution([
            'level' => 'adset',
            'time_range' => json_encode(['since' => $dateFrom, 'until' => $dateTo]),
            'time_increment' => 1, // Daily
            'fields' => 'campaign_id,campaign_name,adset_id,adset_name,date_start,impressions,reach,clicks,spend,actions,action_values',
        ]));

        foreach ($insights as $row) {
            // Find or create campaign
            $campaign = MetaCampaign::where('campaign_id', $row['campaign_id'] ?? '')->first();
            if (!$campaign && !empty($row['campaign_id'])) {
                $campaign = MetaCampaign::create([
                    'campaign_id' => $row['campaign_id'],
                    'meta_ad_account_id' => $adAccount->id,
                    'name' => $row['campaign_name'] ?? 'Unknown Campaign',
                    'status' => 'ARCHIVED', // Mark as archived since it's not in active campaigns
                ]);
            }

            // Find or create ad set
            $adSet = MetaAdSet::where('adset_id', $row['adset_id'] ?? '')->first();
            if (!$adSet && !empty($row['adset_id']) && $campaign) {
                $adSet = MetaAdSet::create([
                    'adset_id' => $row['adset_id'],
                    'meta_campaign_id' => $campaign->id,
                    'name' => $row['adset_name'] ?? 'Unknown Ad Set',
                    'status' => 'ARCHIVED',
                ]);
            }

            if (!$campaign || !$adSet) {
                continue;
            }

            $actions = $this->parseActions($row['actions'] ?? []);
            $actionValues = $this->parseActions($row['action_values'] ?? []);

            MetaAdsInsight::updateOrCreate(
                [
                    'meta_ad_set_id' => $adSet->id,
                    'date' => $row['date_start'],
                ],
                [
                    'meta_ad_account_id' => $campaign->adAccount->id ?? $adSet->campaign->adAccount->id,
                    'meta_campaign_id' => $campaign->id,
                    'impressions' => $row['impressions'] ?? 0,
                    'reach' => $row['reach'] ?? 0,
                    'clicks' => $row['clicks'] ?? 0,
                    'spend' => $row['spend'] ?? 0,
                    'post_engagement' => $actions['post_engagement'] ?? 0,
                    'page_engagement' => $actions['page_engagement'] ?? 0,
                    'link_clicks' => $actions['link_click'] ?? 0,
                    'video_views' => $actions['video_view'] ?? 0,
                    'purchases' => $actions['purchase'] ?? ($actions['offsite_conversion.fb_pixel_purchase'] ?? 0),
                    'purchase_value' => $actionValues['purchase'] ?? ($actionValues['offsite_conversion.fb_pixel_purchase'] ?? 0),
                    'add_to_cart' => $actions['add_to_cart'] ?? ($actions['offsite_conversion.fb_pixel_add_to_cart'] ?? 0),
                    'initiate_checkout' => $actions['initiate_checkout'] ?? ($actions['offsite_conversion.fb_pixel_initiate_checkout'] ?? 0),
                    'leads' => $actions['lead'] ?? ($actions['offsite_conversion.fb_pixel_lead'] ?? 0),
                    'messaging_conversations' => $actions['onsite_conversion.total_messaging_connection'] ?? $actions['onsite_conversion.messaging_conversation_started_7d'] ?? 0,
                    'messaging_conversations_replied' => $actions['onsite_conversion.messaging_conversation_replied_7d'] ?? 0,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        // Sync breakdowns separately
        $this->syncBreakdowns($accountId, $dateFrom, $dateTo);

        return $count;
    }

    /**
     * Re-sync only the platform breakdown payload for a date range.
     * Used for targeted backfills without running full ads sync.
     */
    public function syncPlatformBreakdownOnly(string $dateFrom, string $dateTo): int
    {
        $accountId = config('meta.ad_account_id');
        if (!$accountId) {
            return 0;
        }

        return $this->syncBreakdowns($accountId, $dateFrom, $dateTo, ['publisher_platform']);
    }

    /**
     * Sync de-duplicated period reach (no time_increment).
     * Meta de-duplicates reach across the entire period when time_increment is omitted.
     */
    public function syncPeriodReach(string $dateFrom, string $dateTo): void
    {
        $accountId = config('meta.ad_account_id');
        $adAccount = MetaAdAccount::first();
        if (!$adAccount) {
            return;
        }

        try {
            $insights = $this->api->getAdsInsights($accountId, $this->withAttribution([
                'level' => 'account',
                'time_range' => json_encode(['since' => $dateFrom, 'until' => $dateTo]),
                // NO time_increment — gives de-duplicated total for the entire period
                'fields' => 'reach',
            ]));

            $reach = 0;
            if (!empty($insights) && isset($insights[0]['reach'])) {
                $reach = (int) $insights[0]['reach'];
            }

            MetaAdsPeriodReach::updateOrCreate(
                [
                    'meta_ad_account_id' => $adAccount->id,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
                [
                    'reach' => $reach,
                    'synced_at' => now(),
                ]
            );

            Log::info("Synced period reach: {$dateFrom} to {$dateTo} = {$reach}");
        } catch (Exception $e) {
            Log::warning("Failed to sync period reach: " . $e->getMessage());
        }
    }

    /**
     * Sync breakdown data and aggregate into JSON columns.
     */
    private function syncBreakdowns(string $accountId, string $dateFrom, string $dateTo, ?array $onlyBreakdowns = null): int
    {
        $breakdownTypes = [
            'age' => 'age_breakdown',
            'gender' => 'gender_breakdown',
            'publisher_platform' => 'platform_breakdown',
            'platform_position' => 'placement_breakdown',
        ];
        $updatedRows = 0;

        if (!empty($onlyBreakdowns)) {
            $breakdownTypes = array_filter(
                $breakdownTypes,
                fn ($_column, $breakdown) => in_array($breakdown, $onlyBreakdowns, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        foreach ($breakdownTypes as $breakdownField => $column) {
            try {
                $fields = "adset_id,date_start,impressions,reach,clicks,spend";
                
                // Meta API Error 100: Current combination of data breakdown columns (action_type, platform_position) is invalid
                // We must explicitly only ask for actions when the breakdown supports it (like publisher_platform)
                // Other breakdowns like age, gender, and platform_position will fail if actions are requested.
                if ($breakdownField === 'publisher_platform') {
                    $fields .= ',actions,action_values';
                }

                // API Error 100: platform_position cannot be used alone. It requires publisher_platform
                // to be grouped with it to avoid the action_type error clash implicitly triggered by Meta.
                $apiBreakdown = $breakdownField === 'platform_position' 
                    ? 'publisher_platform,platform_position' 
                    : $breakdownField;

                $data = $this->api->getAdsInsights($accountId, $this->withAttribution([
                    'level' => 'adset',
                    'time_range' => json_encode(['since' => $dateFrom, 'until' => $dateTo]),
                    'time_increment' => 1,
                    'breakdowns' => $apiBreakdown,
                    'fields' => $fields,
                ]));

                // Group by adset_id + date
                $grouped = [];
                foreach ($data as $row) {
                    $key = ($row['adset_id'] ?? '') . '_' . ($row['date_start'] ?? '');
                    $rawBreakdown = $row[$breakdownField] ?? 'unknown';
                    $breakdownValue = $breakdownField === 'publisher_platform'
                        ? $this->normalizePlatform($rawBreakdown)
                        : $rawBreakdown;

                    if (!isset($grouped[$key][$breakdownValue])) {
                        $grouped[$key][$breakdownValue] = [
                            'impressions' => 0,
                            'reach' => 0,
                            'clicks' => 0,
                            'spend' => 0.0,
                        ];

                        if ($breakdownField === 'publisher_platform') {
                            $grouped[$key][$breakdownValue] += [
                                'link_clicks' => 0,
                                'purchases' => 0,
                                'purchase_value' => 0.0,
                                'add_to_cart' => 0,
                                'initiate_checkout' => 0,
                                'leads' => 0,
                                'messaging_conversations' => 0,
                                'messaging_conversations_replied' => 0,
                            ];
                        }
                    }

                    $grouped[$key][$breakdownValue]['impressions'] += (int) ($row['impressions'] ?? 0);
                    $grouped[$key][$breakdownValue]['reach'] += (int) ($row['reach'] ?? 0);
                    $grouped[$key][$breakdownValue]['clicks'] += (int) ($row['clicks'] ?? 0);
                    $grouped[$key][$breakdownValue]['spend'] += (float) ($row['spend'] ?? 0);

                    if ($breakdownField === 'publisher_platform') {
                        $actions = $this->parseActions($row['actions'] ?? []);
                        $actionValues = $this->parseActions($row['action_values'] ?? []);

                        $grouped[$key][$breakdownValue]['link_clicks'] +=
                            (float) ($actions['link_click'] ?? 0);
                        $grouped[$key][$breakdownValue]['purchases'] +=
                            (float) ($actions['purchase'] ?? ($actions['offsite_conversion.fb_pixel_purchase'] ?? 0));
                        $grouped[$key][$breakdownValue]['purchase_value'] +=
                            (float) ($actionValues['purchase'] ?? ($actionValues['offsite_conversion.fb_pixel_purchase'] ?? 0));
                        $grouped[$key][$breakdownValue]['add_to_cart'] +=
                            (float) ($actions['add_to_cart'] ?? ($actions['offsite_conversion.fb_pixel_add_to_cart'] ?? 0));
                        $grouped[$key][$breakdownValue]['initiate_checkout'] +=
                            (float) ($actions['initiate_checkout'] ?? ($actions['offsite_conversion.fb_pixel_initiate_checkout'] ?? 0));
                        $grouped[$key][$breakdownValue]['leads'] +=
                            (float) ($actions['lead'] ?? ($actions['offsite_conversion.fb_pixel_lead'] ?? 0));
                        // Prefer total_messaging_connection (broader, matches Meta Business Suite)
                        // over messaging_conversation_started_7d (7-day attribution window only).
                        $grouped[$key][$breakdownValue]['messaging_conversations'] +=
                            (float) ($actions['onsite_conversion.total_messaging_connection'] ?? $actions['onsite_conversion.messaging_conversation_started_7d'] ?? 0);
                        $grouped[$key][$breakdownValue]['messaging_conversations_replied'] +=
                            (float) ($actions['onsite_conversion.messaging_conversation_replied_7d'] ?? 0);
                    }
                }

                // Update insights with breakdown JSON
                foreach ($grouped as $key => $breakdownData) {
                    [$adsetId, $date] = explode('_', $key, 2);
                    $adSet = MetaAdSet::query()->where('adset_id', $adsetId)->first();
                    if (!$adSet) {
                        continue;
                    }

                    $updatedRows += MetaAdsInsight::where('meta_ad_set_id', $adSet->id)
                        ->where('date', $date)
                        ->update([$column => json_encode($breakdownData)]);
                }

                // Pause between breakdown calls to respect rate limits
                sleep(config('meta.pause_between_batches', 5));
            } catch (Exception $e) {
                Log::warning("Failed to sync {$breakdownField} breakdown: " . $e->getMessage());
                // Continue with other breakdowns even if one fails
            }
        }

        return $updatedRows;
    }

    /**
     * Parse Meta actions array into key-value pairs.
     * Input:  [{"action_type": "link_click", "value": "45"}, ...]
     * Output: ["link_click" => 45, ...]
     */
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

    /**
     * Get the attribution params array for external use (e.g. resolver live API calls).
     */
    public function getAttributionParams(): array
    {
        $configured = config('meta.ads_attribution', self::ATTRIBUTION_PARAMS);
        if (isset($configured['use_account_attribution_setting'])) {
            $configured['use_account_attribution_setting'] = $configured['use_account_attribution_setting'] ? 'true' : 'false';
        }
        return $configured;
    }

    private function withAttribution(array $params): array
    {
        return array_merge($this->getAttributionParams(), $params);
    }

    private function normalizePlatform(?string $platform): string
    {
        static $loggedUnknownPlatforms = [];

        $normalized = strtolower((string) ($platform ?? 'unknown'));

        $mapped = match ($normalized) {
            'facebook', 'instagram', 'audience_network', 'messenger', 'threads' => $normalized,
            'facebook_messenger' => 'messenger',
            default => 'unknown',
        };

        if ($mapped === 'unknown' && $normalized !== 'unknown' && !isset($loggedUnknownPlatforms[$normalized])) {
            $loggedUnknownPlatforms[$normalized] = true;
            Log::warning("Unknown publisher_platform received from Meta: {$normalized}");
        }

        return $mapped;
    }
}
