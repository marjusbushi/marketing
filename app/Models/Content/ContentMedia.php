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
        'alt_text',
        'folder',
        'stage',
    ];

    public const FOLDERS = ['reels', 'videos', 'photos', 'stories', 'referenca', 'imported'];
    public const STAGES = ['raw', 'edited', 'final'];

    protected $appends = [
        'url',
        'thumbnail_url',
        'is_video',
        'human_size',
    ];

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->resolvedDisk())->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::disk($this->resolvedDisk())->url($this->thumbnail_path);
        }

        return $this->url;
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
     * Resolve the storage disk — fall back to 'public' if the saved disk doesn't exist.
     */
    private function resolvedDisk(): string
    {
        $disk = $this->disk ?? 'public';

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
