<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\DailyBasketPostStage;
use App\Enums\MarketingPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\DailyBasketPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Production phone view — photographers claim, shoot, advance posts.
 *
 * Mirrors the spec at docs/superpowers/specs/2026-04-29-production-phone-view-design.md.
 * Reuses Laravel auth + marketing.permission middleware; no new auth stack.
 *
 * Each action is a thin wrapper: queue() / show() return Blade or JSON,
 * claim() / release() / advance() touch only DailyBasketPost rows
 * directly. Media uploads forward to the existing daily-basket endpoint.
 */
class ProductionController extends Controller
{
    public function queue(Request $request): View|JsonResponse
    {
        $userId = $request->user()->id;

        $base = DailyBasketPost::query()
            ->where('stage', DailyBasketPostStage::PRODUCTION->value)
            ->with(['basket', 'media' => fn ($q) => $q->limit(1)])
            ->orderByDesc('priority')
            ->orderBy('scheduled_for');

        $mine  = (clone $base)->where('claimed_by_user_id', $userId)->get();
        $free  = (clone $base)->whereNull('claimed_by_user_id')->get();
        $taken = (clone $base)
            ->whereNotNull('claimed_by_user_id')
            ->where('claimed_by_user_id', '!=', $userId)
            ->with('claimer:id,name')
            ->get();

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'mine'  => $this->serializeList($mine),
                'free'  => $this->serializeList($free),
                'taken' => $this->serializeList($taken, withClaimer: true),
            ]);
        }

        return view('production.queue', compact('mine', 'free', 'taken'));
    }

    public function show(DailyBasketPost $post): View
    {
        $post->load([
            'basket',
            'media',
            'itemGroups',
            'claimer:id,name',
        ]);

        $sameDay = DailyBasketPost::query()
            ->where('daily_basket_id', $post->daily_basket_id)
            ->where('stage', DailyBasketPostStage::PRODUCTION->value)
            ->orderByDesc('priority')
            ->orderBy('scheduled_for')
            ->pluck('id')
            ->all();
        $position = (array_search($post->id, $sameDay, true) === false ? 0 : array_search($post->id, $sameDay, true)) + 1;
        $totalToday = count($sameDay) ?: 1;

        $referencePreview = $this->referencePreview($post);

        $userId = auth()->id();
        $claimState = match (true) {
            $post->claimed_by_user_id === null    => 'free',
            $post->claimed_by_user_id === $userId => 'mine',
            default                                => 'taken',
        };

        return view('production.detail', compact('post', 'position', 'totalToday', 'referencePreview', 'claimState'));
    }

    public function claim(DailyBasketPost $post): JsonResponse
    {
        if ($post->stage->value !== DailyBasketPostStage::PRODUCTION->value) {
            return response()->json(['message' => 'Posti nuk është në fazë prodhimi.'], 422);
        }

        $userId = auth()->id();

        $affected = DB::table('daily_basket_posts')
            ->where('id', $post->id)
            ->whereNull('claimed_by_user_id')
            ->update([
                'claimed_by_user_id' => $userId,
                'claimed_at'         => now(),
                'updated_at'         => now(),
            ]);

        if ($affected === 0) {
            $post->refresh()->load('claimer:id,name');

            return response()->json([
                'message'          => 'Posti është marrë nga tjetri.',
                'claimed_by'       => $post->claimer?->name,
                'claimed_at_human' => $post->claimed_at?->diffForHumans(),
            ], 409);
        }

        $post->refresh();

        return response()->json([
            'ok'         => true,
            'claimed_at' => $post->claimed_at?->toIso8601String(),
        ]);
    }

    public function release(DailyBasketPost $post): JsonResponse
    {
        $userId  = auth()->id();
        $isAdmin = auth()->user()
            && method_exists(auth()->user(), 'hasMarketingPermission')
            && auth()->user()->hasMarketingPermission(MarketingPermissionEnum::PRODUCTION_ADVANCE->value);

        if ($post->claimed_by_user_id !== null
            && $post->claimed_by_user_id !== $userId
            && ! $isAdmin) {
            return response()->json(['message' => 'Vetëm marrësi mund ta heqë.'], 403);
        }

        $post->update(['claimed_by_user_id' => null, 'claimed_at' => null]);

        return response()->json(['ok' => true]);
    }

    public function advance(Request $request, DailyBasketPost $post): JsonResponse
    {
        $userId = auth()->id();

        if ($post->stage->value !== DailyBasketPostStage::PRODUCTION->value) {
            return response()->json(['message' => 'Posti nuk është në fazë prodhimi.'], 422);
        }

        if ($post->claimed_by_user_id !== null && $post->claimed_by_user_id !== $userId) {
            return response()->json(['message' => 'Vetëm marrësi mund ta avancojë.'], 403);
        }

        $hasMedia = $post->media()->exists();
        if (! $hasMedia && ! $request->boolean('force')) {
            return response()->json([
                'warning' => 'S\'ke ngarkuar asnjë foto/video.',
                'code'    => 'no_media',
            ], 422);
        }

        $post->update(['stage' => DailyBasketPostStage::EDITING->value]);

        return response()->json([
            'ok'    => true,
            'stage' => $post->fresh()->stage->value,
        ]);
    }

    /**
     * Serialize a collection of posts for the queue JSON response.
     */
    private function serializeList($posts, bool $withClaimer = false): array
    {
        return $posts->map(function (DailyBasketPost $p) use ($withClaimer) {
            $first = $p->media->first();

            return [
                'id'              => $p->id,
                'title'           => $p->title ?: 'Pa titull',
                'post_type'       => $p->post_type?->value,
                'post_type_label' => $p->post_type?->label(),
                'priority'        => $p->priority,
                'scheduled_for'   => $p->scheduled_for?->toIso8601String(),
                'lokacioni'       => $p->lokacioni,
                'thumbnail_url'   => $first?->thumbnail_url ?? $first?->url,
                'claimed_by'      => $withClaimer ? $p->claimer?->name : null,
                'claimed_at'      => $withClaimer ? $p->claimed_at?->toIso8601String() : null,
            ];
        })->all();
    }

    /**
     * Fetch og:image and detect video for the reference URL. Cached 24h
     * keyed on (post id, url hash) so we don't hit Pinterest on every
     * detail render. Returns ['image' => string|null, 'is_video' => bool].
     */
    private function referencePreview(DailyBasketPost $post): array
    {
        if (empty($post->reference_url)) {
            return ['image' => null, 'is_video' => false];
        }

        $cacheKey = 'production:reference:'.$post->id.':'.md5((string) $post->reference_url);

        return Cache::remember($cacheKey, 86400, function () use ($post) {
            try {
                $resp = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get($post->reference_url);
                if (! $resp->successful()) {
                    return ['image' => null, 'is_video' => false];
                }
                $html = $resp->body();
                preg_match('/<meta[^>]+property="og:image"[^>]+content="([^"]+)"/i', $html, $m1);
                preg_match('/<meta[^>]+property="og:video"[^>]+content="([^"]+)"/i', $html, $m2);
                preg_match('/<meta[^>]+property="og:type"[^>]+content="([^"]+)"/i', $html, $m3);
                $img     = $m1[1] ?? null;
                $type    = $m3[1] ?? '';
                $isVideo = ! empty($m2[1]) || str_contains($type, 'video');

                return ['image' => $img, 'is_video' => $isVideo];
            } catch (\Throwable $e) {
                return ['image' => null, 'is_video' => false];
            }
        });
    }
}
