<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'content_posts';

    protected $fillable = [
        'uuid',
        'user_id',
        'campaign_id',
        'platform',
        'content',
        'content_type',
        'scheduled_at',
        // scheduled_at_version is intentionally NOT fillable — it's the
        // load-bearing column for the atomic-claim race protection. Service
        // code bumps it by direct assignment + save(); never accept it from
        // user input or array writes.
        'published_at',
        'status',
        'platform_post_id',
        'permalink',
        'error_message',
        'approval_type',
        'approved_by',
        'approved_at',
        'approval_locked_at',
        'notes',
        'sort_order',
        'external_source',
        'external_post_id',
        'meta_post_type',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'approval_locked_at' => 'datetime',
    ];

    /**
     * Generate a UUID for any row that wasn't given one explicitly. The DB
     * column is `unique NOT NULL`, so paths like ContentFeedImportService
     * (which calls updateOrCreate without a uuid) used to fail with
     * "Field 'uuid' doesn't have a default value". Centralizing it here
     * means every writer — service, importer, factory, manual seed —
     * gets a uuid for free.
     */
    protected static function booted(): void
    {
        static::creating(function (ContentPost $post) {
            if (empty($post->uuid)) {
                $post->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function platforms(): HasMany
    {
        return $this->hasMany(ContentPostPlatform::class, 'content_post_id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(ContentMedia::class, 'content_post_media')
            ->withPivot('sort_order');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ContentLabel::class, 'content_post_labels');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContentComment::class, 'content_post_id')
            ->whereNull('parent_id');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(ContentComment::class, 'content_post_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ContentCampaign::class, 'campaign_id');
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(ContentApprovalStep::class, 'post_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentPostVersion::class, 'post_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ContentSuggestion::class, 'post_id');
    }

    public function shareLinks(): MorphMany
    {
        return $this->morphMany(ContentShareLink::class, 'shareable');
    }

    // ── Scopes ──

    public function scopeDateRange($query, $from, $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('scheduled_at', [$from, $to])
              ->orWhere(function ($q2) use ($from, $to) {
                  $q2->whereNull('scheduled_at')
                     ->whereBetween('created_at', [$from, $to]);
              });
        });
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->whereHas('platforms', fn ($q) => $q->where('platform', $platform));
    }

    // ── Accessors ──

    public function getFirstThumbnailUrlAttribute(): ?string
    {
        $media = $this->media->first();
        if (!$media) return null;

        if ($media->thumbnail_path) {
            return \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->thumbnail_path);
        }

        return $this->first_media_url;
    }

    public function getFirstMediaUrlAttribute(): ?string
    {
        $media = $this->media->first();
        if (!$media) return null;

        return \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path);
    }

    public function getPlatformIconsAttribute(): array
    {
        return $this->platforms->pluck('platform')->toArray();
    }

    public function getIsImportedAttribute(): bool
    {
        return !empty($this->external_source);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => '#9CA3AF',
            'pending_review' => '#F59E0B',
            'approved' => '#3B82F6',
            'scheduled' => '#8B5CF6',
            'publishing' => '#06B6D4',
            'published' => '#10B981',
            'failed' => '#EF4444',
            default => '#6B7280',
        };
    }

    public function getStatusBgColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => '#F3F4F6',
            'pending_review' => '#FEF3C7',
            'approved' => '#DBEAFE',
            'scheduled' => '#EDE9FE',
            'publishing' => '#CFFAFE',
            'published' => '#D1FAE5',
            'failed' => '#FEE2E2',
            default => '#F3F4F6',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_review' => 'Review',
            'approved' => 'Approved',
            'scheduled' => 'Scheduled',
            'publishing' => 'Publishing…',
            'published' => 'Published',
            'failed' => 'Failed',
            default => ucfirst($this->status),
        };
    }
}
