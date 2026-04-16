<?php

namespace App\Services\ContentPlanner;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentAiService
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', env('AI_CLAUDE_API_KEY', ''));
        $this->model = 'claude-haiku-4-5-20251001';
        $this->maxTokens = 1024;
        $this->timeout = 30;
    }

    /**
     * Generate a caption for a social media post.
     */
    public function generateCaption(string $platform, ?string $context = null, ?string $tone = null): ?string
    {
        $platformLabel = match ($platform) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            default => 'social media',
        };

        $charLimit = match ($platform) {
            'instagram', 'tiktok' => 2200,
            'facebook' => 63206,
            default => 2200,
        };

        $toneInstruction = $tone ? "Use a {$tone} tone." : 'Use a professional yet engaging tone.';

        $prompt = "Write a {$platformLabel} post caption. {$toneInstruction}\n";
        $prompt .= "Keep it under {$charLimit} characters.\n";
        $prompt .= "Include relevant emojis where appropriate.\n";
        if ($context) {
            $prompt .= "Context/topic: {$context}\n";
        }
        $prompt .= "\nReturn ONLY the caption text, nothing else. No quotes, no labels, no explanation.";

        return $this->call($prompt);
    }

    /**
     * Suggest hashtags for given content.
     */
    public function suggestHashtags(string $content, string $platform = 'instagram', int $count = 15): ?string
    {
        $prompt = "Given this social media post content, suggest {$count} relevant hashtags for {$platform}.\n\n";
        $prompt .= "Post content: \"{$content}\"\n\n";
        $prompt .= "Return ONLY the hashtags separated by spaces, starting with #. No explanations, no numbering.";

        return $this->call($prompt);
    }

    /**
     * Rewrite content in a given style.
     */
    public function rewriteContent(string $content, string $style = 'shorter'): ?string
    {
        $instruction = match ($style) {
            'shorter' => 'Make this shorter and more concise while keeping the key message.',
            'longer' => 'Expand this with more detail and engagement hooks.',
            'professional' => 'Rewrite this in a more professional and polished tone.',
            'casual' => 'Rewrite this in a casual, friendly, conversational tone.',
            'engaging' => 'Rewrite this to be more engaging with a strong hook and call-to-action.',
            default => "Rewrite this in a {$style} style.",
        };

        $prompt = "{$instruction}\n\nOriginal content:\n\"{$content}\"\n\n";
        $prompt .= "Return ONLY the rewritten text, nothing else. No quotes, no labels.";

        return $this->call($prompt);
    }

    /**
     * Call the Claude API.
     */
    protected function call(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning('ContentAiService: No API key configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('ContentAiService: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('content.0.text');
        } catch (\Throwable $e) {
            Log::error('ContentAiService: Request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
