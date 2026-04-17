<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\DailyBasketPostStage;
use App\Enums\DailyBasketPostType;
use App\Http\Controllers\Controller;
use App\Models\DailyBasket;
use App\Models\DailyBasketPost;
use App\Models\Content\ContentMedia;
use App\Services\ContentPlanner\ContentPostService;
use App\Services\DisApiClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Shporta Ditore — bridge between Merch Calendar and Content Planner.
 *
 * Flow:
 *   DIS Merch Calendar (collections) → Shporta Ditore (daily posts with
 *   5-stage pipeline) → Content Planner (publish to socials).
 *
 * Read-only access to DIS data goes through the existing DisApiClient
 * (for week + item group details); writes stay local to za-marketing.
 */
class DailyBasketController extends Controller
{
    public function __construct(
        private DisApiClient $disApi,
        private ContentPostService $contentPostService,
    ) {}

    // ─── Page views ──────────────────────────────────────────

    /**
     * Landing page. Shows the active collection (DistributionWeek) and the
     * strip of its N daily baskets. Defaults to today's date within the
     * current collection.
     */
    public function index(Request $request): View
    {
        return view('daily-basket.index');
    }

    // ─── JSON endpoints ──────────────────────────────────────

    /**
     * List collections (distribution weeks) the user can pick from.
     * Spans a 3-month window (last month → next 2 months) so the picker
     * shows recent, active, and upcoming collections in one place.
     */
    public function listCollections(Request $request): JsonResponse
    {
        $start = $request->input('start', now()->subMonth()->startOfMonth()->toDateString());
        $end   = $request->input('end',   now()->addMonths(2)->endOfMonth()->toDateString());

        $cacheKey = 'daily_basket:collection_list:'.$start.':'.$end;

        $weeks = Cache::remember($cacheKey, 300, function () use ($start, $end) {
            try {
                return $this->disApi->listWeeks($start, $end);
            } catch (\Throwable $e) {
                report($e);
                return [];
            }
        });

        // Shape minimal payload for the picker + flag the "current" one
        $today = now()->toDateString();
        $items = array_map(function ($w) use ($today) {
            $ws = $w['week_start'] ?? null;
            $we = $w['week_end']   ?? null;
            $isCurrent = $ws && $we && $today >= $ws && $today <= $we;

            return [
                'id'                => (int) ($w['id'] ?? 0),
                'name'              => $w['name']       ?? 'Kolekcion',
                'week_start'        => $ws,
                'week_end'          => $we,
                'status'            => $w['status']     ?? null,
                'item_groups_count' => $w['item_groups_count'] ?? 0,
                'is_current'        => $isCurrent,
            ];
        }, $weeks);

        // Sort: current first, then upcoming, then past (within each group, by week_start desc)
        usort($items, function ($a, $b) use ($today) {
            $bucket = function ($w) use ($today) {
                if ($w['is_current']) return 0;
                if (($w['week_start'] ?? '') > $today) return 1; // upcoming
                return 2; // past
            };
            $ba = $bucket($a);
            $bb = $bucket($b);
            if ($ba !== $bb) return $ba <=> $bb;
            return strcmp($b['week_start'] ?? '', $a['week_start'] ?? '');
        });

        return response()->json($items);
    }

    /**
     * Summary of a whole collection: the week itself + one entry per day.
     *
     * On first open we eagerly upsert a `DailyBasket` row for every day
     * inside the collection window, so all days show up as clickable in
     * the strip (not only the one the user has already visited).
     */
    public function collectionSummary(int $distributionWeekId): JsonResponse
    {
        $week = $this->disApi->getWeek($distributionWeekId);

        $start = Carbon::parse($week['week_start']);
        $end = Carbon::parse($week['week_end']);

        $this->ensureBasketsForRange($distributionWeekId, $start, $end);

        $baskets = DailyBasket::query()
            ->where('distribution_week_id', $distributionWeekId)
            ->withCount([
                'posts',
                'posts as published_count' => fn ($q) => $q->where('stage', DailyBasketPostStage::PUBLISHED->value),
            ])
            ->get()
            ->keyBy(fn ($b) => $b->date->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $basket = $baskets->get($key);
            $days[] = [
                'date' => $key,
                'basket_id' => $basket?->id,
                'status' => $basket?->status,
                'posts_total' => $basket?->posts_count ?? 0,
                'posts_published' => $basket?->published_count ?? 0,
            ];
        }

        return response()->json([
            'collection' => [
                'id' => $week['id'],
                'name' => $week['name'],
                'week_start' => $week['week_start'],
                'week_end' => $week['week_end'],
                'status' => $week['status'] ?? null,
            ],
            'days' => $days,
        ]);
    }

