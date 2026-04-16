<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only reference to DIS influencer_product_items table.
 *
 * Individual line items within an influencer product assignment.
 * Marketing does NOT write to this table — all mutations happen in DIS.
 *
 * @property int         $id
 * @property int         $influencer_product_id
 * @property int         $item_id
 * @property int         $quantity_given
 * @property int         $quantity_returned
 * @property string|null $return_condition
 * @property float|null  $product_value
 * @property bool        $is_kept
 * @property string|null $notes
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read InfluencerProduct|null $influencerProduct
 * @property-read DisItem|null           $item
 */
class InfluencerProductItem extends Model
{
    use SoftDeletes;

    protected $connection = 'dis';

    protected $table = 'influencer_product_items';

    protected $fillable = [
        'influencer_product_id',
        'item_id',
        'quantity_given',
        'quantity_returned',
        'return_condition',
        'product_value',
        'is_kept',
        'notes',
    ];

    protected $casts = [
        'product_value' => 'decimal:2',
        'is_kept'       => 'boolean',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function influencerProduct(): BelongsTo
    {
        return $this->belongsTo(InfluencerProduct::class, 'influencer_product_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DisItem::class, 'item_id');
    }
}
