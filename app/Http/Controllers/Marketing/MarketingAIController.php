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
}
