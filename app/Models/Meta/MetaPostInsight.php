<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-post/reel/story insights (Facebook + Instagram).
 *
 * @property int         $id
 * @property string      $source
 * @property string      $source_id
 * @property string      $post_id
 * @property string|null $post_type
 * @property string|null $message
 * @property string|null $permalink_url
 * @property string|null $media_url
 * @property \Carbon\Carbon|null $created_at_meta
 * @property int|null    $impressions
 * @property int|null    $reach
 * @property int|null    $likes
 * @property int|null    $comments
 * @property int|null    $shares
 * @property int|null    $saves
 * @property int|null    $video_views
 * @property int|null    $clicks
 * @property int|null    $exits
 * @property int|null    $replies
 * @property int|null    $taps_forward
 * @property int|null    $taps_back
 * @property int|null    $plays
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaPostInsight extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_post_insights';

    protected $fillable = [
        'source',
        'source_id',
        'post_id',
        'post_type',
        'message',
        'permalink_url',
        'media_url',
        'created_at_meta',
        'impressions',
        'reach',
        'likes',
        'comments',
        'shares',
        'saves',
        'video_views',
        'clicks',
        'exits',
        'replies',
        'taps_forward',
        'taps_back',
        'plays',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at_meta' => 'datetime',
            'synced_at'       => 'datetime',
        ];
    }

    /**
     * All media items on this post (1 for images/videos, N for carousel).
     * Ordered by position — first item is the thumbnail candidate.
     */
    public function mediaItems(): HasMany
    {
        return $this->hasMany(MetaPostMedia::class)->orderBy('position');
    }
}
