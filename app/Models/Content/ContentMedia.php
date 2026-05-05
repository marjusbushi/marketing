<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ContentMedia extends Model
{
    use SoftDeletes;

    protected $table = 'content_media';

    protected $fillable = [
        'uuid',
        'user_id',
        'filename',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'thumbnail_path',
        'cover_path',
        'alt_text',
        'folder',
        'stage',
    ];

    public const FOLDERS = ['reels', 'videos', 'photos', 'stories', 'referenca', 'imported'];
    public const STAGES = ['raw', 'edited', 'final'];

    protected $appends = [
        'url',
        'thumbnail_url',
        'cover_url',
        'is_video',
        'human_size',
        'item_group_ids',
        'distribution_week_ids',
    ];

    /**
     * Linked DIS item_groups (products). Cross-DB: the pivot stores ids only,
     * no model hydration on the other side — consumers look up item_group
     * details via DisApiClient as needed.
     *
     * Cache the pluck per-instance so accessing the append twice during a
     * single request doesn't fire the query twice. Use preloadLinkedIds() on
     * the service for batch rendering.
     */
    public function getItemGroupIdsAttribute(): array
    {
        if (isset($this->relations['_item_group_ids'])) {
            return $this->relations['_item_group_ids'];
        }

        if (! $this->exists) {
            return [];
        }

        $ids = \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->where('content_media_id', $this->id)
            ->pluck('item_group_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->relations['_item_group_ids'] = $ids;

        return $ids;
    }

    public function getDistributionWeekIdsAttribute(): array
    {
        if (isset($this->relations['_distribution_week_ids'])) {
            return $this->relations['_distribution_week_ids'];
        }

        if (! $this->exists) {
            return [];
        }

        $ids = \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
            ->where('content_media_id', $this->id)
            ->pluck('distribution_week_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->relations['_distribution_week_ids'] = $ids;

        return $ids;
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
        // Cover (user-picked) wins over auto-generated thumbnail when set.
        // The order mirrors what Meta sees as the Reel/Video cover.
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
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size_bytes ?? 0;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Resolve the storage disk for URL generation. Two layers of fallback:
     *
     *   1. If the row has no disk recorded (shouldn't happen for new rows
     *      written by ContentMediaService, but legacy data exists), use
     *      whatever disk Content Planner is currently configured to write
     *      to — that's the closest match to "where this file probably is".
     *   2. If the recorded disk no longer exists in config (renamed or
     *      removed), fall back to 'public' as a last-resort safe default.
     */
    private function resolvedDisk(): string
    {
        $disk = $this->disk ?? config('content-planner.media_disk', 'public');

        if (array_key_exists($disk, config('filesystems.disks', []))) {
            return $disk;
        }

        return 'public';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(ContentPost::class, 'content_post_media')
            ->withPivot('sort_order');
    }
}
