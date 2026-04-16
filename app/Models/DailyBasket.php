<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One Daily Basket = one (collection, date) pair.
 *
 * A "collection" is a DIS distribution_week. Since DIS lives in another
 * database, we store `distribution_week_id` as a plain unsignedBigInteger
 * and resolve the relationship via a model whose connection is 'dis'.
 */
class DailyBasket extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'daily_baskets';

    protected $fillable = [
        'distribution_week_id',
        'date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // ── Relationships ───────────────────────────────────────

    public function posts(): HasMany
    {
        return $this->hasMany(DailyBasketPost::class);
    }

    public function creator(): BelongsTo
    {
        // User lives in DIS (cross-DB); the relationship works because
        // User::$connection = 'dis'. No FK, just the id column.
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Derived helpers ─────────────────────────────────────

    /**
     * How many posts of this basket have reached the 'published' stage.
     */
    public function publishedCount(): int
    {
        return $this->posts()
            ->where('stage', \App\Enums\DailyBasketPostStage::PUBLISHED->value)
            ->count();
    }

    /**
     * Progress as a 0-100 integer (share of posts that are published).
     * Returns 0 when the basket has no posts yet.
     */
    public function progressPercent(): int
    {
        $total = $this->posts()->count();
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->publishedCount() / $total) * 100);
    }
}
