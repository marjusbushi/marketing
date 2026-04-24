<?php

namespace App\Jobs;

use App\Models\Meta\MetaIgDmEvent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a Meta webhook payload asynchronously — handles both Instagram
 * DMs AND Facebook Messenger events (both arrive at the same webhook URL when
 * the app subscribes to multiple products in the App Dashboard).
 *
 * Platform is detected from the top-level `object` field:
 *   - object: "instagram" → platform 'instagram'  (ig_account_id is "self")
 *   - object: "page"      → platform 'messenger'  (page_id        is "self")
 *
 * Meta delivers batches of messaging events. We iterate entry[].messaging[],
 * idempotently upsert one meta_ig_dm_events row per message (unique by mid —
 * despite the table name, it is multi-platform via the `platform` column),
 * and flag is_first_of_thread when an incoming message starts a new
 * conversation — defined as no prior incoming in the last
 * meta.ig_conversation_gap_minutes (default 1440 = 24h).
 *
 * The raw payload is stored (minus message.text and message.attachments for
 * privacy) for debugging; only metadata reaches the database.
 */
class ProcessMetaIgWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(private readonly string $rawBody) {}

    public function handle(): void
    {
        $payload = json_decode($this->rawBody, true);
        if (! is_array($payload)) {
            Log::channel('meta-webhooks')->error('Meta webhook body not valid JSON', [
                'bytes' => strlen($this->rawBody),
            ]);
            return;
        }

        // Instagram events arrive with object:"instagram"; Messenger events
        // with object:"page". Both flow through the same queue + table because
        // the data shape is identical — only the "self" identifier differs.
        $object = strtolower((string) ($payload['object'] ?? 'instagram'));
        $platform = $object === 'page' ? 'messenger' : 'instagram';

        $selfId = $platform === 'messenger'
            ? (string) config('meta.page_id', '')
            : (string) config('meta.ig_account_id', '');

        if ($selfId === '') {
            Log::channel('meta-webhooks')->warning(
                "Self-ID not set for platform {$platform} — is_from_page detection will be incorrect",
                ['platform' => $platform, 'object' => $object]
            );
        }

        $entries = $payload['entry'] ?? [];
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $events = $entry['messaging'] ?? [];
            if (! is_array($events)) {
                continue;
            }

            foreach ($events as $event) {
                try {
                    $this->processEvent($event, $entry, $selfId, $platform);
                } catch (Throwable $e) {
                    Log::channel('meta-webhooks')->error('Meta webhook event processing failed', [
                        'platform' => $platform,
                        'error' => $e->getMessage(),
                        'mid' => data_get($event, 'message.mid'),
                    ]);
                    // Do not rethrow — one bad event shouldn't sink the whole batch.
                    // Meta will redeliver the batch on 5xx, which would duplicate
                    // successful events (idempotency handles it, but retries are
                    // wasteful).
                }
            }
        }
    }

    private function processEvent(array $event, array $entry, string $selfId, string $platform): void
    {
        $senderId = (string) data_get($event, 'sender.id', '');
        $recipientId = (string) data_get($event, 'recipient.id', '');
        $mid = (string) data_get($event, 'message.mid', '');
        $timestampMs = (int) data_get($event, 'timestamp', 0);
        $adId = data_get($event, 'message.referral.ad_id');

        // Ignore non-message events (read receipts, deliveries, reactions).
        // We care only about actual DMs — identified by presence of message.mid.
        if ($mid === '' || $senderId === '' || $recipientId === '' || $timestampMs <= 0) {
            return;
        }

        $isFromPage = ($selfId !== '' && $senderId === $selfId);
        $threadId = $isFromPage ? $recipientId : $senderId;
        $receivedAt = Carbon::createFromTimestampMs($timestampMs);

        // Strip sensitive content before persisting. We only retain metadata.
        $sanitizedEntry = $entry;
        if (isset($sanitizedEntry['messaging']) && is_array($sanitizedEntry['messaging'])) {
            foreach ($sanitizedEntry['messaging'] as &$m) {
                if (isset($m['message'])) {
                    unset($m['message']['text'], $m['message']['attachments']);
                }
            }
            unset($m);
        }

        DB::connection('dis')->transaction(function () use (
            $mid,
            $threadId,
            $senderId,
            $selfId,
            $isFromPage,
            $receivedAt,
            $adId,
            $sanitizedEntry,
            $platform
        ) {
            $row = MetaIgDmEvent::updateOrCreate(
                ['message_id' => $mid],
                [
                    'thread_id' => $threadId,
                    'from_id' => $senderId,
                    'page_id' => $selfId,
                    'is_from_page' => $isFromPage,
                    'received_at' => $receivedAt,
                    'ad_id' => $adId !== null ? (string) $adId : null,
                    'platform' => $platform,
                    'raw_payload' => $sanitizedEntry,
                    'is_first_of_thread' => false,
                ]
            );

            if (! $isFromPage) {
                $gapMinutes = (int) config('meta.ig_conversation_gap_minutes', 1440);
                $cutoff = $receivedAt->copy()->subMinutes($gapMinutes);

                // Gap check is per-platform — same thread_id could exist on
                // IG and Messenger for different users; we must not bleed
                // first-of-thread state across platforms.
                $priorExists = MetaIgDmEvent::where('platform', $platform)
                    ->where('thread_id', $threadId)
                    ->where('is_from_page', false)
                    ->where('received_at', '>=', $cutoff)
                    ->where('received_at', '<', $receivedAt)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if (! $priorExists) {
                    $row->update(['is_first_of_thread' => true]);
                }
            }
        });
    }
}
