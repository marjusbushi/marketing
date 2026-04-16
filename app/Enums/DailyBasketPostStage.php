<?php

namespace App\Enums;

/**
 * The 5-stage pipeline a basket post travels through.
 *
 *   planning   → marketing picks products + reference
 *   production → photographer/editor shoots the material
 *   editing    → caption + hashtags + platform wiring
 *   scheduling → final review + schedule to Content Planner
 *   published  → handed off to Content Planner + live on social
 */
enum DailyBasketPostStage: string
{
    case PLANNING   = 'planning';
    case PRODUCTION = 'production';
    case EDITING    = 'editing';
    case SCHEDULING = 'scheduling';
    case PUBLISHED  = 'published';

    public static function default(): self
    {
        return self::PLANNING;
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PLANNING   => 'Planifikim',
            self::PRODUCTION => 'Prodhim',
            self::EDITING    => 'Editim',
            self::SCHEDULING => 'Skedulim',
            self::PUBLISHED  => 'Publikuar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLANNING   => '#94a3b8',
            self::PRODUCTION => '#f59e0b',
            self::EDITING    => '#8b5cf6',
            self::SCHEDULING => '#3b82f6',
            self::PUBLISHED  => '#22c55e',
        };
    }

    /**
     * Zero-indexed position in the pipeline (useful for progress bars).
     */
    public function order(): int
    {
        return match ($this) {
            self::PLANNING   => 0,
            self::PRODUCTION => 1,
            self::EDITING    => 2,
            self::SCHEDULING => 3,
            self::PUBLISHED  => 4,
        };
    }

    /**
     * The stage that normally comes after this one, or null when already final.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::PLANNING   => self::PRODUCTION,
            self::PRODUCTION => self::EDITING,
            self::EDITING    => self::SCHEDULING,
            self::SCHEDULING => self::PUBLISHED,
            self::PUBLISHED  => null,
        };
    }
}
