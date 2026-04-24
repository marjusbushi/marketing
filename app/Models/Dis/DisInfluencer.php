<?php

namespace App\Models\Dis;

use App\Enums\InfluencerPlatformEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Influencer profile — source-of-truth lives in the DIS database.
 *
 * Marketing reads directly through the 'dis' connection and routes all
 * writes through DisApiClient → /api/internal/influencers so DIS stays
 * authoritative (influencer_products.influencer_id references this
 * table from the same DB).
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
 * @property-read string $label
 * @property-read int    $active_products_count
 *
 * @property-read User|null                           $createdBy
 * @property-read Collection<int, InfluencerProduct>  $influencerProducts
 */
class DisInfluencer extends Model
{
    use SoftDeletes;

    protected $connection = 'dis';

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
     * Works both when influencerProducts is eager-loaded and when not.
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
