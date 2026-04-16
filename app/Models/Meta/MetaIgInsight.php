<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily Instagram account-level insights.
 *
 * @property int         $id
 * @property string      $ig_account_id
 * @property \Carbon\Carbon $date
 * @property int|null    $impressions
 * @property int|null    $reach
 * @property int|null    $profile_views
 * @property int|null    $follower_count
 * @property int|null    $new_followers
 * @property int|null    $website_clicks
 * @property int|null    $views
 * @property int|null    $accounts_engaged
 * @property int|null    $total_interactions
 * @property int|null    $likes
 * @property int|null    $comments
 * @property int|null    $shares
 * @property int|null    $saves
 * @property int|null    $replies
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaIgInsight extends Model
{
    protected $table = 'meta_ig_insights';

    protected $fillable = [
        'ig_account_id',
        'date',
        'impressions',
        'reach',
        'profile_views',
        'follower_count',
        'new_followers',
        'website_clicks',
        'views',
        'accounts_engaged',
        'total_interactions',
        'likes',
        'comments',
        'shares',
        'saves',
        'replies',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
