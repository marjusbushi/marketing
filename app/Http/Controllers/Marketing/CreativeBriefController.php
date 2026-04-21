<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\DailyBasketPost;
use App\Models\DailyBasketPostMedia;
use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\Template;
use App\Services\Marketing\CreativeBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creative Brief API — CRUD + editor state persistence + duplication.
 *
 * The editor debounces updates (~2s) and pushes the full state JSON; this
 * controller accepts it, validates size, and persists. Read endpoints
 * include related template and the latest render job so the editor can
 * hydrate its UI in one round-trip.
 */
class CreativeBriefController extends Controller
{
    /**
     * Hard limit on the state JSON payload (after encoding). Matches spec
     * §6.3: "state max 5MB" to accommodate large Polotno/Remotion states
     * while protecting the DB from abusive writes.
     */
    private const STATE_MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly CreativeBriefService $briefs,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = CreativeBrief::query()->with(['template']);

        if ($postId = $request->query('daily_basket_post_id')) {
            $query->where('daily_basket_post_id', $postId);
        }

        if ($source = $request->query('source')) {
            $query->bySource($source);
        }

        if ($postType = $request->query('post_type')) {
            $query->ofType($postType);
        }

        $briefs = $query->orderByDesc('id')->limit(50)->get();

        return response()->json([
            'creative_briefs' => $briefs->map(fn (CreativeBrief $b) => $this->serialize($b))->values(),
        ]);
    }

    public function show(CreativeBrief $creativeBrief): JsonResponse
    {
        $creativeBrief->load(['template', 'dailyBasketPost.itemGroups']);

        return response()->json([
            'creative_brief' => $this->serialize(
                $creativeBrief,
                includeState: true,
                includeProduct: true,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'daily_basket_post_id' => ['nullable', 'integer', 'exists:daily_basket_posts,id'],
            'template_slug'        => ['nullable', 'string', 'exists:marketing_templates,slug'],
            'post_type'            => ['required', 'in:photo,carousel,reel,video,story'],
            'aspect'               => ['nullable', 'string', 'max:10'],
            'duration_sec'         => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $template = null;
        if (! empty($validated['template_slug'])) {
            $template = Template::query()->where('slug', $validated['template_slug'])->first();
        }

        if (! empty($validated['daily_basket_post_id'])) {
            $post = DailyBasketPost::query()->findOrFail($validated['daily_basket_post_id']);
            $brief = $this->briefs->createForPost(
                $post,
                $validated['post_type'],
                $template,
                $request->user()?->id,
            );

            if (isset($validated['aspect']) || isset($validated['duration_sec'])) {
                $this->briefs->updateFields($brief, array_filter([
                    'aspect'       => $validated['aspect'] ?? null,
                    'duration_sec' => $validated['duration_sec'] ?? null,
                ], fn ($v) => $v !== null));
            }
        } else {
            // Standalone brief (no post yet) — used from /marketing/studio
            // when the user is designing a template or ad-hoc piece.
            $brief = CreativeBrief::query()->create([
                'template_id'  => $template?->id,
                'post_type'    => $validated['post_type'],
                'aspect'       => $validated['aspect'] ?? null,
                'duration_sec' => $validated['duration_sec'] ?? null,
                'source'       => 'manual',
                'created_by'   => $request->user()?->id,
            ]);
        }

        return response()->json([
            'creative_brief' => $this->serialize($brief->refresh(), includeState: true),
        ], 201);
    }

    public function update(Request $request, CreativeBrief $creativeBrief): JsonResponse
    {
        $validated = $request->validate([
            'aspect'            => ['sometimes', 'nullable', 'string', 'max:10'],
            'duration_sec'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'caption_sq'        => ['sometimes', 'nullable', 'string'],
            'caption_en'        => ['sometimes', 'nullable', 'string'],
            'hashtags'          => ['sometimes', 'nullable', 'array'],
            'music_id'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'script'            => ['sometimes', 'nullable', 'array'],
            'media_slots'       => ['sometimes', 'nullable', 'array'],
            'suggested_time'    => ['sometimes', 'nullable', 'date'],
            'ai_prompt_version' => ['sometimes', 'nullable', 'string', 'max:20'],
            'state'             => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('state', $validated) && $validated['state'] !== null) {
            $encoded = json_encode($validated['state']);
            if ($encoded === false || strlen($encoded) > self::STATE_MAX_BYTES) {
                return response()->json([
                    'message' => 'State payload exceeds 5MB.',
                ], 413);
            }
        }

        $this->briefs->updateFields($creativeBrief, $validated);

        return response()->json([
            'creative_brief' => $this->serialize($creativeBrief->refresh(), includeState: true),
        ]);
    }

    public function destroy(CreativeBrief $creativeBrief): JsonResponse
    {
        $creativeBrief->delete();

        return response()->json(['message' => 'Creative brief deleted.']);
    }

    public function duplicate(CreativeBrief $creativeBrief): JsonResponse
    {
        $copy = $this->briefs->duplicate($creativeBrief);

        return response()->json([
            'creative_brief' => $this->serialize($copy, includeState: true),
        ], 201);
    }

    /**
     * Upload a CapCut-exported video (or any video/*) against the brief.
     *
     * The staff's workflow: edit in CapCut → export MP4 → drag into Studio.
     * The client side runs a lightweight probe first (HTML5 <video> gives us
     * duration + resolution for free) and captures a thumbnail from a canvas
     * draw; both come in as siblings of the video file in this multipart
     * request. No server-side ffmpeg dependency.
     *
     * Max size defaults to 500MB — raise `upload_max_filesize`,
     * `post_max_size` (PHP) and `client_max_body_size` (nginx) accordingly
     * in each environment. See docs/capcut-upload-setup.md.
     */
    public function uploadVideo(Request $request, CreativeBrief $creativeBrief): JsonResponse
    {
        $maxKb = (int) config('content-planner.video_max_size_mb', 500) * 1024;

        $validated = $request->validate([
            'file'             => ["required", "file", "max:{$maxKb}", "mimetypes:video/mp4,video/quicktime,video/x-m4v,video/webm"],
            'thumbnail'        => ['nullable', 'image', 'max:5120'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'width'            => ['nullable', 'integer', 'min:1', 'max:8192'],
            'height'           => ['nullable', 'integer', 'min:1', 'max:8192'],
        ]);

        $file     = $request->file('file');
        $dir      = "marketing/videos/{$creativeBrief->id}";
        $videoPath = $file->store($dir, 'public');

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store("{$dir}/thumbnails", 'public');
        }

        // If the brief is wired to a daily-basket post, also create the
        // canonical DailyBasketPostMedia row so the planner grid surfaces
        // the video alongside any photos already attached to the post.
        $media = null;
        if ($creativeBrief->daily_basket_post_id !== null) {
            $media = DB::transaction(function () use ($creativeBrief, $file, $videoPath, $thumbnailPath, $validated) {
                $nextOrder = (int) DailyBasketPostMedia::query()
                    ->where('daily_basket_post_id', $creativeBrief->daily_basket_post_id)
                    ->max('sort_order') + 1;

                return DailyBasketPostMedia::query()->create([
                    'daily_basket_post_id' => $creativeBrief->daily_basket_post_id,
                    'disk'                 => 'public',
                    'path'                 => $videoPath,
                    'original_filename'    => $file->getClientOriginalName(),
                    'mime_type'            => $file->getMimeType(),
                    'size_bytes'           => $file->getSize(),
                    'width'                => $validated['width'] ?? null,
                    'height'               => $validated['height'] ?? null,
                    'duration_seconds'     => $validated['duration_seconds'] ?? null,
                    'thumbnail_path'       => $thumbnailPath,
                    'sort_order'           => $nextOrder,
                ]);
            });
        }

        // Always append to the brief's media_slots + state snapshot so the
        // SPA can render a preview immediately, even for standalone briefs
        // (no post yet).
        $slotEntry = [
            'kind'             => 'video',
            'source'           => 'capcut',
            'disk'             => 'public',
            'path'             => $videoPath,
            'thumbnail_path'   => $thumbnailPath,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'width'            => $validated['width'] ?? null,
            'height'           => $validated['height'] ?? null,
            'mime_type'        => $file->getMimeType(),
            'size_bytes'       => $file->getSize(),
            'media_id'         => $media?->id,
            'uploaded_at'      => now()->toIso8601String(),
        ];

        $slots = $creativeBrief->media_slots ?? [];
        $slots[] = $slotEntry;

        $state = $creativeBrief->state ?? [];
        $state['capcut'] = array_values(array_filter(
            array_merge($state['capcut'] ?? [], [$slotEntry]),
            fn ($v) => is_array($v),
        ));

        $creativeBrief->forceFill([
            'media_slots' => $slots,
            'state'       => $state,
            // Keep the first-uploaded duration on the brief for AI prompts.
            'duration_sec' => $creativeBrief->duration_sec ?? ($validated['duration_seconds'] ?? null),
        ])->save();

        return response()->json([
            'creative_brief' => $this->serialize($creativeBrief->refresh(), includeState: true),
            'media'          => $media ? [
                'id'               => $media->id,
                'url'              => $media->url,
                'thumbnail_url'    => $media->thumbnail_url,
                'duration_seconds' => $media->duration_seconds,
                'width'            => $media->width,
                'height'           => $media->height,
                'size_bytes'       => $media->size_bytes,
            ] : null,
            'slot' => $slotEntry,
        ], 201);
    }

    private function serialize(CreativeBrief $brief, bool $includeState = false, bool $includeProduct = false): array
    {
        $data = [
            'id'                   => $brief->id,
            'daily_basket_post_id' => $brief->daily_basket_post_id,
            'template_id'          => $brief->template_id,
            'template_slug'        => $brief->template?->slug,
            'post_type'            => $brief->post_type,
            'aspect'               => $brief->aspect,
            'duration_sec'         => $brief->duration_sec,
            'caption_sq'           => $brief->caption_sq,
            'caption_en'           => $brief->caption_en,
            'hashtags'             => $brief->hashtags,
            'music_id'             => $brief->music_id,
            'script'               => $brief->script,
            'media_slots'          => $brief->media_slots,
            'suggested_time'       => $brief->suggested_time?->toIso8601String(),
            'source'               => $brief->source,
            'ai_prompt_version'    => $brief->ai_prompt_version,
            'created_at'           => $brief->created_at?->toIso8601String(),
            'updated_at'           => $brief->updated_at?->toIso8601String(),
        ];

        if ($includeState) {
            $data['state'] = $brief->state;
        }

        if ($includeProduct) {
            // AI caption endpoints need a product context. Expose the
            // first item group from the linked daily-basket post so the
            // editor's "Generate" button works out of the box.
            $firstGroup = $brief->dailyBasketPost?->itemGroups?->first();
            $data['primary_item_group_id'] = $firstGroup?->id;
            $data['primary_item_group_name'] = $firstGroup?->name ?? null;
        }

        return $data;
    }
}
