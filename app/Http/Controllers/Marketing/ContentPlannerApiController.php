<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Content\ContentApprovalStep;
use App\Models\Content\ContentCampaign;
use App\Models\Content\ContentComment;
use App\Models\Content\ContentLabel;
use App\Models\Content\ContentMedia;
use App\Models\Content\ContentPost;
use App\Models\Content\ContentPostVersion;
use App\Models\Content\ContentShareLink;
use App\Models\Content\ContentSuggestion;
use App\Services\ContentPlanner\ContentAiService;
use App\Services\ContentPlanner\ContentFeedImportService;
use App\Services\ContentPlanner\ContentMediaService;
use App\Services\ContentPlanner\ContentPostService;
use App\Services\ContentPlanner\ExternalPostService;
use App\Services\ContentPlanner\ShareLinkService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ContentPlannerApiController extends Controller
{
    public function __construct(
        protected ContentPostService $postService,
        protected ContentMediaService $mediaService,
        protected ExternalPostService $externalPostService,
    ) {}

    // ── Posts ──

    public function listPosts(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->endOfMonth()->toDateString());
        $platforms = $request->get('platforms') ? explode(',', $request->get('platforms')) : null;
        $statuses = $request->get('statuses') ? explode(',', $request->get('statuses')) : null;
        $labelIds = $request->get('label_ids') ? explode(',', $request->get('label_ids')) : null;
        $campaignId = $request->get('campaign_id') ? (int) $request->get('campaign_id') : null;
        $includeExternal = $request->get('include_external', '1') === '1';

        $events = $this->postService->getPostsForCalendar(
            Carbon::parse($from),
            Carbon::parse($to),
            $platforms,
            $statuses,
            $labelIds,
            $campaignId,
        );

        // Merge external (published) posts from FB/IG/TikTok
        if ($includeExternal && !$statuses) {
            $externalEvents = $this->externalPostService->getExternalPostsForCalendar(
                Carbon::parse($from),
                Carbon::parse($to),
                $platforms,
            );

            // Deduplicate: skip external events whose content already exists as a planned post
            if ($events && $externalEvents) {
                $plannedKeys = [];
                foreach ($events as $ev) {
                    $content = $ev['extendedProps']['content'] ?? '';
                    if ($content) {
                        $plannedKeys[] = md5(mb_substr(preg_replace('/\s+/', ' ', trim($content)), 0, 100));
                    }
                }
                $plannedKeys = array_flip($plannedKeys);

                $externalEvents = array_filter($externalEvents, function ($ext) use ($plannedKeys) {
                    $content = $ext['extendedProps']['content'] ?? '';
                    if (!$content) {
                        return true;
                    }
                    $key = md5(mb_substr(preg_replace('/\s+/', ' ', trim($content)), 0, 100));

                    return !isset($plannedKeys[$key]);
                });
            }

            $events = array_merge($events, $externalEvents);
        }

        return response()->json($events);
    }

    public function feedPosts(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to = $request->get('to', now()->addDays(30)->toDateString());
        $platforms = $request->get('platforms') ? explode(',', $request->get('platforms')) : null;

        // Get planned posts
        $planned = $this->postService->getPostsForCalendar(
            Carbon::parse($from),
            Carbon::parse($to),
            $platforms,
        );

        // Convert to feed format
        $feedItems = array_map(function ($event) {
            return [
                'id' => $event['id'],
                'type' => 'planned',
                'platform' => $event['extendedProps']['platform'] ?? 'multi',
                'platform_icons' => $event['extendedProps']['platform_icons'] ?? [],
                'content' => $event['extendedProps']['content'] ?? $event['title'],
                'thumbnail' => $event['extendedProps']['thumbnail'] ?? null,
                'first_media_url' => $event['extendedProps']['first_media_url'] ?? null,
                'is_video' => $event['extendedProps']['is_video'] ?? false,
                'status' => $event['extendedProps']['status'] ?? 'draft',
                'status_label' => $event['extendedProps']['status_label'] ?? 'Draft',
                'status_color' => $event['extendedProps']['status_color'] ?? '#9CA3AF',
                'sort_date' => $event['start'],
                'scheduled_at' => $event['start'],
                'user_name' => $event['extendedProps']['user_name'] ?? null,
                'labels' => $event['extendedProps']['labels'] ?? [],
                'has_media' => $event['extendedProps']['has_media'] ?? false,
                'metrics' => null,
            ];
        }, $planned);

        // Get external posts
        $external = $this->externalPostService->getExternalPostsForFeed(
            Carbon::parse($from),
            Carbon::parse($to),
            $platforms,
        );

        $allItems = array_merge($feedItems, $external);

        // Sort chronologically by sort_date
        usort($allItems, fn ($a, $b) => strcmp($a['sort_date'], $b['sort_date']));

        return response()->json([
            'items' => $allItems,
            'now' => now()->toIso8601String(),
        ]);
    }

    public function listPostsPaginated(Request $request): JsonResponse
    {
        $filters = [
            'platforms' => $request->get('platforms') ? explode(',', $request->get('platforms')) : null,
            'statuses' => $request->get('statuses') ? explode(',', $request->get('statuses')) : null,
            'label_ids' => $request->get('label_ids') ? explode(',', $request->get('label_ids')) : null,
            'search' => $request->get('search'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
            'campaign_id' => $request->get('campaign_id') ? (int) $request->get('campaign_id') : null,
        ];

        $posts = $this->postService->getPostsForList(
            $filters,
            $request->get('sort_by', 'scheduled_at'),
            $request->get('sort_dir', 'desc'),
            (int) $request->get('per_page', 20),
        );

        return response()->json($posts);
    }

    public function getPost(int $id): JsonResponse
    {
        $post = $this->postService->getPost($id);
        return response()->json($post);
    }

    public function storePost(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string|max:5000',
            'platform' => 'required|string|in:facebook,instagram,tiktok,multi',
            'platforms' => 'required_if:platform,multi|array',
            'platforms.*' => 'string|in:facebook,instagram,tiktok',
            'scheduled_at' => 'nullable|date',
            'status' => 'nullable|string|in:draft,pending_review,approved,scheduled',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'integer|exists:content_media,id',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:content_labels,id',
            'notes' => 'nullable|string|max:2000',
            'campaign_id' => 'nullable|integer|exists:content_campaigns,id',
        ]);

        $post = $this->postService->createPost($request->all(), auth()->id());

        return response()->json($post, 201);
    }

    public function updatePost(Request $request, int $id): JsonResponse
    {
        $post = ContentPost::findOrFail($id);

        $request->validate([
            'content' => 'nullable|string|max:5000',
            'platform' => 'nullable|string|in:facebook,instagram,tiktok,multi',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string|in:facebook,instagram,tiktok',
            'scheduled_at' => 'nullable|date',
            'status' => 'nullable|string|in:draft,pending_review,approved,scheduled',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'integer|exists:content_media,id',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:content_labels,id',
            'notes' => 'nullable|string|max:2000',
            'campaign_id' => 'nullable|integer|exists:content_campaigns,id',
        ]);

        $post = $this->postService->updatePost($post, $request->all());

        return response()->json($post);
    }

    public function deletePost(int $id): JsonResponse
    {
        $post = ContentPost::findOrFail($id);
        $this->postService->deletePost($post);

        return response()->json(['message' => 'Post deleted.']);
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|string|in:draft,pending_review,approved,scheduled,published,failed']);

        $post = ContentPost::findOrFail($id);

        try {
            $post = $this->postService->changeStatus($post, $request->status, auth()->id());
            return response()->json($post);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $request->validate(['scheduled_at' => 'required|date']);

        $post = ContentPost::findOrFail($id);
        $post = $this->postService->reschedule($post, Carbon::parse($request->scheduled_at));

        return response()->json($post);
    }

    public function reorderGrid(Request $request): JsonResponse
    {
        $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:content_posts,id',
        ]);

        $swapTimes = (bool) $request->input('swap_times', false);
        $this->postService->reorderGrid($request->ordered_ids, $swapTimes);

        return response()->json(['message' => 'Reordered.']);
    }

    // ── Meta Sync ──

    public function syncFromMeta(): JsonResponse
    {
        $postSyncService = app(\App\Services\Meta\MetaPostSyncService::class);

        // Diagnose config up-front so a 0-count result can be explained
        // without the user having to SSH and read logs.
        $issues = [];
        if (! config('meta.page_id')) {
            $issues[] = 'META_PAGE_ID mungon (.env)';
        }
        if (! config('meta.page_token')) {
            $issues[] = 'META_PAGE_TOKEN mungon ose ka skaduar (.env)';
        }
        if (! config('meta.ig_account_id')) {
            $issues[] = 'META_IG_ACCOUNT_ID mungon (auto-discover do provohet)';
        }

        try {
            $fbCount = $postSyncService->syncFacebookPosts();
            $igCount = $postSyncService->syncInstagramPosts();
        } catch (\Exception $e) {
            return response()->json([
                'message'  => 'Sync failed: ' . $e->getMessage(),
                'facebook' => 0,
                'instagram' => 0,
                'issues'   => $issues,
                'hint'     => 'Kontrollo /marketing/meta-auth per token ose .env ne server.',
            ], 500);
        }

        $total = $fbCount + $igCount;

        // Kur total=0 dhe ka issue konfigurimi, tregoji user-it — ben debug
        // te menjehershme ne vend te "mos del asgje".
        if ($total === 0 && ! empty($issues)) {
            return response()->json([
                'message'  => 'Sync ran por 0 poste u importuan. Arsye te mundshme ↓',
                'facebook' => 0,
                'instagram' => 0,
                'issues'   => $issues,
                'hint'     => 'Rifresko token-in ne /marketing/meta-auth, ose kontrollo .env ne server.',
            ], 200);
        }

        return response()->json([
            'message'   => "Synced {$total} posts from Meta.",
            'facebook'  => $fbCount,
            'instagram' => $igCount,
            'issues'    => $issues,
        ]);
    }

    // ── Media ──

    public function uploadMedia(Request $request): JsonResponse
    {
        $maxSize = config('content-planner.media_max_size_mb', 50) * 1024;

        $request->validate([
            'file' => "required|file|max:{$maxSize}",
        ]);

        $media = $this->mediaService->upload($request->file('file'), auth()->id());

        $data = $media->toArray();
        $data['url'] = Storage::disk($media->disk)->url($media->path);
        $data['thumbnail_url'] = $media->thumbnail_path
            ? Storage::disk($media->disk)->url($media->thumbnail_path)
            : $data['url'];

        return response()->json($data, 201);
    }

    public function listMedia(Request $request): JsonResponse
    {
        $media = $this->mediaService->list(
            [
                'type' => $request->get('type'),
                'search' => $request->get('search'),
                'usage' => $request->get('usage'),
            ],
            (int) $request->get('per_page', 30),
        );

        return response()->json($media);
    }

    public function deleteMedia(int $id): JsonResponse
    {
        $media = ContentMedia::findOrFail($id);
        $this->mediaService->delete($media);

        return response()->json(['message' => 'Media deleted.']);
    }

    // ── Comments ──

    public function storeComment(Request $request): JsonResponse
    {
        $request->validate([
            'content_post_id' => 'required|integer|exists:content_posts,id',
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:content_comments,id',
        ]);

        $comment = ContentComment::create([
            'content_post_id' => $request->content_post_id,
            'user_id' => auth()->id(),
            'body' => $request->body,
            'parent_id' => $request->parent_id,
            'is_internal' => true,
        ]);

        return response()->json($comment->load('user'), 201);
    }

    public function deleteComment(int $id): JsonResponse
    {
        $comment = ContentComment::findOrFail($id);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function resolveComment(int $id): JsonResponse
    {
        $comment = ContentComment::findOrFail($id);
        $comment->update(['resolved_at' => $comment->resolved_at ? null : now()]);

        return response()->json($comment);
    }

    // ── Labels ──

    public function listLabels(): JsonResponse
    {
        return response()->json(ContentLabel::orderBy('name')->get());
    }

    public function storeLabel(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $label = ContentLabel::create($request->only('name', 'color'));

        return response()->json($label, 201);
    }

    public function updateLabel(Request $request, int $id): JsonResponse
    {
        $label = ContentLabel::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:50',
            'color' => 'sometimes|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $label->update($request->only('name', 'color'));

        return response()->json($label);
    }

    public function deleteLabel(int $id): JsonResponse
    {
        $label = ContentLabel::findOrFail($id);
        $label->posts()->detach();
        $label->delete();

        return response()->json(['message' => 'Label deleted.']);
    }

    // ── AI ──

    public function aiGenerateCaption(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'required|string|in:facebook,instagram,tiktok',
            'context' => 'nullable|string|max:500',
            'tone' => 'nullable|string|max:50',
        ]);

        $ai = app(ContentAiService::class);
        $result = $ai->generateCaption($request->platform, $request->context, $request->tone);

        if ($result === null) {
            return response()->json(['error' => 'AI service unavailable.'], 503);
        }

        return response()->json(['text' => $result]);
    }

    public function aiSuggestHashtags(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'platform' => 'nullable|string|in:facebook,instagram,tiktok',
            'count' => 'nullable|integer|min:5|max:30',
        ]);

        $ai = app(ContentAiService::class);
        $result = $ai->suggestHashtags($request->content, $request->platform ?? 'instagram', $request->count ?? 15);

        if ($result === null) {
            return response()->json(['error' => 'AI service unavailable.'], 503);
        }

        return response()->json(['text' => $result]);
    }

    public function aiRewriteContent(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'style' => 'required|string|in:shorter,longer,professional,casual,engaging',
        ]);

        $ai = app(ContentAiService::class);
        $result = $ai->rewriteContent($request->content, $request->style);

        if ($result === null) {
            return response()->json(['error' => 'AI service unavailable.'], 503);
        }

        return response()->json(['text' => $result]);
    }

    // ── Duplicate Post ──

    public function duplicatePost(int $id): JsonResponse
    {
        $post = ContentPost::findOrFail($id);
        $duplicate = $this->postService->duplicatePost($post, auth()->id());

        return response()->json($duplicate, 201);
    }

    // ── Batch Schedule ──

    public function batchSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => 'required|array|min:1',
            'post_ids.*' => 'integer|exists:content_posts,id',
            'scheduled_at' => 'required|date',
            'interval_minutes' => 'nullable|integer|min:0',
        ]);

        $scheduledAt = Carbon::parse($request->scheduled_at);
        $interval = (int) $request->input('interval_minutes', 0);

        $updated = 0;
        foreach ($request->post_ids as $i => $postId) {
            $post = ContentPost::find($postId);
            if ($post && in_array($post->status, ['draft', 'pending_review', 'approved', 'scheduled'])) {
                $time = $scheduledAt->copy()->addMinutes($interval * $i);
                $post->update([
                    'scheduled_at' => $time,
                    'status' => 'scheduled',
                ]);
                $updated++;
            }
        }

        return response()->json(['message' => "{$updated} posts scheduled.", 'count' => $updated]);
    }

    // ── Campaigns ──

    public function listCampaigns(): JsonResponse
    {
        $campaigns = ContentCampaign::withCount('posts')
            ->orderBy('name')
            ->get();

        return response()->json($campaigns);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $campaign = ContentCampaign::create([
            ...$request->only('name', 'color', 'description', 'start_date', 'end_date'),
            'created_by' => auth()->id(),
        ]);

        return response()->json($campaign, 201);
    }

    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = ContentCampaign::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $campaign->update($request->only('name', 'color', 'description', 'start_date', 'end_date'));

        return response()->json($campaign);
    }

    public function deleteCampaign(int $id): JsonResponse
    {
        $campaign = ContentCampaign::findOrFail($id);
        ContentPost::where('campaign_id', $campaign->id)->update(['campaign_id' => null]);
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted.']);
    }

    // ── Approval Steps ──

    public function listApprovalSteps(int $postId): JsonResponse
    {
        $steps = ContentApprovalStep::where('post_id', $postId)
            ->with(['assignee', 'actor'])
            ->orderBy('step_order')
            ->get();

        return response()->json($steps);
    }

    public function storeApprovalStep(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'role' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $post = ContentPost::findOrFail($postId);

        $maxOrder = ContentApprovalStep::where('post_id', $postId)->max('step_order') ?? 0;

        $step = ContentApprovalStep::create([
            'post_id' => $post->id,
            'step_order' => $maxOrder + 1,
            'role' => $request->role,
            'assigned_to' => $request->assigned_to,
        ]);

        return response()->json($step->load(['assignee']), 201);
    }

    public function actOnApprovalStep(Request $request, int $stepId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'feedback' => 'nullable|string|max:2000',
        ]);

        $step = ContentApprovalStep::findOrFail($stepId);

        $step->update([
            'status' => $request->status,
            'acted_by' => auth()->id(),
            'acted_at' => now(),
            'feedback' => $request->feedback,
        ]);

        // Check if all steps are approved — lock the post
        $post = $step->post;
        $pendingSteps = ContentApprovalStep::where('post_id', $post->id)->pending()->count();

        if ($pendingSteps === 0) {
            $allApproved = ContentApprovalStep::where('post_id', $post->id)
                ->where('status', '!=', 'approved')
                ->doesntExist();

            if ($allApproved) {
                $post->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                    'approval_locked_at' => now(),
                ]);
            }
        }

        return response()->json($step->load(['assignee', 'actor']));
    }

    public function deleteApprovalStep(int $stepId): JsonResponse
    {
        $step = ContentApprovalStep::findOrFail($stepId);
        $step->delete();

        return response()->json(['message' => 'Approval step removed.']);
    }

    // ── Version History ──

    public function listVersions(int $postId): JsonResponse
    {
        $versions = ContentPostVersion::where('post_id', $postId)
            ->with('creator')
            ->orderByDesc('version_number')
            ->get();

        return response()->json($versions);
    }

    public function restoreVersion(int $versionId): JsonResponse
    {
        $version = ContentPostVersion::findOrFail($versionId);
        $post = $version->post;
        $snapshot = $version->snapshot;

        // Create a snapshot of current state before restoring
        $this->createVersionSnapshot($post, 'Before restore to v' . $version->version_number);

        $post->update([
            'content' => $snapshot['content'] ?? $post->content,
            'platform' => $snapshot['platform'] ?? $post->platform,
            'notes' => $snapshot['notes'] ?? $post->notes,
        ]);

        return response()->json(['message' => 'Post restored to version ' . $version->version_number]);
    }

    protected function createVersionSnapshot(ContentPost $post, ?string $summary = null): ContentPostVersion
    {
        $maxVersion = ContentPostVersion::where('post_id', $post->id)->max('version_number') ?? 0;

        return ContentPostVersion::create([
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
            'change_summary' => $summary,
            'created_by' => auth()->id(),
        ]);
    }

    // ── Text Suggestions ──

    public function listSuggestions(int $postId): JsonResponse
    {
        $suggestions = ContentSuggestion::where('post_id', $postId)
            ->with(['author', 'resolver'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($suggestions);
    }

    public function storeSuggestion(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'original_text' => 'required|string',
            'suggested_text' => 'required|string',
            'position_start' => 'nullable|integer|min:0',
            'position_end' => 'nullable|integer|min:0',
        ]);

        $suggestion = ContentSuggestion::create([
            'post_id' => $postId,
            'user_id' => auth()->id(),
            'original_text' => $request->original_text,
            'suggested_text' => $request->suggested_text,
            'position_start' => $request->position_start,
            'position_end' => $request->position_end,
        ]);

        return response()->json($suggestion->load('author'), 201);
    }

    public function resolveSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        $suggestion = ContentSuggestion::findOrFail($suggestionId);

        $suggestion->update([
            'status' => $request->status,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        // If accepted, apply the text change to the post
        if ($request->status === 'accepted') {
            $post = $suggestion->post;
            $newContent = str_replace($suggestion->original_text, $suggestion->suggested_text, $post->content);
            $post->update(['content' => $newContent]);
        }

        return response()->json($suggestion->load(['author', 'resolver']));
    }

    // ── Share Links ──

    public function listShareLinks(int $postId): JsonResponse
    {
        $links = ContentShareLink::where('shareable_type', ContentPost::class)
            ->where('shareable_id', $postId)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($links);
    }

    public function createShareLink(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'permission' => 'required|in:view,comment,approve',
            'password' => 'nullable|string|min:4',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $service = app(ShareLinkService::class);
        $link = $service->createLink(
            ContentPost::class,
            $postId,
            auth()->id(),
            [
                'permission' => $request->permission,
                'password' => $request->password,
                'expires_at' => $request->expires_at,
            ]
        );

        $link->load('creator');
        $link->url = url("/share/{$link->token}");

        return response()->json($link, 201);
    }

    public function deactivateShareLink(int $linkId): JsonResponse
    {
        $link = ContentShareLink::findOrFail($linkId);
        $link->update(['is_active' => false]);

        return response()->json(['message' => 'Share link deactivated.']);
    }

    // ── Schedule suggestions (engagement forecast) ──
    //
    // Returns:
    //   - hourly_engagement: 8 buckets (00,03,06,09,12,15,18,21) with a
    //     0-1 normalized value representing how often posts have been
    //     scheduled/published in that 3-hour window historically.
    //   - top_picks: the 2 highest-scoring time slots.
    //
    // When there is no history yet, we seed the response with sensible
    // IG-peak defaults (11:00 and 19:00) so the UI is usable day one.
    public function scheduleSuggestions(Request $request): JsonResponse
    {
        $platform = $request->input('platform'); // optional filter
        $now      = Carbon::now();
        $sinceDate = $now->copy()->subDays(90)->toDateTimeString();

        $query = ContentPost::query()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $sinceDate);

        if ($platform && in_array($platform, ['instagram', 'facebook', 'tiktok', 'multi'], true)) {
            $query->where('platform', $platform);
        }

        // Bucket by floor(hour / 3) * 3 so we land on 00/03/06/…/21.
        // This is dialect-agnostic so it runs the same on dev + prod MySQL.
        $rows = $query
            ->selectRaw('FLOOR(HOUR(scheduled_at) / 3) * 3 AS hour_bucket, COUNT(*) AS n')
            ->groupBy('hour_bucket')
            ->pluck('n', 'hour_bucket')
            ->all();

        $buckets = [0, 3, 6, 9, 12, 15, 18, 21];
        $max = max([1, ...array_values($rows)]); // avoid divide-by-zero

        $hourly = [];
        $hasData = false;
        foreach ($buckets as $h) {
            $count = (int) ($rows[$h] ?? 0);
            if ($count > 0) {
                $hasData = true;
            }
            $hourly[] = [
                'hour'  => $h,
                'count' => $count,
                'value' => round($count / $max, 2),
            ];
        }

        if (! $hasData) {
            // Pleasant IG-shaped curve so the bar chart is never empty.
            // (Low overnight, builds to a 12:00 lunch peak and a 19:00 evening peak.)
            $placeholder = [0.1, 0.05, 0.1, 0.5, 0.8, 0.55, 0.95, 0.7];
            foreach ($hourly as $i => &$b) {
                $b['value'] = $placeholder[$i];
            }
            unset($b);
        }

        // Top 2 buckets by value
        $sorted = $hourly;
        usort($sorted, fn ($a, $b) => $b['value'] <=> $a['value']);
        $topBuckets = array_slice($sorted, 0, 2);

        // For each top bucket, nudge into a human-feeling exact minute
        // (avoids the picker suggesting a bland ":00" every time).
        $topPicks = array_map(function ($b) {
            $hour = (int) $b['hour'];
            // Reasonable anchor minutes per bucket: mid-of-bucket.
            $minute = match ($hour) {
                0, 21 => 0,
                3     => 30,
                6     => 30,
                9     => 0,
                12    => 0,
                15    => 30,
                18    => 0,
                default => 0,
            };

            return [
                'time'  => sprintf('%02d:%02d', $hour, $minute),
                'score' => $b['value'],
            ];
        }, $topBuckets);

        return response()->json([
            'top_picks'         => $topPicks,
            'hourly_engagement' => $hourly,
            'has_data'          => $hasData,
        ]);
    }
}
