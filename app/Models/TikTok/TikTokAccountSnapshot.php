<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily snapshot of a TikTok account's follower/engagement counters.
 *
 * @property int         $id
 * @property int         $tiktok_account_id
 * @property \Carbon\Carbon $date
 * @property int|null    $follower_count
 * @property int|null    $following_count
 * @property int|null    $likes_count
 * @property int|null    $video_count
 * @property int|null    $follower_change
 * @property int|null    $following_change
 * @property int|null    $likes_change
 * @property int|null    $video_count_change
 * @property int|null    $total_views_change
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokAccountSnapshot extends Model
{
    protected $table = 'tiktok_account_snapshots';

    protected $fillable = [
        'tiktok_account_id',
        'date',
        'follower_count',
        'following_count',
        'likes_count',
        'video_count',
        'follower_change',
        'following_change',
        'likes_change',
        'video_count_change',
        'total_views_change',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(TikTokAccount::class);
    }
}
