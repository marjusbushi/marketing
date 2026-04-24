<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only reference to DIS invoices.
 *
 * When an influencer product is converted to expense, DIS emits an invoice;
 * the show page surfaces a link (serial + id) back to DIS. Marketing never
 * writes to this table.
 *
 * @property int         $id
 * @property string|null $serial
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class DisInvoice extends Model
{
    use SoftDeletes;

    protected $connection = 'dis';

    protected $table = 'invoices';

    // Read-only mirror — never mass-assigned from this app.
    protected $guarded = ['*'];
}