    /**
     * Upsert one DailyBasket per day in the collection window.
     *
     * Uses a single bulk insertOrIgnore so opening a collection for the
     * first time creates N baskets in one round-trip, and subsequent
     * opens are idempotent.
     */
    private function ensureBasketsForRange(int $distributionWeekId, Carbon $start, Carbon $end): void
    {
        $existingDates = DailyBasket::query()
            ->where('distribution_week_id', $distributionWeekId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->all();

        $existing = array_flip($existingDates);
        $rows = [];
        $now = now();
        $creatorId = $this->currentUserId();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->toDateString();
            if (isset($existing[$dateStr])) {
                continue;
            }
            $rows[] = [
                'distribution_week_id' => $distributionWeekId,
                'date' => $dateStr,
                'status' => 'draft',
                'created_by' => $creatorId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            // insertOrIgnore protects against race conditions on the
            // (distribution_week_id, date) unique index.
            DailyBasket::insertOrIgnore($rows);
        }
    }

    /**
     * The Kanban view of one day's basket.
     * Auto-creates the basket row if it doesn't exist yet (drafts), loads
     * the available products from the linked DIS collection (cached 5 min),
     * and enriches each post's products with their current DIS metadata.
     */
    public function show(int $distributionWeekId, string $date): JsonResponse
    {
        $dateObj = Carbon::parse($date)->startOfDay();

        $basket = DailyBasket::firstOrCreate(
            [
                'distribution_week_id' => $distributionWeekId,
                'date' => $dateObj->toDateString(),
            ],
            [
                'status' => 'draft',
                'created_by' => $this->currentUserId(),
            ]
        );

        $basket->load([
            'posts' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        // Pull products for this collection (cached) — the single source of
        // product data for the whole page.
        $availableProducts = $this->loadCollectionProducts($distributionWeekId);
        $productLookup = collect($availableProducts)->keyBy('id');

        // Pivot rows per post — cross-DB items are attached by id only.
        $postIds = $basket->posts->pluck('id');
        $pivotRows = DB::table('daily_basket_post_products')
            ->whereIn('daily_basket_post_id', $postIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('daily_basket_post_id');

        $byStage = [];
        foreach (DailyBasketPostStage::cases() as $stage) {
            $byStage[$stage->value] = [];
        }

        foreach ($basket->posts as $post) {
            $rows = $pivotRows->get($post->id, collect());
            $byStage[$post->stage->value][] = [
                'id' => $post->id,
                'title' => $post->title,
                'post_type' => $post->post_type->value,
                'post_type_label' => $post->post_type->label(),
                'stage' => $post->stage->value,
                'stage_label' => $post->stage->label(),
                'priority' => $post->priority,
                'target_platforms' => $post->target_platforms ?? [],
                'scheduled_for' => $post->scheduled_for?->toIso8601String(),
                'assigned_to' => $post->assigned_to,
                'caption' => $post->caption,
                'reference_url' => $post->reference_url,
                'products' => $rows->map(function ($r) use ($productLookup) {
                    $meta = $productLookup->get($r->item_group_id);

                    return [
                        'item_group_id' => $r->item_group_id,
                        'sort_order' => $r->sort_order,
                        'is_hero' => (bool) $r->is_hero,
                        // Enrichment from DIS (null-safe for stale references)
                        'name' => $meta['name'] ?? null,
                        'code' => $meta['code'] ?? null,
                        'image_url' => $meta['image_url'] ?? null,
                        'classification' => $meta['classification'] ?? null,
                    ];
                })->values()->all(),
            ];
        }

        return response()->json([
            'basket' => [
                'id' => $basket->id,
                'distribution_week_id' => $basket->distribution_week_id,
                'date' => $basket->date->toDateString(),
                'status' => $basket->status,
                'notes' => $basket->notes,
            ],
            'columns' => array_map(
                fn (DailyBasketPostStage $s) => [
                    'key' => $s->value,
                    'label' => $s->label(),
                    'color' => $s->color(),
                    'count' => count($byStage[$s->value]),
                    'posts' => $byStage[$s->value],
                ],
                DailyBasketPostStage::cases(),
            ),
            'available_products' => $availableProducts,
        ]);
    }

    /**
     * Fetch the item_groups of a DIS distribution_week, shaped for the
     * product picker UI. Cached 5 minutes.
     *
     * Returns an empty array (plus logs) on DIS errors — the basket UI
     * stays usable even when DIS is unreachable.
     */
    private function loadCollectionProducts(int $distributionWeekId): array
    {
        $cacheKey = 'daily_basket:collection_products:'.$distributionWeekId;

        return Cache::remember($cacheKey, 300, function () use ($distributionWeekId) {
            try {
                $week = $this->disApi->getWeek($distributionWeekId);
            } catch (\Throwable $e) {
                report($e);

                return [];
            }

            $groups = $week['item_groups'] ?? [];

            return array_map(fn ($g) => [
                'id' => (int) ($g['id'] ?? 0),
                'code' => $g['code'] ?? null,
                'name' => $g['name'] ?? 'Unnamed',
                'vendor_name' => $g['vendor_name'] ?? null,
                'image_url' => $g['image_url'] ?? null,
                'avg_price' => $g['avg_price'] ?? null,
                'pricelist_price' => $g['pricelist_price'] ?? null,
                'classification' => $g['classification'] ?? null,
                'total_stock' => $g['total_stock'] ?? 0,
            ], $groups);
        });
    }

    /**
     * Create a new post inside a basket.
     */
    public function storePost(Request $request, int $basketId): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'post_type' => ['required', Rule::in(DailyBasketPostType::values())],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'reference_url' => 'nullable|url|max:500',
            'reference_notes' => 'nullable|string',
            'target_platforms' => 'nullable|array',
            'target_platforms.*' => 'string',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',
            'hero_product_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer',
        ]);

        $basket = DailyBasket::findOrFail($basketId);

        $post = $basket->posts()->create([
            'title' => $validated['title'],
            'post_type' => $validated['post_type'],
            'stage' => DailyBasketPostStage::PLANNING,
            'priority' => $validated['priority'] ?? 'normal',
            'reference_url' => $validated['reference_url'] ?? null,
            'reference_notes' => $validated['reference_notes'] ?? null,
            'target_platforms' => $validated['target_platforms'] ?? [],
            'assigned_to' => $validated['assigned_to'] ?? null,
        ]);

        if (! empty($validated['product_ids'])) {
            $heroId = $validated['hero_product_id'] ?? $validated['product_ids'][0];
            $now = now();
            $rows = [];
            foreach ($validated['product_ids'] as $idx => $groupId) {
                $rows[] = [
                    'daily_basket_post_id' => $post->id,
                    'item_group_id' => $groupId,
                    'sort_order' => $idx,
                    'is_hero' => $groupId === $heroId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('daily_basket_post_products')->insert($rows);
        }

        return response()->json(['id' => $post->id], 201);
    }

    /**
     * Edit an existing post. Does NOT change stage — see transitionPost().
     */
    public function updatePost(Request $request, DailyBasketPost $post): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'post_type' => ['sometimes', Rule::in(DailyBasketPostType::values())],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'reference_url' => 'nullable|url|max:500',
            'reference_notes' => 'nullable|string',
            'production_brief' => 'nullable|string',
            'caption' => 'nullable|string',
            'hashtags' => 'nullable|string',
            'target_platforms' => 'sometimes|array',
            'target_platforms.*' => 'string',
            'scheduled_for' => 'nullable|date',
            'assigned_to' => 'nullable|integer',
            'sort_order' => 'sometimes|integer',
        ]);

        // Only update fields that were actually sent.
        $post->fill($validated);
        $post->save();

        return response()->json(['id' => $post->id, 'stage' => $post->stage->value]);
    }

