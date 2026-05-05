<?php

namespace App\Console\Commands;

use App\Services\DisApiClient;
use Illuminate\Console\Command;

/**
 * Diagnostic command that walks every distribution week the marketing app
 * can see and prints the raw DIS API payload for a given product (item
 * group) id or code. Useful when the rail shows wrong stock/value: run
 * this against prod to see exactly what DIS returns, with no mapping
 * applied. Read-only; safe to run anytime.
 *
 *   php artisan dis:probe-product 1718982
 *   php artisan dis:probe-product 1718982 --months-back=6 --months-forward=2
 */
class DisProbeProductCommand extends Command
{
    protected $signature = 'dis:probe-product
                            {needle : Item group id or code to look up}
                            {--months-back=3 : How many months of weeks to scan backwards}
                            {--months-forward=2 : How many months of weeks to scan forwards}';

    protected $description = 'Print the raw DIS API payload for a single product across recent distribution weeks';

    public function handle(DisApiClient $dis): int
    {
        $needle = (string) $this->argument('needle');
        $start  = now()->subMonths((int) $this->option('months-back'))->startOfMonth()->toDateString();
        $end    = now()->addMonths((int) $this->option('months-forward'))->endOfMonth()->toDateString();

        $this->info("Probing DIS for needle={$needle} in [{$start} … {$end}]");

        // ─── Source A: searchItemGroups (the rich endpoint DIS UI uses) ───
        $this->newLine();
        $this->line('── A) searchItemGroups (likely rich shape) ──────────');
        try {
            $matches = $dis->searchItemGroups($needle);
            if (empty($matches)) {
                $this->warn('searchItemGroups returned 0 matches for "'.$needle.'"');
            } else {
                foreach ($matches as $m) {
                    $this->line('Top-level keys: '.implode(', ', array_keys($m)));
                    $this->line(json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $this->newLine();
                }
            }
        } catch (\Throwable $e) {
            $this->warn('searchItemGroups failed: '.$e->getMessage());
        }

        // ─── Source B: getWeek (the endpoint Marketing app reads) ──────────
        $this->newLine();
        $this->line('── B) getWeek (what Marketing actually consumes) ────');
        try {
            $weeks = $dis->listWeekSummaries($start, $end);
        } catch (\Throwable $e) {
            $this->error('listWeekSummaries failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $hits = 0;
        foreach ($weeks as $w) {
            try {
                $detail = $dis->getWeek((int) $w['id']);
            } catch (\Throwable $e) {
                $this->warn('getWeek('.$w['id'].') failed: '.$e->getMessage());
                continue;
            }

            foreach ($detail['item_groups'] ?? [] as $g) {
                $id   = (string) ($g['id'] ?? '');
                $code = (string) ($g['code'] ?? '');
                if ($id !== $needle && $code !== $needle) {
                    continue;
                }

                $hits++;
                $weekLabel = ($w['code'] ?? '?').' (#'.($w['id'] ?? '?').')';
                $this->newLine();
                $this->line('── HIT in week '.$weekLabel.' ──────────────');
                $this->line('Top-level keys: '.implode(', ', array_keys($g)));
                $this->newLine();
                $this->line(json_encode($g, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        $this->newLine();
        if ($hits === 0) {
            $this->warn("No match in getWeek across ".count($weeks).' weeks. (Source A above is the more complete endpoint.)');
        } else {
            $this->info("getWeek matched {$hits} occurrence(s). Compare A vs B above to see which fields are missing.");
        }

        return self::SUCCESS;
    }
}
