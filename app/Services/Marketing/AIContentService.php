<?php

namespace App\Services\Marketing;

use App\Models\Dis\DisItemGroup;
use App\Models\Marketing\BrandKit;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Light (Faza 1) content generation.
 *
 * Responsibilities:
 *   • Build the system + user prompts from Brand Kit voice + product data
 *   • Call Claude via direct HTTP (no SDK — the official PHP SDK is still
 *     unstable at the time of writing, and Http::withHeaders gives us the
 *     retry / timeout / test fake story for free)
 *   • Parse the JSON response and surface it as a typed array
 *   • Audit the call in marketing_ai_calls for cost tracking + eval
 *
 * The Faza 2 `generateDraftPackage()` will be added to this service
 * without changing the current shape — extra fields, same entry points.
 */
class AIContentService
{
    public function __construct(
        private readonly BrandKitService $brandKitService,
    ) {
    }

    /**
     * Generate a caption + hashtags bundle for a product.
     *
     * @return array{caption_sq: ?string, caption_en: ?string, hashtags: array<int,string>}
     */
    public function generateCaption(
        int $productId,
        string $postType,
        string $language = 'both',
        ?int $userId = null,
    ): array {
        $brandKit = $this->brandKitService->get();
        $product = $this->loadProduct($productId);

        $prompt = $this->buildCaptionPrompt($brandKit, $product, $postType, $language);

        $payload = $this->call(
            endpoint: 'caption',
            systemPrompt: $this->captionSystemPrompt($brandKit),
            userPrompt: $prompt,
            userId: $userId,
        );

        return $this->extractCaptionJson($payload);
    }

    /**
     * Rewrite a piece of text in the requested tone and language.
     */
    public function rewriteText(
        string $text,
        string $tone = 'brand',
        string $language = 'sq',
        ?int $userId = null,
    ): string {
        $brandKit = $this->brandKitService->get();
        $voice = $language === 'en'
            ? ($brandKit->voice_en ?? '')
            : ($brandKit->voice_sq ?? '');

        $system = "You rewrite marketing copy. Preserve meaning, change only tone/style. "
                . "Brand voice hint: {$voice}";
        $user = "Rewrite in tone='{$tone}', language='{$language}'. "
              . "Return ONLY the rewritten text, no quotes, no labels.\n\n"
              . "ORIGINAL:\n{$text}";

        $payload = $this->call(
            endpoint: 'rewrite',
            systemPrompt: $system,
            userPrompt: $user,
            userId: $userId,
        );

        return trim($payload['text'] ?? '');
    }

    // ── Internals ──────────────────────────────────────────────────────

    private function captionSystemPrompt(BrandKit $brandKit): string
    {
        $voiceSq = $brandKit->voice_sq ?? 'ton i drejtpërdrejtë, modern, pa emoji të tepruar';
        $voiceEn = $brandKit->voice_en ?? 'bold, minimal, confident';

        return <<<PROMPT
        You are the marketing copywriter for Zero Absolute.
        You write short, on-brand captions for social media posts.

        Brand voice (sq): {$voiceSq}
        Brand voice (en): {$voiceEn}

        Output rules:
        - Return STRICT JSON matching {"caption_sq": string|null, "caption_en": string|null, "hashtags": string[]}.
        - 1–2 sentences per caption; no quotes around the caption text.
        - Max 8 hashtags, lowercase, no duplicates, prefixed with #.
        - Do NOT wrap the JSON in markdown or prose.
        PROMPT;
    }

    private function buildCaptionPrompt(BrandKit $brandKit, array $product, string $postType, string $language): string
    {
        $lang = match ($language) {
            'sq'   => 'Return only caption_sq filled; caption_en=null.',
            'en'   => 'Return only caption_en filled; caption_sq=null.',
            default => 'Fill caption_sq and caption_en.',
        };

        $defaultTags = is_array($brandKit->default_hashtags ?? null)
            ? implode(' ', $brandKit->default_hashtags)
            : '';

        return "Product: {$product['name']} · {$product['price']}€ · {$product['description']}\n"
             . "Post type: {$postType}\n"
             . "Default hashtags hint: {$defaultTags}\n"
             . $lang;
    }

    /**
     * @return array{caption_sq: ?string, caption_en: ?string, hashtags: array<int,string>}
     */
    private function extractCaptionJson(array $payload): array
    {
        $raw = $payload['text'] ?? '';
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return ['caption_sq' => null, 'caption_en' => null, 'hashtags' => []];
        }

        $hashtags = array_values(array_filter(
            (array) ($decoded['hashtags'] ?? []),
            fn ($h) => is_string($h) && str_starts_with($h, '#'),
        ));

