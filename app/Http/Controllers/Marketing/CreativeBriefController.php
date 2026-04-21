<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\DailyBasketPost;
use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\Template;
use App\Services\Marketing\CreativeBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $creativeBrief->load(['template', 'dailyBasketPost']);

        return response()->json([
            'creative_brief' => $this->serialize($creativeBrief, includeState: true),
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

    private function serialize(CreativeBrief $brief, bool $includeState = false): array
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
            'render_job_id'        => $brief->render_job_id,
            'created_at'           => $brief->created_at?->toIso8601String(),
            'updated_at'           => $brief->updated_at?->toIso8601String(),
        ];

        if ($includeState) {
            $data['state'] = $brief->state;
        }

        return $data;
    }
}
