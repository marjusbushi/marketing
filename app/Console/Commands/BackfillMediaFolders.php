<?php

namespace App\Console\Commands;

use App\Models\Content\ContentMedia;
use App\Services\ContentPlanner\ContentMediaService;
use Illuminate\Console\Command;

/**
 * Backfill folder classification for existing ContentMedia rows.
 *
 * When Media Library v2 shipped, auto-classification ran on NEW uploads only.
 * Older records (uncategorized) stayed with folder=NULL. This command walks
 * every content_media row that has no folder set and applies classifyFolder()
 * using the saved mime_type + width + height, writing the result back.
 *
 * Usage:
 *   php artisan media:backfill-folders           # classify all
 *   php artisan media:backfill-folders --dry-run # show what would change
 */
class BackfillMediaFolders extends Command
{
    protected $signature = 'media:backfill-folders {--dry-run : Do not write changes, just report}';
    protected $description = 'Retrospectively classify existing content_media rows into folders (Reels/Videos/Photos) using auto-classification logic.';

    public function handle(ContentMediaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = ContentMedia::query()->whereNull('folder');
        $total = $query->count();

        if ($total === 0) {
            $this->info('No uncategorized media found. Nothing to backfill.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$total} uncategorized media. Classifying...");

        $buckets = ['reels' => 0, 'videos' => 0, 'photos' => 0, 'null' => 0];

        $query->orderBy('id')->chunk(200, function ($rows) use ($service, $dryRun, &$buckets) {
            foreach ($rows as $m) {
                $folder = $service->classifyFolder($m->mime_type, $m->width, $m->height);
                $key = $folder ?? 'null';
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;

                if (! $dryRun && $folder !== null) {
                    $m->update(['folder' => $folder]);
                }
            }
        });

        $this->newLine();
        $this->table(
            ['Folder', 'Count'],
            collect($buckets)->map(fn ($n, $k) => [$k, $n])->values()->all(),
        );

        if ($dryRun) {
            $this->warn('Dry run — no records were modified. Re-run without --dry-run to apply.');
        } else {
            $classified = $total - ($buckets['null'] ?? 0);
            $this->info("Done. {$classified} of {$total} media were classified. The rest (audio/unknown mime) remain uncategorized.");
        }

        return self::SUCCESS;
    }
}
