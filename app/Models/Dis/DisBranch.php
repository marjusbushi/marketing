<?php

namespace App\Models\Dis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-only reference to DIS branches.
 *
 * Marketing does NOT manage branches — this model exists solely
 * to resolve foreign-key relationships from other DIS reference models.
 *
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property bool   $status
 * @property bool   $is_branch_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class DisBranch extends Model
{
    protected $connection = 'dis';

    protected $table = 'branches';

    protected $fillable = [
        'name',
        'code',
        'status',
        'is_branch_active',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_branch_active' => 'boolean',
    ];

    public function warehouses(): HasMany
    {
        return $this->hasMany(DisWarehouse::class, 'branch_id');
    }
}
