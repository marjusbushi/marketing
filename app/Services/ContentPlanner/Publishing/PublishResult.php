<?php

namespace App\Services\ContentPlanner\Publishing;

class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $platformPostId = null,
        public readonly ?string $permalink = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $platformPostId, ?string $permalink = null): self
    {
        return new self(true, $platformPostId, $permalink);
    }

    public static function failure(string $error): self
    {
        return new self(false, error: $error);
    }
}
