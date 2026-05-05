<?php

namespace App\Jobs;

use App\Models\Content\ContentMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Throwable;

/**
 * Post-upload processing for ContentMedia: probe dimensions/duration and
 * generate a thumbnail, then update the row. Decoupled from the upload
 * controller so the user gets a 201 the moment R2 acks the file — they
 * don't wait 2-5 s for ffmpeg / Imagick to finish.
 *
 * Reads the file from a local staging path that the controller wrote to
 * before dispatching (avoids a redundant 500 MB R2 download). On success
 * the staging file is deleted; on failure it's left for inspection.
 */
class ProcessMediaUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public int $mediaId,
        public string $stagingPath,
    ) {
    }

    public function handle(): void
    {
        $media = ContentMedia::find($this->mediaId);
        if (! $media) {
            Log::warning('ProcessMediaUploadJob: media row not found', ['media_id' => $this->mediaId]);
            $this->cleanupStaging();
            return;
        }

        $localPath = Storage::disk('local')->path($this->stagingPath);
        if (! is_file($localPath)) {
            Log::warning('ProcessMediaUploadJob: staging file missing', [
                'media_id' => $this->mediaId,
                'staging_path' => $this->stagingPath,
            ]);
            return;
        }

        $mime = (string) $media->mime_type;
        $width = null;
        $height = null;
        $duration = null;
        $thumbnailPath = null;

        try {
            if (str_starts_with($mime, 'image/')) {
                [$width, $height] = $this->probeImage($localPath);
                $thumbnailPath = $this->generateImageThumbnail($localPath, $media->path, $media->filename);
            } elseif (str_starts_with($mime, 'video/')) {
                $probe = $this->probeVideo($localPath);
                $width = $probe['width'] ?? null;
                $height = $probe['height'] ?? null;
                $duration = $probe['duration'] ?? null;
                $thumbnailPath = $this->generateVideoThumbnail($localPath, $media->path);
            }

            $folder = $media->folder ?? $this->classifyFolder($mime, $width, $height);

            $media->update([
                'width' => $width,
                'height' => $height,
                'duration_seconds' => $duration,
                'thumbnail_path' => $thumbnailPath,
                'folder' => $folder,
            ]);
        } catch (Throwable $e) {
            Log::warning('ProcessMediaUploadJob: processing failed; row left with current values', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupStaging();
        }
    }

    protected function probeImage(string $path): array
    {
        try {
            $info = @getimagesize($path);
            if (is_array($info)) {
                return [(int) $info[0], (int) $info[1]];
            }
        } catch (Throwable $e) {
            // fall through
        }

        try {
            $img = Image::make($path);
            return [$img->width(), $img->height()];
        } catch (Throwable $e) {
            return [null, null];
        }
    }

    protected function probeVideo(string $path): array
    {
        if (! $this->binaryAvailable('ffprobe')) {
            return [];
        }

        $result = Process::timeout(60)->run([
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

    protected function generateImageThumbnail(string $localSourcePath, string $r2OriginalPath, string $filename): ?string
    {
        $thumbPath = str_replace('/media/', '/thumbs/', $r2OriginalPath);
        $thumbWidth = (int) config('content-planner.thumbnail_width', 400);
        $thumbQuality = (int) config('content-planner.thumbnail_quality', 80);
        $disk = (string) config('content-planner.media_disk', 'r2_social');

        try {
            $image = Image::make($localSourcePath);
            $image->resize($thumbWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $encoded = $image->encode($ext ?: 'jpg', $thumbQuality);
            Storage::disk($disk)->put($thumbPath, (string) $encoded);

            return $thumbPath;
        } catch (Throwable $e) {
            Log::info('Image thumbnail generation skipped/failed', [
                'path' => $r2OriginalPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function generateVideoThumbnail(string $localSourcePath, string $r2OriginalPath): ?string
    {
        if (! $this->binaryAvailable('ffmpeg')) {
            return null;
        }

        $thumbWidth = (int) config('content-planner.thumbnail_width', 400);
        $disk = (string) config('content-planner.media_disk', 'r2_social');

        $thumbPath = str_replace('/media/', '/thumbs/', $r2OriginalPath);
        $thumbPath = preg_replace('/\.[^.]+$/', '.jpg', $thumbPath);

        $localTmp = tempnam(sys_get_temp_dir(), 'flare_thumb_') . '.jpg';

        try {
            $result = Process::timeout(120)->run([
                'ffmpeg',
                '-y',
                '-i', $localSourcePath,
                '-ss', '00:00:01.000',
                '-vframes', '1',
                '-vf', 'scale=' . $thumbWidth . ':-2',
                '-q:v', '3',
                $localTmp,
            ]);

            if (! $result->successful() || ! file_exists($localTmp) || filesize($localTmp) === 0) {
                Log::warning('Video thumbnail extraction failed in job', [
                    'video_path' => $r2OriginalPath,
                    'exit' => $result->exitCode(),
                ]);
                return null;
            }

            Storage::disk($disk)->put($thumbPath, file_get_contents($localTmp));
            return $thumbPath;
        } finally {
            if (file_exists($localTmp)) {
                @unlink($localTmp);
            }
        }
    }

    protected function classifyFolder(?string $mimeType, ?int $width, ?int $height): ?string
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

    protected function binaryAvailable(string $name): bool
    {
        $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $result = Process::run([$finder, $name]);
        return $result->successful() && trim($result->output()) !== '';
    }

    protected function cleanupStaging(): void
    {
        try {
            if (Storage::disk('local')->exists($this->stagingPath)) {
                Storage::disk('local')->delete($this->stagingPath);
            }
        } catch (Throwable $e) {
            Log::info('ProcessMediaUploadJob: staging cleanup failed (will be GC\'d eventually)', [
                'staging_path' => $this->stagingPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
