<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A TikTok video belonging to an account.
 *
 * @property int         $id
 * @property int         $tiktok_account_id
 * @property string      $video_id
 * @property string|null $title
 * @property string|null $video_description
 * @property string|null $cover_image_url
 * @property string|null $share_url
 * @property string|null $embed_link
 * @property int|null    $duration
 * @property int|null    $width
 * @property int|null    $height
 * @property int|null    $view_count
 * @property int|null    $like_count
 * @property int|null    $comment_count
 * @property int|null    $share_count
 * @property \Carbon\Carbon|null $created_at_tiktok
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokVideo extends Model
{
    protected $table = 'tiktok_videos';

    protected $fillable = [
        'tiktok_account_id',
        'video_id',
        'title',
        'video_description',
        'cover_image_url',
        'share_url',
        'embed_link',
        'duration',
        'width',
        'height',
        'view_count',
        'like_count',
        'comment_count',
        'share_count',
        'created_at_tiktok',
    ];

    protected function casts(): array
    {
        return [
            'created_at_tiktok' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function account(): BelongsTo
    {
        return $this->belongsTo(TikTokAccount::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TikTokVideoSnapshot::class);
    }
}
