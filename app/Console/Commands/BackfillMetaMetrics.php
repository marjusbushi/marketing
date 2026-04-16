<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Meta\MetaPageSyncService;
use Carbon\Carbon;

class BackfillMetaMetrics extends Command
{
    protected $signature = 'meta:backfill-metrics';
    protected $description = 'Backfill missing page metrics (page_posts_impressions, page_messages_new_threads) for the last 45 days';

    public function handle(MetaPageSyncService $syncService)
    {
        $this->info("Backfilling metrics for the last 45 days...");
        $dateFrom = Carbon::now()->subDays(45)->toDateString();
        $dateTo = Carbon::now()->toDateString();
        
        $count = $syncService->syncPageInsights($dateFrom, $dateTo);
        
        $this->info("Successfully processed {$count} daily records for the page insights.");
    }
}
