<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Explicit pivot model for daily_basket_post_products.
 *
 * Needed because the "other side" (DisItemGroup) lives on the `dis`
 * connection. Without this pivot class, Laravel would pick up that
 * connection for INSERT/UPDATE against the pivot table — but the pivot
 * table actually lives in the `mysql` (za_marketing) database. Forcing
 * the connection here keeps pivot writes in the right place.
 */
class DailyBasketPostProduct extends Pivot
{
    protected $connection = 'mysql';

    protected $table = 'daily_basket_post_products';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'daily_basket_post_id',
        'item_group_id',
        'sort_order',
        'is_hero',
    ];

    protected $casts = [
        'is_hero' => 'boolean',
    ];
}
