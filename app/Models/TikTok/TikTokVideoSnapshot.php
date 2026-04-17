<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily snapshot of a TikTok video's engagement counters.
 *
 * @property int         $id
 * @property int         $tiktok_video_id
 * @property \Carbon\Carbon $date
 * @property int|null    $view_count
 * @property int|null    $like_count
 * @property int|null    $comment_count
 * @property int|null    $share_count
 * @property int|null    $view_change
 * @property int|null    $like_change
 * @property int|null    $comment_change
 * @property int|null    $share_change
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokVideoSnapshot extends Model
{
    // TikTok data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'tiktok_video_snapshots';

    protected $fillable = [
        'tiktok_video_id',
        'date',
        'view_count',
        'like_count',
        'comment_count',
        'share_count',
        'view_change',
        'like_change',
        'comment_change',
        'share_change',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function video(): BelongsTo
    {
        return $this->belongsTo(TikTokVideo::class);
    }
}
