<?php

namespace App\Console\Commands;

use App\Models\Meta\MetaPostInsight;
use App\Services\Meta\MetaPostSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-download media for IG posts whose `media_url` is unusable:
 *   - starts with http(s)://  → raw IG CDN URL (expired token, 403 on fetch)
 *   - points to /storage/...  → expected local path but file is missing on disk
 *
 * Root cause: `MetaPostSyncService::downloadMedia` used to fall back to the
 * original IG CDN URL when the server-side download failed. That URL expires
 * within hours, leaving the grid rendering empty placeholders. The code bug
 * is now fixed upstream; this command repairs the already-broken rows.
 *
 * Run with:
 *   php artisan meta:backfill-ig-media               # all broken IG posts
 *   php artisan meta:backfill-ig-media --limit=50    # cap per run
 *   php artisan meta:backfill-ig-media --dry-run     # report only
 */
class MetaBackfillIgMediaCommand extends Command
{
    protected $signature = 'meta:backfill-ig-media
        {--limit=0 : Max posts to process (0 = no limit)}
        {--dry-run : Report what would be repaired without touching Graph API or DB}
        {--sleep=100 : Milliseconds to sleep between Graph API calls (rate-limit friendly)}';

    protected $description = 'Re-download IG post media for rows with expired CDN URLs or missing local files.';

    public function handle(MetaPostSyncService $sync): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $sleepMs = (int) $this->option('sleep');

        $this->info('Scanning IG posts for broken media_url…');

        $candidates = $this->findBrokenIgPosts($limit);
        $total = count($candidates);

        if ($total === 0) {
            $this->info('No broken IG media found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} IG posts needing repair.");
        if ($dryRun) {
            foreach ($candidates as $c) {
                $this->line("  #{$c['id']} post_id={$c['post_id']} reason={$c['reason']} url=".substr($c['media_url'] ?? '(null)', 0, 80));
            }
            $this->info('DRY RUN — no changes made.');
            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($candidates as $c) {
            $post = MetaPostInsight::find($c['id']);
            if (!$post) {
                $bar->advance();
                continue;
            }

            $success = $sync->backfillIgPostMedia($post);
            if ($success) {
                $ok++;
            } else {
                $fail++;
                $this->newLine();
                $this->warn("  Failed: #{$post->id} post_id={$post->post_id}");
            }

            $bar->advance();
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Backfill complete — ok: {$ok} / failed: {$fail} / total: {$total}");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Return IG post rows whose media_url is unusable.
     *   - http(s)://... → expired CDN URL, will 403
     *   - /storage/... where file is missing on disk
     *   - null/empty (never successfully downloaded)
     */
    private function findBrokenIgPosts(int $limit): array
    {
        $query = MetaPostInsight::where('source', 'instagram')
            ->orderByDesc('created_at_meta');

        $out = [];
        foreach ($query->cursor() as $post) {
            $url = $post->media_url;
            $reason = null;

            if (!$url) {
                $reason = 'no_media_url';
            } elseif (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                $reason = 'raw_cdn_url';
            } elseif (str_starts_with($url, '/storage/')) {
                // Translate /storage/xxx → <public>/storage/xxx
                $relative = ltrim(substr($url, strlen('/storage/')), '/');
                if (!Storage::disk('public')->exists($relative)) {
                    $reason = 'file_missing';
                }
            }

            if ($reason !== null) {
                $out[] = [
                    'id' => $post->id,
                    'post_id' => $post->post_id,
                    'media_url' => $url,
                    'reason' => $reason,
                ];
                if ($limit > 0 && count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }
}
