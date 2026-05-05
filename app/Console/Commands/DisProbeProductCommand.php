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
            $this->warn("No match for {$needle} across ".count($weeks).' weeks. Try widening --months-back / --months-forward.');

            return self::FAILURE;
        }

        $this->info("Found {$hits} occurrence(s).");

        return self::SUCCESS;
    }
}
