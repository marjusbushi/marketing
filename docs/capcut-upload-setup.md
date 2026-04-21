# CapCut Video Upload Setup

Post Decision #14, the video path in Visual Studio is **"CapCut manual"** —
staff edits videos in CapCut (mobile or desktop), exports the MP4, and drags
it into the studio. No server-side ffmpeg, no render queue, no embedded
timeline editor.

This doc covers the infrastructure config required for uploads up to 500MB.

## 1. Laravel / server config

The upload endpoint (`POST /marketing/api/creative-briefs/{id}/upload-video`)
accepts the file in a single multipart POST. For the default 500MB ceiling
you must raise three limits:

### PHP `upload_max_filesize` + `post_max_size`

Add to your `php.ini` (Laravel Herd: `~/Library/Application Support/Herd/config/php/<version>/php.ini`):

```ini
upload_max_filesize = 500M
post_max_size = 520M
memory_limit = 512M
max_execution_time = 300
```

Restart PHP after editing:

```sh
# Herd
valet restart  # or the equivalent per environment
```

### Nginx `client_max_body_size`

On prod / staging edit the nginx vhost (or the global http block):

```nginx
# /etc/nginx/sites-available/za-marketing.conf
http {
    client_max_body_size 520M;
    client_body_timeout 300s;
}
```

Reload nginx:

```sh
sudo nginx -t && sudo systemctl reload nginx
```

### Laravel env override

If a single environment needs a smaller cap (shared-host preview, etc.), set
in `.env`:

```env
MARKETING_VIDEO_MAX_SIZE_MB=200
```

The value in [config/content-planner.php](../config/content-planner.php)
reads this via `env('MARKETING_VIDEO_MAX_SIZE_MB', 500)`.

## 2. Storage disk

Files land on the `public` disk under `marketing/videos/{creative_brief_id}/`.
Thumbnails (JPEG, quality 85) land under
`marketing/videos/{creative_brief_id}/thumbnails/`.

For prod we recommend S3 — switch the disk in `config/filesystems.php` and set
the credentials in `.env`. The upload code uses the disk via
`$file->store(..., 'public')` so it transparently follows whatever `public`
resolves to.

Run once per environment so the public symlink exists:

```sh
php artisan storage:link
```

## 3. Client-side flow

The [VideoUploadButton](../resources/js/studio/components/VideoUploadButton.tsx)
runs two steps locally before sending the file:

1. **Probe** — an off-screen `<video>` element reads `duration`,
   `videoWidth`, and `videoHeight`. No ffmpeg.wasm is required for this path.
2. **Thumbnail** — we seek to `min(1s, 10% of duration)` to skip CapCut's
   fade-in, draw the frame to a canvas, and `toBlob` as JPEG (0.85 quality).

Both the video and the JPEG travel in the same `multipart/form-data` request,
along with the probed `duration_seconds` / `width` / `height`.

### Quick Trim integration

When an upload finishes, the button surfaces a ✂︎ Trim pill that hands the
original blob back up to `BriefEditor`, which opens the existing
[QuickTrimModal](../resources/js/studio/components/QuickTrimModal.tsx)
(task #1245, FFmpeg.wasm). The modal handles trim → render → download;
re-upload of the trimmed file is a follow-up (marked TODO in the component).

## 4. Validation (server)

The endpoint enforces:

- `file` — required, `max:{video_max_size_mb * 1024}` KB,
  `mimetypes:video/mp4,video/quicktime,video/x-m4v,video/webm`
- `thumbnail` — optional, `image`, `max:5120` KB
- `duration_seconds` — optional integer, 0..3600
- `width` / `height` — optional integers, 1..8192

Validation failures return `422` with Laravel's standard error envelope.
Non-video files (images, text, etc.) fail on `mimetypes`; oversized files fail
on `max`. See
[CreativeBriefVideoUploadTest](../tests/Feature/Marketing/CreativeBriefVideoUploadTest.php)
for the complete assertion set.

## 5. What lands where

Successful upload produces:

- **File on disk** — `marketing/videos/{brief}/<random>.mp4`
- **Thumbnail** — `marketing/videos/{brief}/thumbnails/<random>.jpg`
- **`daily_basket_post_media` row** (only if the brief is linked to a post) —
  includes duration / width / height / size / thumbnail path, ordered after
  any existing media on the post
- **`creative_brief.media_slots`** — appended with `{kind: 'video', source:
  'capcut', path, thumbnail_path, duration, w, h, size, media_id?, …}`
- **`creative_brief.state.capcut[]`** — same entry mirrored into state so the
  SPA can re-hydrate without re-querying
- **`creative_brief.duration_sec`** — filled once, with the first upload's
  duration (used by AI caption prompts)

## 6. Follow-ups (future subtasks)

- **Chunked upload** — for unreliable connections and very large files,
  integrate a library like `pion/laravel-chunk-upload` so uploads resume on
  network drops. Current single-request path works but retries restart
  from zero.
- **S3 multipart + direct-to-S3** — skip the app server entirely with
  presigned POST URLs; reduces app-server bandwidth and avoids
  `client_max_body_size` entirely.
- **Virus scan** — for staff-uploaded content the risk is low, but a ClamAV
  hook on the disk's `uploaded` event closes the loop.
- **Trim → re-upload round-trip** — the QuickTrimModal currently downloads
  the trimmed file locally; wiring it back to overwrite the original or
  attach a new version is the natural next iteration.
