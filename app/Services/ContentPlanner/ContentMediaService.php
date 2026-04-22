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

        return $query->paginate($perPage);
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
