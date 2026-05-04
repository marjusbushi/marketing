<?php

namespace App\Services\ContentPlanner;

use App\Jobs\PublishContentPostJob;
use App\Models\Content\ContentPost;
use App\Models\Content\ContentPostPlatform;
use App\Models\Content\ContentPostVersion;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContentPostService
{
    /**
     * Get posts formatted for FullCalendar events.
     */
    public function getPostsForCalendar(
        Carbon $from,
        Carbon $to,
        ?array $platforms = null,
        ?array $statuses = null,
        ?array $labelIds = null,
        ?int $campaignId = null
    ): array {
        $query = ContentPost::with(['media', 'labels', 'platforms', 'user'])
            ->dateRange($from, $to);

        $this->applyFilters($query, $platforms, $statuses, $labelIds, $campaignId);

        $posts = $query->orderBy('scheduled_at')->get();

        return $posts->map(function (ContentPost $post) {
            return [
                'id' => $post->id,
                'title' => Str_limit_plain($post->content, 60),
                'start' => ($post->scheduled_at ?? $post->created_at)->toIso8601String(),
                'allDay' => $post->scheduled_at ? false : true,
                'backgroundColor' => $post->status_bg_color,
                'borderColor' => $post->status_color,
                'textColor' => '#374151',
                'extendedProps' => [
                    'uuid' => $post->uuid,
                    'content' => $post->content,
                    'content_type' => $post->content_type ?? 'post',
                    'status' => $post->status,
                    'status_label' => $post->status_label,
                    'status_color' => $post->status_color,
                    'status_bg_color' => $post->status_bg_color,
                    'platform' => $post->platform,
                    'platform_icons' => $post->platform_icons,
                    'thumbnail' => $post->first_thumbnail_url,
                    'first_media_url' => $post->first_media_url,
                    'is_video' => $post->media->first()?->is_video ?? false,
                    'labels' => $post->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color]),
                    'user_name' => $post->user?->name,
                    'media_count' => $post->media->count(),
                    'has_media' => $post->media->isNotEmpty(),
                    'is_external' => false,
                    'external_source' => $post->external_source,
                    'is_imported' => $post->is_imported,
                    'permalink' => $post->permalink,
                    'meta_post_type' => $post->meta_post_type,
                    'error_message' => $post->error_message,
                ],
            ];
        })->toArray();
    }

    /**
     * Get posts for the list view with pagination.
     */
    public function getPostsForList(
        array $filters = [],
        string $sortBy = 'scheduled_at',
        string $sortDir = 'desc',
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = ContentPost::with(['media', 'labels', 'platforms', 'user']);

        if (!empty($filters['platforms'])) {
            $this->applyFilters($query, $filters['platforms']);
        }
        if (!empty($filters['statuses'])) {
            $this->applyFilters($query, null, $filters['statuses']);
        }
        if (!empty($filters['label_ids'])) {
            $this->applyFilters($query, null, null, $filters['label_ids']);
        }
        if (!empty($filters['search'])) {
            $query->where('content', 'like', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['from']) && !empty($filters['to'])) {
            $query->dateRange(Carbon::parse($filters['from']), Carbon::parse($filters['to']));
        }
        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        $allowedSorts = ['scheduled_at', 'created_at', 'status', 'platform'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'scheduled_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Get posts ordered for the Instagram grid view.
     */
    public function getPostsForGrid(?string $platform = 'instagram'): Collection
    {
        $query = ContentPost::with(['media', 'labels', 'platforms'])
            ->whereIn('status', ['draft', 'pending_review', 'approved', 'scheduled', 'published']);

        if ($platform) {
            $query->byPlatform($platform);
        }

        return $query->orderBy('sort_order')->orderByDesc('scheduled_at')->get();
    }

    /**
     * Approval gate — a post may enter `scheduled` only when human review is
     * either not required (approval_type='none') or already complete
     * (approved_at set). Checked at every boundary that can flip the status
     * so the failure surfaces synchronously to the caller, not at fire time.
     */
    protected function ensureApprovalReady(string $approvalType, bool $hasBeenApproved): void
    {
        if ($approvalType === 'none') {
            return;
        }
        if ($hasBeenApproved) {
            return;
        }
        throw new \InvalidArgumentException(
            'Posti kërkon aprovim para se të skedulohet. Dërgoje për review fillimisht.'
        );
    }

    /**
     * Hand the post off to the queue for delayed publish. Called after any
     * write that lands the post in `scheduled`. Safe to invoke speculatively
     * — it no-ops on every other status, on missing scheduled_at, and on
     * un-approved posts (the gate is enforced upstream too).
     *
     * Reschedules work because scheduled_at_version was bumped before this
     * call: the new job captures the new version, while any older job that
     * was sitting in the queue fails its atomic claim at fire time and
     * exits without publishing.
     */
    protected function dispatchScheduledJob(ContentPost $post): void
    {
        if ($post->status !== 'scheduled') {
            return;
        }
        if (! $post->scheduled_at) {
            return;
        }
        if ($post->approval_type !== 'none' && $post->approved_at === null) {
            return;
        }

        // Past-time fallback: if the user picked a moment that has already
        // passed (e.g., scheduled at 14:00 while it's 14:01), don't refuse
        // — fire it as soon as the worker pulls it off the queue.
        $delay = $post->scheduled_at->isPast() ? now() : $post->scheduled_at;

        if (now()->diffInDays($delay, false) > 7) {
            Log::info('PublishContentPostJob scheduled >7 days out', [
                'post_id' => $post->id,
                'scheduled_at' => $delay->toIso8601String(),
                'version' => $post->scheduled_at_version,
            ]);
        }

        PublishContentPostJob::dispatch($post)->delay($delay);
    }

    /**
     * Create a new post with platforms and media attachments.
     */
    public function createPost(array $data, int $userId): ContentPost
    {
        if (($data['status'] ?? null) === 'scheduled') {
            $this->ensureApprovalReady(
                $data['approval_type'] ?? 'none',
                !empty($data['approved_at'])
            );
        }

        return DB::transaction(function () use ($data, $userId) {
            $post = ContentPost::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $userId,
                'platform' => $data['platform'] ?? 'multi',
                'content' => $data['content'] ?? null,
                'content_type' => $data['content_type'] ?? 'post',
                'scheduled_at' => !empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
                'status' => $data['status'] ?? 'draft',
                'approval_type' => $data['approval_type'] ?? 'none',
                'notes' => $data['notes'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
            ]);

            // Create platform entries
            $selectedPlatforms = $data['platforms'] ?? [];
            if ($post->platform !== 'multi') {
                $selectedPlatforms = [$post->platform];
            }

            foreach ($selectedPlatforms as $platform) {
                ContentPostPlatform::create([
                    'content_post_id' => $post->id,
                    'platform' => $platform,
                    'platform_content' => $data['platform_content'][$platform] ?? null,
                ]);
            }

            // Attach media
            if (!empty($data['media_ids'])) {
                $mediaSync = [];
                foreach ($data['media_ids'] as $order => $mediaId) {
                    $mediaSync[$mediaId] = ['sort_order' => $order];
                }
                $post->media()->sync($mediaSync);
            }

            // Attach labels
            if (!empty($data['label_ids'])) {
                $post->labels()->sync($data['label_ids']);
            }

            // Auto-set status to scheduled if scheduled_at is set and status is approved
            if ($post->scheduled_at && $post->status === 'approved') {
                $post->update(['status' => 'scheduled']);
            }

            $this->dispatchScheduledJob($post->fresh() ?? $post);

            return $post->load(['media', 'labels', 'platforms', 'user']);
        });
    }

    /**
     * Update an existing post.
     */
    public function updatePost(ContentPost $post, array $data): ContentPost
    {
        if (($data['status'] ?? null) === 'scheduled') {
            $this->ensureApprovalReady(
                $data['approval_type'] ?? $post->approval_type,
                $post->approved_at !== null
            );
        }

        return DB::transaction(function () use ($post, $data) {
            // Snapshot current state before updating
            $maxVersion = ContentPostVersion::where('post_id', $post->id)->max('version_number') ?? 0;
            ContentPostVersion::create([
                'post_id' => $post->id,
                'version_number' => $maxVersion + 1,
                'snapshot' => [
                    'content' => $post->content,
                    'platform' => $post->platform,
                    'status' => $post->status,
                    'notes' => $post->notes,
                    'scheduled_at' => $post->scheduled_at?->toISOString(),
                    'campaign_id' => $post->campaign_id,
                ],
                'change_summary' => 'Auto-saved before update',
                'created_by' => auth()->id(),
            ]);

            $newScheduledAt = array_key_exists('scheduled_at', $data)
                ? (!empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null)
                : $post->scheduled_at;

            $scheduledAtChanged = ! $this->sameTimestamp($post->scheduled_at, $newScheduledAt);

            $updates = array_filter([
                'content' => $data['content'] ?? $post->content,
                'platform' => $data['platform'] ?? $post->platform,
                'scheduled_at' => $newScheduledAt,
                'status' => $data['status'] ?? $post->status,
                'approval_type' => $data['approval_type'] ?? $post->approval_type,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $post->notes,
                'campaign_id' => array_key_exists('campaign_id', $data) ? $data['campaign_id'] : $post->campaign_id,
            ], fn ($v) => $v !== null);

            if ($scheduledAtChanged) {
                $updates['scheduled_at_version'] = ((int) ($post->scheduled_at_version ?? 0)) + 1;
            }

            $post->update($updates);

            // Update platforms
            if (isset($data['platforms'])) {
                $post->platforms()->delete();
                foreach ($data['platforms'] as $platform) {
                    ContentPostPlatform::create([
                        'content_post_id' => $post->id,
                        'platform' => $platform,
                        'platform_content' => $data['platform_content'][$platform] ?? null,
                    ]);
                }
            }

            // Update media
            if (isset($data['media_ids'])) {
                $mediaSync = [];
                foreach ($data['media_ids'] as $order => $mediaId) {
                    $mediaSync[$mediaId] = ['sort_order' => $order];
                }
                $post->media()->sync($mediaSync);
            }

            // Update labels
            if (isset($data['label_ids'])) {
                $post->labels()->sync($data['label_ids']);
            }

            $refreshed = $post->fresh(['media', 'labels', 'platforms', 'user']);
            $this->dispatchScheduledJob($refreshed ?? $post);

            return $refreshed;
        });
    }

    /**
     * Change post status with validation.
     */
    public function changeStatus(ContentPost $post, string $newStatus, ?int $userId = null): ContentPost
    {
        $validTransitions = [
            'draft' => ['pending_review', 'approved', 'scheduled'],
            'pending_review' => ['draft', 'approved', 'scheduled'],
            'approved' => ['draft', 'scheduled', 'pending_review'],
            'scheduled' => ['draft', 'approved', 'published', 'failed'],
            'published' => ['draft'],
            'failed' => ['draft', 'scheduled'],
        ];

        $allowed = $validTransitions[$post->status] ?? [];
        if (!in_array($newStatus, $allowed)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$post->status}' to '{$newStatus}'."
            );
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === 'approved' && $userId) {
            $updates['approved_by'] = $userId;
            $updates['approved_at'] = now();
        }

        if ($newStatus === 'scheduled' && !$post->scheduled_at) {
            throw new \InvalidArgumentException('Cannot schedule a post without a scheduled_at date.');
        }

        if ($newStatus === 'scheduled') {
            $this->ensureApprovalReady($post->approval_type, $post->approved_at !== null);
        }

        $post->update($updates);

        $refreshed = $post->fresh();
        $this->dispatchScheduledJob($refreshed ?? $post);

        return $refreshed;
    }

    /**
     * Re-queue a failed post. Bumps the schedule a minute into the future
     * (so the worker reliably picks it up), increments the version so any
     * stale job in the queue exits, clears the previous error, and lets
     * the standard dispatchScheduledJob plumbing handle the rest.
     *
     * Per-platform retry is intentionally out of scope here — multi-platform
     * partial failures are rare enough to handle as a v2 feature.
     */
    public function retry(ContentPost $post): ContentPost
    {
        if ($post->status !== 'failed') {
            throw new \InvalidArgumentException(
                "Vetëm post-et me status='failed' mund të riprovohen. Statusi aktual: '{$post->status}'."
            );
        }

        $post->status = 'scheduled';
        $post->scheduled_at = now()->addMinute();
        $post->scheduled_at_version = ((int) ($post->scheduled_at_version ?? 0)) + 1;
        $post->error_message = null;
        $post->save();

        $this->dispatchScheduledJob($post->fresh() ?? $post);

        return $post->fresh();
    }

    /**
     * Reschedule a post (drag-drop on calendar).
     *
     * Bumps scheduled_at_version so any in-flight PublishContentPostJob
     * that captured the old version exits silently when it fires — the
     * caller (or downstream wiring) is responsible for dispatching a new
     * job with the new version.
     */
    public function reschedule(ContentPost $post, Carbon $scheduledAt): ContentPost
    {
        if (! $this->sameTimestamp($post->scheduled_at, $scheduledAt)) {
            $post->scheduled_at = $scheduledAt;
            $post->scheduled_at_version = ((int) ($post->scheduled_at_version ?? 0)) + 1;
            $post->save();

            $this->dispatchScheduledJob($post->fresh() ?? $post);
        }

        return $post;
    }

    /**
     * True when two nullable Carbon-ish values point at the same instant.
     * Used to decide whether scheduled_at really moved (and therefore
     * whether scheduled_at_version needs to bump).
     */
    protected function sameTimestamp($a, $b): bool
    {
        if ($a === null && $b === null) return true;
        if ($a === null || $b === null) return false;
        return Carbon::parse($a)->equalTo(Carbon::parse($b));
    }

    /**
     * Bulk reorder posts for grid view.
     * If $swapTimes is true, reassign scheduled_at values based on new order.
     */
    public function reorderGrid(array $orderedIds, bool $swapTimes = false): void
    {
        DB::transaction(function () use ($orderedIds, $swapTimes) {
            if ($swapTimes) {
                // Collect all existing scheduled_at values in old order
                $posts = ContentPost::whereIn('id', $orderedIds)
                    ->orderBy('sort_order')
                    ->orderByDesc('scheduled_at')
                    ->get(['id', 'scheduled_at', 'sort_order']);

                // Get the times sorted descending (newest first for grid top)
                $times = $posts->pluck('scheduled_at')->filter()->sort()->values()->toArray();

                // Assign sort_order AND swap times
                foreach ($orderedIds as $order => $id) {
                    $update = ['sort_order' => $order];
                    if (isset($times[$order])) {
                        $update['scheduled_at'] = $times[$order];
                    }
                    ContentPost::where('id', $id)->update($update);
                }
            } else {
                foreach ($orderedIds as $order => $id) {
                    ContentPost::where('id', $id)->update(['sort_order' => $order]);
                }
            }
        });
    }

    /**
     * Get a single post with all relations.
     */
    // $id pranon string ose int -- Laravel route params vijne si string,
    // dhe disa entry te grid-it perdorin composite ID si "db_draft_<n>"
    // (DailyBasketPost) qe nuk korrespondon me ContentPost. Ketu trajtohen
    // vetem ContentPost; per db_draft_ thirresi duhet te bej routing.
    public function getPost($id): ContentPost
    {
        return ContentPost::with([
            'media',
            'labels',
            'platforms',
            'user',
            'approver',
            'comments.user',
            'comments.replies.user',
        ])->findOrFail($id);
    }

    /**
     * Duplicate a post as a new draft.
     */
    public function duplicatePost(ContentPost $post, int $userId): ContentPost
    {
        return DB::transaction(function () use ($post, $userId) {
            $post->load(['media', 'labels', 'platforms']);

            $duplicate = ContentPost::create([
                'user_id' => $userId,
                'platform' => $post->platform,
                'content' => $post->content,
                'scheduled_at' => null,
                'status' => 'draft',
                'approval_type' => $post->approval_type,
                'notes' => $post->notes,
            ]);

            // Copy platforms
            foreach ($post->platforms as $platform) {
                ContentPostPlatform::create([
                    'content_post_id' => $duplicate->id,
                    'platform' => $platform->platform,
                    'platform_content' => $platform->platform_content,
                ]);
            }

            // Copy media attachments
            if ($post->media->isNotEmpty()) {
                $mediaSync = [];
                foreach ($post->media as $media) {
                    $mediaSync[$media->id] = ['sort_order' => $media->pivot->sort_order ?? 0];
                }
                $duplicate->media()->sync($mediaSync);
            }

            // Copy labels
            if ($post->labels->isNotEmpty()) {
                $duplicate->labels()->sync($post->labels->pluck('id'));
            }

            return $duplicate->load(['media', 'labels', 'platforms', 'user']);
        });
    }

    /**
     * Soft-delete a post.
     */
    public function deletePost(ContentPost $post): bool
    {
        return $post->delete();
    }

    /**
     * Apply shared filters to a query.
     */
    protected function applyFilters($query, ?array $platforms = null, ?array $statuses = null, ?array $labelIds = null, ?int $campaignId = null): void
    {
        if ($platforms && count($platforms)) {
            $query->where(function ($q) use ($platforms) {
                $q->whereIn('platform', $platforms)
                  ->orWhere(function ($q2) use ($platforms) {
                      $q2->where('platform', 'multi')
                         ->whereHas('platforms', fn ($p) => $p->whereIn('platform', $platforms));
                  });
            });
        }

        if ($statuses && count($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($labelIds && count($labelIds)) {
            $query->whereHas('labels', fn ($q) => $q->whereIn('content_labels.id', $labelIds));
        }

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
    }
}

/**
 * Truncate text to a given length (plain text).
 */
function Str_limit_plain(?string $value, int $limit = 100, string $end = '...'): string
{
    if ($value === null) return '';
    $value = strip_tags($value);
    if (mb_strwidth($value, 'UTF-8') <= $limit) return $value;
    return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
}
