<?php

namespace App\Console\Commands;

use App\Models\TikTok\TikTokToken;
use App\Services\Tiktok\TiktokAdsApiService;
use App\Services\Tiktok\TiktokApiService;
use Exception;
use Illuminate\Console\Command;

class TiktokRefreshTokenCommand extends Command
{
    protected $signature = 'tiktok:refresh-tokens {--force : Refresh even if not expiring soon}';

    protected $description = 'Refresh TikTok access tokens (Marketing: 24h, Organic: 24h)';

    public function handle(): int
    {
        $tokens = TiktokToken::where('is_active', true)->get();

        if ($tokens->isEmpty()) {
            $this->info('No active TikTok tokens found.');

            return self::SUCCESS;
        }

        foreach ($tokens as $token) {
            if (! $this->option('force') && ! $token->needsRefresh()) {
                $this->line("  [{$token->name}] token OK, expires {$token->access_token_expires_at->diffForHumans()}. Skipping.");
                continue;
            }

            if ($token->isRefreshTokenExpired()) {
                $this->error("  [{$token->name}] refresh token expired! Re-authenticate required.");
                continue;
            }

            try {
                $type = $token->token_type ?? 'organic';

                if ($type === 'marketing') {
                    $api = app(TiktokAdsApiService::class);
                    $api->refreshAccessToken($token);
                } else {
                    $api = app(TiktokApiService::class);
                    $api->refreshAccessToken($token);
                }

                $token->refresh();
                $this->info("  [{$token->name}] refreshed. New expiry: {$token->access_token_expires_at->format('Y-m-d H:i')}");
            } catch (Exception $e) {
                $this->error("  [{$token->name}] refresh failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
