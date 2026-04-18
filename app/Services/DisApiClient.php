<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for DIS internal API.
 *
 * Handles influencer product write operations that require DIS-side
 * business logic (stock movements, transfer orders, Zoho sync).
 *
 * Reads go directly via Dis models ($connection = 'dis').
 * Writes go through this client → DIS internal API.
 *
 * Config: DIS_API_URL and DIS_INTERNAL_API_KEY env vars.
 */
class DisApiClient
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.dis_api.url', ''), '/');
        $this->apiKey = config('services.dis_api.key', '');

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new RuntimeException(
                'DIS API not configured. Set DIS_API_URL and DIS_INTERNAL_API_KEY in .env'
            );
        }
    }

    // ─── Influencer Product Operations ──────────────────

    /**
     * Create a new influencer product allocation.
     *
     * @param  array  $data  Keys: influencer_id, source_branch_id, source_warehouse_id, agreement_type, expected_return_date, notes
     * @param  array  $items  Each: [item_id, quantity_given, product_value]
     * @param  int    $actingUserId  User performing the action
     * @return array  The created influencer product
     *
     * @throws RuntimeException
     */
    public function createInfluencerProduct(array $data, array $items, int $actingUserId): array
    {
        $response = $this->post('/api/internal/influencer-products', array_merge($data, [
            'items' => $items,
            'acting_user_id' => $actingUserId,
        ]));

        return $this->parseResponse($response, 'influencer_product');
    }

    /**
     * Activate a draft influencer product (triggers stock movement + Zoho sync).
     */
    public function activateInfluencerProduct(int $productId): array
    {
        $response = $this->post("/api/internal/influencer-products/{$productId}/activate");

        return $this->parseResponse($response, 'influencer_product');
    }

    /**
     * Register return of items from an influencer.
     *
     * @param  int    $productId
     * @param  array  $returnItems  Each: [influencer_product_item_id, quantity_returned, return_condition]
     * @param  int|null  $returnWarehouseId
     * @return array
     */
    public function registerReturn(int $productId, array $returnItems, ?int $returnWarehouseId = null): array
    {
        $response = $this->post("/api/internal/influencer-products/{$productId}/return", [
            'return_items' => $returnItems,
            'return_warehouse_id' => $returnWarehouseId,
        ]);

        return $this->parseResponse($response, 'influencer_product');
    }

    /**
     * Convert items to expense (influencer keeps them).
     *
     * @param  int    $productId
     * @param  array  $keptItems  Each: [influencer_product_item_id, product_value]
     * @return array
     */
    public function convertToExpense(int $productId, array $keptItems): array
    {
        $response = $this->post("/api/internal/influencer-products/{$productId}/convert", [
            'kept_items' => $keptItems,
        ]);

        return $this->parseResponse($response, 'influencer_product');
    }

    /**
     * Cancel an influencer product allocation.
     */
    public function cancelInfluencerProduct(int $productId): array
    {
        $response = $this->post("/api/internal/influencer-products/{$productId}/cancel");

        return $this->parseResponse($response, 'influencer_product');
    }

    /**
     * Extend the expected return date.
     */
    public function extendDeadline(int $productId, string $newDate): array
    {
        $response = $this->post("/api/internal/influencer-products/{$productId}/extend", [
            'expected_return_date' => $newDate,
        ]);

        return $this->parseResponse($response, 'influencer_product');
    }

    // ─── Merch Calendar Operations ────────────────────

    /**
     * List distribution weeks for a date range.
     */
    public function listWeeks(string $start, string $end): array
    {
        $response = $this->get('/api/internal/merch-calendar/weeks', [
            'start' => $start,
            'end' => $end,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * List lightweight week summaries for timeline/gantt views.
     */
    public function listWeekSummaries(string $start, string $end): array
    {
        $response = $this->get('/api/internal/merch-calendar/weeks-summary', [
            'start' => $start,
            'end' => $end,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Get full detail of a distribution week (groups, images, classifications).
     */
    public function getWeek(int $id): array
    {
        $response = $this->get("/api/internal/merch-calendar/weeks/{$id}");

        return $this->parseResponse($response);
    }

    /**
     * Create a new distribution week.
     */
    public function createWeek(array $data): array
    {
        $response = $this->post('/api/internal/merch-calendar/weeks', $data);

        return $this->parseResponse($response);
    }

    /**
     * Update a distribution week.
     */
    public function updateWeek(int $id, array $data): array
    {
        $response = $this->put("/api/internal/merch-calendar/weeks/{$id}", $data);

        return $this->parseResponse($response);
    }

    /**
     * Delete a distribution week.
     */
    public function deleteWeek(int $id): array
    {
        $response = $this->delete("/api/internal/merch-calendar/weeks/{$id}");

        return $this->parseResponse($response);
    }

    /**
     * Update only the status of a distribution week.
     */
    public function updateWeekStatus(int $id, string $status): array
    {
        $response = $this->post("/api/internal/merch-calendar/weeks/{$id}/status", [
            'status' => $status,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Assign a single group → date for a distribution week.
     *
     * Used by the inline date picker in the merch-calendar collection sidebar.
     * Use bulkAssignDates() for the Quick Scan workflow instead — it is one
     * round-trip per call instead of N.
     */
    public function assignGroupDate(int $weekId, int $groupId, string $date, bool $isPrimary = true): array
    {
        $response = $this->post(
            "/api/internal/merch-calendar/weeks/{$weekId}/groups/{$groupId}/dates",
            ['date' => $date, 'is_primary' => $isPrimary],
        );

        return $this->parseResponse($response);
    }

    /**
     * Remove a single group → date assignment.
     */
    public function removeGroupDate(int $weekId, int $groupId, int $dateId): array
    {
        $response = $this->delete(
            "/api/internal/merch-calendar/weeks/{$weekId}/groups/{$groupId}/dates/{$dateId}"
        );

        return $this->parseResponse($response);
    }

    /**
     * Lookup an item by barcode (SKU, item_group code, ose partial match).
     *
     * Kthen full meta per Quick Scan UI: item_group_id, name, image, cmim, etc.
     * `weekId` opsional — kur pasohet, pergjigja perfshin classification per ate week.
     */
    public function lookupItemByBarcode(string $barcode, ?int $weekId = null): array
    {
        $params = ['barcode' => $barcode];
        if ($weekId) {
            $params['week_id'] = $weekId;
        }

        $response = $this->get('/api/internal/merch-calendar/items/by-barcode', $params);

        return $this->parseResponse($response);
    }

    /**
     * Quick Scan bulk save — ruan te gjitha grupet e skanuar per nje dite ne nje thirrje atomic.
     *
     * Logjika ne DIS: grupet jashte kolekcionit shtohen automatikisht dhe shenjohen
     * is_primary=false (re-marketing). Grupet ne kolekcion shenjohen is_primary=true.
     * Caktime ekzistuese per (week, group, date) kapercehen pa error.
     */
    public function quickScanSave(int $weekId, string $date, array $itemGroupIds): array
    {
        $response = $this->post(
            "/api/internal/merch-calendar/weeks/{$weekId}/quick-scan",
            [
                'date' => $date,
                'item_group_ids' => array_values($itemGroupIds),
            ],
        );

        return $this->parseResponse($response);
    }

    /**
     * Search item groups (for autocomplete in week edit).
     */
    public function searchItemGroups(string $query = ''): array
    {
        $response = $this->get('/api/internal/merch-calendar/item-groups/search', [
            'q' => $query,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * List active price lists.
     */
    public function listPriceLists(): array
    {
        $response = $this->get('/api/internal/merch-calendar/price-lists');

        return $this->parseResponse($response);
    }

    // ─── HTTP Helpers ───────────────────────────────────

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-Internal-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(30);
    }

    protected function get(string $path, array $query = []): Response
    {
        return $this->client()->get($path, $query);
    }

    protected function post(string $path, array $data = []): Response
    {
        return $this->client()->post($path, $data);
    }

    protected function put(string $path, array $data = []): Response
    {
        return $this->client()->put($path, $data);
    }

    protected function delete(string $path): Response
    {
        return $this->client()->delete($path);
    }

    /**
     * Parse response and throw on failure.
     *
     * @throws RuntimeException
     */
    protected function parseResponse(Response $response, ?string $dataKey = null): array
    {
        if ($response->failed()) {
            $body = $response->json();
            $message = $body['message'] ?? 'DIS API request failed';

            throw new RuntimeException("DIS API error: {$message}", $response->status());
        }

        $body = $response->json();

        if (isset($body['success']) && $body['success'] === false) {
            throw new RuntimeException($body['message'] ?? 'DIS operation failed');
        }

        if ($dataKey && isset($body[$dataKey])) {
            return $body[$dataKey];
        }

        return $body;
    }
}
