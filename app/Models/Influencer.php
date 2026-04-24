<?php

namespace App\Models;

use App\Enums\InfluencerPlatformEnum;
use App\Models\Dis\InfluencerProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Influencer tracked for marketing collaborations.
 *
 * Stored in the marketing database (Flare-owned). Products given to the
 * influencer live in the DIS database via App\Models\Dis\InfluencerProduct
 * — the relationship is cross-connection, so methods that aggregate
 * products resolve them through the 'dis' connection.
 *
 * @property int                    $id
 * @property string                 $name
 * @property InfluencerPlatformEnum $platform
 * @property string|null            $handle
 * @property string|null            $phone
 * @property string|null            $email
 * @property string|null            $notes
 * @property bool                   $is_active
 * @property int|null               $created_by_user_id
 * @property Carbon|null            $created_at
 * @property Carbon|null            $updated_at
 * @property Carbon|null            $deleted_at
 *
 * Computed
 * @property-read string $label
 * @property-read int    $active_products_count
 * @property-read float  $total_value_out
 *
 * Relations
 * @property-read User|null                                $createdBy
 * @property-read Collection<int, InfluencerProduct>       $influencerProducts
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

    protected $casts = [
        'platform'  => InfluencerPlatformEnum::class,
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'label',
    ];

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getLabelAttribute(): string
    {
        $label = $this->name;
        if ($this->handle) {
            $label .= " (@{$this->handle})";
        }
        return $label;
    }

    /**
     * Number of currently-out products (active / partially_returned).
     * Cross-DB aware: resolves through the dis connection.
     */
    public function getActiveProductsCountAttribute(): int
    {
        if ($this->relationLoaded('influencerProducts')) {
            return $this->influencerProducts
                ->whereIn('status', ['active', 'partially_returned'])
                ->count();
        }

        return $this->influencerProducts()
            ->whereIn('status', ['active', 'partially_returned'])
            ->count();
    }

    /**
     * Total declared value of products currently out with this influencer.
     * Loads products + items on access — use only on single-record pages
     * (show); aggregate reports should query DIS directly to avoid N+1.
     */
    public function getTotalValueOutAttribute(): float
    {
        return $this->influencerProducts()
            ->whereIn('status', ['active', 'partially_returned'])
            ->with('items')
            ->get()
            ->sum(fn (InfluencerProduct $ip) => $ip->total_value);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function influencerProducts(): HasMany
    {
        return $this->hasMany(InfluencerProduct::class, 'influencer_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('handle', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }
}
