<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Meta Ads ad set belonging to a campaign.
 *
 * @property int         $id
 * @property int         $meta_campaign_id
 * @property string      $adset_id
 * @property string      $name
 * @property string|null $status
 * @property float|null  $daily_budget
 * @property array|null  $targeting_summary
 * @property string|null $optimization_goal
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaAdSet extends Model
{
    protected $table = 'meta_ad_sets';

    protected $fillable = [
        'meta_campaign_id',
        'adset_id',
        'name',
        'status',
        'daily_budget',
        'targeting_summary',
        'optimization_goal',
    ];

    protected function casts(): array
    {
        return [
            'daily_budget'      => 'decimal:4',
            'targeting_summary' => 'array',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaAdsInsight::class, 'meta_ad_set_id');
    }
}
