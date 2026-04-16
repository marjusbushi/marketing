<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta API Authentication
    |--------------------------------------------------------------------------
    |
    | System User Token from Meta Business Manager (never expires).
    | Create at: Business Settings > Users > System Users > Generate Token
    |
    */
    'token' => env('META_SYSTEM_USER_TOKEN'),
    'page_token' => env('META_PAGE_TOKEN'),
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
    'ad_account_id' => env('META_AD_ACCOUNT_ID'),
    'page_id' => env('META_PAGE_ID'),
    'ig_account_id' => env('META_IG_ACCOUNT_ID', env('META_INSTAGRAM_BUSINESS_ACCOUNT_ID')),
    'business_id' => env('META_BUSINESS_ID'),
    'api_version' => env('META_API_VERSION', 'v24.0'),
    'base_url' => 'https://graph.facebook.com',

    /*
    |--------------------------------------------------------------------------
    | OAuth Settings
    |--------------------------------------------------------------------------
    |
    | Used for the OAuth login flow that exchanges a short-lived token
    | for a long-lived user token and then a Page token (auto-refresh).
    |
    */
    'oauth' => [
        'redirect_uri' => env('META_REDIRECT_URI', '/management/meta-auth/callback'),
        'scopes' => [
            'pages_show_list',
            'pages_read_engagement',
            'pages_read_user_content',
            'read_insights',
            'ads_read',
            'instagram_basic',
            'instagram_manage_insights',
            'pages_messaging',
            'instagram_manage_messages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'daily_resync_days' => 28,
    'initial_history_days' => 365,
    'batch_size_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Handling
    |--------------------------------------------------------------------------
    */
    'max_retries' => 3,
    'retry_delay_seconds' => [1, 4, 16],
    'pause_between_batches' => 5,

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'facebook_module' => env('META_FEATURE_FACEBOOK_MODULE', false),
        'ads_platform_split' => env('META_FEATURE_ADS_PLATFORM_SPLIT', false),
        'db_first_mode' => env('META_DB_FIRST_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ads Attribution Contract
    |--------------------------------------------------------------------------
    |
    | Keep attribution settings consistent across all ads insights calls to
    | avoid metric drift between endpoints.
    |
    */
    'ads_attribution' => [
        'use_account_attribution_setting' => env('META_USE_ACCOUNT_ATTRIBUTION_SETTING', true),
    ],
];
