<?php

namespace App\Observers;

use App\Enums\DailyBasketPostStage;
use App\Models\Content\ContentPost;
use App\Models\DailyBasketPost;
use App\Support\SyncGuard;

/**
 * Mirrors ContentPost changes back onto the originating DailyBasketPost.
 *
 * A DailyBasketPost reaches stage=published via transitionPost(), which
 * creates a ContentPost and stores its id. From that moment on, if the
 * marketing team further edits the ContentPost (caption, schedule) or
 * its status moves through the Planner workflow, the basket view should
 * stay in sync so dashboards and the sidebar strip show reality.
 *
 * Loop-safe via SyncGuard.
 */
class ContentPostObserver
{
    public function updated(ContentPost $post): void
    {
        if (SyncGuard::isSyncing()) {
            return;
        }

        // Only care about posts linked back to a basket.
        $basketPost = DailyBasketPost::query()
            ->where('content_post_id', $post->id)
            ->first();

        if (! $basketPost) {
            return;
        }

        $changed = $post->getChanges();
        $updates = [];

        if (array_key_exists('content', $changed) && $post->content !== null) {
            $updates['caption'] = $post->content;
        }
        if (array_key_exists('scheduled_at', $changed) && $post->scheduled_at !== null) {
            $updates['scheduled_for'] = $post->scheduled_at;
        }
        if (array_key_exists('status', $changed)) {
            $targetStage = $this->stageForStatus($post->status);
            if ($targetStage !== null && $basketPost->stage !== $targetStage) {
                $updates['stage'] = $targetStage;
            }
        }

        if (empty($updates)) {
            return;
        }

        SyncGuard::wrap(function () use ($basketPost, $updates) {
            $basketPost->fill($updates)->save();
        });
    }

    /**
     * Map a Content Planner status onto a Daily Basket stage.
     * Nulls indicate "don't touch" — e.g. a manual 'failed' status
     * shouldn't drag the basket backwards.
     */
    private function stageForStatus(?string $status): ?DailyBasketPostStage
    {
        return match ($status) {
            'draft'          => DailyBasketPostStage::SCHEDULING,
            'pending_review' => DailyBasketPostStage::SCHEDULING,
            'approved'       => DailyBasketPostStage::SCHEDULING,
            'scheduled'      => DailyBasketPostStage::SCHEDULING,
            // Intermediate publishing — basket still belongs in SCHEDULING
            // until Meta returns success. Treating it as PUBLISHED would
            // flip dashboards prematurely and roll back if the call fails.
            'publishing'     => DailyBasketPostStage::SCHEDULING,
            'published'      => DailyBasketPostStage::PUBLISHED,
            default          => null,
        };
    }
}
