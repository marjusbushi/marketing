<?php

namespace App\Console\Commands;

use App\Jobs\PublishContentPostJob;
use App\Models\Content\ContentPost;
use Illuminate\Console\Command;

/**
 * Catches scheduled posts whose primary delay-dispatch job never fired —
 * worker died, queue table got truncated, dispatch failed silently, etc.
 *
 * The primary path is dispatch-with-delay from ContentPostService: when a
 * post enters `scheduled`, a PublishContentPostJob is queued with delay
 * equal to scheduled_at. The worker picks it up at the right second.
 *
 * This command is the safety net. It runs every 30 min and asks "is there
 * any post sitting in `scheduled` whose time has clearly passed and which
 * is approved (or doesn't need approval)?". For each it dispatches a fresh
 * job. The job's atomic claim (status='scheduled' AND version=expected →
 * status='publishing') means duplicate dispatches can't double-publish:
 * whichever lands first wins, the others find status='publishing' and exit.
 *
 * 10-minute lag (`scheduled_at <= now - 10min`) gives the primary dispatch
 * a chance to fire under load before we second-guess it.
 *
 * Usage: php artisan content-planner:publish-due-safety-net [--limit=20]
 */
class ContentPlannerPublishDueSafetyNetCommand extends Command
{
    protected $signature = 'content-planner:publish-due-safety-net {--limit=20 : Max posts to dispatch in one pass}';

    protected $description = 'Re-dispatches stuck scheduled Content Planner posts whose delay-dispatch job never fired.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $cutoff = now()->subMinutes(10);

        $stuck = ContentPost::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->where('approval_type', 'none')->orWhereNotNull('approved_at');
            })
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('Safety net: 0 stuck posts.');
            return self::SUCCESS;
        }

        foreach ($stuck as $post) {
            PublishContentPostJob::dispatch($post);
            $this->line("dispatched #{$post->id} (scheduled at {$post->scheduled_at})");
        }

        $count = $stuck->count();
        $this->info("Safety net: re-dispatched {$count} stuck post(s).");

        return self::SUCCESS;
    }
}
