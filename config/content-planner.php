<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Planner Module
    |--------------------------------------------------------------------------
    |
    | Feature flag to enable/disable the Content Planner (Planable-like)
    | module within the marketing dashboard.
    |
    */

    'enabled' => env('CONTENT_PLANNER_MODULE', false),

    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    */

    'media_disk' => env('CONTENT_PLANNER_MEDIA_DISK', 'public'),
    'media_max_size_mb' => 50,
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_video_types' => ['mp4', 'mov', 'avi'],
    'thumbnail_width' => 400,
    'thumbnail_quality' => 80,

    /*
    |--------------------------------------------------------------------------
    | Post Statuses
    |--------------------------------------------------------------------------
    */

    'statuses' => [
        'draft',
        'pending_review',
        'approved',
        'scheduled',
        'published',
        'failed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Platforms
    |--------------------------------------------------------------------------
    */

    'platforms' => ['facebook', 'instagram', 'tiktok'],

    /*
    |--------------------------------------------------------------------------
    | Feed Import (Meta)
    |--------------------------------------------------------------------------
    |
    | Pull published posts from Facebook/Instagram into the Content Planner.
    |
    */

    'import_user_id' => env('CONTENT_PLANNER_IMPORT_USER_ID', 1),
    'import_days_back' => env('CONTENT_PLANNER_IMPORT_DAYS', 90),

];
