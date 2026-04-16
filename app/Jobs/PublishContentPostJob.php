<?php

namespace App\Jobs;

use App\Models\Content\ContentPost;
use App\Services\ContentPlanner\Publishing\ContentPublishManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishContentPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ContentPost $post,
    ) {
        $this->queue = "content-publish";
    }

    public function handle(ContentPublishManager $manager): void
    {
        if ($this->post->status !== "scheduled") {
            Log::info("Post is no longer scheduled, skipping publish", ["post_id" => $this->post->id, "status" => $this->post->status]);
            return;
        }

        if ($this->post->scheduled_at && $this->post->scheduled_at->isFuture()) {
            Log::info("Post scheduled_at is in the future, skipping", ["post_id" => $this->post->id]);
            return;
        }

        $manager->publishPost($this->post);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("PublishContentPostJob failed permanently", [
            "post_id" => $this->post->id,
            "error" => $e->getMessage(),
        ]);

        $this->post->update(["status" => "failed"]);
    }
}
