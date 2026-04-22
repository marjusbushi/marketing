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
    | Legacy credential source (hrms_meta_credentials)
    |--------------------------------------------------------------------------
    |
    | When meta_tokens (OAuth) is empty, MetaTokenResolver falls back to
    | the hrms_meta_credentials table in the DIS database — seeded by the
    | HRMS app which stores page tokens encrypted with its own APP_KEY.
    |
    | Set HRMS_APP_KEY in this app's .env to HRMS's exact APP_KEY value so
    | the encrypter can decrypt. If empty, the resolver defaults to this
    | app's APP_KEY (only works when HRMS & Marketing share a key).
    |
    */
    'hrms_credentials_key' => env('HRMS_APP_KEY'),

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
    // 2s is comfortably below the documented Graph API rate limits for a
    // single-page account and cuts a full 20-day Rifresko from ~10min to
    // ~5-6min. Override with META_PAUSE_BETWEEN_BATCHES in .env if a sync
    // ever hits 403 / rate-limit errors — bumping back to 5 is one env var.
    'pause_between_batches' => (int) env('META_PAUSE_BETWEEN_BATCHES', 2),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'facebook_module' => env('META_FEATURE_FACEBOOK_MODULE', true),
        'ads_platform_split' => env('META_FEATURE_ADS_PLATFORM_SPLIT', true),
        'db_first_mode' => env('META_DB_FIRST_MODE', true),
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

    /*
    |--------------------------------------------------------------------------
    | Instagram DM Webhook
    |--------------------------------------------------------------------------
    |
    | Meta delivers IG DM events to /webhooks/meta/instagram. The dashboard
    | reads from meta_ig_dm_events for dates >= ig_webhook_start_date, giving
    | 100% match with Meta Business Suite. Dates before start_date use the
    | Conversations API sample (historical fallback).
    |
    | Setup:
    | 1. Generate a random 32-char hex string: openssl rand -hex 16
    | 2. Set META_WEBHOOK_VERIFY_TOKEN to that value (same value in Meta App
    |    Dashboard > Webhooks > Verify Token).
    | 3. META_APP_SECRET already set for OAuth — reused for HMAC signature.
    | 4. After test DM succeeds, set META_IG_WEBHOOK_START_DATE=YYYY-MM-DD
    |    (today's date when subscription went live).
    | 5. ig_conversation_gap_minutes controls the "new conversation" threshold:
    |    a message starts a new conversation if no prior incoming from same
    |    thread within this window. 1440 (24h) is industry norm; tune if gap
    |    vs Meta BS > 2%.
    */
    'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    'ig_webhook_start_date' => env('META_IG_WEBHOOK_START_DATE'),
    'ig_conversation_gap_minutes' => (int) env('META_IG_CONVERSATION_GAP_MINUTES', 1440),
];
