<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\AIContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Visual Studio AI endpoints — Faza 1 (AI Light).
 *
 * Rate limiting lives at the route layer (throttle:marketing-ai) to keep
 * Claude costs bounded and to provide simple quota enforcement per user.
 */
class MarketingAIController extends Controller
{
    public function __construct(
        private readonly AIContentService $ai,
    ) {
    }

    public function caption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'post_type'  => ['required', 'in:photo,carousel,reel,video,story'],
            'language'   => ['sometimes', 'in:sq,en,both'],
        ]);

        $result = $this->ai->generateCaption(
            productId: $validated['product_id'],
            postType:  $validated['post_type'],
            language:  $validated['language'] ?? 'both',
            userId:    $request->user()?->id,
        );

        return response()->json($result);
    }

    public function rewrite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'     => ['required', 'string', 'max:5000'],
            'tone'     => ['sometimes', 'string', 'max:40'],
            'language' => ['sometimes', 'in:sq,en'],
        ]);

        $rewritten = $this->ai->rewriteText(
            text:     $validated['text'],
            tone:     $validated['tone'] ?? 'brand',
            language: $validated['language'] ?? 'sq',
            userId:   $request->user()?->id,
        );

        return response()->json(['text' => $rewritten]);
    }

    /**
     * Polish a creator-written Albanian caption. Two modes:
     *   • `mode=clean` (default) — fix grammar/diacritics only. Cheap;
     *     returns {cleaned_sq}. Used by the inline AI button next to the
     *     Caption textarea in the daily-basket post detail panel.
     *   • `mode=per_platform` — also emit IG/FB/TikTok variants. Heavier.
     *     Reserved for flows that need ready-to-paste per-platform text.
     */
    public function polishCaption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'        => ['required', 'string', 'max:5000'],
            'mode'        => ['sometimes', 'in:clean,per_platform'],
            'platforms'   => ['sometimes', 'array', 'max:3'],
            'platforms.*' => ['string', 'in:instagram,facebook,tiktok'],
        ]);

        $mode = $validated['mode'] ?? 'clean';

        if ($mode === 'clean') {
            $cleaned = $this->ai->cleanCaption(
                text:   $validated['text'],
                userId: $request->user()?->id,
            );
            return response()->json([
                'cleaned_sq'   => $cleaned,
                'per_platform' => null,
            ]);
        }

        $result = $this->ai->polishCaption(
            text:      $validated['text'],
            platforms: $validated['platforms'] ?? ['instagram', 'facebook', 'tiktok'],
            userId:    $request->user()?->id,
        );

        return response()->json($result);
    }
}
