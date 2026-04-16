<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only model for DIS `item_groups` table.
 *
 * Exists solely so other za-marketing models can eager-load product data
 * via a cross-DB relationship. All writes happen via the DIS internal API
 * (DisApiClient), never through this model.
 */
class DisItemGroup extends Model
{
    protected $connection = 'dis';
    protected $table = 'item_groups';
    public $timestamps = false;

    protected $guarded = [];
}
