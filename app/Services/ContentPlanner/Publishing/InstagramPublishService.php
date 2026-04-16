<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramPublishService implements PlatformPublisherInterface
{
    protected string $graphUrl = 'https://graph.facebook.com/v21.0';

    public function publish(ContentPost $post, ?string $platformContent = null): PublishResult
    {
        try {
            $accountId = config('services.instagram.business_account_id');
            $token = config('services.instagram.access_token');

            if (!$accountId || !$token) {
                return PublishResult::failure('Instagram business account ID or access token not configured.');
            }

            $content = $platformContent ?? $post->content;
            $media = $post->media;

            if ($media->isEmpty()) {
                return PublishResult::failure('Instagram requires at least one media item.');
            }

            if ($media->count() === 1) {
                $containerId = $this->createSingleContainer($accountId, $token, $content, $media->first());
            } else {
                $containerId = $this->createCarouselContainer($accountId, $token, $content, $media);
            }

            if (!$containerId) {
                return PublishResult::failure('Failed to create Instagram media container.');
            }

            $response = Http::post("{$this->graphUrl}/{$accountId}/media_publish", [
                'access_token' => $token,
                'creation_id' => $containerId,
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown Instagram API error');
                return PublishResult::failure($error);
            }

            $igMediaId = $response->json('id');
            $permalink = $this->fetchPermalink($igMediaId, $token);

            return PublishResult::success($igMediaId, $permalink);
        } catch (\Throwable $e) {
            Log::error('Instagram publish exception', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return PublishResult::failure($e->getMessage());
        }
    }

    protected function createSingleContainer(string $accountId, string $token, string $caption, $media): ?string
    {
        $params = [
            'access_token' => $token,
            'caption' => $caption,
            'image_url' => $media->url,
        ];

        if (str_starts_with($media->mime_type ?? '', 'video/')) {
            $params['media_type'] = 'VIDEO';
            $params['video_url'] = $media->url;
            unset($params['image_url']);
        }

        $response = Http::post("{$this->graphUrl}/{$accountId}/media", $params);
        return $response->json('id');
    }

    protected function createCarouselContainer(string $accountId, string $token, string $caption, $mediaItems): ?string
    {
        $childIds = [];
        foreach ($mediaItems as $media) {
            $params = ['access_token' => $token, 'is_carousel_item' => true, 'image_url' => $media->url];
            if (str_starts_with($media->mime_type ?? '', 'video/')) {
                $params['media_type'] = 'VIDEO';
                $params['video_url'] = $media->url;
                unset($params['image_url']);
            }
            $res = Http::post("{$this->graphUrl}/{$accountId}/media", $params);
            if ($id = $res->json('id')) {
                $childIds[] = $id;
            }
        }

        $response = Http::post("{$this->graphUrl}/{$accountId}/media", [
            'access_token' => $token,
            'caption' => $caption,
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
        ]);

        return $response->json('id');
    }

    protected function fetchPermalink(string $mediaId, string $token): ?string
    {
        $response = Http::get("{$this->graphUrl}/{$mediaId}", [
            'access_token' => $token,
            'fields' => 'permalink',
        ]);
        return $response->json('permalink');
    }

    public function supports(string $platform): bool
    {
        return $platform === 'instagram';
    }
}
