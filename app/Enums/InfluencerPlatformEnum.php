<?php

namespace App\Enums;

enum InfluencerPlatformEnum: string
{
    case INSTAGRAM = 'instagram';
    case TIKTOK    = 'tiktok';
    case YOUTUBE   = 'youtube';
    case OTHER     = 'other';

    public static function default(): self
    {
        return self::INSTAGRAM;
    }

    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match($this) {
            self::INSTAGRAM => 'Instagram',
            self::TIKTOK    => 'TikTok',
            self::YOUTUBE   => 'YouTube',
            self::OTHER     => __('influencer.platform.other'),
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::INSTAGRAM => 'bi:instagram',
            self::TIKTOK    => 'bi:tiktok',
            self::YOUTUBE   => 'bi:youtube',
            self::OTHER     => 'heroicons:globe-alt',
        };
    }
}
