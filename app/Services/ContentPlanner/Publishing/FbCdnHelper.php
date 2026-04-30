<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Workaround for the IG Graph API "Media URI doesn't meet requirements"
 * issue (error 9004 / subcode 2207052) that started 2026-03-13 and affects
 * every external CDN we tried — Cloudflare R2 (custom domain, Worker proxy,
 * r2.dev), AWS S3, CloudFront, Azure, GCP. IG's content_publish fetcher
 * silently rejects these even when curl + Meta's own sharing debugger fetch
 * the same URL successfully.
 *
 * The trick: ALL of those URLs are external to Meta. But fbcdn.net URLs are
 * Meta's own — and the IG fetcher accepts them. So we hop the file through
 * Facebook first:
 *
 *   1. Upload our R2-hosted media to the FB Page as `published=false`
 *      (invisible to anyone but Page admins).
 *   2. Pull back the resulting fbcdn.net URL.
 *   3. Hand that URL to IG's content_publish endpoint as image_url /
 *      video_url. IG accepts it.
 *   4. Once IG is done — success or failure — delete the unpublished FB
 *      stub. No public artifact, no quota leak.
 *
 * Confirmed working 2026-04-30 for both photos (instant) and Reels videos
 * (FB transcodes in ~3s; far faster than IG's own ingest).
 */
class FbCdnHelper
{
    /** Track FB media IDs created during one publish so they can be torn down. */
    protected array $tempIds = [];

    /** Max time we wait for FB to finish processing a video upload. */
    private const VIDEO_READY_TIMEOUT_SECONDS = 120;
    private const VIDEO_READY_POLL_INTERVAL_SECONDS = 3;

    /**
     * Upload a single media item to FB as unpublished and return the
     * fbcdn.net URL we can hand to IG. Throws on any failure so callers
     * can short-circuit + run cleanup.
     */
    public function uploadAndGetCdnUrl(ContentMedia $media): string
    {
        $isVideo = str_starts_with((string) $media->mime_type, 'video/');
        return $isVideo
            ? $this->uploadVideo($media)
            : $this->uploadPhoto($media);
    }

    protected function uploadPhoto(ContentMedia $media): string
    {
        [$pageId, $token, $graphUrl] = $this->metaConfig();

        $upload = Http::asForm()->post("{$graphUrl}/{$pageId}/photos", [
            'access_token' => $token,
            'url' => $media->url,
            'published' => 'false',
        ]);

        if ($upload->failed() || ! $upload->json('id')) {
            $err = MetaErrorSanitizer::redact($upload->json('error.message') ?? 'FB photo upload failed');
            throw new \RuntimeException("FB photo upload failed: {$err}");
        }

        $photoId = (string) $upload->json('id');
        $this->tempIds[] = $photoId;

        // Photos are immediately addressable; pull the canonical fbcdn URL
        // from the `images` array (highest-resolution entry first).
        $details = Http::get("{$graphUrl}/{$photoId}", [
            'access_token' => $token,
            'fields' => 'images',
        ]);

        $cdnUrl = $details->json('images.0.source');
        if (! $cdnUrl) {
            throw new \RuntimeException('FB photo CDN URL not returned');
        }

        return $cdnUrl;
    }

    protected function uploadVideo(ContentMedia $media): string
    {
        [$pageId, $token, $graphUrl] = $this->metaConfig();

        $upload = Http::asForm()->timeout(180)->post("{$graphUrl}/{$pageId}/videos", [
            'access_token' => $token,
            'file_url' => $media->url,
            'description' => 'flare-internal',
            'published' => 'false',
        ]);

        if ($upload->failed() || ! $upload->json('id')) {
            $err = MetaErrorSanitizer::redact($upload->json('error.message') ?? 'FB video upload failed');
            throw new \RuntimeException("FB video upload failed: {$err}");
        }

        $videoId = (string) $upload->json('id');
        $this->tempIds[] = $videoId;

        // Videos transcode async; poll status until FB exposes a `source` URL.
        // Empirically ~3s for short clips but Meta sometimes takes longer when
        // their pipeline is busy. Time out after ~2 min — anything past that
        // is broken, not slow.
        $deadline = time() + self::VIDEO_READY_TIMEOUT_SECONDS;
        while (time() < $deadline) {
            $status = Http::get("{$graphUrl}/{$videoId}", [
                'access_token' => $token,
                'fields' => 'status,source',
            ]);

            $videoStatus = $status->json('status.video_status');
            $source = $status->json('source');

            if ($videoStatus === 'ready' && $source) {
                return $source;
            }

            if ($videoStatus === 'error') {
                $reason = $status->json('status.processing_phase') ?? 'error';
                throw new \RuntimeException("FB video processing failed: {$reason}");
            }

            sleep(self::VIDEO_READY_POLL_INTERVAL_SECONDS);
        }

        throw new \RuntimeException('FB video processing timeout (>2min)');
    }

    /**
     * Bulk variant for carousels — same trick, one FB upload per child.
     *
     * @param  iterable<ContentMedia>  $items
     * @return array<string>  fbcdn URLs in the same order as input
     */
    public function uploadAllAndGetCdnUrls(iterable $items): array
    {
        $urls = [];
        foreach ($items as $media) {
            $urls[] = $this->uploadAndGetCdnUrl($media);
        }
        return $urls;
    }

    /**
     * Tear down every temp FB media this helper created. Safe to call
     * multiple times and from a `finally` block — it never throws and
     * keeps trying even if individual deletes fail.
     */
    public function cleanup(): void
    {
        if (empty($this->tempIds)) {
            return;
        }

        [, $token, $graphUrl] = $this->metaConfig();

        foreach ($this->tempIds as $id) {
            try {
                Http::delete("{$graphUrl}/{$id}", ['access_token' => $token]);
            } catch (\Throwable $e) {
                Log::warning('FB temp media cleanup failed', [
                    'id' => $id,
                    'error' => MetaErrorSanitizer::redact($e->getMessage()),
                ]);
            }
        }

        $this->tempIds = [];
    }

    /**
     * @return array{0: string, 1: string, 2: string}  [pageId, pageToken, graphBaseUrl]
     */
    protected function metaConfig(): array
    {
        $pageId = (string) config('meta.page_id');
        $token = (string) config('meta.page_token');
        $apiVersion = (string) config('meta.api_version', 'v21.0');
        $graphUrl = rtrim((string) config('meta.base_url', 'https://graph.facebook.com'), '/') . '/' . $apiVersion;

        if (! $pageId || ! $token) {
            throw new \RuntimeException('Meta page_id or page_token missing — reconnect /marketing/meta-auth.');
        }

        return [$pageId, $token, $graphUrl];
    }
}
