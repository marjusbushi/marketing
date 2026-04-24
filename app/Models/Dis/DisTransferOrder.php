<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only reference to DIS transfer_orders.
 *
 * The influencer product show page links to the underlying transfer order
 * (inventory movement that gave / returned the product). Marketing does
 * not manage transfer orders — displays them by serial + id only.
 *
 * @property int         $id
 * @property string|null $serial
 * @property string|null $status
 * @property \Carbon\Carbon|null $transfer_order_date
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class DisTransferOrder extends Model
{
    use SoftDeletes;

    protected $connection = 'dis';

    protected $table = 'transfer_orders';

    // Read-only mirror — never mass-assigned from this app.
    protected $guarded = ['*'];

    protected $casts = [
        'transfer_order_date' => 'date',
    ];
}
