<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An influencer tracked for marketing collaborations.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $platform
 * @property string      $handle
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool        $is_active
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Influencer extends Model
{
    use SoftDeletes;

    protected $table = 'influencers';

    protected $fillable = [
        'name',
        'platform',
        'handle',
        'phone',
        'email',
        'notes',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function influencerProducts(): HasMany
    {
        return $this->hasMany(\App\Models\Dis\InfluencerProduct::class, 'influencer_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                            */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
