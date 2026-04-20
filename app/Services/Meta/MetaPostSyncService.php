<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaPostInsight;
use App\Models\Meta\MetaPostMedia;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MetaPostSyncService
{
    public function __construct(
        private readonly MetaApiService $api,
    ) {}

    /**
     * Sync Facebook Page posts and their insights.
     * Requires Page Token.
     *
     * @param int|null $sinceDays  Restrict to posts from the last N days. Null = no since filter (full history).
     * @param int      $maxPages   Pagination ceiling. Default 20 pages × 50 = 1000 posts.
     */
    public function syncFacebookPosts(?int $sinceDays = 30, int $maxPages = 20): int
    {
        $pageId = config('meta.page_id');
        if (!$pageId || !config('meta.page_token')) {
            Log::info('Meta Page ID or Page Token not configured. Skipping FB posts sync.');
            return 0;
        }

        $count = 0;

        try {
            // Note: 'type' and 'full_picture' were deprecated in v3.3+
            // Use 'attachments' for media info instead.
            // Pass since filter only when bounded — full history syncs skip it.
            $sinceDate = $sinceDays !== null ? Carbon::now()->subDays($sinceDays)->toDateString() : null;
            $posts = $this->api->getPagePosts($pageId, [
                'id', 'message', 'created_time', 'permalink_url',
                'attachments{type,media_type,url,media,subattachments}',
            ], 50, $sinceDate, $maxPages);

            foreach ($posts as $post) {
                $postId = $post['id'] ?? null;
                if (!$postId) continue;

                $createdTime = isset($post['created_time']) ? Carbon::parse($post['created_time']) : null;

                // Get post insights
                $insights = $this->fetchFbPostInsights($postId);

                // Extract type and media from attachments (v21.0+)
                $attachments = $post['attachments']['data'][0] ?? [];
                $postType = $this->mapFbPostType($attachments['type'] ?? ($attachments['media_type'] ?? 'status'));
                
                $rawMediaUrl = $attachments['media']['image']['src'] ?? ($attachments['url'] ?? null);
                $localMediaUrl = $this->downloadMedia($rawMediaUrl, 'fb_' . $postId);

                MetaPostInsight::updateOrCreate(
                    ['post_id' => $postId],
                    [
                        'source' => 'facebook',
                        'source_id' => $pageId,
                        'post_type' => $postType,
                        'message' => mb_substr($post['message'] ?? '', 0, 65000),
                        'permalink_url' => $post['permalink_url'] ?? null,
                        'media_url' => $localMediaUrl,
                        'created_at_meta' => $createdTime,
                        'impressions' => $insights['impressions'] ?? 0,
                        'reach' => $insights['reach'] ?? 0,
                        'likes' => $insights['likes'] ?? 0,
                        'comments' => $insights['comments'] ?? 0,
                        'shares' => $insights['shares'] ?? 0,
                        'saves' => 0, // FB doesn't have saves at post level
                        'video_views' => $insights['video_views'] ?? 0,
                        'clicks' => $insights['clicks'] ?? 0,
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }
        } catch (Exception $e) {
            Log::error('Failed to sync Facebook posts: ' . $e->getMessage());
        }

        return $count;
    }

    /**
     * Sync Instagram posts, stories, and reels.
     * Requires Page Token and IG Business Account linked to Page.
     *
     * @param int|null $sinceDays  Skip items older than N days. Null = full history.
     * @param int      $maxPages   Graph API page walk ceiling (50 items per page).
     */
    public function syncInstagramPosts(?int $sinceDays = 30, int $maxPages = 20): int
    {
        $igAccountId = config('meta.ig_account_id');
        if (!$igAccountId || !config('meta.page_token')) {
            // Try auto-discover from page
            $igAccountId = $this->discoverIgAccount();
            if (!$igAccountId) {
                Log::info('Meta IG Account ID not configured. Skipping IG posts sync.');
                return 0;
            }
        }

        $count = 0;
        $cutoff = $sinceDays !== null ? Carbon::now()->subDays($sinceDays) : null;

        try {
            $media = $this->api->getIgMedia($igAccountId, [
                'id', 'caption', 'media_type', 'media_product_type', 'permalink',
                'thumbnail_url', 'media_url', 'timestamp', 'like_count', 'comments_count',
            ], 50, $maxPages);

            foreach ($media as $item) {
                $mediaId = $item['id'] ?? null;
                if (!$mediaId) continue;

                // Respect the since-days cutoff when set; full-history runs pass null
                // so every media item is persisted regardless of age.
                $createdTime = isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null;
                if ($cutoff && $createdTime && $createdTime->lt($cutoff)) {
                    continue;
                }

                $postType = $this->mapIgMediaType($item['media_type'] ?? '', $item['media_product_type'] ?? '');

                // Get media insights based on type
                $insights = $this->fetchIgMediaInsights($mediaId, $postType);
                
                $rawMediaUrl = $item['thumbnail_url'] ?? ($item['media_url'] ?? null);
                $localMediaUrl = $this->downloadMedia($rawMediaUrl, 'ig_' . $mediaId);

                $post = MetaPostInsight::updateOrCreate(
                    ['post_id' => $mediaId],
                    [
                        'source' => 'instagram',
                        'source_id' => $igAccountId,
                        'post_type' => $postType,
                        'message' => mb_substr($item['caption'] ?? '', 0, 65000),
                        'permalink_url' => $item['permalink'] ?? null,
                        'media_url' => $localMediaUrl,
                        'created_at_meta' => $createdTime,
                        'impressions' => $insights['impressions'] ?? 0,
                        'reach' => $insights['reach'] ?? 0,
                        'likes' => $item['like_count'] ?? 0,
                        'comments' => $item['comments_count'] ?? 0,
                        'shares' => $insights['shares'] ?? 0,
                        'saves' => $insights['saved'] ?? 0,
                        'video_views' => $insights['views'] ?? 0,
                        'clicks' => 0,
                        'exits' => null,
                        'replies' => $insights['replies'] ?? null,
                        'taps_forward' => null,
                        'taps_back' => null,
                        'plays' => $insights['views'] ?? null,
                        'synced_at' => now(),
                    ]
                );

                // Sync full media set (carousel children, video + cover, etc).
                // Failures are logged but non-fatal — existing insights row stays.
                try {
                    $this->syncIgPostMedia($post, $item);
                } catch (Exception $e) {
                    Log::warning("IG media sync failed for {$mediaId}: " . $e->getMessage());
                }

                $count++;
            }
        } catch (Exception $e) {
            Log::error('Failed to sync Instagram posts: ' . $e->getMessage());
        }

        return $count;
    }

    /**
     * Fetch Facebook post insights.
     * Updated for Graph API v21.0+ (Nov 2025): post_impressions deprecated → post_media_view.
     */
    private function fetchFbPostInsights(string $postId): array
    {
        $insights = [];

        // v21.0+: post_impressions replaced by post_media_view
        // Batch post_clicks into the same API call to avoid extra round-trips
        try {
            $response = $this->api->getPostInsights($postId, 'post_media_view,post_clicks');
            foreach ($response['data'] ?? [] as $metric) {
                $name = $metric['name'] ?? '';
                $value = $metric['values'][0]['value'] ?? 0;
                if ($name === 'post_media_view') {
                    $insights['impressions'] = $value;
                } elseif ($name === 'post_clicks') {
                    $insights['clicks'] = $value;
                }
            }
        } catch (Exception $e) {
            Log::debug("Could not fetch FB post insights for {$postId}: " . $e->getMessage());
        }

        // Get comments, shares, reactions from post object (most reliable method)
        try {
            $postData = $this->api->getWithPageToken($postId, [
                'fields' => 'shares,comments.summary(true),reactions.summary(true)',
            ]);
            $insights['shares'] = $postData['shares']['count'] ?? 0;
            $insights['comments'] = $postData['comments']['summary']['total_count'] ?? 0;
            $insights['likes'] = $postData['reactions']['summary']['total_count'] ?? 0;
        } catch (Exception $e) {
            Log::debug("Could not fetch post object fields for FB post {$postId}: " . $e->getMessage());
        }

        return $insights;
    }

    /**
     * Fetch Instagram media insights based on type.
     * Updated for Graph API v21.0+: impressions/plays deprecated → views.
     */
    private function fetchIgMediaInsights(string $mediaId, string $postType): array
    {
        try {
            // v21.0+: 'impressions' and 'plays' replaced by 'views'
            $metricsMap = match ($postType) {
                'story' => 'views,reach,replies',
                'reel' => 'views,reach,saved,shares',
                default => 'views,reach,saved,shares',
            };

            $response = $this->api->getPostInsights($mediaId, $metricsMap);

            $insights = [];
            foreach ($response['data'] ?? [] as $metric) {
                $name = $metric['name'] ?? '';
                $value = $metric['values'][0]['value'] ?? 0;
                $insights[$name] = $value;
            }

            // Map 'views' to 'impressions' for our unified schema
            if (isset($insights['views'])) {
                $insights['impressions'] = $insights['views'];
            }

            return $insights;
        } catch (Exception $e) {
            Log::debug("Could not fetch insights for IG media {$mediaId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Map Facebook post type to our unified post_type.
     */
    private function mapFbPostType(string $type): string
    {
        return match ($type) {
            'photo' => 'photo',
            'video' => 'video',
            'link' => 'link',
            default => 'status',
        };
    }

    /**
     * Map Instagram media_type + media_product_type to our unified post_type.
     */
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

    /**
     * Sync all media items for an Instagram post — handles:
     *  - IMAGE posts: 1 row (position 0, IMAGE, downloaded locally)
     *  - VIDEO posts: 1 row (position 0, VIDEO, video_url + thumbnail downloaded)
     *  - CAROUSEL_ALBUM: fetches `/{id}/children` API and loops, N rows
     *
     * Idempotent: uses (meta_post_insight_id, position) unique key. Existing rows
     * with a local_path are left alone (saves bandwidth on re-runs).
     */
    private function syncIgPostMedia(MetaPostInsight $post, array $item): void
    {
        $mediaType = strtoupper($item['media_type'] ?? '');
        $igId = $item['id'] ?? null;

        if ($mediaType === 'CAROUSEL_ALBUM' && $igId) {
            // Fetch children list — Graph API v19+ exposes them under /children
            $response = $this->api->getWithPageToken("{$igId}/children", [
                'fields' => 'id,media_type,media_url,thumbnail_url',
            ]);
            $children = $response['data'] ?? [];
            foreach (array_values($children) as $index => $child) {
                $this->upsertMediaItem($post, $index, $child);
            }
            return;
        }

        // Single item (IMAGE or VIDEO) — use the post-level fields.
        $this->upsertMediaItem($post, 0, [
            'id'            => $igId,
            'media_type'    => $mediaType ?: 'IMAGE',
            'media_url'     => $item['media_url'] ?? null,
            'thumbnail_url' => $item['thumbnail_url'] ?? null,
        ]);
    }

    /**
     * Insert or refresh one media item. Downloads the file to local storage so
     * it survives Meta CDN token expiry (~1-2h), and updates the row fields.
     */
    private function upsertMediaItem(MetaPostInsight $post, int $position, array $child): void
    {
        $childIgId  = $child['id'] ?? null;
        $type       = strtoupper($child['media_type'] ?? 'IMAGE');
        $mediaUrl   = $child['media_url'] ?? null;
        $thumbUrl   = $child['thumbnail_url'] ?? null;
        $isVideo    = $type === 'VIDEO';

        // For videos: media_url is the .mp4, thumbnail_url is the poster frame.
        // For images: media_url is the image, no separate thumbnail needed.
        $primaryUrl   = $mediaUrl;
        $prefix       = 'ig_' . $post->post_id . '_' . $position;
        $localPath    = null;
        $localThumb   = null;
        $mimeType     = null;
        $sizeBytes    = null;

        if ($primaryUrl) {
            [$localPath, $mimeType, $sizeBytes] = $this->downloadToStorage($primaryUrl, $prefix);
        }

        if ($isVideo && $thumbUrl) {
            [$localThumb] = $this->downloadToStorage($thumbUrl, $prefix . '_thumb');
        }

        MetaPostMedia::updateOrCreate(
            [
                'meta_post_insight_id' => $post->id,
                'position'             => $position,
            ],
            [
                'media_type'           => $isVideo ? 'VIDEO' : 'IMAGE',
                'ig_media_id'          => $childIgId,
                'original_url'         => $mediaUrl,
                'video_url'            => $isVideo ? $mediaUrl : null,
                'thumbnail_url'        => $thumbUrl,
                'local_path'           => $localPath,
                'local_disk'           => $localPath ? 'public' : null,
                'local_thumbnail_path' => $localThumb,
                'mime_type'            => $mimeType,
                'size_bytes'           => $sizeBytes,
                'downloaded_at'        => $localPath ? now() : null,
            ]
        );
    }

    /**
     * Download a URL to public storage and return [path, mime, size].
     * Returns [null, null, null] on failure — the caller should fall back
     * to the original_url for display (proxy route will serve it temporarily).
     */
    private function downloadToStorage(?string $url, string $prefix): array
    {
        if (! $url) {
            return [null, null, null];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::debug("downloadToStorage: HTTP {$response->status()} for {$prefix}");
                return [null, null, null];
            }

            $contentType = $response->header('Content-Type') ?: 'application/octet-stream';
            $mime = trim(explode(';', $contentType)[0]);
            $ext = match (strtolower($mime)) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png'              => 'png',
                'image/webp'             => 'webp',
                'image/gif'              => 'gif',
                'video/mp4'              => 'mp4',
                'video/quicktime'        => 'mov',
                'video/webm'             => 'webm',
                default                  => $this->extFromUrl($url) ?? 'bin',
            };

            $path = "meta_media/{$prefix}.{$ext}";
            $body = $response->body();
            Storage::disk('public')->put($path, $body);

            return [$path, $mime, strlen($body)];
        } catch (Exception $e) {
            Log::debug("downloadToStorage failed [{$prefix}]: " . $e->getMessage());
            return [null, null, null];
        }
    }

    private function extFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if (preg_match('/\.([a-zA-Z0-9]{2,5})$/', $path, $m)) {
            return strtolower($m[1]);
        }
        return null;
    }

    /**
     * Download expiring Meta CDN media to local storage so it remains accessible.
     */
    private function downloadMedia(?string $url, string $filenamePrefix): ?string
    {
        if (!$url) return null;

        try {
            // Determine extension from URL or default to jpg
            $ext = 'jpg';
            $path = parse_url($url, PHP_URL_PATH);
            if ($path && preg_match('/\.([a-zA-Z0-9]+)$/', $path, $matches)) {
                $ext = $matches[1];
            }
            
            $filename = "meta_media/{$filenamePrefix}.{$ext}";
            
            // Check if we already have it to save bandwidth and time
            if (Storage::disk('public')->exists($filename)) {
                return "/storage/" . $filename;
            }

            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])
                ->get($url);
            
            if ($response->successful()) {
                // If the URL has no extension but we can guess from content type
                $contentType = $response->header('Content-Type');
                if ($contentType) {
                    if (str_contains($contentType, 'video/mp4')) $ext = 'mp4';
                    elseif (str_contains($contentType, 'image/png')) $ext = 'png';
                    elseif (str_contains($contentType, 'image/webp')) $ext = 'webp';
                }

                $filename = "meta_media/{$filenamePrefix}.{$ext}";
                Storage::disk('public')->put($filename, $response->body());
                
                return "/storage/" . $filename;
            }
        } catch (Exception $e) {
            Log::debug("Failed to download media [{$filenamePrefix}]: " . $e->getMessage());
        }

        // Fallback to original URL if download fails
        return $url;
    }
}
