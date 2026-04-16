<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only reference to DIS inventory items.
 *
 * Marketing does NOT manage inventory — this model exists solely
 * to resolve foreign-key relationships (e.g. influencer product items).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $sku
 * @property float|null  $rate
 * @property string|null $product_type
 * @property bool        $status
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
    ];

    protected $casts = [
        'rate'   => 'decimal:2',
        'status' => 'boolean',
    ];
}
