<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;
use App\Services\ContentPlanner\Publishing\MetaErrorSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publishes a post to a Facebook Page via the Graph API.
 *
 * Token plumbing: reads config('meta.page_token') and config('meta.page_id'),
 * which AppServiceProvider hydrates at boot via MetaTokenResolver. Local
 * .env values win; otherwise the resolver pulls from meta_tokens (OAuth)
 * or hrms_meta_credentials (HRMS-encrypted) on the DIS database.
 *
 * Supported shapes:
 *   • text-only            → POST /PAGE_ID/feed
 *   • single photo         → POST /PAGE_ID/photos with public image URL
 *   • carousel (≥2 photos) → unpublished /PAGE_ID/photos for each, then
 *                            /PAGE_ID/feed with attached_media[] linking them
 *   • video                → POST /PAGE_ID/videos with file_url; Meta fetches
 *                            the binary directly (no upload from us, so the
 *                            URL must be publicly reachable — R2 does that)
 */
class FacebookPublishService implements PlatformPublisherInterface
{
    public function publish(ContentPost $post, ?string $platformContent = null): PublishResult
    {
        try {
            $pageId = (string) config('meta.page_id');
            $token = (string) config('meta.page_token');
            $apiVersion = (string) config('meta.api_version', 'v21.0');
            $graphUrl = rtrim((string) config('meta.base_url', 'https://graph.facebook.com'), '/') . '/' . $apiVersion;

            if (! $pageId || ! $token) {
                return PublishResult::failure(
                    'Facebook page ID ose access token mungojnë. Hap /marketing/meta-auth për të lidhur faqen.'
                );
            }

            $message = $platformContent ?? $post->content ?? '';
            $images = $post->media->filter(fn ($m) => str_starts_with((string) $m->mime_type, 'image/'))->values();
            $video = $post->media->first(fn ($m) => str_starts_with((string) $m->mime_type, 'video/'));

            if ($video) {
                $response = $this->postVideo($graphUrl, $pageId, $token, $message, $video->url);
            } elseif ($images->count() >= 2) {
                $response = $this->postCarousel($graphUrl, $pageId, $token, $message, $images);
            } elseif ($images->count() === 1) {
                $response = $this->postSinglePhoto($graphUrl, $pageId, $token, $message, $images->first()->url);
            } else {
                $response = $this->postTextOnly($graphUrl, $pageId, $token, $message);
            }

            if ($response->failed()) {
                return PublishResult::failure($this->formatMetaError($response->json('error') ?? []));
            }

            $postId = (string) ($response->json('post_id') ?? $response->json('id') ?? '');
            if ($postId === '') {
                return PublishResult::failure('Facebook returned an empty post id.');
            }

            $permalink = $this->fetchPermalink($graphUrl, $postId, $token);

            return PublishResult::success($postId, $permalink);
        } catch (\Throwable $e) {
            $safe = MetaErrorSanitizer::redact($e->getMessage());
            Log::error('Facebook publish exception', ['post_id' => $post->id, 'error' => $safe]);
            return PublishResult::failure($safe);
        }
    }

    public function supports(string $platform): bool
    {
        return $platform === 'facebook';
    }

    protected function postTextOnly(string $graphUrl, string $pageId, string $token, string $message): \Illuminate\Http\Client\Response
    {
        return Http::asForm()->post("{$graphUrl}/{$pageId}/feed", [
            'access_token' => $token,
            'message' => $message,
        ]);
    }

    protected function postSinglePhoto(string $graphUrl, string $pageId, string $token, string $caption, string $imageUrl): \Illuminate\Http\Client\Response
    {
        return Http::asForm()->post("{$graphUrl}/{$pageId}/photos", [
            'access_token' => $token,
            'message' => $caption,
            'url' => $imageUrl,
        ]);
    }

    /**
     * Carousel/album: upload each child as `published=false` so it doesn't
     * appear standalone, then create the album post that references them.
     * If any child upload fails, abort early — half-uploaded albums are a
     * worse failure mode than the whole post failing.
     */
    protected function postCarousel(string $graphUrl, string $pageId, string $token, string $caption, $images): \Illuminate\Http\Client\Response
    {
        $attachedMedia = [];
        foreach ($images as $image) {
            $childResponse = Http::asForm()->post("{$graphUrl}/{$pageId}/photos", [
                'access_token' => $token,
                'url' => $image->url,
                'published' => 'false',
            ]);

            if ($childResponse->failed() || ! $childResponse->json('id')) {
                return $childResponse;
            }

            $attachedMedia[] = ['media_fbid' => $childResponse->json('id')];
        }

        // attached_media[] in Graph requires JSON-encoded array entries.
        $payload = [
            'access_token' => $token,
            'message' => $caption,
        ];
        foreach ($attachedMedia as $idx => $entry) {
            $payload["attached_media[{$idx}]"] = json_encode($entry);
        }

        return Http::asForm()->post("{$graphUrl}/{$pageId}/feed", $payload);
    }

    protected function postVideo(string $graphUrl, string $pageId, string $token, string $description, string $videoUrl): \Illuminate\Http\Client\Response
    {
        return Http::asForm()->post("{$graphUrl}/{$pageId}/videos", [
            'access_token' => $token,
            'file_url' => $videoUrl,
            'description' => $description,
        ]);
    }

    /**
     * Pull the canonical Page-post URL Facebook returns under permalink_url.
     * Best-effort — if it fails we still report success with a null permalink.
     */
    protected function fetchPermalink(string $graphUrl, string $postId, string $token): ?string
    {
        $response = Http::get("{$graphUrl}/{$postId}", [
            'access_token' => $token,
            'fields' => 'permalink_url',
        ]);

        return $response->json('permalink_url');
    }

    /**
     * Translate Meta's error envelope into a single readable string.
     * Common codes we map for clarity; everything else falls through with
     * the raw message so support staff can grep for it.
     *
     *   190    — token expired or invalid; needs reauth
     *   200    — permission missing on the token
     *   1366046 — media URL not reachable from Meta's servers (CDN config)
     */
    protected function formatMetaError(array $error): string
    {
        $code = $error['code'] ?? null;
        $message = $error['message'] ?? 'Unknown Facebook API error';

        return match ($code) {
            190 => "Token i Meta-s ka skaduar ose është i pavlefshëm — rilidh faqen tek /marketing/meta-auth. ({$message})",
            200 => "Token-i nuk ka leje për këtë veprim — verifiko scopes te Meta App. ({$message})",
            1366046 => "URL-ja e media nuk u arrit nga server-at e Meta — kontrollo që R2 ose APP_URL është publike. ({$message})",
            default => "Facebook API: {$message}" . ($code ? " (code {$code})" : ''),
        };
    }
}
