<?php

namespace App\Jobs;

use App\Http\Middleware\MetaMarketingCache;
use App\Services\Meta\MetaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaYoYDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        protected string $from,
        protected string $to,
    ) {}

    public function handle(MetaSyncService $syncService): void
    {
        Log::info("SyncMetaYoYDataJob: syncing {$this->from} to {$this->to}");

        $results = $syncService->syncManual($this->from, $this->to);
        $totalRecords = collect($results)->sum('records');

        MetaMarketingCache::bustCache();

        Log::info("SyncMetaYoYDataJob: done — {$totalRecords} records synced");
    }
}
