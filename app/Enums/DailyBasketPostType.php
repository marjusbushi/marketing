<?php

namespace App\Enums;

/**
 * Supported post formats for a basket post.
 * Matches the typical mix a social-media plan produces.
 */
enum DailyBasketPostType: string
{
    case PHOTO    = 'photo';
    case VIDEO    = 'video';
    case REEL     = 'reel';
    case CAROUSEL = 'carousel';
    case STORY    = 'story';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PHOTO    => 'Photo',
            self::VIDEO    => 'Video',
            self::REEL     => 'Reel',
            self::CAROUSEL => 'Carousel',
            self::STORY    => 'Story',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PHOTO    => 'heroicons:photo',
            self::VIDEO    => 'heroicons:film',
            self::REEL     => 'heroicons:play-circle',
            self::CAROUSEL => 'heroicons:rectangle-stack',
            self::STORY    => 'heroicons:bolt',
        };
    }
}
