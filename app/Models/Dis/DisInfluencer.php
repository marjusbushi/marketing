<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only reference to the DIS influencers table.
 *
 * Used by InfluencerProduct to resolve influencer relationships
 * within the DIS database. The marketing-owned Influencer model
 * (App\Models\Influencer) lives in the marketing database.
 */
class DisInfluencer extends Model
{
    protected $connection = 'dis';

    protected $table = 'influencers';

    protected $fillable = [
        'name',
        'platform',
        'handle',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function influencerProducts(): HasMany
    {
        return $this->hasMany(InfluencerProduct::class, 'influencer_id');
    }
}
