<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only reference to DIS inventory items.
 *
 * Marketing does NOT manage inventory — this model exists solely
 * to resolve foreign-key relationships (e.g. influencer product items)
 * and to power searches like "select an item to give to an influencer".
 *
 * DIS `items.status` is a nullable string ('active' / 'inactive') — not
 * a boolean — so do NOT cast it; queries should compare against 'active'.
 *
 * @property int              $id
 * @property string           $name
 * @property string           $sku
 * @property float|null       $rate
 * @property string|null      $product_type
 * @property string|null      $status
 * @property string|null      $r2_thumbnail_url
 * @property string|null      $r2_image_url
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class DisItem extends Model
{
    protected $connection = 'dis';

    protected $table = 'items';

    protected $fillable = [
        'name',
        'sku',
        'rate',
        'product_type',
        'status',
        'r2_thumbnail_url',
        'r2_image_url',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
    ];
}
