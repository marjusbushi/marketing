<?php

namespace App\Observers;

use App\Models\Content\ContentPost;
use App\Models\DailyBasketPost;
use App\Support\SyncGuard;

/**
 * Mirrors DailyBasketPost changes forward onto the linked ContentPost.
 *
 * When marketing edits a basket post after publication (caption tweak,
 * reschedule, etc.), the Planner post should reflect the change rather
 * than serve stale content.
 *
 * Loop-safe via SyncGuard.
 */
class DailyBasketPostObserver
{
    public function updated(DailyBasketPost $post): void
    {
        if (SyncGuard::isSyncing()) {
            return;
        }

        if (! $post->content_post_id) {
            return;
        }

        $changed = $post->getChanges();
        $updates = [];

        if (array_key_exists('caption', $changed)) {
            $updates['content'] = $post->caption;
        }
        if (array_key_exists('scheduled_for', $changed) && $post->scheduled_for !== null) {
            $updates['scheduled_at'] = $post->scheduled_for;
        }

        // A manual revert back to 'scheduling' from 'published' means the
        // publisher shouldn't send this out — flip the ContentPost back to
        // the draft status so the publish pipeline ignores it.
        if (array_key_exists('stage', $changed)) {
            if ($post->stage?->value !== 'published') {
                $updates['status'] = 'draft';
            }
        }

        if (empty($updates)) {
            return;
        }

        $contentPost = ContentPost::find($post->content_post_id);
        if (! $contentPost) {
            return;
        }

        SyncGuard::wrap(function () use ($contentPost, $updates) {
            $contentPost->fill($updates)->save();
        });
    }
}
