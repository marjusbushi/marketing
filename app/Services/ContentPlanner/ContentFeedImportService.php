<?php

namespace App\Services\ContentPlanner;

use App\Models\Content\ContentMedia;
use App\Models\Content\ContentPost;
use App\Services\Meta\MetaApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContentFeedImportService
{
    public function __construct(
        private readonly MetaApiService $api,
    ) {}

    /**
     * Import published posts from both Facebook and Instagram.
     */
    public function importAll(?string $since = null): array
    {
        $since = $since ?? Carbon::now()
            ->subDays(config('content-planner.import_days_back', 90))
            ->toDateString();

        return [
            'facebook' => $this->importFacebookPosts($since),
            'instagram' => $this->importInstagramPosts($since),
        ];
    }

    /**
     * Import published Facebook page posts into ContentPost.
     */
    public function importFacebookPosts(string $since): int
    {
        $pageId = config('meta.page_id');
        if (!$pageId || !config('meta.page_token')) {
            Log::info('ContentFeedImport: Meta Page ID or Page Token not configured. Skipping FB.');
            return 0;
        }

        $count = 0;

        try {
            $posts = $this->api->getPagePosts($pageId, [
                'id', 'message', 'created_time', 'permalink_url',
                'attachments{type,media_type,url,media,subattachments}',
            ], 100, $since);

            foreach ($posts as $post) {
                $postId = $post['id'] ?? null;
                if (!$postId) continue;

                try {
                    $this->upsertFacebookPost($post, $postId);
                    $count++;
                } catch (Exception $e) {
                    Log::warning("ContentFeedImport: Failed to import FB post {$postId}: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            Log::error('ContentFeedImport: Failed to fetch Facebook posts: ' . $e->getMessage());
        }

        Log::info("ContentFeedImport: Imported {$count} Facebook posts.");
        return $count;
    }

    /**
     * Import published Instagram media into ContentPost.
     */
    public function importInstagramPosts(string $since): int
    {
        $igAccountId = config('meta.ig_account_id');
        if (!$igAccountId || !config('meta.page_token')) {
            // Try auto-discover from page
            $igAccountId = $this->discoverIgAccount();
            if (!$igAccountId) {
                Log::info('ContentFeedImport: IG Account ID not configured. Skipping IG.');
                return 0;
            }
        }

        $count = 0;
        $sinceDate = Carbon::parse($since);

        try {
            $media = $this->api->getIgMedia($igAccountId, [
                'id', 'caption', 'media_type', 'media_product_type', 'permalink',
                'thumbnail_url', 'media_url', 'timestamp',
                'children{media_url,media_type,thumbnail_url}',
            ], 100);

            foreach ($media as $item) {
                $mediaId = $item['id'] ?? null;
                if (!$mediaId) continue;

                // Skip posts older than our import window
                $createdTime = isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null;
                if ($createdTime && $createdTime->lt($sinceDate)) {
                    continue;
                }

                try {
                    $this->upsertInstagramPost($item, $mediaId);
                    $count++;
                } catch (Exception $e) {
                    Log::warning("ContentFeedImport: Failed to import IG post {$mediaId}: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            Log::error('ContentFeedImport: Failed to fetch Instagram posts: ' . $e->getMessage());
        }

        Log::info("ContentFeedImport: Imported {$count} Instagram posts.");
        return $count;
    }

    // ── Private Helpers ──

    private function upsertFacebookPost(array $post, string $postId): void
    {
        $createdTime = isset($post['created_time']) ? Carbon::parse($post['created_time']) : now();
        $attachments = $post['attachments']['data'][0] ?? [];
        $postType = $this->mapFbPostType($attachments['type'] ?? ($attachments['media_type'] ?? 'status'));

        $contentPost = DB::transaction(function () use ($post, $postId, $createdTime, $postType) {
            return ContentPost::updateOrCreate(
                [
                    'platform' => 'facebook',
                    'platform_post_id' => $postId,
                ],
                [
                    'user_id' => config('content-planner.import_user_id', 1),
                    'content' => mb_substr($post['message'] ?? '', 0, 5000),
                    'scheduled_at' => $createdTime,
                    'published_at' => $createdTime,
                    'status' => 'published',
                    'external_source' => 'meta_facebook',
                    'meta_post_type' => $postType,
                    'permalink' => $post['permalink_url'] ?? null,
                ]
            );
        });

        // Attach media
        $this->attachFacebookMedia($contentPost, $attachments, $postId);
    }

    private function upsertInstagramPost(array $item, string $mediaId): void
    {
        $createdTime = isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : now();
        $postType = $this->mapIgMediaType(
            $item['media_type'] ?? '',
            $item['media_product_type'] ?? ''
        );

        $contentPost = DB::transaction(function () use ($item, $mediaId, $createdTime, $postType) {
            return ContentPost::updateOrCreate(
                [
                    'platform' => 'instagram',
                    'platform_post_id' => $mediaId,
                ],
                [
                    'user_id' => config('content-planner.import_user_id', 1),
                    'content' => mb_substr($item['caption'] ?? '', 0, 5000),
                    'scheduled_at' => $createdTime,
                    'published_at' => $createdTime,
                    'status' => 'published',
                    'external_source' => 'meta_instagram',
                    'meta_post_type' => $postType,
                    'permalink' => $item['permalink'] ?? null,
                ]
            );
        });

        // Attach media
        $mediaType = strtoupper($item['media_type'] ?? '');

        if ($mediaType === 'CAROUSEL_ALBUM' && !empty($item['children']['data'])) {
            // Carousel: attach each child as separate media
            foreach ($item['children']['data'] as $index => $child) {
                $childType = strtoupper($child['media_type'] ?? 'IMAGE');
                $url = ($childType === 'VIDEO')
                    ? ($child['thumbnail_url'] ?? $child['media_url'] ?? null)
                    : ($child['media_url'] ?? null);
                $this->attachMediaFromUrl($contentPost, $url, "ig_{$mediaId}_{$index}", $childType === 'VIDEO', $index);
            }
        } else {
            // Single image or video
            $isVideo = $mediaType === 'VIDEO';
            $url = $isVideo
                ? ($item['thumbnail_url'] ?? $item['media_url'] ?? null)
                : ($item['media_url'] ?? null);
            $this->attachMediaFromUrl($contentPost, $url, "ig_{$mediaId}", $isVideo);
        }
    }

    private function attachFacebookMedia(ContentPost $contentPost, array $attachments, string $postId): void
    {
        if (empty($attachments)) return;

        // Check for subattachments (multi-photo posts)
        if (!empty($attachments['subattachments']['data'])) {
            foreach ($attachments['subattachments']['data'] as $index => $sub) {
                $url = $sub['media']['image']['src'] ?? ($sub['url'] ?? null);
                $isVideo = ($sub['type'] ?? '') === 'video' || ($sub['media_type'] ?? '') === 'video';
                $this->attachMediaFromUrl($contentPost, $url, "fb_{$postId}_{$index}", $isVideo, $index);
            }
        } else {
            // Single attachment
            $url = $attachments['media']['image']['src'] ?? ($attachments['url'] ?? null);
            $isVideo = ($attachments['type'] ?? '') === 'video_inline' || ($attachments['media_type'] ?? '') === 'video';
            $this->attachMediaFromUrl($contentPost, $url, "fb_{$postId}", $isVideo);
        }
    }

    /**
     * Download media from a URL and attach as ContentMedia to the post.
     */
    private function attachMediaFromUrl(
        ContentPost $contentPost,
        ?string $url,
        string $filenamePrefix,
        bool $isVideoThumbnail = false,
        int $sortOrder = 0
    ): void {
        if (!$url) return;

        try {
            // Determine extension from URL
            $ext = 'jpg';
            $path = parse_url($url, PHP_URL_PATH);
            if ($path && preg_match('/\.([a-zA-Z0-9]+)$/', $path, $matches)) {
                $ext = strtolower($matches[1]);
            }

            $storagePath = "content-planner/meta-imports/{$filenamePrefix}.{$ext}";

            // Check if we already have this media file
            $existingMedia = ContentMedia::where('path', $storagePath)->first();
            if ($existingMedia) {
                // Just ensure it's attached to the post
                if (!$contentPost->media()->where('content_media.id', $existingMedia->id)->exists()) {
                    $contentPost->media()->attach($existingMedia->id, ['sort_order' => $sortOrder]);
                }
                return;
            }

            // Download the file
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::debug("ContentFeedImport: Failed to download media [{$filenamePrefix}]: HTTP {$response->status()}");
                return;
            }

            // Detect content type from response
            $contentType = $response->header('Content-Type', 'image/jpeg');
            if (str_contains($contentType, 'video/mp4')) $ext = 'mp4';
            elseif (str_contains($contentType, 'image/png')) $ext = 'png';
            elseif (str_contains($contentType, 'image/webp')) $ext = 'webp';
            elseif (str_contains($contentType, 'image/gif')) $ext = 'gif';

            // Update path with correct extension
            $storagePath = "content-planner/meta-imports/{$filenamePrefix}.{$ext}";

            // Store to public disk
            Storage::disk('public')->put($storagePath, $response->body());

            // Detect dimensions for images
            $width = null;
            $height = null;
            if (str_starts_with($contentType, 'image/')) {
                $tempPath = Storage::disk('public')->path($storagePath);
                $imageSize = @getimagesize($tempPath);
                if ($imageSize) {
                    $width = $imageSize[0];
                    $height = $imageSize[1];
                }
            }

            // Determine MIME type
            $mimeType = $isVideoThumbnail ? 'image/jpeg' : $contentType;
            if (str_contains($mimeType, ';')) {
                $mimeType = trim(explode(';', $mimeType)[0]);
            }

            // Create ContentMedia record
            $media = ContentMedia::create([
                'user_id' => config('content-planner.import_user_id', 1),
                'filename' => basename($storagePath),
                'original_filename' => $filenamePrefix . '.' . $ext,
                'disk' => 'public',
                'path' => $storagePath,
                'mime_type' => $mimeType,
                'size_bytes' => strlen($response->body()),
                'width' => $width,
                'height' => $height,
            ]);

            // Attach to post
            $contentPost->media()->attach($media->id, ['sort_order' => $sortOrder]);

        } catch (Exception $e) {
            Log::warning("ContentFeedImport: Failed to download/attach media [{$filenamePrefix}]: " . $e->getMessage());
        }
    }

    // ── Type Mappers (reuse logic from MetaPostSyncService) ──

    private function mapFbPostType(string $type): string
    {
        return match ($type) {
            'photo' => 'photo',
            'video', 'video_inline' => 'video',
            'link' => 'link',
            default => 'status',
        };
    }

    private function mapIgMediaType(string $mediaType, string $productType): string
    {
        if (strtolower($productType) === 'reels' || strtolower($productType) === 'reel') {
            return 'reel';
        }
        if (strtolower($productType) === 'story') {
            return 'story';
        }

        return match (strtoupper($mediaType)) {
            'IMAGE' => 'photo',
            'VIDEO' => 'video',
            'CAROUSEL_ALBUM' => 'carousel_album',
            default => 'photo',
        };
    }

    /**
     * Try to discover IG Business Account from linked Page.
     */
    private function discoverIgAccount(): ?string
    {
        try {
            $pageId = config('meta.page_id');
            if (!$pageId || !config('meta.page_token')) {
                return null;
            }

            $result = $this->api->getWithPageToken($pageId, [
                'fields' => 'instagram_business_account{id}',
            ]);

            return $result['instagram_business_account']['id'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
