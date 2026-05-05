<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Media asset (photo / video) attached to a DailyBasketPost.
 *
 * Kept separate from ContentMedia so the daily-basket workflow can collect
 * assets during production/editing without polluting the published-posts
 * media library. When a DailyBasketPost transitions to "published", the
 * media can be cloned into ContentMedia at that point (see
 * DailyBasketController::cloneProductImagesToMedia for the existing pattern).
 */
class DailyBasketPostMedia extends Model
{
    protected $table = 'daily_basket_post_media';

    protected $fillable = [
        'daily_basket_post_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'thumbnail_path',
        'cover_path',
        'cover_timestamp_ms',
        'sort_order',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'cover_timestamp_ms' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
        'thumbnail_url',
        'cover_url',
        'is_video',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(DailyBasketPost::class, 'daily_basket_post_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->resolvedDisk())->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        // User-picked cover wins (mirrors what Meta will show on IG once
        // the post is handed off + published).
        if ($this->cover_path) {
            return Storage::disk($this->resolvedDisk())->url($this->cover_path);
        }
        if ($this->thumbnail_path) {
            return Storage::disk($this->resolvedDisk())->url($this->thumbnail_path);
        }

        return $this->url;
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->cover_path) {
            return Storage::disk($this->resolvedDisk())->url($this->cover_path);
        }

        return null;
    }

    public function getIsVideoAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    private function resolvedDisk(): string
    {
        $disk = $this->disk ?? 'public';

        if (array_key_exists($disk, config('filesystems.disks', []))) {
            return $disk;
        }

        return 'public';
    }
}
