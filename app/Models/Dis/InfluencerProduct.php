<?php

namespace App\Models\Dis;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only reference to DIS influencer_products table.
 *
 * Tracks products given to influencers for marketing collaborations.
 * Marketing does NOT write to this table — all mutations happen in DIS.
 *
 * @property int         $id
 * @property string|null $serial
 * @property int         $influencer_id
 * @property int|null    $created_by_user_id
 * @property int|null    $source_branch_id
 * @property int|null    $source_warehouse_id
 * @property string      $status
 * @property string|null $agreement_type
 * @property \Carbon\Carbon|null $expected_return_date
 * @property \Carbon\Carbon|null $actual_return_date
 * @property string|null $notes
 * @property int|null    $transfer_order_id
 * @property int|null    $return_transfer_order_id
 * @property int|null    $invoice_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read bool $is_overdue
 * @property-read DisInfluencer|null    $influencer
 * @property-read User|null             $createdBy
 * @property-read DisBranch|null        $branch
 * @property-read DisWarehouse|null     $warehouse
 * @property-read \Illuminate\Database\Eloquent\Collection<InfluencerProductItem> $items
 */
class InfluencerProduct extends Model
{
    use SoftDeletes;

    protected $connection = 'dis';

    protected $table = 'influencer_products';

    protected $fillable = [
        'serial',
        'influencer_id',
        'created_by_user_id',
        'source_branch_id',
        'source_warehouse_id',
        'status',
        'agreement_type',
        'expected_return_date',
        'actual_return_date',
        'notes',
        'transfer_order_id',
        'return_transfer_order_id',
        'invoice_id',
    ];

    protected $casts = [
        'expected_return_date' => 'date',
        'actual_return_date'   => 'date',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(DisInfluencer::class, 'influencer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(DisBranch::class, 'source_branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(DisWarehouse::class, 'source_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InfluencerProductItem::class, 'influencer_product_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Accessors                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Whether the product assignment is overdue for return.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_return_date !== null
            && $this->expected_return_date->lt(now()->startOfDay())
            && in_array($this->status, ['active', 'partially_returned'], true);
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Only active or partially-returned assignments.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'partially_returned']);
    }

    /**
     * Alias for scopeActive — used by reports controller.
     */
    public function scopeActiveOrPartial($query)
    {
        return $this->scopeActive($query);
    }

    /**
     * Only draft assignments.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Overdue assignments (past expected return date and still active).
     */
    public function scopeOverdue($query)
    {
        return $query->whereDate('expected_return_date', '<', now()->toDateString())
                     ->whereIn('status', ['active', 'partially_returned']);
    }

    /**
     * Total value of all items in this product.
     */
    public function getTotalValueAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->quantity_given * $item->product_value);
    }
}
