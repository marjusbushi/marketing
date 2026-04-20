<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One row per media item on a Meta (IG/FB) post. A single post may have N rows
 * (carousel), one row (image/video), or zero (text-only).
 *
 * @property int         $id
 * @property int         $meta_post_insight_id
 * @property int         $position
 * @property string      $media_type            IMAGE | VIDEO
 * @property string|null $ig_media_id
 * @property string|null $original_url
 * @property string|null $video_url
 * @property string|null $thumbnail_url
 * @property string|null $local_path
 * @property string|null $local_disk
 * @property string|null $local_thumbnail_path
 * @property string|null $mime_type
 * @property int|null    $size_bytes
 * @property \Carbon\Carbon|null $downloaded_at
 */
class MetaPostMedia extends Model
{
    // Lives alongside MetaPostInsight in the DIS database.
    protected $connection = 'dis';

    protected $table = 'meta_post_media';

    protected $fillable = [
        'meta_post_insight_id',
        'position',
        'media_type',
        'ig_media_id',
        'original_url',
        'video_url',
        'thumbnail_url',
        'local_path',
        'local_disk',
        'local_thumbnail_path',
        'mime_type',
        'size_bytes',
        'downloaded_at',
    ];

    protected $casts = [
        'position'       => 'integer',
        'size_bytes'     => 'integer',
        'downloaded_at'  => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(MetaPostInsight::class, 'meta_post_insight_id');
    }

    /**
     * URL per konsumim ne front-end: prefer lokal (surviving expiry), fall back
     * to proxy route for original URL. Null nese asgje s'eshte disponueshem.
     */
    public function getDisplayUrlAttribute(): ?string
    {
        if ($this->local_path) {
            return Storage::disk($this->local_disk ?? 'public')->url($this->local_path);
        }
        return $this->original_url ?: $this->thumbnail_url;
    }

    public function getDisplayThumbnailAttribute(): ?string
    {
        if ($this->local_thumbnail_path) {
            return Storage::disk($this->local_disk ?? 'public')->url($this->local_thumbnail_path);
        }
        if ($this->local_path && $this->media_type === 'IMAGE') {
            return $this->display_url; // same file for images
        }
        return $this->thumbnail_url ?: $this->original_url;
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'VIDEO';
    }
}
