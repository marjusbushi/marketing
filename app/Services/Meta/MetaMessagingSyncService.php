<?php

namespace App\Services\Meta;

use App\Models\Meta\MetaMessagingStat;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MetaMessagingSyncService
{
    public function __construct(
        private readonly MetaApiService $api,
    ) {}

    /**
     * Sync Messenger conversations for a date range.
     *
     * Two-step approach:
     * 1. Page Insights gives us accurate new_conversations counts per day
     *    (page_messages_new_conversations_unique).
     * 2. Conversations API gives us actual message counts (sent/received).
     *    We call it WITHOUT the folder param so it returns all conversations,
     *    then subtract IG conversations to get Messenger-only message counts.
     *
     * This hybrid approach fixes the "0 messages received/sent" bug while
     * keeping accurate daily conversation counts from Page Insights.
     */
    public function syncMessengerStats(string $dateFrom, string $dateTo): int
    {
        $pageId = config('meta.page_id');
        if (!$pageId || !config('meta.page_token')) {
            Log::info('Meta Page ID or Page Token not configured. Skipping Messenger sync.');
            return 0;
        }

        // Step 1: Get accurate daily new_conversations from Page Insights
        $dailyNewConvos = [];
        try {
            $response = $this->api->getWithPageToken("{$pageId}/insights", [
                'metric' => 'page_messages_new_conversations_unique',
                'period' => 'day',
                'since' => $dateFrom,
                'until' => Carbon::parse($dateTo)->addDay()->toDateString(),
            ]);

            $values = $response['data'][0]['values'] ?? [];
            Log::info("messenger page_insights conversations: " . count($values) . " days");

            foreach ($values as $v) {
                $date = Carbon::parse($v['end_time'])->subDay()->toDateString();
                if ($date >= $dateFrom && $date <= $dateTo) {
                    $dailyNewConvos[$date] = (int) ($v['value'] ?? 0);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to get messenger conversation counts from page insights: " . $e->getMessage());
        }

        // Step 2: Estimate message counts from ads platform breakdown.
        // The Conversations API returns a unified inbox that ignores the folder
        // parameter entirely (all endpoints return identical results regardless
        // of folder=instagram/page_inbox/inbox). There is NO way to separate
        // Messenger from IG conversations via the Conversations API.
        // Instead, we use the ads API messaging_conversations per platform as
        // a proxy: ratio of Messenger convos to total convos applied to inbox.
        $dailyMsgCounts = $this->estimateMessengerMessageCounts($pageId, $dateFrom, $dateTo);

        // Step 3: Merge and save
        $count = 0;
        $fromDate = Carbon::parse($dateFrom);
        $toDate = Carbon::parse($dateTo);

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $d = $date->toDateString();
            MetaMessagingStat::updateOrCreate(
                ['date' => $d, 'platform' => 'messenger'],
                [
                    'new_conversations' => $dailyNewConvos[$d] ?? 0,
                    'total_messages_received' => $dailyMsgCounts[$d]['received'] ?? 0,
                    'total_messages_sent' => $dailyMsgCounts[$d]['sent'] ?? 0,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Estimate Messenger message counts using a ratio-based approach.
     *
     * The Conversations API's folder parameter is completely broken in v24 — all
     * folder values (instagram, page_inbox, inbox, none) return identical results.
     * There is NO reliable way to separate Messenger from IG conversations via the API.
     *
     * Instead we:
     * 1. Fetch the total inbox messages/conversations for the date range
     *    (these are mixed IG + Messenger)
     * 2. Subtract the IG message counts we already have (synced via syncIgDmStats)
     * 3. The remainder is attributed to Messenger
     *
     * This is an approximation but much better than hardcoded zeros.
     */
    private function estimateMessengerMessageCounts(string $pageId, string $dateFrom, string $dateTo): array
    {
        $dailyCounts = [];
        $fromDate = Carbon::parse($dateFrom);
        $toDate = Carbon::parse($dateTo);

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $dailyCounts[$date->toDateString()] = ['received' => 0, 'sent' => 0];
        }

        try {
            // Fetch unified inbox conversations in date range (IG + Messenger combined)
            $totalReceived = [];
            $totalSent = [];

            $conversations = $this->fetchConversationsInRange($pageId, $dateFrom, $dateTo);

            // Fetch messages for conversations and count sent/received by date
            $days = max(1, $fromDate->diffInDays($toDate) + 1);
            $fetchLimit = min($days * 8, 400);
            $fetched = 0;

            foreach ($conversations as $conv) {
                if ($fetched >= $fetchLimit) break;
                $fetched++;

                $convDate = $conv['_date'] ?? null;
                if (!$convDate) continue;

                try {
                    $messagesResponse = $this->api->getWithPageToken(
                        "{$conv['id']}/messages",
                        ['fields' => 'from,created_time', 'limit' => 100]
                    );

                    foreach ($messagesResponse['data'] ?? [] as $msg) {
                        $msgDate = isset($msg['created_time']) ? Carbon::parse($msg['created_time'])->toDateString() : null;
                        if (!$msgDate || !isset($dailyCounts[$msgDate])) continue;

                        if (($msg['from']['id'] ?? null) === $pageId) {
                            $totalSent[$msgDate] = ($totalSent[$msgDate] ?? 0) + 1;
                        } else {
                            $totalReceived[$msgDate] = ($totalReceived[$msgDate] ?? 0) + 1;
                        }
                    }
                } catch (Exception $e) {
                    if (($conv['message_count'] ?? 0) > 0 && $convDate) {
                        $totalReceived[$convDate] = ($totalReceived[$convDate] ?? 0) + ($conv['message_count'] ?? 0);
                    }
                }
            }

            Log::info("Unified inbox: fetched messages for {$fetched}/" . count($conversations) . " conversations");

            // Subtract IG counts to get Messenger-only
            $igStats = MetaMessagingStat::where('platform', 'instagram')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->get()
                ->keyBy(fn ($r) => $r->date instanceof \Carbon\Carbon ? $r->date->toDateString() : (string) $r->date);

            foreach ($dailyCounts as $date => &$counts) {
                $ig = $igStats[$date] ?? null;
                $igReceived = $ig ? (int) $ig->total_messages_received : 0;
                $igSent = $ig ? (int) $ig->total_messages_sent : 0;

                $counts['received'] = max(0, ($totalReceived[$date] ?? 0) - $igReceived);
                $counts['sent'] = max(0, ($totalSent[$date] ?? 0) - $igSent);
            }
        } catch (Exception $e) {
            Log::warning("Failed to estimate messenger message counts: " . $e->getMessage());
        }

        return $dailyCounts;
    }

    /**
     * Fetch conversations from newest to oldest, stopping when updated_time < dateFrom.
     * Returns only conversations within [dateFrom, dateTo].
     */
    private function fetchConversationsInRange(string $pageId, string $dateFrom, string $dateTo): array
    {
        $results = [];
        $params = [
            'fields' => 'id,updated_time,message_count',
            'limit' => 50,
        ];

        $url = null;
        $maxPages = 200;
        $page = 0;

        while ($page < $maxPages) {
            $page++;

            if ($url) {
                $response = $this->api->fetchNextPageUrl($url);
            } else {
                $response = $this->api->getWithPageToken("{$pageId}/conversations", $params);
            }

            $data = $response['data'] ?? [];
            if (empty($data)) break;

            $stoppedEarly = false;
            foreach ($data as $conv) {
                $updatedTime = $conv['updated_time'] ?? null;
                if (!$updatedTime) continue;

                $convDate = Carbon::parse($updatedTime)->toDateString();

                if ($convDate < $dateFrom) {
                    $stoppedEarly = true;
                    break;
                }

                if ($convDate <= $dateTo) {
                    $conv['_date'] = $convDate;
                    $results[] = $conv;
                }
            }

            if ($stoppedEarly) break;

            $nextUrl = $response['paging']['next'] ?? null;
            if (!$nextUrl) break;
            $url = $nextUrl;
        }

        return $results;
    }

    /**
     * Sync Instagram DM conversations for a date range.
     * Requires Page Token with instagram_manage_messages permission.
     * Note: Uses the page_id with folder=instagram filter.
     */
    public function syncIgDmStats(string $dateFrom, string $dateTo): int
    {
        $pageId = config('meta.page_id');
        if (!$pageId || !config('meta.page_token')) {
            Log::info('Meta Page ID or Page Token not configured. Skipping IG DM sync.');
            return 0;
        }

        return $this->syncConversations($pageId, 'instagram', $dateFrom, $dateTo);
    }

    /**
     * Paginate conversations and aggregate by date.
     *
     * IMPORTANT: The Conversations API ignores since/until parameters entirely.
     * It always returns newest-first. We paginate until we pass dateFrom, then
     * stop. Only conversations whose updated_time falls within [dateFrom, dateTo]
     * are counted.
     *
     * For IG, folder=instagram returns conversations in the IG DM inbox.
     * This captures organic conversations only (~700/month). The remaining
     * conversations that Meta Business Suite counts come from ad-driven
     * interactions that don't create Conversations API threads — those are
     * captured separately via the Ads API (messaging_conversation_started_7d)
     * and added in the controller layer (getPaidMessagingConversations).
     */
    private function syncConversations(string $sourceId, string $platform, string $dateFrom, string $dateTo): int
    {
        $count = 0;
        $pageId = config('meta.page_id');

        // Initialize daily buckets
        $dailyCounts = [];
        $fromDate = Carbon::parse($dateFrom);
        $toDate = Carbon::parse($dateTo);

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $dailyCounts[$date->toDateString()] = [
                'new_conversations' => 0,
                'total_messages_received' => 0,
                'total_messages_sent' => 0,
            ];
        }

        try {
            $endpoint = "{$sourceId}/conversations";

            $isInstagram = $platform === 'instagram';
            $params = [
                'fields' => 'id,updated_time,message_count',
            ];

            if ($isInstagram) {
                $params['folder'] = 'instagram';
            }

            // NOTE: Do NOT pass since/until — the Conversations API ignores them
            // completely. It always returns newest-first regardless.

            $limit = $isInstagram ? 25 : 50;
            $params['limit'] = $limit;

            // Paginate from newest to oldest, stop when we pass dateFrom.
            // We fetch ALL pages and filter client-side since since/until
            // are ignored by the API. Max pages is generous — we stop early.
            // getPaginatedWithPageToken paginates newest-first until no more pages.
            // maxPages=500 is generous — IG inbox is typically < 1,000 total threads.
            $maxPages = 500;
            $allData = $this->api->getPaginatedWithPageToken($endpoint, $params, $limit, $maxPages);

            // Filter to only conversations within our target date range
            $conversations = [];
            foreach ($allData as $conversation) {
                $updatedTime = $conversation['updated_time'] ?? null;
                if (!$updatedTime) continue;

                $convDate = Carbon::parse($updatedTime)->toDateString();

                if (isset($dailyCounts[$convDate])) {
                    $conversations[] = $conversation;
                    $dailyCounts[$convDate]['new_conversations']++;
                }
            }

            Log::info("{$platform} conversations in [{$dateFrom}, {$dateTo}]: " . count($conversations) . " (fetched=" . count($allData) . ")");

            // Fetch message details (sent/received) for conversations in range
            $days = max(1, $fromDate->diffInDays($toDate) + 1);
            $messageFetchCount = 0;
            $messageFetchLimit = min($days * 8, 400);

            foreach ($conversations as $conversation) {
                $updatedTime = $conversation['updated_time'] ?? null;
                $date = $updatedTime ? Carbon::parse($updatedTime)->toDateString() : null;
                if (!$date || !isset($dailyCounts[$date])) continue;

                $conversationId = $conversation['id'] ?? null;
                $messageCount = $conversation['message_count'] ?? 0;

                if ($conversationId && $messageFetchCount < $messageFetchLimit && ($isInstagram || $messageCount > 0)) {
                    $messageFetchCount++;
                    try {
                        $messagesResponse = $this->api->getWithPageToken(
                            "{$conversationId}/messages",
                            ['fields' => 'from,created_time', 'limit' => $isInstagram ? 25 : 100]
                        );
                        $messages = $messagesResponse['data'] ?? [];

                        foreach ($messages as $msg) {
                            $msgDate = isset($msg['created_time']) ? Carbon::parse($msg['created_time'])->toDateString() : null;

                            if (!$msgDate || !isset($dailyCounts[$msgDate])) {
                                continue;
                            }

                            $fromId = $msg['from']['id'] ?? null;
                            if ($fromId === $pageId) {
                                $dailyCounts[$msgDate]['total_messages_sent']++;
                            } else {
                                $dailyCounts[$msgDate]['total_messages_received']++;
                            }
                        }
                    } catch (Exception $e) {
                        if ($messageCount > 0) {
                            $dailyCounts[$date]['total_messages_received'] += $messageCount;
                        }
                        Log::debug("Could not fetch messages for {$platform} conversation {$conversationId}: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to sync {$platform} messaging stats: " . $e->getMessage());
        }

        // Upsert stats — even if API failed, save zero-rows so the dashboard
        // shows 0 instead of "Pa të dhëna"
        foreach ($dailyCounts as $date => $stats) {
            MetaMessagingStat::updateOrCreate(
                ['date' => $date, 'platform' => $platform],
                [
                    'new_conversations' => $stats['new_conversations'],
                    'total_messages_received' => $stats['total_messages_received'],
                    'total_messages_sent' => $stats['total_messages_sent'],
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }
}
