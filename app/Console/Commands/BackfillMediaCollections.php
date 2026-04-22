<?php

namespace App\Console\Commands;

use App\Models\Content\ContentMedia;
use App\Services\ContentPlanner\ContentMediaService;
use Illuminate\Console\Command;

/**
 * Walk every media that has at least one linked product but no linked
 * collection, and try to auto-link a collection via the product→week
 * inference. Fixes media uploaded before the auto-link feature shipped, and
 * catches cases where the inference was silent (product was in 2+ weeks and
 * later narrowed to 1).
 */
class BackfillMediaCollections extends Command
{
    protected $signature = 'media:backfill-collections {--dry-run : Do not write, just report}';
    protected $description = 'Infer + apply collection for media linked to products but missing a collection.';

    public function handle(ContentMediaService $service): int
    {
        $dry = (bool) $this->option('dry-run');

        // Media with at least one product link AND no collection link.
        $mediaIds = \Illuminate\Support\Facades\DB::table('content_media_item_groups')
            ->select('content_media_id')
            ->distinct()
            ->whereNotIn('content_media_id', function ($q) {
                $q->select('content_media_id')->from('content_media_distribution_weeks');
            })
            ->pluck('content_media_id');

        if ($mediaIds->isEmpty()) {
            $this->info('All media with products already have collections. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Scanning {$mediaIds->count()} media…");

        $linked = 0;
        $skipped = 0;

        foreach ($mediaIds->chunk(200) as $batch) {
            $media = ContentMedia::whereIn('id', $batch)->get();
            foreach ($media as $m) {
                $productIds = $m->item_group_ids;
                if (empty($productIds)) continue;

                if ($dry) {
                    $weekId = $service->inferCollectionForProducts($productIds);
                    if ($weekId !== null) {
                        $this->line("  Would link media #{$m->id} → week #{$weekId} (products: " . implode(',', $productIds) . ')');
                        $linked++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $weekId = $service->autoLinkCollectionFromProducts($m, $productIds);
                    if ($weekId !== null) $linked++;
                    else $skipped++;
                }
            }
        }

        $this->newLine();
        $this->info("Linked: {$linked} · Skipped (ambiguous or no match): {$skipped}");

        if ($dry) {
            $this->warn('Dry run — no writes. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
