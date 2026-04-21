<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Canva Connect API
    |--------------------------------------------------------------------------
    |
    | Public REST API for Canva. Used by Zero Absolute Marketing Studio so the
    | staff designs inside Canva (a tool they already know) and the finished
    | design flows back into our app as the asset for a content post.
    |
    | Register the app at: https://www.canva.com/developers/
    | Docs: https://www.canva.dev/docs/connect/
    |
    | Authentication is OAuth 2.0 with PKCE. We store per-user access_token +
    | refresh_token (encrypted at rest via the `encrypted` cast on the
    | CanvaConnection model) in the `marketing_canva_connections` table.
    |
    */

    'client_id'     => env('CANVA_CLIENT_ID'),
    'client_secret' => env('CANVA_CLIENT_SECRET'),

    'base_url'     => env('CANVA_API_BASE_URL', 'https://api.canva.com/rest/v1'),
    'auth_url'     => env('CANVA_AUTH_URL', 'https://www.canva.com/api/oauth/authorize'),
    'token_url'    => env('CANVA_TOKEN_URL', 'https://api.canva.com/rest/v1/oauth/token'),
    'revoke_url'   => env('CANVA_REVOKE_URL', 'https://api.canva.com/rest/v1/oauth/revoke'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Redirect
    |--------------------------------------------------------------------------
    |
    | Relative path — the controller prepends APP_URL. In Canva's developer
    | console, register one redirect URI per environment:
    |   • Local dev:  https://<ngrok-host>/marketing/canva/callback
    |   • Staging:    https://stage.zeroabsolute.dev/marketing/canva/callback
    |   • Production: https://<prod-host>/marketing/canva/callback
    |
    */

    'oauth' => [
        'redirect_uri' => env('CANVA_REDIRECT_URI', '/marketing/canva/callback'),

        // Scopes required for Visual Studio: read templates + brand,
        // create/export designs, read user profile (for identity).
        'scopes' => [
            'design:content:read',
            'design:content:write',
            'design:meta:read',
            'brandtemplate:content:read',
            'brandtemplate:meta:read',
            'asset:read',
            'asset:write',
            'profile:read',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling (no webhooks on Canva Connect today)
    |--------------------------------------------------------------------------
    |
    | After the user finishes designing in Canva, we poll the export job until
    | `status = success`. Defaults give ~90s budget with exponential backoff.
    |
    */

    'polling' => [
        'initial_delay_seconds' => 3,
        'max_attempts'          => 15,
        'backoff_factor'        => 1.5,
        'max_delay_seconds'     => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Defaults
    |--------------------------------------------------------------------------
    |
    | Exports for Instagram/Facebook content default to PNG at native size.
    | PDF is reserved for carousels that will be sliced into individual pages.
    |
    */

    'export' => [
        'default_format' => env('CANVA_DEFAULT_EXPORT_FORMAT', 'png'),
        'allowed_formats' => ['png', 'jpg', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Handling
    |--------------------------------------------------------------------------
    */

    'max_retries'          => 3,
    'retry_delay_seconds'  => [1, 4, 10],

    /*
    |--------------------------------------------------------------------------
    | Feature Flag
    |--------------------------------------------------------------------------
    |
    | Off by default until creds + redirect URIs are wired in prod.
    |
    */

    'features' => [
        'canva_connect' => env('CANVA_FEATURE_CONNECT', false),
    ],
];
