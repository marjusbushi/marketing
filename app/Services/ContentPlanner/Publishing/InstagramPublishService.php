<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publishes a post to an Instagram Business account via the Graph API.
 *
 * Token plumbing: same as FacebookPublishService — config('meta.*') has
 * already been hydrated by MetaTokenResolver at boot. Both services share
 * the page token (IG Business is reached through its parent FB Page).
 *
 * IG's three-step publish (vs FB's one-shot) is the load-bearing detail:
 *
 *   1. Create a media container (POST /IG_ID/media). Returns container_id.
 *      For video / Reels / carousels, this kicks off Meta-side processing.
 *   2. Poll GET /CONTAINER_ID?fields=status_code until it returns
 *      "FINISHED". Required for video; sometimes required for image too if
 *      Meta is busy. Skipping this and going straight to step 3 is the
 *      single most common reason IG video publishing "randomly fails".
 *   3. POST /IG_ID/media_publish with creation_id=CONTAINER_ID.
 *
 * Content type mapping:
 *   post  → IMAGE or VIDEO based on media (legacy IG feed)
 *   reel  → REELS (note: plural — Meta enforces this casing)
 *   story → STORIES
 *
 * FB-CDN workaround for the 9004/2207052 issue:
 *   Since 2026-03-13, IG content_publish silently rejects every external
 *   CDN URL we hand it (R2, S3, CloudFront, Azure, GCP — all of them).
 *   The fix that actually works is the "FB-bounce trick": upload the media
 *   to the parent FB Page as `published=false`, take the resulting
 *   fbcdn.net URL, and feed THAT to IG. IG accepts its own CDN. After
 *   the IG operation finishes (success or failure), the unpublished FB
 *   stub is deleted by FbCdnHelper::cleanup(). See FbCdnHelper for the
 *   full mechanic and failure modes.
 */
class InstagramPublishService implements PlatformPublisherInterface
{
    /** Max time we'll wait for Meta-side video processing before giving up. */
    private const STATUS_POLL_TIMEOUT_SECONDS = 300;

    /** Pause between status checks. 5s matches Meta's recommended cadence. */
    private const STATUS_POLL_INTERVAL_SECONDS = 5;

    public function publish(ContentPost $post, ?string $platformContent = null): PublishResult
    {
        $fbCdn = new FbCdnHelper();

        try {
            $accountId = (string) config('meta.ig_account_id');
            $token = (string) config('meta.page_token');
            $apiVersion = (string) config('meta.api_version', 'v21.0');
            $graphUrl = rtrim((string) config('meta.base_url', 'https://graph.facebook.com'), '/') . '/' . $apiVersion;

            if (! $accountId || ! $token) {
                return PublishResult::failure(
                    'Instagram business account ose access token mungojnë. Hap /marketing/meta-auth për të lidhur llogarinë.'
                );
            }

            $caption = $platformContent ?? $post->content ?? '';
            $media = $post->media;

            if ($media->isEmpty()) {
                return PublishResult::failure('Instagram kërkon të paktën një foto ose video.');
            }

            $containerId = $media->count() === 1
                ? $this->createSingleContainer($graphUrl, $accountId, $token, $caption, $media->first(), $post->content_type, $fbCdn)
                : $this->createCarouselContainer($graphUrl, $accountId, $token, $caption, $media, $fbCdn);

            if ($containerId instanceof PublishResult) {
                return $containerId; // already a failure result
            }

            $waitResult = $this->waitForContainerReady($graphUrl, $containerId, $token);
            if ($waitResult !== null) {
                return $waitResult;
            }

            $publishResponse = Http::asForm()->post("{$graphUrl}/{$accountId}/media_publish", [
                'access_token' => $token,
                'creation_id' => $containerId,
            ]);

            if ($publishResponse->failed()) {
                return PublishResult::failure($this->formatMetaError($publishResponse->json('error') ?? []));
            }

            $igMediaId = (string) $publishResponse->json('id');
            $permalink = $this->fetchPermalink($graphUrl, $igMediaId, $token);

            return PublishResult::success($igMediaId, $permalink);
        } catch (\Throwable $e) {
            $safe = MetaErrorSanitizer::redact($e->getMessage());
            Log::error('Instagram publish exception', ['post_id' => $post->id, 'error' => $safe]);
            return PublishResult::failure($safe);
        } finally {
            // Always tear down the unpublished FB stubs, even on early throws.
            // Cleanup never throws — see FbCdnHelper::cleanup().
            $fbCdn->cleanup();
        }
    }

    public function supports(string $platform): bool
    {
        return $platform === 'instagram';
    }

    /**
     * Create the container for a single media item. Returns the container
     * id on success, or a PublishResult on failure (so the caller can
     * surface the original Meta error verbatim).
     *
     * @return string|PublishResult
     */
    protected function createSingleContainer(string $graphUrl, string $accountId, string $token, string $caption, $media, ?string $contentType, FbCdnHelper $fbCdn)
    {
        try {
            $cdnUrl = $fbCdn->uploadAndGetCdnUrl($media);
        } catch (\Throwable $e) {
            return PublishResult::failure(
                'Përgatitja e mediave për Instagram dështoi: ' . MetaErrorSanitizer::redact($e->getMessage())
            );
        }

        $params = ['access_token' => $token, 'caption' => $caption];

        if (str_starts_with((string) $media->mime_type, 'video/')) {
            $params['media_type'] = $contentType === 'reel' ? 'REELS' : ($contentType === 'story' ? 'STORIES' : 'VIDEO');
            $params['video_url'] = $cdnUrl;
        } else {
            if ($contentType === 'story') {
                $params['media_type'] = 'STORIES';
            }
            $params['image_url'] = $cdnUrl;
        }

        $response = Http::asForm()->post("{$graphUrl}/{$accountId}/media", $params);

        if ($response->failed() || ! $response->json('id')) {
            return PublishResult::failure($this->formatMetaError($response->json('error') ?? []));
        }

        return (string) $response->json('id');
    }

