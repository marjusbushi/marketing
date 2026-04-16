<?php

namespace App\Services\ContentPlanner\Publishing;

use App\Models\Content\ContentPost;

interface PlatformPublisherInterface
{
    public function publish(ContentPost $post, ?string $platformContent = null): PublishResult;

    public function supports(string $platform): bool;
}
