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
    // CapCut exports can be big (4K 60fps). 500MB is the ceiling we
    // enforce at the Laravel layer; PHP `upload_max_filesize` / `post_max_size`
    // and nginx `client_max_body_size` must match or exceed it.
    'video_max_size_mb' => (int) env('MARKETING_VIDEO_MAX_SIZE_MB', 500),
    // Direct photo upload ceiling (Rruga C). Social-first exports are
    // typically 1-5 MB; 50 MB gives head-room for Retina/PDF export prints.
    'photo_max_size_mb' => (int) env('MARKETING_PHOTO_MAX_SIZE_MB', 50),
    // Maximum-permissive whitelist — library accepts anything that looks
    // like an image or video. Thumbnail / probe may not work for every
    // exotic format, but the file is preserved as-is. The publish step
    // re-validates against Meta requirements before sending to FB/IG.
    'allowed_image_types' => [
        // JPEG family
        'jpg', 'jpeg', 'jpe', 'jfif', 'pjpeg', 'pjp',
        // PNG
        'png', 'apng',
        // GIF
        'gif',
        // Modern web
        'webp', 'avif', 'avifs',
        // HEIC / HEIF (iPhone defaults)
        'heic', 'heif', 'heics', 'heifs',
        // Bitmap
        'bmp', 'dib',
        // TIFF
        'tiff', 'tif',
        // Icons
        'ico', 'cur',
        // Vector
        'svg', 'svgz', 'ai', 'eps',
        // Camera RAW (Canon, Nikon, Sony, Adobe, Olympus, Fuji, Panasonic,
        // Samsung, Pentax, Sigma, Minolta, Kodak, Epson, Mamiya, Hasselblad,
        // Phase One)
        'raw', 'cr2', 'cr3', 'crw', 'nef', 'nrw', 'arw', 'srf', 'sr2',
        'dng', 'orf', 'raf', 'rw2', 'rwl', 'srw', 'pef', 'ptx', 'x3f',
        'mrw', 'kdc', 'dcr', 'erf', 'mef', '3fr', 'iiq', 'fff', 'mos',
        // Source / editor files
        'psd', 'psb', 'xcf',
        // HDR
        'exr', 'hdr',
        // JPEG 2000
        'jp2', 'jpx', 'j2k', 'j2c',
    ],
    'allowed_video_types' => [
        // MP4 family
        'mp4', 'm4v', 'm4p', 'mp4v',
        // QuickTime
        'mov', 'qt',
        // AVI + codec-tagged variants
        'avi', 'divx', 'xvid',
        // Matroska
        'mkv', 'mk3d',
        // WebM
        'webm',
        // Flash
        'flv', 'f4v', 'f4p', 'swf',
        // Windows Media
        'wmv', 'asf',
        // Mobile
        '3gp', '3g2', '3gpp', '3gpp2',
        // MPEG
        'mpg', 'mpeg', 'mpe', 'm1v', 'm2v', 'mp2', 'mpv', 'm2p',
        // Broadcast / AVCHD
        'mts', 'm2ts', 'ts', 'tts', 'mod', 'tod', 'mxf',
        // Theora / Ogg
        'ogv', 'ogg', 'ogm',
        // DVD
        'vob', 'ifo',
        // RealMedia
        'rm', 'rmvb',
        // Other
        'dv', 'dvr-ms', 'wtv', 'nsv', 'yuv',
    ],
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
        // Intermediate, set by PublishContentPostJob's atomic claim. Not
        // a status users can pick — UI shows it as a transient "Publishing…"
        // badge while the job hits Meta.
        'publishing',
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
