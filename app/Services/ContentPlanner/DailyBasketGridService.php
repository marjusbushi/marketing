<?php

namespace App\Services\ContentPlanner;

use App\Enums\DailyBasketPostStage;
use App\Models\DailyBasketPost;
use App\Services\DisApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Expose daily_basket_posts (those not yet linked to a ContentPost) as grid
 * feed events, shaped like ContentPostService::getPostsForCalendar so the
 * Content Planner grid renders all three sources — planned ContentPost,
 * external Meta/TikTok, daily-basket drafts — through the same pipeline.
 *
 * Daily-basket posts get a content_post_id once the user transitions them to
 * SCHEDULING (see DailyBasketController::handOffToContentPlanner). Filtering
 * by `content_post_id IS NULL` is the dedup boundary — after handoff the
 * ContentPost source covers them.
 */
class DailyBasketGridService
{
    public function __construct(
        private DisApiClient $disApi,
    ) {}

    /**
     * Return draft daily-basket posts in the [from, to] window as calendar
     * events. Platform filter intersects against `target_platforms` JSON.
     */
    public function getBasketDraftsForGrid(
        Carbon $from,
        Carbon $to,
        ?array $platforms = null,
    ): array {
        $posts = DailyBasketPost::query()
            ->with(['media', 'basket'])
            ->whereNull('content_post_id')
            ->whereHas('basket', fn ($q) => $q->whereBetween('date', [
                $from->toDateString(),
                $to->toDateString(),
            ]))
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        if ($platforms !== null && ! empty($platforms)) {
            // Drafts in early stages often have no target_platforms yet — the
            // user hasn't decided where to post. Keep those visible under any
            // filter (they're platform-agnostic until scheduling). Only hide
            // drafts whose explicit target list doesn't intersect the filter.
            $posts = $posts->filter(function (DailyBasketPost $p) use ($platforms) {
                $postPlatforms = (array) ($p->target_platforms ?? []);
                if (empty($postPlatforms)) {
                    return true;
                }
                return ! empty(array_intersect($postPlatforms, $platforms));
            })->values();
        }

        $resolvedThumb = $this->resolveProductThumbnails($posts);

        return $posts->map(fn (DailyBasketPost $post) => $this->shapeEvent(
            $post,
            $resolvedThumb[$post->id] ?? null,
        ))->values()->all();
    }

