<?php

namespace App\Services\ContentPlanner;

use App\Models\Content\ContentMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ContentMediaService
{
    protected string $disk;
    protected int $maxSizeMb;
    protected int $thumbWidth;
    protected int $thumbQuality;

    public function __construct()
    {
        $this->disk = config('content-planner.media_disk', 'r2_cdn');
        $this->maxSizeMb = config('content-planner.media_max_size_mb', 50);
        $this->thumbWidth = config('content-planner.thumbnail_width', 400);
        $this->thumbQuality = config('content-planner.thumbnail_quality', 80);
    }

    public function upload(UploadedFile $file, int $userId, array $overrides = []): ContentMedia
    {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filename = $uuid . '.' . $extension;
        $path = 'content-planner/media/' . now()->format('Y/m') . '/' . $filename;

        // Store original
        Storage::disk($this->disk)->put($path, file_get_contents($file));

        // Get dimensions for images
        $width = null;
        $height = null;
        $thumbnailPath = null;

        if ($this->isImage($file->getMimeType())) {
            try {
                $imageSize = getimagesize($file->getPathname());
                if ($imageSize) {
                    $width = $imageSize[0];
                    $height = $imageSize[1];
                }
                $thumbnailPath = $this->generateThumbnail($file, $path);
            } catch (\Throwable $e) {
                // Thumbnail generation failed — non-fatal
            }
        }

        $folder = $overrides['folder'] ?? $this->classifyFolder($file->getMimeType(), $width, $height);
        $stage = $overrides['stage'] ?? 'raw';

        return ContentMedia::create([
            'uuid' => $uuid,
            'user_id' => $userId,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => $this->disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'thumbnail_path' => $thumbnailPath,
            'folder' => $folder,
            'stage' => in_array($stage, ContentMedia::STAGES, true) ? $stage : 'raw',
        ]);
    }

    /**
     * Auto-klasifikimi i folder-it nga mime-type + dimensionet.
     *
     * Video portrait ose 9:16 → reels; video landscape → videos;
     * çdo image → photos; audio ose i panjohur → null (user klasifikon).
     * Stories shkruhen manualisht (s'ka dallim nga photo/reels pa kontekst).
     */
    public function classifyFolder(?string $mimeType, ?int $width, ?int $height): ?string
    {
        if (! $mimeType) {
            return null;
        }

        if (str_starts_with($mimeType, 'video/')) {
            if ($width && $height && $height > $width) {
                return 'reels';
            }

            return 'videos';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'photos';
        }

        return null;
    }

    public function setFolder(ContentMedia $media, ?string $folder): ContentMedia
    {
        if ($folder !== null && ! in_array($folder, ContentMedia::FOLDERS, true)) {
            throw new \InvalidArgumentException("Invalid folder: {$folder}");
        }

        $media->update(['folder' => $folder]);

        return $media->fresh();
    }

    public function setStage(ContentMedia $media, string $stage): ContentMedia
    {
        if (! in_array($stage, ContentMedia::STAGES, true)) {
            throw new \InvalidArgumentException("Invalid stage: {$stage}");
        }

        $media->update(['stage' => $stage]);

        return $media->fresh();
    }

    public function bulkMove(array $ids, ?string $folder): int
    {
        if ($folder !== null && ! in_array($folder, ContentMedia::FOLDERS, true)) {
            throw new \InvalidArgumentException("Invalid folder: {$folder}");
        }

        return ContentMedia::whereIn('id', $ids)->update(['folder' => $folder]);
    }

    public function bulkSetStage(array $ids, string $stage): int
    {
        if (! in_array($stage, ContentMedia::STAGES, true)) {
            throw new \InvalidArgumentException("Invalid stage: {$stage}");
        }

        return ContentMedia::whereIn('id', $ids)->update(['stage' => $stage]);
    }

    // ── Products (DIS item_groups) linking ──

    public function linkProducts(ContentMedia $media, array $itemGroupIds, bool $replace = false): int
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($itemGroupIds))));

        if ($replace) {
            \Illuminate\Support\Facades\DB::table('content_media_item_groups')
                ->where('content_media_id', $media->id)
                ->delete();
        }

        if (empty($ids)) {
            return 0;
        }

        $rows = array_map(fn ($id) => [
            'content_media_id' => $media->id,
            'item_group_id' => $id,
            'created_at' => now(),
        ], $ids);

        return \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->insertOrIgnore($rows);
    }

    public function unlinkProducts(ContentMedia $media, array $itemGroupIds): int
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($itemGroupIds))));
        if (empty($ids)) {
            return 0;
        }

        return \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->where('content_media_id', $media->id)
            ->whereIn('item_group_id', $ids)
            ->delete();
    }

    public function bulkLinkProducts(array $mediaIds, array $itemGroupIds): int
    {
        $mIds = array_values(array_unique(array_map('intval', array_filter($mediaIds))));
        $pIds = array_values(array_unique(array_map('intval', array_filter($itemGroupIds))));

        if (empty($mIds) || empty($pIds)) {
            return 0;
        }

        $rows = [];
        $now = now();
        foreach ($mIds as $m) {
            foreach ($pIds as $p) {
                $rows[] = [
                    'content_media_id' => $m,
                    'item_group_id' => $p,
                    'created_at' => $now,
                ];
            }
        }

        return \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->insertOrIgnore($rows);
    }

    // ── Collections (DIS distribution_weeks) linking ──

    public function linkCollections(ContentMedia $media, array $weekIds, bool $replace = false): int
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($weekIds))));

        if ($replace) {
            \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
                ->where('content_media_id', $media->id)
                ->delete();
        }

        if (empty($ids)) {
            return 0;
        }

        $rows = array_map(fn ($id) => [
            'content_media_id' => $media->id,
            'distribution_week_id' => $id,
            'created_at' => now(),
        ], $ids);

        return \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
            ->insertOrIgnore($rows);
    }

    public function unlinkCollections(ContentMedia $media, array $weekIds): int
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($weekIds))));
        if (empty($ids)) {
            return 0;
        }

        return \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
            ->where('content_media_id', $media->id)
            ->whereIn('distribution_week_id', $ids)
            ->delete();
    }

    /**
     * Infer a single "active" collection for a set of products.
     *
     * Strategy (in order):
     *   1. Look at Daily Basket posts in za-marketing — if a product is
     *      actively planned in a post and that post lives in one basket/week,
     *      that's the best signal.
     *   2. Fall back to DIS merch_calendar: query the
     *      distribution_week_item_group_dates pivot cross-DB. Products that
     *      haven't been scheduled in a basket yet still have a week in DIS
     *      where marketing assigned them — that's the "campaign".
     *
     * Returns the distribution_week_id IFF exactly one week covers the given
     * products. Returns null when zero or multiple matches exist.
     *
     * Rationale: in ~90% of cases a product sits in one active collection.
     * Linking media to a product can then auto-apply the collection, saving
     * a click. Ambiguous cases stay silent to avoid polluting filters.
     */
    public function inferCollectionForProducts(array $productIds): ?int
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($productIds))));
        if (empty($ids)) {
            return null;
        }

        // Step 1: basket posts (local, fastest, most explicit marketing intent)
        $weekIds = \Illuminate\Support\Facades\DB::table('daily_baskets')
            ->join('daily_basket_posts', 'daily_basket_posts.daily_basket_id', '=', 'daily_baskets.id')
            ->join('daily_basket_post_products', 'daily_basket_post_products.daily_basket_post_id', '=', 'daily_basket_posts.id')
            ->whereIn('daily_basket_post_products.item_group_id', $ids)
            ->whereNull('daily_baskets.deleted_at')
            ->whereNull('daily_basket_posts.deleted_at')
            ->distinct()
            ->pluck('daily_baskets.distribution_week_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (count($weekIds) === 1) {
            return $weekIds[0];
        }
        if (count($weekIds) > 1) {
            return null; // ambiguous in basket — don't let DIS override
        }

        // Step 2: DIS fallback — the product hasn't been planned into a basket
        // yet, but merch calendar has already assigned it to a week. Use that.
        try {
            $disWeekIds = \Illuminate\Support\Facades\DB::connection('dis')
                ->table('distribution_week_item_group_dates')
                ->whereIn('item_group_id', $ids)
                ->distinct()
                ->pluck('distribution_week_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            return count($disWeekIds) === 1 ? $disWeekIds[0] : null;
        } catch (\Throwable $e) {
            // DIS unavailable or connection misconfigured — don't block the
            // primary flow. Logged but not rethrown.
            report($e);

            return null;
        }
    }

    /**
     * If the product set points to a single collection and the media does
     * not already have that collection linked, attach it. Returns the week
     * id that was auto-linked, or null if no auto-link happened.
     */
    public function autoLinkCollectionFromProducts(ContentMedia $media, array $productIds): ?int
    {
        $weekId = $this->inferCollectionForProducts($productIds);
        if ($weekId === null) {
            return null;
        }

        if (in_array($weekId, $media->distribution_week_ids, true)) {
            return null; // already linked, nothing to do
        }

        $this->linkCollections($media, [$weekId], false);

        return $weekId;
    }

    public function bulkLinkCollections(array $mediaIds, array $weekIds): int
    {
        $mIds = array_values(array_unique(array_map('intval', array_filter($mediaIds))));
        $wIds = array_values(array_unique(array_map('intval', array_filter($weekIds))));

        if (empty($mIds) || empty($wIds)) {
            return 0;
        }

        $rows = [];
        $now = now();
        foreach ($mIds as $m) {
            foreach ($wIds as $w) {
                $rows[] = [
                    'content_media_id' => $m,
                    'distribution_week_id' => $w,
                    'created_at' => $now,
                ];
            }
        }

        return \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
            ->insertOrIgnore($rows);
    }

    // ── Counts ──

    public function productCounts(int $limit = 100): array
    {
        return \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->selectRaw('item_group_id, COUNT(*) AS n')
            ->groupBy('item_group_id')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->item_group_id => (int) $r->n])
            ->all();
    }

    public function collectionCounts(int $limit = 100): array
    {
        return \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
            ->selectRaw('distribution_week_id, COUNT(*) AS n')
            ->groupBy('distribution_week_id')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->distribution_week_id => (int) $r->n])
            ->all();
    }

    /**
     * Batch-populate the cached item_group_ids + distribution_week_ids
     * attributes on a collection of ContentMedia — avoids N+1 when the API
     * response appends those ids for each row.
     */
    public function preloadLinkedIds(\Illuminate\Support\Collection $media): void
    {
        if ($media->isEmpty()) {
            return;
        }

        $ids = $media->pluck('id')->all();

        $products = \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->whereIn('content_media_id', $ids)
            ->get(['content_media_id', 'item_group_id'])
            ->groupBy('content_media_id')
            ->map(fn ($rows) => $rows->pluck('item_group_id')->map(fn ($x) => (int) $x)->values()->all());

        $collections = \Illuminate\Support\Facades\DB::table('content_media_distribution_weeks')
            ->whereIn('content_media_id', $ids)
            ->get(['content_media_id', 'distribution_week_id'])
            ->groupBy('content_media_id')
            ->map(fn ($rows) => $rows->pluck('distribution_week_id')->map(fn ($x) => (int) $x)->values()->all());

        $media->each(function ($m) use ($products, $collections) {
            $m->setRelation('_item_group_ids', $products->get($m->id, []));
            $m->setRelation('_distribution_week_ids', $collections->get($m->id, []));
        });
    }

    public function folderCounts(): array
    {
        $raw = ContentMedia::query()
            ->selectRaw('COALESCE(folder, \'__null\') AS folder, COUNT(*) AS n')
            ->groupBy('folder')
            ->pluck('n', 'folder')
            ->all();

        $out = [];
        foreach (ContentMedia::FOLDERS as $f) {
            $out[$f] = (int) ($raw[$f] ?? 0);
        }
        $out['__uncategorized'] = (int) ($raw['__null'] ?? 0);
        $out['__all'] = array_sum($out);

        return $out;
    }

    protected function generateThumbnail(UploadedFile $file, string $originalPath): ?string
    {
        $thumbPath = str_replace('/media/', '/thumbs/', $originalPath);

        try {
            $image = Image::make($file->getPathname());
            $image->resize($this->thumbWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $encoded = $image->encode($file->getClientOriginalExtension(), $this->thumbQuality);
            Storage::disk($this->disk)->put($thumbPath, (string) $encoded);

            return $thumbPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function list(array $filters = [], int $perPage = 30): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ContentMedia::query()
            ->withCount('posts')
            ->orderByDesc('created_at');

        // Përjashto meta imports default, përveç kur user-i po shikon folder='imported'
        // ose po kërkon me path (rast i rrallë).
        $folder = $filters['folder'] ?? null;
        if ($folder !== 'imported' && $folder !== '__all') {
            $query->where('path', 'not like', 'content-planner/meta-imports/%');
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($filters['type'] === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            }
        }

        if (!empty($filters['search'])) {
            $query->where('original_filename', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['usage'])) {
            if ($filters['usage'] === 'used') {
                $query->whereHas('posts');
            } elseif ($filters['usage'] === 'unused') {
                $query->whereDoesntHave('posts');
            }
        }

        if ($folder !== null && $folder !== '' && $folder !== '__all') {
            if ($folder === '__uncategorized') {
                $query->whereNull('folder');
            } elseif (in_array($folder, ContentMedia::FOLDERS, true)) {
                $query->where('folder', $folder);
            }
        }

        if (!empty($filters['stage']) && in_array($filters['stage'], ContentMedia::STAGES, true)) {
            $query->where('stage', $filters['stage']);
        }

        // Filter by product (DIS item_group). Accepts int, csv, or array.
        if (!empty($filters['product'])) {
            $productIds = $this->parseIdList($filters['product']);
            if (!empty($productIds)) {
                $query->whereExists(function ($q) use ($productIds) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('content_media_item_groups')
                        ->whereColumn('content_media_item_groups.content_media_id', 'content_media.id')
                        ->whereIn('content_media_item_groups.item_group_id', $productIds);
                });
            }
        }

        // Filter by collection (DIS distribution_week). Accepts int, csv, or array.
        if (!empty($filters['collection'])) {
            $weekIds = $this->parseIdList($filters['collection']);
            if (!empty($weekIds)) {
                $query->whereExists(function ($q) use ($weekIds) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('content_media_distribution_weeks')
                        ->whereColumn('content_media_distribution_weeks.content_media_id', 'content_media.id')
                        ->whereIn('content_media_distribution_weeks.distribution_week_id', $weekIds);
                });
            }
        }

        $paginator = $query->paginate($perPage);

        // Eager-populate cached linked ids so API responses include them
        // without firing N+1 inside the accessors.
        $this->preloadLinkedIds(collect($paginator->items()));

        return $paginator;
    }

    private function parseIdList($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }
        if (is_string($raw) && str_contains($raw, ',')) {
            return array_values(array_filter(array_map('intval', explode(',', $raw))));
        }
        $int = (int) $raw;

        return $int > 0 ? [$int] : [];
    }

    public function delete(ContentMedia $media): bool
    {
        // Remove from storage
        try {
            Storage::disk($media->disk)->delete($media->path);
            if ($media->thumbnail_path) {
                Storage::disk($media->disk)->delete($media->thumbnail_path);
            }
        } catch (\Throwable $e) {
            // Storage deletion failed — still delete DB record
        }

        return $media->forceDelete();
    }

    protected function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    public function getAllowedMimeTypes(): array
    {
        $types = [];
        foreach (config('content-planner.allowed_image_types', []) as $ext) {
            $types[] = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        }
        foreach (config('content-planner.allowed_video_types', []) as $ext) {
            $types[] = 'video/' . $ext;
        }
        return array_unique($types);
    }

    public function getMaxUploadSizeBytes(): int
    {
        return $this->maxSizeMb * 1024 * 1024;
    }
}
