<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;
use Illuminate\Support\Facades\Log;

class ContentPublishManager
{
    /** @var PlatformPublisherInterface[] */
    protected array $publishers;

    public function __construct()
    {
        $this->publishers = [
            new FacebookPublishService(),
            new InstagramPublishService(),
            new TiktokPublishService(),
        ];
    }

    /**
     * @return array<string, PublishResult>
     */
    public function publishPost(ContentPost $post): array
    {
        $post->load(["media", "platforms"]);

        $targets = $this->resolveTargets($post);
        $results = [];
        $allSucceeded = true;

        foreach ($targets as $target) {
            $platform = $target["platform"];
            $content = $target["content"];
            $platformModel = $target["model"] ?? null;

            $result = $this->publishToPlatform($post, $platform, $content);
            $results[$platform] = $result;

            if ($result->success) {
                if ($platformModel) {
                    $platformModel->update([
                        "platform_post_id" => $result->platformPostId,
                        "published_at" => now(),
                        "status" => "published",
                    ]);
                }
            } else {
                $allSucceeded = false;
                if ($platformModel) {
                    $platformModel->update([
                        "status" => "failed",
                        "error_message" => $result->error,
                    ]);
                }
            }
        }

        $post->update([
            "status" => $allSucceeded ? "published" : "failed",
            "published_at" => $allSucceeded ? now() : null,
            "platform_post_id" => $results[array_key_first($results)]->platformPostId ?? $post->platform_post_id,
            "permalink" => $results[array_key_first($results)]->permalink ?? $post->permalink,
        ]);

        Log::info("Content post publish complete", [
            "post_id" => $post->id,
            "status" => $allSucceeded ? "published" : "failed",
            "results" => collect($results)->map(fn ($r) => ["success" => $r->success, "error" => $r->error])->toArray(),
        ]);

        return $results;
    }

    public function publishToPlatform(ContentPost $post, string $platform, ?string $content = null): PublishResult
    {
        foreach ($this->publishers as $publisher) {
            if ($publisher->supports($platform)) {
                return $publisher->publish($post, $content);
            }
        }

        return PublishResult::failure("No publisher available for platform: {$platform}");
    }

    protected function resolveTargets(ContentPost $post): array
    {
        if ($post->platform !== "multi") {
            return [
                [
                    "platform" => $post->platform,
                    "content" => $post->content,
                    "model" => null,
                ],
            ];
        }

        return $post->platforms->map(function ($platformModel) use ($post) {
            return [
                "platform" => $platformModel->platform,
                "content" => $platformModel->platform_content ?? $post->content,
                "model" => $platformModel,
            ];
        })->toArray();
    }
}