    /**
     * Attach / replace the product set of a post.
     *
     * Uses raw DB ops (instead of belongsToMany->sync) because the related
     * model lives on the DIS connection; Eloquent's sync() would try to
     * read the pivot table via DIS, which doesn't have it. Writing against
     * the DB facade directly keeps this on the mysql (za_marketing) DB.
     */
    public function syncProducts(Request $request, DailyBasketPost $post): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer',
            'hero_product_id' => 'nullable|integer',
        ]);

        $ids = $validated['product_ids'];
        $heroId = $validated['hero_product_id'] ?? ($ids[0] ?? null);

        DB::transaction(function () use ($post, $ids, $heroId) {
            DB::table('daily_basket_post_products')
                ->where('daily_basket_post_id', $post->id)
                ->delete();

            $rows = [];
            $now = now();
            foreach ($ids as $idx => $groupId) {
                $rows[] = [
                    'daily_basket_post_id' => $post->id,
                    'item_group_id' => $groupId,
                    'sort_order' => $idx,
                    'is_hero' => $groupId === $heroId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($rows)) {
                DB::table('daily_basket_post_products')->insert($rows);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Move the post to the next (or an explicit) stage.
     * Enforces simple progression guards: you can only move forward or
     * backward by one step, and some target stages need required fields.
     */
    public function transitionPost(Request $request, DailyBasketPost $post): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(DailyBasketPostStage::values())],
        ]);

        $target = DailyBasketPostStage::from($validated['stage']);
        $current = $post->stage;

        // Only one step forward or one step back.
        $delta = $target->order() - $current->order();
        if (abs($delta) !== 1) {
            return response()->json([
                'message' => "Nuk mund të kalësh nga '{$current->label()}' te '{$target->label()}' direkt — vetëm një hap në kah të njëjtë lejohet.",
            ], 422);
        }

        // Forward transitions have "definition of done" guards.
        if ($delta === 1) {
            $blockingReason = $this->cannotLeave($post, $current);
            if ($blockingReason !== null) {
                return response()->json(['message' => $blockingReason], 422);
            }
        }

        DB::transaction(function () use ($post, $target) {
            $post->stage = $target;

            // Handoff to Content Planner the moment the post is published.
            // Creates a content_posts row exactly once — if the basket post
            // is reverted and re-published, we reuse the existing link.
            if ($target === DailyBasketPostStage::PUBLISHED && $post->content_post_id === null) {
                $contentPostId = $this->handOffToContentPlanner($post);
                $post->content_post_id = $contentPostId;
            }

            $post->save();
        });

        return response()->json([
            'id' => $post->id,
            'stage' => $post->stage->value,
            'stage_label' => $post->stage->label(),
            'content_post_id' => $post->content_post_id,
        ]);
    }

    /**
     * Create the matching Content Planner post and return its id.
     *
     * The basket post carries the planning/production state; Content
     * Planner handles publishing + analytics. We copy: caption, platforms,
     * schedule, hashtags (as notes), and best-effort attach product images
     * as ContentMedia so the post renders with thumbnails in the planner.
     */
    private function handOffToContentPlanner(DailyBasketPost $post): int
    {
        $platforms = $post->target_platforms ?? [];
        $platform = count($platforms) === 1 ? $platforms[0] : 'multi';

        // Compose a small "notes" blob so the publisher can see provenance
        $notes = trim(
            ($post->hashtags ? $post->hashtags."\n\n" : '').
            'From Shporta Ditore #'.$post->daily_basket_id.' · post #'.$post->id
        );

        // Best-effort: attach product images as media so the planner shows them.
        // Any failure (DIS slow, download fail, storage fail) is logged but must
        // not block the publish — the ContentPost can still be created without media.
        $mediaIds = $this->cloneProductImagesToMedia($post);

        // Map the basket's post_type into Content Planner's content_type.
        // Stories need to go to the Stories strip; everything else is a feed post.
        // Keep the map explicit so reels/carousels render correctly too.
        $contentType = match ($post->post_type?->value) {
            'story'    => 'story',
            'reel'     => 'reel',
            'carousel' => 'carousel',
            default    => 'post',   // photo, video, or null
        };

        $contentPost = $this->contentPostService->createPost([
            'platform' => $platform,
            'platforms' => $platforms,
            'content' => $post->caption,
            'content_type' => $contentType,
            'scheduled_at' => $post->scheduled_for?->toIso8601String(),
            'status' => 'scheduled',
            'notes' => $notes,
            'media_ids' => $mediaIds,
        ], $this->currentUserId() ?? 1);

        return $contentPost->id;
    }

    /**
     * For each product linked to the post, download its image and create a
     * ContentMedia record, returning the media ids (in pivot order). Failures
     * are swallowed and reported — publishing proceeds even if 0 images land.
     */
    private function cloneProductImagesToMedia(DailyBasketPost $post): array
    {
        $basket = $post->basket()->first();
        if (! $basket) {
            return [];
        }

        $products = $this->loadCollectionProducts($basket->distribution_week_id);
        $byId = collect($products)->keyBy('id');

        $pivotRows = DB::table('daily_basket_post_products')
            ->where('daily_basket_post_id', $post->id)
            ->orderBy('sort_order')
            ->get();

        $ids = [];
        foreach ($pivotRows as $pivot) {
            $product = $byId->get($pivot->item_group_id);
            if (! $product || empty($product['image_url'])) {
                continue;
            }

            try {
                $media = $this->createMediaFromUrl($product['image_url'], $product);
                if ($media) {
                    $ids[] = $media->id;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $ids;
    }

    /**
     * Download a remote image URL and persist it as a ContentMedia row on the
     * public disk. Returns null when the fetch fails or the body is empty.
     */
    private function createMediaFromUrl(string $url, array $product): ?ContentMedia
    {
        $response = Http::timeout(8)->get($url);
        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($body === '' || $body === null) {
            return null;
        }

        $contentType = $response->header('Content-Type') ?: 'image/jpeg';
        // Strip any charset suffix (e.g. "image/jpeg; charset=binary")
        $mime = trim(explode(';', $contentType)[0]);
        $ext = match (strtolower($mime)) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default       => 'jpg',
        };

        $uuid = (string) Str::uuid();
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($product['code'] ?? 'na')) ?: 'na';
        $filename = 'product-'.$safeCode.'-'.substr($uuid, 0, 8).'.'.$ext;
        $path = 'content-media/daily-basket/'.date('Y/m').'/'.$filename;

        Storage::disk('public')->put($path, $body);

        return ContentMedia::create([
            'uuid'              => $uuid,
            'user_id'           => $this->currentUserId() ?? 1,
            'filename'          => $filename,
            'original_filename' => basename(parse_url($url, PHP_URL_PATH) ?: $filename),
            'disk'              => 'public',
            'path'              => $path,
            'mime_type'         => $mime,
            'size_bytes'        => strlen($body),
            'alt_text'          => $product['name'] ?? null,
        ]);
    }

    /**
     * Delete a post (soft delete).
     */
    public function deletePost(DailyBasketPost $post): JsonResponse
    {
        $post->delete();

        return response()->json(['success' => true]);
    }

    // ─── Guards ──────────────────────────────────────────────

    /**
     * Return null if the post can leave this stage, or a user-facing
     * reason otherwise.
     */
    private function cannotLeave(DailyBasketPost $post, DailyBasketPostStage $stage): ?string
    {
        return match ($stage) {
            DailyBasketPostStage::PLANNING => empty($post->reference_url)
                ? 'Duhet një reference para se të kalojmë në prodhim.'
                : null,

            DailyBasketPostStage::EDITING => empty($post->caption)
                ? 'Caption duhet plotësuar para se të kalojmë në skedulim.'
                : null,

            DailyBasketPostStage::SCHEDULING => empty($post->scheduled_for) || empty($post->target_platforms)
                ? 'Duhen data e skedulimit + paku një platformë para publikimit.'
                : null,

            default => null,
        };
    }

    private function currentUserId(): ?int
    {
        return auth()->id();
    }
}
