<?php

namespace App\Console\Commands;

use App\Services\ContentPlanner\ContentFeedImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Hourly importer of published Facebook + Instagram posts as ContentPost
 * records (planner layer). Complements meta:sync which produces
 * MetaPostInsight records (metrics layer).
 *
 * Run manually with:
 *   php artisan content-planner:import-feed --days=30
 */
class ContentPlannerImportFeedCommand extends Command
{
    protected $signature = 'content-planner:import-feed
        {--days=30 : How many days back to scan (default: 30)}';

    protected $description = 'Import IG/FB published posts into the Content Planner (ContentPost records).';

    public function handle(ContentFeedImportService $feed): int
    {
        $days = (int) $this->option('days');
        $since = Carbon::now()->subDays($days)->toDateString();

        $this->info("Importing posts since {$since}…");

        $fb = $feed->importFacebookPosts($since);
        $ig = $feed->importInstagramPosts($since);

        $this->info("Imported {$fb} Facebook + {$ig} Instagram posts.");

        return self::SUCCESS;
    }
}
