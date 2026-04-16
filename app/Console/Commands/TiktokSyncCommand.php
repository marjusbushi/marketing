<?php

namespace App\Console\Commands;

use App\Models\TikTok\TikTokToken;
use App\Services\Tiktok\TiktokApiService;
use App\Services\Tiktok\TiktokSyncService;
use Illuminate\Console\Command;

class TiktokSyncCommand extends Command
{
    protected $signature = 'tiktok:sync
        {--type= : Sync specific type only (account, videos)}
        {--deltas : Run delta-aware sync with daily change tracking}';

    protected $description = 'Sync data from TikTok API (account info + videos)';

    public function handle(): int
    {
        if (!config('tiktok.features.tiktok_module', false)) {
            $this->warn('TikTok module is disabled. Set TIKTOK_FEATURE_MODULE=true to enable.');
            return self::SUCCESS;
        }

        // Check for active token
        $token = TiktokToken::getActiveToken();
        if (!$token) {
            $this->error('No active TikTok token found. Please authenticate at /management/tiktok-auth first.');
            return self::FAILURE;
        }

        if ($token->isAccessTokenExpired() && $token->isRefreshTokenExpired()) {
            $this->error('TikTok tokens are expired. Please re-authenticate at /management/tiktok-auth');
            return self::FAILURE;
        }

        $useDeltas = $this->option('deltas');
        $this->info('Starting TikTok sync' . ($useDeltas ? ' (with deltas)' : '') . '...');
        $startTime = microtime(true);

        try {
            $api = app(TiktokApiService::class);
            $syncService = new TiktokSyncService($api);
            $type = $this->option('type');

            if ($useDeltas) {
                $this->info('Running delta-aware sync (account + videos with daily tracking)...');
                $results = $syncService->syncWithDeltas();

                // Handle skip case (another sync running)
                if (($results['status'] ?? '') === 'skipped') {
                    $this->warn("Sync skipped: {$results['reason']}");
                    return self::SUCCESS;
                }
            } elseif ($type === 'account') {
                $this->info('Syncing account info...');
                $results = ['account' => $syncService->syncAccount()];
            } elseif ($type === 'videos') {
                $this->info('Syncing videos...');
                $results = ['videos' => $syncService->syncVideos()];
            } else {
                $this->info('Running full sync (account + videos)...');
                $results = $syncService->syncAll();
            }

            // Output results
            $this->newLine();
            $this->info('Sync Results:');

            foreach ($results as $key => $result) {
                if (!is_array($result)) {
                    continue;
                }

                $status = ($result['status'] ?? 'unknown') === 'success'
                    ? '<fg=green>Success</>'
                    : '<fg=red>Failed</>';

                $this->line("  [{$key}] {$status}");

                if (isset($result['account'])) {
                    $this->line("    Account: {$result['account']}");
                }
                if (isset($result['followers'])) {
                    $this->line("    Followers: " . number_format($result['followers']));
                }
                if (isset($result['follower_change']) && $result['follower_change'] !== null) {
                    $sign = $result['follower_change'] >= 0 ? '+' : '';
                    $this->line("    Follower change: {$sign}{$result['follower_change']}");
                }
                if (isset($result['synced'])) {
                    $this->line("    Videos synced: {$result['synced']}");
                }
                if (isset($result['snapshots_created'])) {
                    $this->line("    Video snapshots: {$result['snapshots_created']}");
                }
                if (isset($result['total_views_change'])) {
                    $sign = $result['total_views_change'] >= 0 ? '+' : '';
                    $this->line("    Total views change: {$sign}" . number_format($result['total_views_change']));
                }
                if (isset($result['failed']) && $result['failed'] > 0) {
                    $this->warn("    Videos failed: {$result['failed']}");
                }
                if (isset($result['error'])) {
                    $this->error("    Error: {$result['error']}");
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("Completed in {$duration}s");

            $hasFailures = collect($results)->filter(fn($r) => is_array($r))->contains(fn($r) => ($r['status'] ?? '') === 'failed');
            return $hasFailures ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('TikTok sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
