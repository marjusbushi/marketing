<?php

namespace App\Console\Commands;

use App\Http\Middleware\MetaMarketingCache;
use App\Services\Meta\MetaSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MetaSyncCommand extends Command
{
    protected $signature = 'meta:sync
        {--type= : Sync specific type only (ads, ads-platform, page, ig, posts, messaging)}
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d)}
        {--full : Run full initial sync (1 year history)}';

    protected $description = 'Sync data from Meta (Facebook/Instagram) APIs';

    public function handle(MetaSyncService $syncService): int
    {
        // Clear config cache to ensure fresh .env values are loaded
        // This fixes "Provide valid app ID" errors on longer date ranges
        Artisan::call('config:clear');

        $type = $this->option('type');
        $dateFrom = $this->option('from');
        $dateTo = $this->option('to');
        $isFull = $this->option('full');

        // Validate token is configured
        if (!config('meta.token')) {
            $this->error('META_SYSTEM_USER_TOKEN is not configured. Please set it in .env');
            return self::FAILURE;
        }

        $this->info('Starting Meta sync...');
        $startTime = microtime(true);

        try {
            if ($type) {
                // Sync specific type
                $from = $dateFrom ?? Carbon::now()->subDays(config('meta.daily_resync_days'))->toDateString();
                $to = $dateTo ?? Carbon::yesterday()->toDateString();

                // `--full` combined with `--type=posts` switches the posts sync
                // from incremental (30 days) to full-history backfill. Without
                // it the hourly scheduler stays fast and incremental.
                $syncMode = $isFull ? 'full' : 'daily';
                $this->info("Syncing [{$type}] ({$syncMode}) from {$from} to {$to}");
                $results = $syncService->syncType($type, $from, $to, $syncMode);
            } elseif ($isFull) {
                // Full initial sync
                $this->warn('Running FULL initial sync (this may take a while)...');
                $results = $syncService->syncFull();
            } elseif ($dateFrom && $dateTo) {
                // Manual date range
                $this->info("Manual sync from {$dateFrom} to {$dateTo}");
                $results = $syncService->syncManual($dateFrom, $dateTo);
            } else {
                // Default daily sync
                $days = config('meta.daily_resync_days', 7);
                $this->info("Daily sync (last {$days} days)");
                $results = $syncService->syncDaily();
            }

            // Output results
            $this->newLine();
            $this->info('Sync Results:');
            $this->table(
                ['Data Type', 'Status', 'Records'],
                collect($results)->map(fn($r, $key) => [
                    $key,
                    $r['status'] === 'success' ? '<fg=green>✓ Success</>' : '<fg=red>✗ Failed</>',
                    $r['records'],
                ])->toArray()
            );

            $totalRecords = collect($results)->sum('records');
            $duration = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("Total: {$totalRecords} records synced in {$duration}s");

            // Bust dashboard caches so fresh data is served on next load
            MetaMarketingCache::bustCache();
            $this->info('Dashboard caches invalidated.');

            // Check for any failures
            $failures = collect($results)->where('status', 'failed');
            if ($failures->isNotEmpty()) {
                $this->warn('Some sync types failed:');
                foreach ($failures as $key => $result) {
                    $this->error("  [{$key}]: {$result['error']}");
                }
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Meta sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
