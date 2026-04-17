<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A TikTok creator/business account profile.
 *
 * @property int         $id
 * @property string      $open_id
 * @property string|null $union_id
 * @property string|null $display_name
 * @property string|null $username
 * @property string|null $avatar_url
 * @property string|null $bio_description
 * @property bool        $is_verified
 * @property string|null $profile_deep_link
 * @property int|null    $follower_count
 * @property int|null    $following_count
 * @property int|null    $likes_count
 * @property int|null    $video_count
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokAccount extends Model
{
    // TikTok data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'tiktok_accounts';

    protected $fillable = [
        'open_id',
        'union_id',
        'display_name',
        'username',
        'avatar_url',
        'bio_description',
        'is_verified',
        'profile_deep_link',
        'follower_count',
        'following_count',
        'likes_count',
        'video_count',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function snapshots(): HasMany
    {
        return $this->hasMany(TikTokAccountSnapshot::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(TikTokVideo::class);
    }
}