        return [
            'caption_sq' => $decoded['caption_sq'] ?? null,
            'caption_en' => $decoded['caption_en'] ?? null,
            'hashtags'   => array_slice($hashtags, 0, 8),
        ];
    }

    /**
     * @return array{text: string, usage: array, raw: array}
     */
    private function call(
        string $endpoint,
        string $systemPrompt,
        string $userPrompt,
        ?int $userId,
    ): array {
        $apiKey = config('anthropic.api_key');
        $model = config('anthropic.model');
        $maxTokens = (int) config('anthropic.max_tokens');
        $timeout = (int) config('anthropic.timeout');

        if (empty($apiKey)) {
            Log::warning('AIContentService: ANTHROPIC_API_KEY not configured — returning empty.');
            return ['text' => '', 'usage' => [], 'raw' => []];
        }

        $startedAt = microtime(true);
        $promptHash = hash('sha256', $systemPrompt . "\n---\n" . $userPrompt);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => config('anthropic.version'),
                'content-type'      => 'application/json',
            ])
                ->timeout($timeout)
                ->retry((int) config('anthropic.retries'), 500)
                ->post(rtrim((string) config('anthropic.base_url'), '/') . '/messages', [
                    'model'      => $model,
                    'max_tokens' => $maxTokens,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $userPrompt]],
                ]);

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            if (! $response->successful()) {
                $this->audit($endpoint, $model, $promptHash, null, null, $durationMs, false, (string) $response->status(), $userId);

                Log::warning('AIContentService: Claude returned non-success', [
                    'endpoint' => $endpoint,
                    'status'   => $response->status(),
                ]);

                return ['text' => '', 'usage' => [], 'raw' => []];
            }

            $body = $response->json();
            $text = $this->extractText($body);
            $usage = (array) ($body['usage'] ?? []);

            $costCents = $this->estimateCostCents(
                (int) ($usage['input_tokens'] ?? 0),
                (int) ($usage['output_tokens'] ?? 0),
            );

            $this->audit(
                $endpoint,
                $model,
                $promptHash,
                (int) ($usage['input_tokens'] ?? 0),
                (int) ($usage['output_tokens'] ?? 0),
                $durationMs,
                true,
                null,
                $userId,
                $costCents,
            );

            return ['text' => $text, 'usage' => $usage, 'raw' => $body];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $this->audit($endpoint, $model, $promptHash, null, null, $durationMs, false, 'exception', $userId);

            Log::error('AIContentService: Claude call failed', [
                'endpoint' => $endpoint,
                'message'  => $e->getMessage(),
            ]);

            return ['text' => '', 'usage' => [], 'raw' => []];
        }
    }

    private function extractText(array $body): string
    {
        $parts = $body['content'] ?? [];
        if (! is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            if (($part['type'] ?? null) === 'text') {
                $text .= (string) ($part['text'] ?? '');
            }
        }

        return trim($text);
    }

    private function estimateCostCents(int $in, int $out): int
    {
        $inPrice = (int) config('anthropic.pricing_cents_per_mtok.input');
        $outPrice = (int) config('anthropic.pricing_cents_per_mtok.output');

        return (int) ceil(($in * $inPrice + $out * $outPrice) / 1_000_000);
    }

    private function audit(
        string $endpoint,
        string $model,
        string $promptHash,
        ?int $tokensIn,
        ?int $tokensOut,
        int $durationMs,
        bool $ok,
        ?string $errorCode,
        ?int $userId,
        ?int $costCents = null,
    ): void {
        DB::table('marketing_ai_calls')->insert([
            'user_id'     => $userId,
            'endpoint'    => $endpoint,
            'model'       => $model,
            'prompt_hash' => $promptHash,
            'tokens_in'   => $tokensIn,
            'tokens_out'  => $tokensOut,
            'cost_cents'  => $costCents,
            'duration_ms' => $durationMs,
            'ok'          => $ok,
            'error_code'  => $errorCode,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * @return array{name: string, price: float, description: string}
     */
    private function loadProduct(int $productId): array
    {
        // Products live in DIS; we best-effort read. Tests may stub this
        // with a Fake HTTP client; for unit coverage we read by id and
        // fall back to placeholders if the cross-DB read is unavailable.
        try {
            /** @var DisItemGroup|null $item */
            $item = DisItemGroup::query()->find($productId);

            return [
                'name'        => (string) ($item?->name ?? 'Produkt'),
                'price'       => (float) ($item?->unit_price ?? 0),
                'description' => (string) ($item?->description ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::debug('AIContentService: product lookup failed', ['id' => $productId, 'err' => $e->getMessage()]);

            return ['name' => 'Produkt', 'price' => 0.0, 'description' => ''];
        }
    }
}
