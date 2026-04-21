<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Asset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Marketing Asset uploads + lookup.
 *
 * Files are stored on the configured `marketing` disk (see config/filesystems.php).
 * Kind-specific metadata (e.g. music tags, font weights) is attached via
 * the `metadata` JSON field at upload time and can be updated later.
 */
class AssetService
{
    private const DEFAULT_DISK = 'public';
    private const UPLOAD_DIR = 'marketing/assets';

    public function upload(
        UploadedFile $file,
        string $kind,
        string $name,
        array $metadata = [],
        ?int $userId = null,
        ?string $disk = null,
    ): Asset {
        $usedDisk = $disk ?? self::DEFAULT_DISK;

        $path = Storage::disk($usedDisk)->putFile(
            self::UPLOAD_DIR . '/' . $kind,
            $file,
        );

        return Asset::query()->create([
            'kind'             => $kind,
            'name'             => $name,
            'path'             => $path,
            'mime_type'        => $file->getMimeType(),
            'duration_seconds' => null,
            'metadata'         => $metadata,
            'uploaded_by'      => $userId,
        ]);
    }

    /**
     * @return Collection<int, Asset>
     */
    public function byKind(string $kind): Collection
    {
        return Asset::query()->ofKind($kind)->orderBy('name')->get();
    }

    public function find(int $id): ?Asset
    {
        return Asset::query()->find($id);
    }

    public function delete(Asset $asset, ?string $disk = null): void
    {
        Storage::disk($disk ?? self::DEFAULT_DISK)->delete($asset->path);
        $asset->delete();
    }
}
