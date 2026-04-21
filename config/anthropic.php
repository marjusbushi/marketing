<?php

/**
 * Anthropic / Claude configuration for the Visual Studio AI layer.
 *
 * Kept separate from config/services.php so that Marketing AI usage is
 * tunable (model, token budget, timeout) without touching the broader
 * services config consumed by other parts of the app.
 */
return [

    'api_key' => env('ANTHROPIC_API_KEY'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
    'version'  => env('ANTHROPIC_API_VERSION', '2023-06-01'),

    'model' => env('MARKETING_AI_MODEL', 'claude-sonnet-4-6'),

    'max_tokens' => (int) env('MARKETING_AI_MAX_TOKENS', 500),
    'timeout'    => (int) env('MARKETING_AI_TIMEOUT', 30),

    // Approximate price in USD cents per million tokens, used only for
    // cost_cents telemetry (not for billing). Update when pricing changes.
    'pricing_cents_per_mtok' => [
        'input'  => (int) env('MARKETING_AI_PRICE_IN', 300),  // $3 / 1M input tokens
        'output' => (int) env('MARKETING_AI_PRICE_OUT', 1500), // $15 / 1M output tokens
    ],

    // Max Claude retries on rate-limit / 5xx (exponential backoff).
    'retries' => (int) env('MARKETING_AI_RETRIES', 2),

];
