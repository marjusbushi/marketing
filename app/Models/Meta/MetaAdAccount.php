<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Meta (Facebook) Ad Account.
 *
 * @property int         $id
 * @property string      $account_id
 * @property string      $name
 * @property string|null $currency
 * @property string|null $timezone
 * @property string|null $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaAdAccount extends Model
{
    protected $table = 'meta_ad_accounts';

    protected $fillable = [
        'account_id',
        'name',
        'currency',
        'timezone',
        'status',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaAdsInsight::class);
    }
}
