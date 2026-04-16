<?php

namespace App\Jobs;

use App\Http\Middleware\MetaMarketingCache;
use App\Models\Meta\MetaMessagingStat;
use App\Services\Meta\MetaApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background gap-fill for messaging stats.
 *
 * Uses the fast 3-page-capped conversations fetch (same as live API mode)
 * instead of the full sync service which paginates ALL conversations.
 * Typically completes in 5-15s instead of 60-120s+.
 */
class MetaMessagingGapFillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        protected string $platform,
        protected string $from,
        protected string $to,
    ) {}

    public function handle(MetaApiService $api): void
    {
        $startTime = microtime(true);
        Log::info("MetaMessagingGapFillJob: {$this->platform} [{$this->from}..{$this->to}]");

        $oldestAllowed = Carbon::today()->subDays(60)->toDateString();
        $effectiveFrom = max($this->from, $oldestAllowed);

        if ($effectiveFrom > $this->to) {
            Log::info("MetaMessagingGapFillJob: range too old (>60 days), skipping");
            return;
        }

        $pageId = (string) config('meta.page_id', '');
        if (! $pageId || ! config('meta.page_token')) {
            Log::info('MetaMessagingGapFillJob: no page_id or page_token configured');
            return;
        }

        // Build daily buckets for the full range
        $daily = [];
        for ($d = Carbon::parse($effectiveFrom)->copy(); $d->lte(Carbon::parse($this->to)); $d->addDay()) {
            $daily[$d->toDateString()] = [
                'new_conversations' => 0,
                'total_messages_received' => 0,
                'total_messages_sent' => 0,
            ];
        }

        $isInstagram = $this->platform === 'instagram';

        try {
            // Fast fetch: 3 pages max (~1500 conversations), same as live API mode
            $untilExclusive = Carbon::parse($this->to)->addDay()->toDateString();
            $all = $api->getConversations($pageId, $this->platform, $effectiveFrom, $untilExclusive, 3);

            $fromStart = Carbon::parse($effectiveFrom)->startOfDay();
            $toEnd = Carbon::parse($this->to)->endOfDay();

            foreach ($all as $conversation) {
                $updated = $conversation['updated_time'] ?? null;
                if (! $updated) {
                    continue;
                }

                $updatedAt = Carbon::parse($updated);
                if ($updatedAt->lt($fromStart) || $updatedAt->gt($toEnd)) {
                    continue;
                }

                $date = $updatedAt->toDateString();
                if (! isset($daily[$date])) {
                    continue;
                }

                $daily[$date]['new_conversations']++;
                $messageCount = $isInstagram ? 1 : max(1, (int) ($conversation['message_count'] ?? 1));
                $daily[$date]['total_messages_received'] += $messageCount;
            }

            Log::info("MetaMessagingGapFillJob: fetched " . count($all) . " conversations for {$this->platform}");
        } catch (Throwable $e) {
            Log::warning("MetaMessagingGapFillJob: {$this->platform} fetch failed: {$e->getMessage()}");
            return;
        }

        // Upsert into meta_messaging_stats
        $upserted = 0;
        foreach ($daily as $date => $counts) {
            MetaMessagingStat::updateOrCreate(
                ['platform' => $this->platform, 'date' => $date],
                [
                    'new_conversations' => $counts['new_conversations'],
                    'total_messages_received' => $counts['total_messages_received'],
                    'total_messages_sent' => $counts['total_messages_sent'],
                    'synced_at' => now(),
                ]
            );
            $upserted++;
        }

        // Bust cache so next page load picks up the new data
        MetaMarketingCache::bustCache();

        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info("MetaMessagingGapFillJob: {$this->platform} DONE — {$upserted} rows upserted in {$elapsed}s");
    }
}
