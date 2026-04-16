<?php

namespace App\Console\Commands;

use App\Models\Meta\MetaToken;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaRefreshTokenCommand extends Command
{
    protected $signature = 'meta:refresh-tokens
        {--force : Refresh all tokens regardless of expiry}';

    protected $description = 'Refresh Meta long-lived tokens that are expiring within 7 days';

    public function handle(): int
    {
        $force = $this->option('force');
        $refreshed = 0;

        $tokens = MetaToken::active()
            ->ofType('long_lived_user')
            ->get();

        foreach ($tokens as $token) {
            if (!$force && !$token->expiresWithinDays(7)) {
                $this->line("Token '{$token->name}' expires at {$token->expires_at->format('Y-m-d')} - OK, skipping.");
                continue;
            }

            $this->info("Refreshing token: {$token->name}...");

            try {
                $response = Http::get(config('meta.base_url') . '/' . config('meta.api_version') . '/oauth/access_token', [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => config('meta.app_id'),
                    'client_secret' => config('meta.app_secret'),
                    'fb_exchange_token' => $token->getDecryptedToken(),
                ]);

                if ($response->failed()) {
                    $error = $response->json('error.message', 'Unknown error');
                    throw new Exception("Refresh failed: {$error}");
                }

                $data = $response->json();
                $token->update([
                    'access_token' => $data['access_token'],
                    'expires_at' => now()->addSeconds($data['expires_in'] ?? 5184000),
                ]);

                $this->info("  Refreshed! New expiry: {$token->fresh()->expires_at->format('Y-m-d H:i')}");
                $refreshed++;

                // Also refresh related page tokens
                $this->refreshPageTokens($data['access_token']);
            } catch (Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
                Log::error("Meta token refresh failed for '{$token->name}': {$e->getMessage()}");
            }
        }

        if ($refreshed > 0) {
            $this->info("Refreshed {$refreshed} token(s).");
        } else {
            $this->line('No tokens needed refreshing.');
        }

        return self::SUCCESS;
    }

    /**
     * Refresh page tokens derived from the user token.
     */
    private function refreshPageTokens(string $userToken): void
    {
        try {
            $response = Http::get(config('meta.base_url') . '/' . config('meta.api_version') . '/me/accounts', [
                'access_token' => $userToken,
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ]);

            if ($response->failed()) {
                return;
            }

            foreach ($response->json('data', []) as $page) {
                MetaToken::where('page_id', $page['id'])
                    ->where('token_type', 'page')
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                MetaToken::create([
                    'name' => "Page: {$page['name']}",
                    'token_type' => 'page',
                    'access_token' => $page['access_token'],
                    'page_id' => $page['id'],
                    'ig_account_id' => $page['instagram_business_account']['id'] ?? null,
                    'scopes' => config('meta.oauth.scopes'),
                    'expires_at' => null,
                ]);

                $this->line("  Refreshed page token: {$page['name']}");
            }
        } catch (Exception $e) {
            Log::warning('Failed to refresh page tokens: ' . $e->getMessage());
        }
    }
}
