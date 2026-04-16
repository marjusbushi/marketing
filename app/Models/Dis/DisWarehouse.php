<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only reference to DIS warehouses.
 *
 * Marketing does NOT manage warehouses — this model exists solely
 * to resolve foreign-key relationships from other DIS reference models.
 *
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property int    $branch_id
 * @property bool   $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @property-read DisBranch|null $branch
 */
class DisWarehouse extends Model
{
    protected $connection = 'dis';

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(DisBranch::class, 'branch_id');
    }
}
