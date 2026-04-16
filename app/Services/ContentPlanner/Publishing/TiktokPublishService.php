<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;
use App\Models\TikTok\TikTokToken;
use App\Services\Tiktok\TiktokApiService;
use Illuminate\Support\Facades\Log;

class TiktokPublishService implements PlatformPublisherInterface
{
    /**
     * Default privacy when creator info is unavailable.
     * SELF_ONLY is the safest default — never accidentally go public.
     */
    private const DEFAULT_PRIVACY_LEVEL = 'SELF_ONLY';

    /**
     * Preferred privacy level when creator supports it.
     */
    private const DESIRED_PRIVACY_LEVEL = 'PUBLIC_TO_EVERYONE';

    /**
     * Max title length (UTF-16 code units) for video posts.
     */
    private const MAX_VIDEO_TITLE_LENGTH = 2200;

    /**
     * Max title length for photo posts.
     */
    private const MAX_PHOTO_TITLE_LENGTH = 90;

    private TiktokApiService $api;

    public function __construct(TiktokApiService $api)
    {
        $this->api = $api;
    }

    public function publish(ContentPost $post, ?string $platformContent = null): PublishResult
    {
        try {
            // Validate we have an active token
            $token = TiktokToken::getActiveToken();
            if (!$token) {
                return PublishResult::failure('No active TikTok token found. Please authenticate first.');
            }

            if ($token->isAccessTokenExpired() && $token->isRefreshTokenExpired()) {
                return PublishResult::failure('TikTok tokens are expired. Please re-authenticate.');
            }

            // Set the token on the API service
            $this->api->setToken($token);

            $media = $post->media()->first();
            $content = $platformContent ?? $post->content;

            // Determine media type and route accordingly
            if ($media && str_starts_with($media->mime_type ?? '', 'video/')) {
                return $this->publishVideo($post, $media, $content);
            }

            if ($media && str_starts_with($media->mime_type ?? '', 'image/')) {
                return $this->publishPhoto($post, $content);
            }

            return PublishResult::failure('TikTok requires a video or image media item.');
        } catch (\Throwable $e) {
            Log::error('TikTok publish exception', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return PublishResult::failure($e->getMessage());
        }
    }

    /**
     * Publish a video via PULL_FROM_URL.
     */
    private function publishVideo(ContentPost $post, $media, string $content): PublishResult
    {
        // Query creator info to validate privacy levels and settings
        $privacyLevel = $this->resolvePrivacyLevel();
        $title = mb_substr($content, 0, self::MAX_VIDEO_TITLE_LENGTH);

        $response = $this->api->post('/post/publish/video/init/', [
            'post_info' => [
                'title' => $title,
                'privacy_level' => $privacyLevel,
                'disable_duet' => false,
                'disable_stitch' => false,
                'disable_comment' => false,
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $media->url,
            ],
        ]);

        $publishId = $response['data']['publish_id'] ?? null;
        if (!$publishId) {
            return PublishResult::failure('No publish_id returned from TikTok.');
        }

        return $this->pollPublishStatus($publishId);
    }

    /**
     * Publish a photo post (single image or carousel up to 35 images).
     */
    private function publishPhoto(ContentPost $post, string $content): PublishResult
    {
        $images = $post->media()
            ->where('mime_type', 'like', 'image/%')
            ->pluck('url')
            ->take(35)
            ->values()
            ->toArray();

        if (empty($images)) {
            return PublishResult::failure('No image URLs found for photo post.');
        }

        $privacyLevel = $this->resolvePrivacyLevel();
        $title = mb_substr($content, 0, self::MAX_PHOTO_TITLE_LENGTH);

        $response = $this->api->post('/post/publish/content/init/', [
            'post_info' => [
                'title' => $title,
                'description' => mb_substr($content, 0, 4000),
                'privacy_level' => $privacyLevel,
                'disable_comment' => false,
                'auto_add_music' => true,
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'photo_cover_index' => 0,
                'photo_images' => $images,
            ],
            'post_mode' => 'DIRECT_POST',
            'media_type' => 'PHOTO',
        ]);

        $publishId = $response['data']['publish_id'] ?? null;
        if (!$publishId) {
            return PublishResult::failure('No publish_id returned from TikTok for photo post.');
        }

        return $this->pollPublishStatus($publishId);
    }

    /**
     * Resolve privacy level by querying creator info.
     * Defaults to SELF_ONLY if creator info unavailable (safe default).
     */
    private function resolvePrivacyLevel(): string
    {
        try {
            $creatorInfo = $this->api->post('/post/publish/creator_info/query/');
            $allowedLevels = $creatorInfo['data']['privacy_level_options'] ?? [];

            if (empty($allowedLevels)) {
                Log::warning('TikTok creator info returned no privacy options. Defaulting to SELF_ONLY.');
                return self::DEFAULT_PRIVACY_LEVEL;
            }

            // Use desired level if allowed, otherwise fall back to safest available
            if (in_array(self::DESIRED_PRIVACY_LEVEL, $allowedLevels)) {
                return self::DESIRED_PRIVACY_LEVEL;
            }

            // Preference order: FOLLOWER_OF_CREATOR > MUTUAL_FOLLOW_FRIENDS > SELF_ONLY
            $fallbackOrder = ['FOLLOWER_OF_CREATOR', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'];
            foreach ($fallbackOrder as $level) {
                if (in_array($level, $allowedLevels)) {
                    Log::info("TikTok: PUBLIC not available, using {$level}");
                    return $level;
                }
            }

            return self::DEFAULT_PRIVACY_LEVEL;
        } catch (\Throwable $e) {
            Log::warning('TikTok creator info query failed. Defaulting to SELF_ONLY.', [
                'error' => $e->getMessage(),
            ]);
            return self::DEFAULT_PRIVACY_LEVEL;
        }
    }

    /**
     * Poll TikTok for publish status with exponential backoff.
     */
    private function pollPublishStatus(string $publishId): PublishResult
    {
        $maxAttempts = 10;
        $baseDelay = 3;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep(min($baseDelay * ($attempt + 1), 30)); // Exponential up to 30s

            try {
                $statusResponse = $this->api->post('/post/publish/status/fetch/', [
                    'publish_id' => $publishId,
                ]);

                $status = $statusResponse['data']['status'] ?? null;

                if ($status === 'PUBLISH_COMPLETE') {
                    // TikTok has a known typo in their API response field name
                    $videoId = $statusResponse['data']['publicaly_available_post_id'][0]
                        ?? $statusResponse['data']['publicly_available_post_id'][0]
                        ?? $publishId;
                    return PublishResult::success((string) $videoId);
                }

                if ($status === 'FAILED') {
                    $reason = $statusResponse['data']['fail_reason'] ?? 'Unknown failure';
                    return PublishResult::failure("TikTok publish failed: {$reason}");
                }

                // Still processing (PROCESSING_DOWNLOAD, PROCESSING_UPLOAD, etc.)
                Log::debug("TikTok publish status: {$status}", ['publish_id' => $publishId, 'attempt' => $attempt + 1]);
            } catch (\Throwable $e) {
                Log::warning('TikTok status poll failed', ['publish_id' => $publishId, 'error' => $e->getMessage()]);
            }
        }

        return PublishResult::failure('TikTok publish timed out waiting for completion.');
    }

    public function supports(string $platform): bool
    {
        return $platform === 'tiktok';
    }
}
