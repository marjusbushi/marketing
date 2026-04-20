<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaSyncLog;
// use App\Services\MattermostService; // TODO: port or remove
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MetaSyncService
{
    public function __construct(
        private readonly MetaApiService $api,
        private readonly MetaAdsSyncService $adsSyncService,
        private readonly MetaPageSyncService $pageSyncService,
        private readonly MetaPostSyncService $postSyncService,
        private readonly MetaMessagingSyncService $messagingSyncService,
    ) {}

    /**
     * Run a full daily sync.
     */
    public function syncDaily(): array
    {
        $resyncDays = config('meta.daily_resync_days', 7);
        $dateFrom = Carbon::now()->subDays($resyncDays)->toDateString();
        $dateTo = Carbon::yesterday()->toDateString();

        return $this->sync('daily', $dateFrom, $dateTo);
    }

    /**
     * Run initial full sync (1 year history).
     */
    public function syncFull(): array
    {
        $historyDays = config('meta.initial_history_days', 365);
        $dateFrom = Carbon::now()->subDays($historyDays)->toDateString();
        $dateTo = Carbon::yesterday()->toDateString();

        return $this->sync('full', $dateFrom, $dateTo);
    }

    /**
     * Run a manual sync for a specific date range.
     */
    public function syncManual(string $dateFrom, string $dateTo): array
    {
        return $this->sync('manual', $dateFrom, $dateTo);
    }

    /**
     * Run sync for a specific data type only.
     *
     * $syncMode controls incremental vs full-history behaviour for posts:
     *   - 'daily'  — 30-day window, fast; used by the hourly scheduler
     *   - 'manual' / 'full' — entire history; used by the manual UI trigger
     */
    public function syncType(string $type, string $dateFrom, string $dateTo, string $syncMode = 'manual'): array
    {
        $results = [];

        match ($type) {
            'ads' => $results['ads'] = $this->syncAds($dateFrom, $dateTo, $syncMode),
            'ads-platform', 'ads_platform' => $results['ads-platform'] = $this->syncAdsPlatformBreakdown($dateFrom, $dateTo, $syncMode),
            'page' => $results['page'] = $this->syncPageInsights($dateFrom, $dateTo, $syncMode),
            'ig' => $results['ig'] = $this->syncIgInsights($dateFrom, $dateTo, $syncMode),
            'posts' => $results['posts'] = $this->syncPosts($syncMode),
            'messaging' => $results['messaging'] = $this->syncMessaging($dateFrom, $dateTo, $syncMode),
            default => throw new Exception("Unknown sync type: {$type}"),
        };

        return $results;
    }

    /**
     * Main sync orchestrator.
     */
    private function sync(string $syncType, string $dateFrom, string $dateTo): array
    {
        $results = [];
        $overallSuccess = true;
        $errors = [];

        Log::info("Meta sync started [{$syncType}]: {$dateFrom} to {$dateTo}");

        // a. Ads Sync
        $results['ads'] = $this->syncAds($dateFrom, $dateTo, $syncType);
        if ($results['ads']['status'] === 'failed') {
            $overallSuccess = false;
            $errors[] = 'Ads: ' . $results['ads']['error'];
        }

        // b. FB Page Insights
        $results['page'] = $this->syncPageInsights($dateFrom, $dateTo, $syncType);
        if ($results['page']['status'] === 'failed') {
            $overallSuccess = false;
            $errors[] = 'Page: ' . $results['page']['error'];
        }

        // c. IG Insights
        $results['ig'] = $this->syncIgInsights($dateFrom, $dateTo, $syncType);
        if ($results['ig']['status'] === 'failed') {
            $overallSuccess = false;
            $errors[] = 'IG: ' . $results['ig']['error'];
        }

        // d. Posts (FB + IG)
        $results['posts'] = $this->syncPosts($syncType);
        if ($results['posts']['status'] === 'failed') {
            $overallSuccess = false;
            $errors[] = 'Posts: ' . $results['posts']['error'];
        }

        // e. Messaging (Messenger + IG DMs)
        $results['messaging'] = $this->syncMessaging($dateFrom, $dateTo, $syncType);
        if ($results['messaging']['status'] === 'failed') {
            $overallSuccess = false;
            $errors[] = 'Messaging: ' . $results['messaging']['error'];
        }

        // Notify on failure
        if (!$overallSuccess) {
            $this->notifyFailure($syncType, $errors);
        }

        $totalRecords = collect($results)->sum('records');
        Log::info("Meta sync completed [{$syncType}]: {$totalRecords} records. API calls: {$this->api->getApiCallsCount()}");

        return $results;
    }

    /**
     * Sync Ads data (accounts, campaigns, ad sets, insights).
     */
    private function syncAds(string $dateFrom, string $dateTo, string $syncType = 'daily'): array
    {
        $log = MetaSyncLog::start($syncType, 'ads', $dateFrom, $dateTo);
        $this->api->resetApiCallsCount();

        try {
            $adAccount = $this->adsSyncService->syncAdAccount();
            $campaignCount = $this->adsSyncService->syncCampaigns($adAccount);
            $adSetCount = $this->adsSyncService->syncAdSets();

            // Sync insights in batches of 30 days
            $insightCount = $this->syncInBatches($dateFrom, $dateTo, function ($batchFrom, $batchTo) {
                return $this->adsSyncService->syncInsights($batchFrom, $batchTo);
            });

            // Sync de-duplicated period reach (no time_increment, full period)
            $this->adsSyncService->syncPeriodReach($dateFrom, $dateTo);

            $totalRecords = $campaignCount + $adSetCount + $insightCount;
            $log->markSuccess($totalRecords, $this->api->getApiCallsCount());

            return ['status' => 'success', 'records' => $totalRecords, 'error' => null];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('Meta Ads sync failed: ' . $e->getMessage());

            return ['status' => 'failed', 'records' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync only ads platform breakdown payload for existing ads insight rows.
     */
    private function syncAdsPlatformBreakdown(string $dateFrom, string $dateTo, string $syncType = 'manual'): array
    {
        $log = MetaSyncLog::start($syncType, 'ads-platform', $dateFrom, $dateTo);
        $this->api->resetApiCallsCount();

        try {
            $updatedCount = $this->syncInBatches($dateFrom, $dateTo, function ($batchFrom, $batchTo) {
                return $this->adsSyncService->syncPlatformBreakdownOnly($batchFrom, $batchTo);
            });

            $log->markSuccess($updatedCount, $this->api->getApiCallsCount());

            return ['status' => 'success', 'records' => $updatedCount, 'error' => null];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('Meta Ads platform breakdown sync failed: ' . $e->getMessage());

            return ['status' => 'failed', 'records' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync FB Page Insights.
     */
    private function syncPageInsights(string $dateFrom, string $dateTo, string $syncType = 'daily'): array
    {
        $log = MetaSyncLog::start($syncType, 'page', $dateFrom, $dateTo);
        $this->api->resetApiCallsCount();

        try {
            $count = $this->pageSyncService->syncPageInsights($dateFrom, $dateTo);
            $log->markSuccess($count, $this->api->getApiCallsCount());

            return ['status' => 'success', 'records' => $count, 'error' => null];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('Meta Page sync failed: ' . $e->getMessage());

            return ['status' => 'failed', 'records' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync Instagram Insights.
     */
    private function syncIgInsights(string $dateFrom, string $dateTo, string $syncType = 'daily'): array
    {
        $log = MetaSyncLog::start($syncType, 'ig', $dateFrom, $dateTo);
        $this->api->resetApiCallsCount();

        try {
            $count = $this->pageSyncService->syncIgInsights($dateFrom, $dateTo);
            $log->markSuccess($count, $this->api->getApiCallsCount());

            return ['status' => 'success', 'records' => $count, 'error' => null];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('Meta IG sync failed: ' . $e->getMessage());

            return ['status' => 'failed', 'records' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync FB + IG Posts.
     *
     * `manual` and `full` both pull the entire available history (no since-cutoff,
     * up to 100 pages per source) so the planner grid can show every historic
     * post. The hourly `daily` cron keeps the 30-day window for incremental
     * freshness without hammering the Graph API.
     */
    private function syncPosts(string $syncType = 'daily'): array
    {
        $log = MetaSyncLog::start($syncType, 'posts');
        $this->api->resetApiCallsCount();

        $fullHistory = in_array($syncType, ['manual', 'full', 'backfill'], true);
        $sinceDays = $fullHistory ? null : 30;
        $maxPages  = $fullHistory ? 100 : 20;

        try {
            $fbCount = $this->postSyncService->syncFacebookPosts($sinceDays, $maxPages);
            $igCount = $this->postSyncService->syncInstagramPosts($sinceDays, $maxPages);

            $totalCount = $fbCount + $igCount;
            $log->markSuccess($totalCount, $this->api->getApiCallsCount());

            return ['status' => 'success', 'records' => $totalCount, 'error' => null];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('Meta Posts sync failed: ' . $e->getMessage());

            return ['status' => 'failed', 'records' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync Messenger + IG DMs.
     */
    private function syncMessaging(string $dateFrom, string $dateTo, string $syncType = 'daily'): array
    {
        $log = MetaSyncLog::start($syncType, 'messaging', $dateFrom, $dateTo);
        $this->api->resetApiCallsCount();

        try {
            $messengerCount = $this->messagingSyncService->syncMessengerStats($dateFrom, $dateTo);
            $igDmCount = $this->messagingSyncService->syncIgDmStats($dateFrom, $dateTo);

            $totalCount = $messengerCount + $igDmCount;
            $log->markSuccess($totalCount, $this->api->getApiCallsCount());

            return ['status' => 'success', 'records' => $totalCount, 'error' => null];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('Meta Messaging sync failed: ' . $e->getMessage());

            return ['status' => 'failed', 'records' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process a date range in batches of configurable days.
     */
    private function syncInBatches(string $dateFrom, string $dateTo, callable $callback): int
    {
        $batchSize = config('meta.batch_size_days', 30);
        $pauseBetween = config('meta.pause_between_batches', 5);
        $totalCount = 0;

        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);

        while ($from->lte($to)) {
            $batchTo = $from->copy()->addDays($batchSize - 1);
            if ($batchTo->gt($to)) {
                $batchTo = $to->copy();
            }

            $count = $callback($from->toDateString(), $batchTo->toDateString());
            $totalCount += $count;

            $from->addDays($batchSize);

            // Pause between batches to respect rate limits
            if ($from->lte($to)) {
                sleep($pauseBetween);
            }
        }

        return $totalCount;
    }

    /**
     * Send failure notification via Mattermost.
     */
    private function notifyFailure(string $syncType, array $errors): void
    {
        try {
            $message = "**Meta Sync Failed** [{$syncType}]\n\n";
            $message .= "Errors:\n";
            foreach ($errors as $error) {
                $message .= "- {$error}\n";
            }

            if (class_exists(MattermostService::class)) {
                app(MattermostService::class)->send($message);
            }
        } catch (Exception $e) {
            Log::error('Failed to send Mattermost notification: ' . $e->getMessage());
        }
    }
}