    /**
     * Build a CAROUSEL container. Each child gets its own container first;
     * video children are polled until FINISHED before the parent is even
     * created — that avoids the parent failing because a child video isn't
     * done processing. Each child URL goes through the FB-CDN bounce.
     *
     * @return string|PublishResult
     */
    protected function createCarouselContainer(string $graphUrl, string $accountId, string $token, string $caption, $mediaItems, FbCdnHelper $fbCdn)
    {
        $childIds = [];

        foreach ($mediaItems as $item) {
            try {
                $cdnUrl = $fbCdn->uploadAndGetCdnUrl($item);
            } catch (\Throwable $e) {
                return PublishResult::failure(
                    'Carousel media prep dështoi: ' . MetaErrorSanitizer::redact($e->getMessage())
                );
            }

            $params = ['access_token' => $token, 'is_carousel_item' => 'true'];
            $isVideo = str_starts_with((string) $item->mime_type, 'video/');

            if ($isVideo) {
                $params['media_type'] = 'VIDEO';
                $params['video_url'] = $cdnUrl;
            } else {
                $params['image_url'] = $cdnUrl;
            }

            $childResponse = Http::asForm()->post("{$graphUrl}/{$accountId}/media", $params);

            if ($childResponse->failed() || ! $childResponse->json('id')) {
                return PublishResult::failure($this->formatMetaError($childResponse->json('error') ?? []));
            }

            $childId = (string) $childResponse->json('id');

            if ($isVideo) {
                $waitResult = $this->waitForContainerReady($graphUrl, $childId, $token);
                if ($waitResult !== null) {
                    return $waitResult;
                }
            }

            $childIds[] = $childId;
        }

        $parentResponse = Http::asForm()->post("{$graphUrl}/{$accountId}/media", [
            'access_token' => $token,
            'caption' => $caption,
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
        ]);

        if ($parentResponse->failed() || ! $parentResponse->json('id')) {
            return PublishResult::failure($this->formatMetaError($parentResponse->json('error') ?? []));
        }

        return (string) $parentResponse->json('id');
    }

    /**
     * Poll GET /CONTAINER_ID?fields=status_code until Meta says FINISHED,
     * ERROR, or EXPIRED. Returns null on success (caller proceeds to
     * publish), or a PublishResult on failure (caller returns it directly).
     * Times out after STATUS_POLL_TIMEOUT_SECONDS — 5 minutes is the
     * documented upper bound for Meta video ingest under normal load.
     */
    protected function waitForContainerReady(string $graphUrl, string $containerId, string $token): ?PublishResult
    {
        $deadline = time() + self::STATUS_POLL_TIMEOUT_SECONDS;

        while (time() < $deadline) {
            $response = Http::get("{$graphUrl}/{$containerId}", [
                'access_token' => $token,
                'fields' => 'status_code,status',
            ]);

            $statusCode = $response->json('status_code');

            if ($statusCode === 'FINISHED') {
                return null;
            }

            if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
                $detail = $response->json('status') ?? $statusCode;
                return PublishResult::failure(
                    "Procesimi i video-s nga Instagram dështoi: {$detail}. Verifiko codec-un (MP4 H.264+AAC) dhe dimensionet."
                );
            }

            // IN_PROGRESS or PUBLISHED-but-not-ours-yet — wait and try again.
            sleep(self::STATUS_POLL_INTERVAL_SECONDS);
        }

        return PublishResult::failure(
            'Instagram po e procesonte video-n më tepër se 5 min. Riprovo më vonë.'
        );
    }

    protected function fetchPermalink(string $graphUrl, string $mediaId, string $token): ?string
    {
        $response = Http::get("{$graphUrl}/{$mediaId}", [
            'access_token' => $token,
            'fields' => 'permalink',
        ]);
        return $response->json('permalink');
    }

    /**
     * Translate Meta's error envelope. Codes we map for clarity:
     *
     *   190    — token expired or invalid
     *   24     — IG content publishing limit reached (50 posts / 24h)
     *   9004   — caption too long (max 2200 chars)
     *   2207020 — invalid image URL or unreachable from Meta
     *   36003  — media format unsupported (codec / container)
     */
    protected function formatMetaError(array $error): string
    {
        $code = $error['code'] ?? null;
        $subcode = $error['error_subcode'] ?? null;
        $message = $error['message'] ?? 'Unknown Instagram API error';

        return match ($code) {
            190 => "Token i Meta-s ka skaduar ose është i pavlefshëm — rilidh faqen tek /marketing/meta-auth. ({$message})",
            24 => "Kufijri 24-orësh i Instagram-it u arrit (50 post). Riprovo nesër. ({$message})",
            9004 => "Caption-i kalon limitin e Instagram-it (2200 karaktere). Shkurtoje. ({$message})",
            2207020 => "Instagram nuk e arriti URL-në e media-s — kontrollo R2 ose APP_URL publike. ({$message})",
            36003 => "Format video i papranuar nga Instagram — përdor MP4 me H.264+AAC. ({$message})",
            default => "Instagram API: {$message}" . ($code ? " (code {$code}" . ($subcode ? "/{$subcode}" : '') . ')' : ''),
        };
    }
}
