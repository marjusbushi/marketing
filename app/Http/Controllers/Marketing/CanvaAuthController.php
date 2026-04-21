<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\CanvaConnection;
use App\Services\Marketing\CanvaConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Per-user OAuth flow for Canva Connect.
 *
 *   GET  /marketing/canva/authorize  — redirect to Canva's consent page
 *   GET  /marketing/canva/callback   — exchange code + persist tokens
 *   POST /marketing/canva/disconnect — revoke the user's tokens
 *   GET  /marketing/api/canva/status — lightweight status for the SPA
 *
 * State and PKCE code_verifier are stored in the session for one round-trip
 * and cleared on callback. Token storage is delegated to the service; the
 * controller only decides *what* to do at each step.
 */
class CanvaAuthController extends Controller
{
    public function __construct(protected CanvaConnectService $canva)
    {
    }

    /**
     * Step 1 — redirect the user to Canva's consent page.
     */
    public function authorize(Request $request): RedirectResponse
    {
        $this->assertFeatureEnabled();
        $this->assertClientCredentials();

        $payload = $this->canva->authorizeUrl();

        $request->session()->put('canva_oauth_state', $payload['state']);
        $request->session()->put('canva_oauth_verifier', $payload['code_verifier']);

        return redirect()->away($payload['url']);
    }

    /**
     * Step 2 — Canva redirects back with `code` and `state`. Validate the
     * state, trade the code for tokens, fetch the Canva profile, and save.
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->assertFeatureEnabled();

        $failureRoute = $this->settingsRoute();

        $expectedState = $request->session()->pull('canva_oauth_state');
        $codeVerifier  = $request->session()->pull('canva_oauth_verifier');

        if (!$expectedState || $expectedState !== $request->query('state')) {
            return redirect()->route($failureRoute)->with('error', 'Invalid Canva OAuth state.');
        }

        if ($request->has('error')) {
            $message = $request->query('error_description', $request->query('error'));
            return redirect()->route($failureRoute)->with('error', "Canva OAuth denied: {$message}");
        }

        $code = (string) $request->query('code', '');
        if ($code === '' || !$codeVerifier) {
            return redirect()->route($failureRoute)->with('error', 'Canva OAuth: missing code or verifier.');
        }

        try {
            $tokens  = $this->canva->exchangeCode($code, $codeVerifier);
            $profile = $this->canva->getCurrentUser($tokens['access_token']);
            $this->canva->storeConnection($request->user()->id, $tokens, $profile);
        } catch (RuntimeException $e) {
            Log::error('Canva OAuth callback failed: ' . $e->getMessage());
            return redirect()->route($failureRoute)->with('error', 'Canva OAuth failed: ' . $e->getMessage());
        }

        return redirect()->route($failureRoute)->with('success', 'Canva u lidh me sukses.');
    }

    /**
     * Step 3 — revoke tokens + deactivate the connection. Safe to call even
     * if there is no active connection (returns `no_connection`).
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->assertFeatureEnabled();

        $connection = CanvaConnection::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$connection) {
            return response()->json(['status' => 'no_connection']);
        }

        try {
            $this->canva->revokeToken($connection->refresh_token);
        } catch (RuntimeException $e) {
            Log::warning('Canva token revoke failed: ' . $e->getMessage());
        }

        $connection->forceFill([
            'is_active'     => false,
            'access_token'  => '',
            'refresh_token' => '',
        ])->save();

        return response()->json(['status' => 'disconnected']);
    }

    /**
     * Lightweight check used by the SPA before showing the "Open in Canva"
     * button — avoids a full settings page render.
     */
    public function status(Request $request): JsonResponse
    {
        $connection = CanvaConnection::query()
            ->active()
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$connection) {
            return response()->json([
                'connected' => false,
                'feature_enabled' => (bool) config('canva.features.canva_connect'),
            ]);
        }

        return response()->json([
            'connected'         => true,
            'feature_enabled'   => (bool) config('canva.features.canva_connect'),
            'canva_user_id'     => $connection->canva_user_id,
            'canva_display_name' => $connection->canva_display_name,
            'expires_at'        => $connection->expires_at?->toIso8601String(),
            'expired'           => $connection->isExpired(),
        ]);
    }

    // ─── helpers ─────────────────────────────────────────────────

    protected function assertFeatureEnabled(): void
    {
        if (!config('canva.features.canva_connect', false)) {
            abort(404);
        }
    }

    protected function assertClientCredentials(): void
    {
        if (!config('canva.client_id') || !config('canva.client_secret')) {
            abort(503, 'Canva client credentials are not configured.');
        }
    }

    protected function settingsRoute(): string
    {
        return 'marketing.settings.brand-kit.index';
    }
}