    /**
     * For posts without attached media, look up the hero product's image from
     * DIS (cached 5 min per distribution_week). Grouped by week so we only
     * hit DIS once per unique collection, even when the window spans many
     * baskets.
     *
     * @param  iterable<DailyBasketPost>  $posts
     * @return array<int,string>  [post_id => image_url]
     */
    private function resolveProductThumbnails($posts): array
    {
        $postsNeedingFallback = collect($posts)->filter(
            fn (DailyBasketPost $p) => $p->thumbnail_url === null && $p->basket !== null
        );

        if ($postsNeedingFallback->isEmpty()) {
            return [];
        }

        $weekIds = $postsNeedingFallback
            ->pluck('basket.distribution_week_id')
            ->unique()
            ->filter()
            ->values();

        $lookupByWeek = [];
        foreach ($weekIds as $weekId) {
            $lookupByWeek[$weekId] = $this->loadWeekImageLookup((int) $weekId);
        }

        $pivots = DB::table('daily_basket_post_products')
            ->whereIn('daily_basket_post_id', $postsNeedingFallback->pluck('id')->all())
            ->orderByDesc('is_hero')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('daily_basket_post_id');

        $resolved = [];
        foreach ($postsNeedingFallback as $post) {
            $rows = $pivots->get($post->id);
            if (! $rows || $rows->isEmpty()) {
                continue;
            }
            $weekLookup = $lookupByWeek[$post->basket->distribution_week_id] ?? [];
            foreach ($rows as $row) {
                $url = $weekLookup[$row->item_group_id] ?? null;
                if ($url) {
                    $resolved[$post->id] = $url;
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * [item_group_id => image_url] for a distribution_week, cached 5 min to
     * match DailyBasketController::loadCollectionProducts. Safe on DIS errors
     * — returns an empty lookup so the grid still renders (no thumbnail).
     */
    private function loadWeekImageLookup(int $weekId): array
    {
        $cacheKey = 'daily_basket_grid:week_image_lookup:'.$weekId;

        return Cache::remember($cacheKey, 300, function () use ($weekId) {
            try {
                $week = $this->disApi->getWeek($weekId);
            } catch (\Throwable $e) {
                report($e);
                return [];
            }

            $lookup = [];
            foreach (($week['item_groups'] ?? []) as $group) {
                $id = (int) ($group['id'] ?? 0);
                $url = $group['image_url'] ?? null;
                if ($id && $url) {
                    $lookup[$id] = $url;
                }
            }
            return $lookup;
        });
    }

    private function truncateTitle(?string $value, int $limit): string
    {
        if ($value === null) return '';
        $plain = strip_tags($value);
        return mb_strwidth($plain, 'UTF-8') <= $limit
            ? $plain
            : rtrim(mb_strimwidth($plain, 0, $limit, '', 'UTF-8')).'...';
    }

    private function shapeEvent(DailyBasketPost $post, ?string $fallbackThumb): array
    {
        $stage = $post->stage;
        $basket = $post->basket;
        $targetPlatforms = (array) ($post->target_platforms ?? []);
        // `thumbnail` must be an image URL the <img> tag can render. For
        // videos without a poster image we fall back to the product thumb
        // (from DIS) if one exists; otherwise null so the grid renders a
        // <video> tag via `first_media_url`.
        $thumbnail = $post->thumbnail_url ?? $fallbackThumb;
        $firstMediaUrl = $post->first_media_url ?? $fallbackThumb;
        $start = $post->scheduled_for
            ?? ($basket ? Carbon::parse($basket->date)->setTime(9, 0, 0) : now());

        $contentType = match ($post->post_type?->value) {
            'story'    => 'story',
            'reel'     => 'reel',
            'carousel' => 'carousel',
            'video'    => 'video',
            default    => 'post',
        };

        $platform = count($targetPlatforms) === 1
            ? $targetPlatforms[0]
            : (count($targetPlatforms) > 1 ? 'multi' : 'multi');

        $mediaItems = $post->media->map(fn ($m) => [
            'url'       => $m->url,
            'thumbnail' => $m->thumbnail_url ?? $m->url,
            'is_video'  => (bool) $m->is_video,
            'position'  => (int) $m->sort_order,
        ])->values()->all();

        return [
            'id'              => 'db_draft_'.$post->id,
            'title'           => $this->truncateTitle($post->caption ?: $post->title, 60),
            'start'           => $start->toIso8601String(),
            'allDay'          => $post->scheduled_for === null,
            'backgroundColor' => $stage->bgColor(),
            'borderColor'     => $stage->color(),
            'textColor'       => '#374151',
            'editable'        => false,
            'extendedProps'   => [
                'is_draft_basket'      => true,
                'is_external'          => false,
                'is_imported'          => false,
                'status'               => 'basket_'.$stage->value,
                'status_label'         => $stage->label(),
                'status_color'         => $stage->color(),
                'status_bg_color'      => $stage->bgColor(),
                'content'              => $post->caption ?: $post->title,
                'content_type'         => $contentType,
                'platform'             => $platform,
                'platform_icons'       => array_values($targetPlatforms),
                'thumbnail'            => $thumbnail,
                'first_media_url'      => $firstMediaUrl,
                'is_video'             => (bool) $post->is_video,
                'has_video'            => (bool) $post->is_video,
                'has_media'            => $thumbnail !== null || $firstMediaUrl !== null,
                'media_count'          => $post->media->count() ?: ($fallbackThumb ? 1 : 0),
                'media_items'          => $mediaItems,
                'labels'               => [],
                'user_name'            => null,
                'metrics'              => null,
                'post_stage'           => $stage->value,
                'post_type'            => $post->post_type?->value,
                'daily_basket_id'      => $post->daily_basket_id,
                'distribution_week_id' => $basket?->distribution_week_id,
                'basket_date'          => $basket ? Carbon::parse($basket->date)->toDateString() : null,
                'db_post_id'           => $post->id,
            ],
        ];
    }
}
