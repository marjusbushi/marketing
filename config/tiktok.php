<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TikTok Marketing API (Ads)
    |--------------------------------------------------------------------------
    |
    | Credentials from TikTok for Business Developer Portal.
    | Register at: https://business-api.tiktok.com/portal/developer
    | Auth header: Access-Token (NOT Bearer)
    |
    */
    'ads_base_url' => env('TIKTOK_ADS_BASE_URL', 'https://business-api.tiktok.com/open_api/v1.3'),
    'ads_sandbox_url' => env('TIKTOK_ADS_SANDBOX_URL', 'https://sandbox-ads.tiktok.com/open_api/v1.3'),
    'advertiser_id' => env('TIKTOK_ADVERTISER_ID'),
    'app_id' => env('TIKTOK_APP_ID'),
    'app_secret' => env('TIKTOK_APP_SECRET'),
    'use_sandbox' => env('TIKTOK_USE_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | TikTok Organic API (v2)
    |--------------------------------------------------------------------------
    |
    | For fetching organic video data and user profile insights.
    | Register at: https://developers.tiktok.com/
    | Auth header: Authorization: Bearer
    |
    */
    'organic_base_url' => env('TIKTOK_ORGANIC_BASE_URL', 'https://open.tiktokapis.com/v2'),
    'client_key' => env('TIKTOK_CLIENT_KEY'),
    'client_secret' => env('TIKTOK_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Settings
    |--------------------------------------------------------------------------
    |
    | Marketing API auth URL: https://ads.tiktok.com/marketing_api/auth
    | Organic API auth URL: https://www.tiktok.com/v2/auth/authorize/
    |
    */
    'oauth' => [
        'redirect_uri' => env('TIKTOK_REDIRECT_URI', '/management/tiktok-auth/callback'),
        'marketing_auth_url' => 'https://ads.tiktok.com/marketing_api/auth',
        'organic_auth_url' => 'https://www.tiktok.com/v2/auth/authorize/',
        'organic_scopes' => [
            'user.info.basic',
            'user.info.profile',
            'user.info.stats',
            'video.list',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'daily_resync_days' => 28,
    'initial_history_days' => 365,
    'max_daily_range' => 30,             // TikTok API max for daily granularity
    'max_period_range' => 90,            // TikTok API max for period totals (no time dimension)
    'data_latency_hours' => 11,          // TikTok data is ~11h behind
    'video_fetch_limit' => 20,           // Max videos per API call (TikTok max is 20)
    'max_videos_to_sync' => 200,         // Max total videos to sync per run

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Handling
    |--------------------------------------------------------------------------
    |
    | ~600 requests/minute per app (sliding window).
    |
    */
    'max_retries' => 3,
    'retry_delay_seconds' => [2, 5, 15],
    'pause_between_batches' => 3,

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'tiktok_module' => env('TIKTOK_FEATURE_MODULE', true),
        'tiktok_organic' => env('TIKTOK_FEATURE_ORGANIC', true),
    ],
];
