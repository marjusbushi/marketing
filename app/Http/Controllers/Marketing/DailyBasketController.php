<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\DailyBasketPostStage;
use App\Enums\DailyBasketPostType;
use App\Http\Controllers\Controller;
use App\Models\DailyBasket;
use App\Models\DailyBasketPost;
use App\Models\DailyBasketPostMedia;
use App\Models\Content\ContentMedia;
use App\Models\Marketing\CreativeBrief;
use App\Services\ContentPlanner\ContentMediaService;
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
        if ($request->boolean('fresh')) {
            Cache::forget($cacheKey);
        }

        $weeks = Cache::remember($cacheKey, 60, function () use ($start, $end) {
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
            'posts.media',
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
                'reference_host' => $post->reference_host,
                'reference_notes' => $post->reference_notes,
                'hashtags' => $post->hashtags,
                'lokacioni' => $post->lokacioni,
                'modelet' => $post->modelet,
                'audienca' => $post->audienca,
                'media' => $post->media->map(fn ($m) => $this->serializeMedia($m))->values()->all(),
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
     * Coverage payload for the Shporta Ditore v2 product rail.
     *
     * Returns the products assigned to THIS basket's date (primary or
     * secondary re-marketing assignment) annotated with a per-product
     * `posts_count` — how many daily_basket_posts in this basket feature
     * that product. The rail uses this to render the cov-0/1/2 badge, the
     * filter chip ("të pambuluara") and the click-to-highlight selection.
     *
     * The response also carries summary totals the strip above the grid
     * renders in one glance: products covered/uncovered, posts per type,
     * total stock and total retail value of the day's basket.
     *
     * A single round-trip avoids N+1 queries from the frontend when the
     * user attaches/detaches products and the rail re-fetches.
     */
    public function coverage(Request $request, DailyBasket $basket): JsonResponse
    {
        $basket->load(['posts' => fn ($q) => $q]);

        // ?fresh=1 bypasses the 60s cache so a manual refresh always pulls
        // the latest stock/value/assignments from DIS.
        $bypassCache = $request->boolean('fresh');
        $collectionProducts = $this->loadCollectionProducts($basket->distribution_week_id, $bypassCache);

        $postIds = $basket->posts->pluck('id')->all();
        $pivotCountByProduct = empty($postIds)
            ? []
            : DB::table('daily_basket_post_products')
                ->whereIn('daily_basket_post_id', $postIds)
                ->selectRaw('item_group_id, COUNT(DISTINCT daily_basket_post_id) as posts_count')
                ->groupBy('item_group_id')
                ->pluck('posts_count', 'item_group_id')
                ->all();

        $payload = $this->computeCoverageData(
            basketDate: $basket->date->toDateString(),
            collectionProducts: $collectionProducts,
            pivotCountByProduct: $pivotCountByProduct,
            posts: $basket->posts->all(),
        );

        return response()->json([
            'basket_id' => $basket->id,
            'basket_date' => $basket->date->toDateString(),
            'products' => $payload['products'],
            'summary' => $payload['summary'],
        ]);
    }

    /**
     * Pure computation for coverage data — extracted so the unit test can
     * feed synthetic inputs without spinning up a DIS connection or a DB.
     *
     * @param  string  $basketDate                  Y-m-d of the basket
     * @param  array<int,array<string,mixed>>  $collectionProducts  Shape from loadCollectionProducts()
     * @param  array<int,int>  $pivotCountByProduct  item_group_id => posts_count
     * @param  iterable<DailyBasketPost>  $posts
     * @return array{products: array<int,array<string,mixed>>, summary: array<string,mixed>}
     */
    private function computeCoverageData(
        string $basketDate,
        array $collectionProducts,
        array $pivotCountByProduct,
        iterable $posts,
    ): array {
        // Filter collection products down to those assigned to THIS day —
        // matches Task #1137 convention: primary OR secondary re-marketing
        // assignment counts; rail shows everything the user planned for today.
        $basketProducts = array_values(array_filter($collectionProducts, function ($p) use ($basketDate) {
            foreach ((array) ($p['assigned_dates'] ?? []) as $a) {
                if (($a['date'] ?? null) === $basketDate) {
                    return true;
                }
            }
            return false;
        }));

        $products = array_map(function ($p) use ($pivotCountByProduct) {
            $id = (int) ($p['id'] ?? 0);
            $price = (float) ($p['pricelist_price'] ?? $p['avg_price'] ?? 0);
            $stock = (int) ($p['total_stock'] ?? 0);
            $postsCount = (int) ($pivotCountByProduct[$id] ?? 0);

            $tags = [];
            $classification = $p['classification'] ?? null;
            if (is_string($classification) && $classification !== '') {
                $tags[] = $classification;
            }

            return [
                'item_group_id' => $id,
                'name' => $p['name'] ?? 'Pa emër',
                'sku' => $p['code'] ?? null,
                'thumbnail_url' => $p['image_url'] ?? null,
                'price' => round($price, 2),
                'stock' => $stock,
                'total_value' => round($price * $stock, 2),
                'tags' => $tags,
                'posts_count' => $postsCount,
            ];
        }, $basketProducts);

        $total = count($products);
        $covered = 0;
        foreach ($products as $p) {
            if ($p['posts_count'] > 0) {
                $covered++;
            }
        }

        $postsByType = ['reel' => 0, 'photo' => 0, 'story' => 0, 'carousel' => 0, 'video' => 0];
        foreach ($posts as $post) {
            $type = $post->post_type?->value ?? 'photo';
            if (! array_key_exists($type, $postsByType)) {
                $postsByType[$type] = 0;
            }
            $postsByType[$type]++;
        }

        return [
            'products' => $products,
            'summary' => [
                'products_total' => $total,
                'products_covered' => $covered,
                'products_uncovered' => $total - $covered,
                'posts_total' => is_countable($posts) ? count($posts) : iterator_count($posts),
                'posts_by_type' => $postsByType,
                'stok_total' => (int) array_sum(array_column($products, 'stock')),
                'vlere_total' => round((float) array_sum(array_column($products, 'total_value')), 2),
            ],
        ];
    }

    /**
     * Fetch the item_groups of a DIS distribution_week, shaped for the
     * product picker UI. Cached 60 seconds — short TTL keeps stock/value
     * fresh while still absorbing the burst of identical reads that happen
     * when the rail re-renders. Pass $bypassCache=true to forge a brand-new
     * read (used by the manual "Rifresko" button + write hooks).
     *
     * Returns an empty array (plus logs) on DIS errors — the basket UI
     * stays usable even when DIS is unreachable.
     */
    private function loadCollectionProducts(int $distributionWeekId, bool $bypassCache = false): array
    {
        $cacheKey = 'daily_basket:collection_products:'.$distributionWeekId;
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 60, function () use ($distributionWeekId) {
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
                // Per-day assignments — empty array when the product has no
                // caktime yet. Frontend (#1137 panorama) filters by selected
                // day; modali i postit i injoron (sheh gjithcka — orientim, jo
                // kufizim). Fallback logjik kalon te frontend.
                'assigned_dates' => array_values(array_map(
                    fn ($a) => [
                        'id' => (int) ($a['id'] ?? 0),
                        'date' => $a['date'] ?? null,
                        'is_primary' => (bool) ($a['is_primary'] ?? false),
                    ],
                    $g['assigned_dates'] ?? []
                )),
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
            'lokacioni' => 'nullable|string|max:255',
            'modelet' => 'nullable|string|max:255',
            'audienca' => 'nullable|string|max:255',
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
        // `present` (not `required`) so removing the last product on a post
        // sends `product_ids: []` and clears the pivot. `required` rejects
        // empty arrays, which broke the chip's × button when only one
        // product was attached.
        $validated = $request->validate([
            'product_ids' => 'present|array',
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

            // Handoff to Content Planner happens at SCHEDULING — that is the
            // moment the user has committed date + platform + caption, so the
            // post is ready to be tracked by the planner as a "scheduled" item.
            //
            // PUBLISHED used to trigger handoff, but UX-wise that was confusing:
            // users saw a post with a scheduled time and expected it in the
            // planner, but had to do one more click. Moving handoff to SCHEDULING
            // matches intuition — the content_post_id then survives subsequent
            // PUBLISHED transitions (that stage now represents "live on socials").
            //
            // Defensive: fall through to create at PUBLISHED too, so any old
            // basket posts that made it to PUBLISHED without a content_post_id
            // (before this change) still get synced on re-transition.
            $shouldHandoff = in_array($target, [
                DailyBasketPostStage::SCHEDULING,
                DailyBasketPostStage::PUBLISHED,
            ], true);
            if ($shouldHandoff && $post->content_post_id === null) {
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

        // The creative brief (#1247/#1248/#1249) is the canonical source for
        // the *composed* post — caption, hashtags, and final media. We fall
        // back to the post's own fields only when no brief is wired up
        // (legacy posts created before the pivot editor).
        $brief = $post->creative_brief_id
            ? CreativeBrief::query()->find($post->creative_brief_id)
            : null;

        $caption = $brief?->caption_sq ?: $post->caption;

        $hashtags = is_array($brief?->hashtags ?? null) && ! empty($brief->hashtags)
            ? implode(' ', $brief->hashtags)
            : (string) ($post->hashtags ?? '');

        $notes = trim(
            ($hashtags ? $hashtags."\n\n" : '').
            'From Shporta Ditore #'.$post->daily_basket_id.' · post #'.$post->id.
            ($brief ? ' · brief #'.$brief->id : '')
        );

        // Media handoff strategy:
        //   1. If the brief has media_slots (Canva designs or CapCut uploads),
        //      clone those into ContentMedia — these are the *finished*
        //      assets the user produced in Studio.
        //   2. Fall back to product images only when the brief path
        //      produced nothing (or no brief exists) so legacy basket posts
        //      still render thumbnails in the planner.
        $mediaIds = $brief ? $this->cloneBriefMediaToContentMedia($brief) : [];
        if (empty($mediaIds)) {
            $mediaIds = $this->cloneProductImagesToMedia($post);
        }

        // Map the basket's post_type into Content Planner's content_type.
        // Stories need to go to the Stories strip; everything else is a feed post.
        // Keep the map explicit so reels/carousels render correctly too.
        $contentType = match ($post->post_type?->value) {
            'story'    => 'story',
            'reel'     => 'reel',
            'carousel' => 'carousel',
            default    => 'post',   // photo, video, or null
        };

        // approval_type='none' is intentional and explicit: the Daily Basket
        // production workflow IS the approval gate for content sourced this
        // way. By the time a basket post reaches the SCHEDULING stage it has
        // already moved through the basket's own production checkpoints. We
        // pass it explicitly (rather than relying on the column default) so
        // the bypass is visible to anyone reading this code, and so a future
        // change to the column default doesn't silently re-introduce a
        // second-gate requirement that would block every basket transition.
        $contentPost = $this->contentPostService->createPost([
            'platform' => $platform,
            'platforms' => $platforms,
            'content' => $caption,
            'content_type' => $contentType,
            'scheduled_at' => $post->scheduled_for?->toIso8601String(),
            'status' => 'scheduled',
            'approval_type' => 'none',
            'notes' => $notes,
            'media_ids' => $mediaIds,
        ], $this->currentUserId() ?? 1);

        return $contentPost->id;
    }

    /**
     * Convert a brief's media_slots (Canva exports + CapCut uploads) into
     * ContentMedia rows. Canva entries carry a public asset_url we download;
     * CapCut entries already live on our public disk so we reference the
     * existing file directly. Failures are swallowed and reported — the
     * planner still gets created even if media attachment partially fails.
     *
     * @return array<int,int>  List of ContentMedia ids in media_slots order.
     */
    private function cloneBriefMediaToContentMedia(CreativeBrief $brief): array
    {
        $ids = [];

        foreach ((array) ($brief->media_slots ?? []) as $slot) {
            if (! is_array($slot)) continue;
            $kind = (string) ($slot['kind'] ?? '');

            try {
                if ($kind === 'canva') {
                    $url = (string) ($slot['url'] ?? $slot['asset_url'] ?? '');
                    if ($url === '') continue;
                    $media = $this->createMediaFromUrl($url, [
                        'code' => 'canva-'.($slot['design_id'] ?? $brief->id),
                        'name' => 'Canva design',
                    ]);
                    if ($media) $ids[] = $media->id;
                } elseif ($kind === 'video' && ($slot['source'] ?? null) === 'capcut') {
                    $path = (string) ($slot['path'] ?? '');
                    if ($path === '' || ! Storage::disk('public')->exists($path)) continue;
                    $media = ContentMedia::create([
                        'uuid'              => (string) Str::uuid(),
                        'user_id'           => $this->currentUserId() ?? 1,
                        'filename'          => basename($path),
                        'original_filename' => basename($path),
                        'disk'              => 'public',
                        'path'              => $path,
                        'mime_type'         => (string) ($slot['mime_type'] ?? 'video/mp4'),
                        'size_bytes'        => (int) ($slot['size_bytes'] ?? Storage::disk('public')->size($path)),
                        'alt_text'          => 'Brief #'.$brief->id.' video',
                        'folder'            => 'videos',
                        'stage'             => 'final',
                    ]);
                    $ids[] = $media->id;
                } elseif ($kind === 'photo' && ($slot['source'] ?? null) === 'upload') {
                    // Rruga C fallback: direct photo uploads live on the
                    // public disk already, so just clone the row into
                    // ContentMedia without re-downloading.
                    $path = (string) ($slot['path'] ?? '');
                    if ($path === '' || ! Storage::disk('public')->exists($path)) continue;
                    $media = ContentMedia::create([
                        'uuid'              => (string) Str::uuid(),
                        'user_id'           => $this->currentUserId() ?? 1,
                        'filename'          => basename($path),
                        'original_filename' => basename($path),
                        'disk'              => 'public',
                        'path'              => $path,
                        'mime_type'         => (string) ($slot['mime_type'] ?? 'image/jpeg'),
                        'size_bytes'        => (int) ($slot['size_bytes'] ?? Storage::disk('public')->size($path)),
                        'alt_text'          => 'Brief #'.$brief->id.' photo',
                        'folder'            => 'photos',
                        'stage'             => 'final',
                    ]);
                    $ids[] = $media->id;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $ids;
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
            'folder'            => 'photos',
            'stage'             => 'final',
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

    // ─── Post media (inline uploader) ────────────────────────

    /**
     * Upload a media asset (photo/video) for a daily-basket post. For non-
     * carousel posts the uploader replaces the existing asset (we delete
     * any prior rows); for carousel it appends at the end of sort_order.
     */
    public function uploadMedia(Request $request, DailyBasketPost $post): JsonResponse
    {
        // Limit dinamik sipas mime-type: video deri 500MB, image deri 25MB.
        $uploaded = $request->file('file');
        $mime = $uploaded?->getMimeType() ?? '';
        if (str_starts_with($mime, 'video/')) {
            $maxSize = (int) config('content-planner.video_max_size_mb', 500) * 1024;
        } elseif (str_starts_with($mime, 'image/')) {
            $maxSize = (int) config('content-planner.photo_max_size_mb', 25) * 1024;
        } else {
            $maxSize = (int) config('content-planner.media_max_size_mb', 50) * 1024;
        }

        $request->validate([
            'file' => "required|file|max:{$maxSize}",
        ]);

        $file = $request->file('file');
        $isCarousel = $post->post_type->value === 'carousel';

        if (! $isCarousel) {
            foreach ($post->media()->get() as $old) {
                $this->deleteMediaFiles($old);
                $old->delete();
            }
        }

        $path = $file->store('daily-basket-media/' . $post->id, 'public');
        [$width, $height] = $this->probeImageDimensions($file);

        $nextOrder = (int) ($post->media()->max('sort_order') ?? -1) + 1;

        $media = $post->media()->create([
            'disk' => 'public',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'sort_order' => $nextOrder,
        ]);

        return response()->json($this->serializeMedia($media), 201);
    }

    /**
     * Attach existing ContentMedia records (from Media Library) to this post.
     * Copies metadata (path/disk/mime/etc.) into daily_basket_post_media so the
     * basket-side flow remains uniform. The underlying file is NOT re-uploaded
     * — both records reference the same storage path.
     */
    public function attachFromLibrary(Request $request, DailyBasketPost $post): JsonResponse
    {
        $validated = $request->validate([
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer|exists:content_media,id',
        ]);

        $isCarousel = $post->post_type->value === 'carousel';

        // For non-carousel posts, replace any existing media.
        if (! $isCarousel) {
            foreach ($post->media()->get() as $old) {
                $this->deleteMediaFiles($old);
                $old->delete();
            }
        }

        $nextOrder = (int) ($post->media()->max('sort_order') ?? -1) + 1;
        $created = [];
        $attachedContentMedia = [];

        $contentMedia = ContentMedia::whereIn('id', $validated['media_ids'])->get()->keyBy('id');

        foreach ($validated['media_ids'] as $mid) {
            $cm = $contentMedia->get($mid);
            if (! $cm) continue;

            $media = $post->media()->create([
                'disk' => $cm->disk ?? 'public',
                'path' => $cm->path,
                'original_filename' => $cm->original_filename,
                'mime_type' => $cm->mime_type,
                'size_bytes' => $cm->size_bytes,
                'width' => $cm->width,
                'height' => $cm->height,
                'duration_seconds' => $cm->duration_seconds,
                'sort_order' => $nextOrder++,
            ]);

            $created[] = $this->serializeMedia($media);
            $attachedContentMedia[] = $cm;

            // For non-carousel, stop after first (replace semantics).
            if (! $isCarousel) break;
        }

        // Auto-link transitive: inherit the post's products and the basket's
        // collection (distribution_week_id) onto each linked ContentMedia. The
        // user manually linked these once (or not at all) — doing it here keeps
        // the media library filters in sync with basket usage.
        if (! empty($attachedContentMedia)) {
            $mediaService = app(ContentMediaService::class);

            $postProductIds = DB::table('daily_basket_post_products')
                ->where('daily_basket_post_id', $post->id)
                ->pluck('item_group_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            $weekId = optional($post->basket()->first())->distribution_week_id;
            $weekIds = $weekId ? [(int) $weekId] : [];

            foreach ($attachedContentMedia as $cm) {
                if (! empty($postProductIds)) {
                    $mediaService->linkProducts($cm, $postProductIds, false);
                }
                if (! empty($weekIds)) {
                    $mediaService->linkCollections($cm, $weekIds, false);
                }
            }
        }

        return response()->json(['media' => $created], 201);
    }

    /**
     * Delete a single media asset. Fails (404) if the media doesn't belong
     * to the given post — cross-post access is not allowed.
     */
    public function deletePostMedia(DailyBasketPost $post, int $mediaId): JsonResponse
    {
        $media = $post->media()->where('id', $mediaId)->firstOrFail();

        $this->deleteMediaFiles($media);
        $media->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reorder carousel media. Accepts an array of media ids in the desired
     * order; anything not listed is pushed to the end unchanged.
     */
    public function reorderPostMedia(Request $request, DailyBasketPost $post): JsonResponse
    {
        $validated = $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($post, $validated) {
            foreach ($validated['media_ids'] as $idx => $mediaId) {
                $post->media()
                    ->where('id', $mediaId)
                    ->update(['sort_order' => $idx]);
            }
        });

        return response()->json(['success' => true]);
    }

    private function deleteMediaFiles(DailyBasketPostMedia $media): void
    {
        try {
            // Attach-from-library can produce multiple DailyBasketPostMedia
            // rows pointing to the same underlying path (same ContentMedia
            // referenced by different posts). Deleting the file blindly
            // would break every other post that shares it.
            //
            // Reference count across DailyBasketPostMedia AND ContentMedia;
            // only unlink the physical file when no other record remains.
            if ($media->path) {
                $sharedInBasket = DailyBasketPostMedia::where('path', $media->path)
                    ->where('id', '!=', $media->id)
                    ->exists();
                $sharedInLibrary = \App\Models\Content\ContentMedia::where('path', $media->path)->exists();

                if (! $sharedInBasket && ! $sharedInLibrary) {
                    Storage::disk($media->disk ?: 'public')->delete($media->path);
                }
            }
            if ($media->thumbnail_path) {
                $thumbShared = DailyBasketPostMedia::where('thumbnail_path', $media->thumbnail_path)
                    ->where('id', '!=', $media->id)
                    ->exists();
                $thumbInLibrary = \App\Models\Content\ContentMedia::where('thumbnail_path', $media->thumbnail_path)->exists();

                if (! $thumbShared && ! $thumbInLibrary) {
                    Storage::disk($media->disk ?: 'public')->delete($media->thumbnail_path);
                }
            }
        } catch (\Throwable $e) {
            // Storage unlink failures shouldn't block DB cleanup; orphan
            // files can be reaped by a separate janitor if needed.
        }
    }

    private function probeImageDimensions($file): array
    {
        try {
            $info = @getimagesize($file->getRealPath());
            if (is_array($info)) {
                return [$info[0] ?? null, $info[1] ?? null];
            }
        } catch (\Throwable $e) {
            // Non-image files (videos) just get null dimensions.
        }

        return [null, null];
    }

    private function serializeMedia(DailyBasketPostMedia $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'thumbnail_url' => $media->thumbnail_url,
            'is_video' => $media->is_video,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'original_filename' => $media->original_filename,
            'width' => $media->width,
            'height' => $media->height,
            'duration_seconds' => $media->duration_seconds,
            'sort_order' => $media->sort_order,
        ];
    }

    // ─── Guards ──────────────────────────────────────────────

    /**
     * Return null if the post can leave this stage, or a user-facing
     * reason otherwise.
     */
    private function cannotLeave(DailyBasketPost $post, DailyBasketPostStage $stage): ?string
    {
        return match ($stage) {
            DailyBasketPostStage::PLANNING => (empty($post->reference_url) && empty($post->reference_notes))
                ? 'Duhet një reference (URL ose shënime) para se të kalojmë në prodhim.'
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
