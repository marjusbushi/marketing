<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Brand Kit — the canonical brand identity record.
 *
 * There is exactly one row in marketing_brand_kit at all times. Callers
 * should go through BrandKitService::get() rather than querying this
 * model directly, to benefit from the 60-second Redis cache.
 *
 * JSON schema for each field is documented in the service layer; the
 * model stays schema-agnostic so that brand evolution (adding new color
 * slots, aspect ratios, etc.) does not require migrations.
 */
class BrandKit extends Model
{
    protected $table = 'marketing_brand_kit';

    protected $fillable = [
        'colors',
        'typography',
        'logo_variants',
        'watermark',
        'voice_sq',
        'voice_en',
        'caption_templates',
        'default_hashtags',
        'music_library',
        'aspect_defaults',
        'updated_by',
    ];

    protected $casts = [
        'colors'            => 'array',
        'typography'        => 'array',
        'logo_variants'     => 'array',
        'watermark'         => 'array',
        'caption_templates' => 'array',
        'default_hashtags'  => 'array',
        'music_library'     => 'array',
        'aspect_defaults'   => 'array',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
