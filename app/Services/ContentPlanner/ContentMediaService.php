<?php

namespace App\Services\ContentPlanner;

use App\Jobs\ProcessMediaUploadJob;
use App\Models\Content\ContentMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ContentMediaService
{
    protected string $disk;
    protected int $maxSizeMb;
    protected int $videoMaxSizeMb;
    protected int $photoMaxSizeMb;
    protected int $thumbWidth;
    protected int $thumbQuality;
    protected array $allowedImageTypes;
    protected array $allowedVideoTypes;

    public function __construct()
    {
        $this->disk = config('content-planner.media_disk', 'r2_social');
        $this->maxSizeMb = config('content-planner.media_max_size_mb', 50);
        $this->videoMaxSizeMb = config('content-planner.video_max_size_mb', 500);
        $this->photoMaxSizeMb = config('content-planner.photo_max_size_mb', 25);
        $this->thumbWidth = config('content-planner.thumbnail_width', 400);
        $this->thumbQuality = config('content-planner.thumbnail_quality', 80);
        $this->allowedImageTypes = config('content-planner.allowed_image_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $this->allowedVideoTypes = config('content-planner.allowed_video_types', ['mp4', 'mov', 'avi']);
    }

    public function upload(UploadedFile $file, int $userId, array $overrides = []): ContentMedia
    {
        $this->validateUpload($file);

        // Capture metadata BEFORE we move the file (move can leave the
        // UploadedFile in a state where these calls return wrong values).
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $originalName = (string) $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        $uuid = (string) Str::uuid();
        $filename = $uuid . '.' . $extension;
        $folderPath = 'content-planner/media/' . now()->format('Y/m');
        $path = $folderPath . '/' . $filename;

        // Move the file to local staging first. The deferred processing
        // job (ffmpeg / Imagick) reads from this staging path so it
        // doesn't have to re-download the file from R2 — saves ~5-30 s
        // for big videos. storeAs creates the dir, uses rename when the
        // tmp upload lives on the same fs as storage/app. Job deletes
        // the staging file when done.
        $stagingPath = $file->storeAs('upload-staging', $filename, 'local');
        $stagingFullPath = Storage::disk('local')->path($stagingPath);

        // Upload to R2 from the staging file. putFileAs streams to S3 so
        // we never load 500 MB into memory.
        Storage::disk($this->disk)->putFileAs(
            $folderPath,
            new \Illuminate\Http\File($stagingFullPath),
            $filename,
        );

        $folder = $overrides['folder'] ?? $this->classifyFolder($mime, null, null);
        $stage = $overrides['stage'] ?? 'raw';

        $media = ContentMedia::create([
            'uuid' => $uuid,
            'user_id' => $userId,
            'filename' => $filename,
            'original_filename' => $originalName,
            'disk' => $this->disk,
            'path' => $path,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'thumbnail_path' => null,
            'folder' => $folder,
            'stage' => in_array($stage, ContentMedia::STAGES, true) ? $stage : 'raw',
        ]);

        // Defer ffmpeg/Imagick to the queue. Worker picks it up immediately
        // (queue=default), reads the staging file, probes + generates thumb,
        // updates this row, deletes staging.
        ProcessMediaUploadJob::dispatch($media->id, $stagingPath);

        return $media;
    }

    /**
     * Replace the cover with a data-URL frame captured client-side
     * (canvas.toDataURL on a video element). Format must be image/jpeg
     * or image/png; the cover ends up alongside the video on R2 at
     * content-planner/covers/<uuid>.<ext>. Old cover (if any) is purged.
     *
     * Returns the refreshed media row so the controller can include the
     * new `cover_url` in its JSON reply.
     */
    public function setCoverFromBase64(ContentMedia $media, string $dataUrl, ?int $timestampMs = null): ContentMedia
    {
        if (! preg_match('#^data:image/(jpe?g|png);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new \InvalidArgumentException('Cover dataUrl duhet të jetë JPG ose PNG (data:image/...;base64,...).');
        }

        $ext = $m[1] === 'png' ? 'png' : 'jpg';
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) === 0) {
            throw new \InvalidArgumentException('Cover dataUrl është i pavlefshëm (base64 decode dështoi).');
        }

        $maxBytes = 8 * 1048576;
        if (strlen($bytes) > $maxBytes) {
            throw new \InvalidArgumentException('Cover është më i madh se 8 MB.');
        }

        return $this->storeCoverBytes($media, $bytes, $ext, $timestampMs);
    }

    /**
     * Replace the cover with a user-uploaded JPG/PNG. Mirrors the
     * data-URL path (same target on R2, same purge behavior) — exists so
     * users can drop a polished cover designed in Photoshop/Canva, not
     * just a frame grab.
     */
    public function setCoverFromUpload(ContentMedia $media, UploadedFile $file): ContentMedia
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            throw new \InvalidArgumentException('Cover duhet të jetë JPG ose PNG.');
        }
        if ($file->getSize() > 8 * 1048576) {
            throw new \InvalidArgumentException('Cover është më i madh se 8 MB.');
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new \InvalidArgumentException('Cover nuk u lexua nga disku.');
        }

        // Custom upload — no source-video timestamp.
        return $this->storeCoverBytes($media, $bytes, $ext === 'jpeg' ? 'jpg' : $ext, null);
    }

    /**
     * Drop any existing cover for a media item, falling back to the
     * auto-generated thumbnail. Useful when the user picks a frame they
     * dislike and wants to revert to "let Meta auto-pick".
     */
    public function clearCover(ContentMedia $media): ContentMedia
    {
        if ($media->cover_path) {
            try {
                Storage::disk($media->disk ?: $this->disk)->delete($media->cover_path);
            } catch (\Throwable $e) {
                Log::info('Cover delete failed (will be GC\'d eventually)', ['path' => $media->cover_path, 'error' => $e->getMessage()]);
            }
        }
        $media->update(['cover_path' => null, 'cover_timestamp_ms' => null]);
        return $media->fresh();
    }

    protected function storeCoverBytes(ContentMedia $media, string $bytes, string $ext, ?int $timestampMs = null): ContentMedia
    {
        $disk = $media->disk ?: $this->disk;
        $coverDir = 'content-planner/covers/' . now()->format('Y/m');
        $coverPath = $coverDir . '/' . (string) Str::uuid() . '.' . $ext;

        Storage::disk($disk)->put($coverPath, $bytes);

        $oldCover = $media->cover_path;
        $media->update([
            'cover_path' => $coverPath,
            'cover_timestamp_ms' => $timestampMs,
        ]);

        if ($oldCover) {
            try {
                Storage::disk($disk)->delete($oldCover);
            } catch (\Throwable $e) {
                Log::info('Old cover purge failed', ['path' => $oldCover, 'error' => $e->getMessage()]);
            }
        }

        return $media->fresh();
    }

    /**
     * Pre-flight validation against config-defined limits. Throwing here
     * surfaces the failure to the controller before we touch storage, so
     * the user gets the size/format error before a 500 MB upload hits R2.
     */
    protected function validateUpload(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $sizeBytes = (int) $file->getSize();
        $sizeMb = $sizeBytes / 1048576;

        // Type detection: extension wins because mime can be empty / wrong on
        // .heic, .mts, .mkv (browser detects as application/octet-stream).
        // Mime is used as a tie-breaker only when extension is unknown.
        $isImageByExt = in_array($extension, $this->allowedImageTypes, true);
        $isVideoByExt = in_array($extension, $this->allowedVideoTypes, true);
        $isImageByMime = $this->isImage($mime);
        $isVideoByMime = $this->isVideo($mime);

        if ($isImageByExt || ($isImageByMime && ! $isVideoByExt)) {
            if (! $isImageByExt) {
                throw new \InvalidArgumentException(
                    "Format i papranuar: .{$extension}. Lejohen: " . implode(', ', $this->allowedImageTypes) . '.'
                );
            }
            if ($sizeMb > $this->photoMaxSizeMb) {
                throw new \InvalidArgumentException(
                    sprintf('Foto është %.1f MB; maksimumi i lejuar është %d MB.', $sizeMb, $this->photoMaxSizeMb)
                );
            }
            return;
        }

        if ($isVideoByExt || $isVideoByMime) {
            if (! $isVideoByExt) {
                throw new \InvalidArgumentException(
                    "Format video i papranuar: .{$extension}. Lejohen: " . implode(', ', $this->allowedVideoTypes) . '.'
                );
            }
            if ($sizeMb > $this->videoMaxSizeMb) {
                throw new \InvalidArgumentException(
                    sprintf('Video është %.1f MB; maksimumi i lejuar është %d MB.', $sizeMb, $this->videoMaxSizeMb)
                );
            }
            if ($extension !== 'mp4') {
                Log::info('Non-MP4 video uploaded; Meta may transcode or reject at publish', [
                    'extension' => $extension,
                    'mime' => $mime,
                ]);
            }
            return;
        }

        throw new \InvalidArgumentException(
            "Tipi i skedarit nuk pranohet: .{$extension} ({$mime}). Vetëm foto ose video lejohen."
        );
    }

    protected function isVideo(?string $mimeType): bool
    {
        return $mimeType !== null && str_starts_with($mimeType, 'video/');
    }

    /**
     * Read width/height/duration from a video using ffprobe. Uses Process
     * with array args so user-supplied paths can never be interpreted as
     * shell. Returns an empty array silently if ffprobe is missing or
     * fails — the upload still succeeds, the row just has nulls for
     * those fields. Fix by `brew install ffmpeg` (which ships ffprobe).
     */
    protected function probeVideo(string $path): array
    {
        if (! $this->binaryAvailable('ffprobe')) {
            return [];
        }

        $result = Process::run([
            'ffprobe',
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height',
            '-show_entries', 'format=duration',
            '-of', 'json',
            $path,
        ]);

        if (! $result->successful()) {
            return [];
        }

        $data = json_decode($result->output(), true);
        if (! is_array($data)) {
            return [];
        }

        return [
            'width' => isset($data['streams'][0]['width']) ? (int) $data['streams'][0]['width'] : null,
            'height' => isset($data['streams'][0]['height']) ? (int) $data['streams'][0]['height'] : null,
            'duration' => isset($data['format']['duration']) ? (int) round((float) $data['format']['duration']) : null,
        ];
    }

    /**
     * Capture a single frame at t=1s and upload it as the thumbnail.
     * Process runs ffmpeg via array args (no shell interpolation). Falls
     * back gracefully when ffmpeg is missing — the post will just show a
     * placeholder in the planner. Stored alongside the video at
     * thumbs/<basename>.jpg.
     */
    protected function generateVideoThumbnail(UploadedFile $file, string $videoPath): ?string
    {
        if (! $this->binaryAvailable('ffmpeg')) {
            Log::info('ffmpeg not installed; skipping video thumbnail generation');
            return null;
        }

        $thumbPath = str_replace('/media/', '/thumbs/', $videoPath);
        $thumbPath = preg_replace('/\.[^.]+$/', '.jpg', $thumbPath);

        $localTmp = tempnam(sys_get_temp_dir(), 'flare_thumb_') . '.jpg';

        try {
            $result = Process::timeout(60)->run([
                'ffmpeg',
                '-y',
                '-i', $file->getPathname(),
                '-ss', '00:00:01.000',
                '-vframes', '1',
                '-vf', 'scale=' . $this->thumbWidth . ':-2',
                '-q:v', '3',
                $localTmp,
            ]);

            if (! $result->successful() || ! file_exists($localTmp) || filesize($localTmp) === 0) {
                Log::warning('Video thumbnail extraction failed', [
                    'video_path' => $videoPath,
                    'exit' => $result->exitCode(),
                ]);
                return null;
            }

            Storage::disk($this->disk)->put($thumbPath, file_get_contents($localTmp));

            return $thumbPath;
        } finally {
            if (file_exists($localTmp)) {
                @unlink($localTmp);
            }
        }
    }

    /**
     * True iff $name is on the system PATH and executable. Used by
     * ffprobe / ffmpeg checks so a missing binary degrades gracefully.
     * Uses Process with array args — no shell injection surface.
     */
    protected function binaryAvailable(string $name): bool
    {
        $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $result = Process::run([$finder, $name]);
        return $result->successful() && trim($result->output()) !== '';
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
     * Find every distribution_week that contains any of the given products.
     *
     * Union of two sources:
     *   1. Daily Basket posts (local) — weeks where marketing actively planned
     *      a post featuring the product.
     *   2. DIS Merch Calendar (cross-DB) — the authoritative source of "which
     *      weeks is this product part of". Looked up via
     *      distribution_week_item_group_dates pivot.
     *
     * Returns all unique week ids. Callers decide whether to link singularly
     * (for back-compat) or to link all of them.
     */
    public function findCollectionsForProducts(array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($productIds))));
        if (empty($ids)) {
            return [];
        }

        // Local basket posts
        $fromBasket = \Illuminate\Support\Facades\DB::table('daily_baskets')
            ->join('daily_basket_posts', 'daily_basket_posts.daily_basket_id', '=', 'daily_baskets.id')
            ->join('daily_basket_post_products', 'daily_basket_post_products.daily_basket_post_id', '=', 'daily_basket_posts.id')
            ->whereIn('daily_basket_post_products.item_group_id', $ids)
            ->whereNull('daily_baskets.deleted_at')
            ->whereNull('daily_basket_posts.deleted_at')
            ->distinct()
            ->pluck('daily_baskets.distribution_week_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        // DIS merch calendar (authoritative assignment)
        $fromDis = [];
        try {
            $fromDis = \Illuminate\Support\Facades\DB::connection('dis')
                ->table('distribution_week_item_group_dates')
                ->whereIn('item_group_id', $ids)
                ->distinct()
                ->pluck('distribution_week_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();
        } catch (\Throwable $e) {
            report($e);
        }

        return array_values(array_unique(array_merge($fromBasket, $fromDis)));
    }

    /**
     * Back-compat: return a single inferred week when there's exactly one
     * candidate. Null otherwise. Prefer findCollectionsForProducts() for full
     * results + autoLinkCollectionsFromProducts() for multi-link behavior.
     */
    public function inferCollectionForProducts(array $productIds): ?int
    {
        $weekIds = $this->findCollectionsForProducts($productIds);

        return count($weekIds) === 1 ? $weekIds[0] : null;
    }

    /**
     * Link ALL weeks that contain any of the given products. User tuned the
     * policy: always link — a product that lives in 3 campaigns should make
     * the media discoverable under each of those 3 collections' filter.
     *
     * Returns the list of week ids that were newly linked (i.e. excluding
     * those already linked to the media). Empty array means nothing changed.
     */
    public function autoLinkCollectionsFromProducts(ContentMedia $media, array $productIds): array
    {
        $weekIds = $this->findCollectionsForProducts($productIds);
        if (empty($weekIds)) {
            return [];
        }

        $already = $media->distribution_week_ids;
        $toLink = array_values(array_diff($weekIds, $already));
        if (empty($toLink)) {
            return [];
        }

        $this->linkCollections($media, $toLink, false);

        return $toLink;
    }

    /**
     * Deprecated alias for back-compat with the earlier single-match flow.
     * Returns the first newly-linked week id, or null if no new link was made.
     */
    public function autoLinkCollectionFromProducts(ContentMedia $media, array $productIds): ?int
    {
        $linked = $this->autoLinkCollectionsFromProducts($media, $productIds);

        return $linked[0] ?? null;
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
