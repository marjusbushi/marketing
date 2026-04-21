<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marketing Asset — reusable media (stickers, music, fonts, logos, watermarks).
 *
 * Distinct from daily_basket_post_media which is scoped to a single post.
 * Assets here are brand-wide; Brand Kit references subsets (music_library,
 * logo_variants) by id inside its JSON columns.
 */
class Asset extends Model
{
    protected $table = 'marketing_assets';

    protected $fillable = [
        'kind',
        'name',
        'path',
        'mime_type',
        'duration_seconds',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }
}
