<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaIgWebhookEventJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaInstagramWebhookController extends Controller
{
    /**
     * Meta Graph webhook subscription handshake.
     *
     * Meta hits this with ?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...
     * when the webhook is first registered in Meta App Dashboard. We must echo
     * the challenge exactly (as plain text) after verifying the token matches.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = (string) config('meta.webhook_verify_token');

        if ($mode !== 'subscribe' || $expected === '' || ! hash_equals($expected, (string) $token)) {
            Log::channel('meta-webhooks')->warning('IG webhook verify rejected', [
                'ip' => $request->ip(),
                'mode' => $mode,
            ]);
            return response('forbidden', 403);
        }

        Log::channel('meta-webhooks')->info('IG webhook verify handshake OK');
        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Receive a signed webhook event batch. We verify the HMAC signature
     * synchronously (<50ms) and dispatch the heavy work to an async Job so
     * we respond to Meta within its 5-second window even under load.
     */
    public function receive(Request $request): Response
    {
        $appSecret = (string) config('meta.app_secret');
        $verifyToken = (string) config('meta.webhook_verify_token');

        if ($appSecret === '' || $verifyToken === '') {
            Log::channel('meta-webhooks')->error(
                'IG webhook POST received but META_APP_SECRET or META_WEBHOOK_VERIFY_TOKEN is empty'
            );
            return response('misconfigured', 500);
        }

        $raw = $request->getContent();
        $providedSignature = (string) $request->header('X-Hub-Signature-256', '');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            Log::channel('meta-webhooks')->warning('IG webhook signature mismatch', [
                'ip' => $request->ip(),
                'bytes' => strlen($raw),
            ]);
            return response('invalid signature', 401);
        }

        ProcessMetaIgWebhookEventJob::dispatch($raw);

        return response('', 200);
    }
}
