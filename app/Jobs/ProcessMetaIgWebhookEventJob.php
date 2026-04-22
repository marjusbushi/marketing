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
 * Processes a Meta IG webhook payload asynchronously.
 *
 * Meta delivers batches of messaging events. We iterate entry[].messaging[],
 * idempotently upsert one meta_ig_dm_events row per message (unique by mid),
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
            Log::channel('meta-webhooks')->error('IG webhook body not valid JSON', [
                'bytes' => strlen($this->rawBody),
            ]);
            return;
        }

        $pageId = (string) config('meta.ig_account_id', '');
        if ($pageId === '') {
            Log::channel('meta-webhooks')->warning(
                'META_IG_ACCOUNT_ID not set — is_from_page detection will be incorrect'
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
                    $this->processEvent($event, $entry, $pageId);
                } catch (Throwable $e) {
                    Log::channel('meta-webhooks')->error('IG webhook event processing failed', [
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

    private function processEvent(array $event, array $entry, string $pageId): void
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

        $isFromPage = ($pageId !== '' && $senderId === $pageId);
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
            $pageId,
            $isFromPage,
            $receivedAt,
            $adId,
            $sanitizedEntry
        ) {
            $row = MetaIgDmEvent::updateOrCreate(
                ['message_id' => $mid],
                [
                    'thread_id' => $threadId,
                    'from_id' => $senderId,
                    'page_id' => $pageId,
                    'is_from_page' => $isFromPage,
                    'received_at' => $receivedAt,
                    'ad_id' => $adId !== null ? (string) $adId : null,
                    'platform' => 'instagram',
                    'raw_payload' => $sanitizedEntry,
                    'is_first_of_thread' => false,
                ]
            );

            if (! $isFromPage) {
                $gapMinutes = (int) config('meta.ig_conversation_gap_minutes', 1440);
                $cutoff = $receivedAt->copy()->subMinutes($gapMinutes);

                $priorExists = MetaIgDmEvent::where('thread_id', $threadId)
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
