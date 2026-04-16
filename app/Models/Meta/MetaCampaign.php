<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Meta Ads campaign belonging to an ad account.
 *
 * @property int         $id
 * @property int         $meta_ad_account_id
 * @property string      $campaign_id
 * @property string      $name
 * @property string|null $objective
 * @property string|null $status
 * @property float|null  $daily_budget
 * @property float|null  $lifetime_budget
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaCampaign extends Model
{
    protected $table = 'meta_campaigns';

    protected $fillable = [
        'meta_ad_account_id',
        'campaign_id',
        'name',
        'objective',
        'status',
        'daily_budget',
        'lifetime_budget',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date'      => 'date',
            'end_date'        => 'date',
            'daily_budget'    => 'decimal:4',
            'lifetime_budget' => 'decimal:4',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class);
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(MetaAdSet::class);
    }
}
