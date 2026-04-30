<?php

namespace App\Jobs;

use App\Models\Content\ContentPost;
use App\Services\ContentPlanner\Publishing\ContentPublishManager;
use App\Services\ContentPlanner\Publishing\MetaErrorSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes a single ContentPost to its target platforms.
 *
 * Concurrency model: dispatched with delay($scheduled_at) when the user
 * schedules a post, plus a safety-net cron that catches any post the
 * primary dispatch missed. Either path can run on multiple workers, so
 * the entry point is an atomic claim that flips the row from
 * `scheduled` → `publishing` only when the captured scheduled_at version
 * still matches the row. The losers — duplicate workers, stale jobs left
 * over from a reschedule, jobs whose post was deleted — drop out silently.
 *
 * Single attempt: Laravel's tries=3 retry pattern interacts badly with the
 * atomic claim. After try 1 flips status to `publishing`, try 2 fails its
 * claim (status is no longer `scheduled`) and exits silently — leaving the
 * row stuck in `publishing` for the duration of the backoff window. We
 * bypass that by running once and surfacing failures immediately. Users
 * retry through the UI, which queues a fresh job with a new version stamp.
 *
 * Timeout: IG video container polling can wait up to 5 min for Meta-side
 * processing; we give the worker 10 min total so a busy publish call has
 * room without getting SIGTERM'd mid-flight (which would also leave the
 * row stuck in `publishing`).
 */
class PublishContentPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    /**
     * The scheduled_at_version captured at dispatch. The atomic claim
     * compares this to the current value in the DB — if a reschedule
     * happened after dispatch the version moved and this job exits.
     */
    public int $scheduledAtVersion;

    public function __construct(
        public ContentPost $post,
    ) {
        $this->queue = "content-publish";
        $this->scheduledAtVersion = (int) ($post->scheduled_at_version ?? 0);
    }

    public function handle(ContentPublishManager $manager): void
    {
        $claimed = DB::table('content_posts')
            ->where('id', $this->post->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at_version', $this->scheduledAtVersion)
            ->update([
                'status' => 'publishing',
                'error_message' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            Log::info('PublishContentPostJob: claim missed, exiting', [
                'post_id' => $this->post->id,
                'expected_version' => $this->scheduledAtVersion,
            ]);
            return;
        }

        $fresh = ContentPost::find($this->post->id);
        if (! $fresh) {
            Log::warning('PublishContentPostJob: post disappeared after claim', [
                'post_id' => $this->post->id,
            ]);
            return;
        }

        // Inline try/catch — any throw between claim and publishPost would
        // leave the row stuck in `publishing` because failed() only fires
        // after Laravel's retry exhaustion. With tries=1 it does fire on
        // the first failure too, but explicit recovery here gives us the
        // sanitized error message stored synchronously on the row.
        try {
            $manager->publishPost($fresh);
        } catch (Throwable $e) {
            DB::table('content_posts')
                ->where('id', $this->post->id)
                ->where('status', 'publishing')
                ->update([
                    'status' => 'failed',
                    'error_message' => MetaErrorSanitizer::redact($e->getMessage()),
                    'updated_at' => now(),
                ]);

            throw $e; // bubble so Laravel marks the job failed + logs it
        }
    }

    public function failed(Throwable $e): void
    {
        $safe = MetaErrorSanitizer::redact($e->getMessage());

        Log::error('PublishContentPostJob failed permanently', [
            'post_id' => $this->post->id,
            'error' => $safe,
        ]);

        // Belt + suspenders: if handle()'s inline catch already flipped the
        // row to `failed`, this update is a no-op (matches nothing). If we
        // crashed before reaching that catch, this rescues us from a stuck
        // `publishing` or `scheduled` state. Either way the row ends up
        // `failed` so the safety-net cron does NOT pick it up again.
        DB::table('content_posts')
            ->where('id', $this->post->id)
            ->whereIn('status', ['publishing', 'scheduled'])
            ->update([
                'status' => 'failed',
                'error_message' => $safe,
                'updated_at' => now(),
            ]);
    }
}
