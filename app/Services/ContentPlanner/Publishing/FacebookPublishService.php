<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublishService implements PlatformPublisherInterface
{
    protected string $graphUrl = 'https://graph.facebook.com/v21.0';

    public function publish(ContentPost $post, ?string $platformContent = null): PublishResult
    {
        try {
            $pageId = config('services.facebook.page_id');
            $token = config('services.facebook.page_access_token');

            if (!$pageId || !$token) {
                return PublishResult::failure('Facebook page ID or access token not configured.');
            }

            $content = $platformContent ?? $post->content;
            $media = $post->media()->first();

            if ($media && in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                $response = Http::post("{$this->graphUrl}/{$pageId}/photos", [
                    'access_token' => $token,
                    'message' => $content,
                    'url' => $media->url,
                ]);
            } else {
                $response = Http::post("{$this->graphUrl}/{$pageId}/feed", [
                    'access_token' => $token,
                    'message' => $content,
                ]);
            }

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown Facebook API error');
                Log::error('Facebook publish failed', ['post_id' => $post->id, 'error' => $error]);
                return PublishResult::failure($error);
            }

            $postId = $response->json('id') ?? $response->json('post_id');
            $permalink = "https://www.facebook.com/{$postId}";

            return PublishResult::success($postId, $permalink);
        } catch (\Throwable $e) {
            Log::error('Facebook publish exception', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return PublishResult::failure($e->getMessage());
        }
    }

    public function supports(string $platform): bool
    {
        return $platform === 'facebook';
    }
}
