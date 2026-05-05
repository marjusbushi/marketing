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
        $this->baseUrl = rtrim((string) config('services.dis_api.url', ''), '/');
        $this->apiKey = (string) config('services.dis_api.key', '');
    }

    /**
     * Lazy config check — runs on first call instead of constructor so the
     * service container can still resolve the client for dependency injection
     * (e.g. controller constructors) even when DIS env vars are unset.
     */
    protected function ensureConfigured(): void
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
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

    // ─── Influencer Profile Operations ──────────────────

    /**
     * List influencers with optional filters. Returns the full response
     * body so callers get data + meta (pagination) in one shape.
     */
    public function listInfluencers(array $filters = []): array
    {
        $response = $this->get('/api/internal/influencers', $filters);

        return $this->parseResponse($response);
    }

    /**
     * Select2-friendly search.
     */
    public function searchInfluencers(string $q): array
    {
        $response = $this->get('/api/internal/influencers/search', ['q' => $q]);

        return $this->parseResponse($response);
    }

    /**
     * Fetch a single influencer profile.
     */
    public function getInfluencer(int $id): array
    {
        $response = $this->get("/api/internal/influencers/{$id}");

        return $this->parseResponse($response, 'influencer');
    }

    /**
     * Create a new influencer (DIS writes the row, marketing sees it
     * immediately via the dis connection).
     */
    public function createInfluencer(array $data, int $actingUserId): array
    {
        $response = $this->post('/api/internal/influencers', array_merge($data, [
            'acting_user_id' => $actingUserId,
        ]));

        return $this->parseResponse($response, 'influencer');
    }

    /**
     * Update an influencer's profile.
     */
    public function updateInfluencer(int $id, array $data): array
    {
        $response = $this->put("/api/internal/influencers/{$id}", $data);

        return $this->parseResponse($response, 'influencer');
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
     * Get a distribution week and back-fill any item_groups that arrive
     * with missing stock / variants / price by re-fetching them through
     * `searchItemGroups`, which the DIS UI uses for `/management/items/items-grouped`
     * and tends to carry the richer shape. Falls back silently to whatever
     * getWeek returned if the secondary endpoint fails.
     *
     * Why this matters: in production we observe item_groups that show
     * `0 var · 0 stk · 0 L` in marketing while DIS UI shows real numbers —
     * the merch-calendar/weeks endpoint omits those columns for items
     * with certain inventory statuses ("Distributed", etc.). This method
     * patches around that without waiting on a DIS-side API change.
     */
    public function getWeekEnriched(int $id): array
    {
        $week = $this->getWeek($id);
        $groups = $week['item_groups'] ?? [];
        if (empty($groups)) {
            return $week;
        }

        $needsEnrichment = function (array $g): bool {
            $stock = self::pickNum($g, ['total_stock', 'available_stock', 'stock_total', 'available_qty', 'stock', 'quantity', 'total_qty']);
            $variants = self::pickNum($g, ['variations_count', 'variants_count', 'variation_count', 'variants', 'items_count']);
            $base = self::pickPositive($g, ['rate', 'avg_price', 'price', 'unit_price', 'pricelist_price']);

            return ($stock <= 0 && $variants <= 0) || $base <= 0;
        };

        $enriched = [];
        foreach ($groups as $g) {
            if (! $needsEnrichment($g)) {
                $enriched[] = $g;
                continue;
            }

            $code = (string) ($g['code'] ?? '');
            $id = (string) ($g['id'] ?? '');
            $needle = $code !== '' ? $code : $id;
            if ($needle === '') {
                $enriched[] = $g;
                continue;
            }

            try {
                $matches = $this->searchItemGroups($needle);
            } catch (\Throwable $e) {
                report($e);
                $enriched[] = $g;
                continue;
            }

            $match = null;
            foreach ($matches as $m) {
                if ((string) ($m['id'] ?? '') === $id || (string) ($m['code'] ?? '') === $code) {
                    $match = $m;
                    break;
                }
            }

            if ($match === null) {
                $enriched[] = $g;
                continue;
            }

            // Fields from $match fill in missing/zero values in $g.
            // Existing non-empty fields in $g win — preserves whatever
            // getWeek already produced (assigned_dates especially).
            foreach ($match as $key => $value) {
                if (! array_key_exists($key, $g) || $g[$key] === null || $g[$key] === '' || $g[$key] === 0 || $g[$key] === '0') {
                    $g[$key] = $value;
                }
            }
            $enriched[] = $g;
        }

        $week['item_groups'] = $enriched;

        return $week;
    }

    /**
     * Helpers used by getWeekEnriched. Numeric pick across keys.
     */
    private static function pickNum(array $row, array $keys): float
    {
        foreach ($keys as $k) {
            if (! array_key_exists($k, $row)) continue;
            $v = $row[$k];
            if (is_numeric($v)) return (float) $v;
        }
        return 0.0;
    }

    private static function pickPositive(array $row, array $keys): float
    {
        foreach ($keys as $k) {
            if (! array_key_exists($k, $row)) continue;
            $v = $row[$k];
            if (is_numeric($v) && (float) $v > 0) return (float) $v;
        }
        return 0.0;
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
        $this->ensureConfigured();

        $headers = [
            'X-Internal-Api-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];

        // Pass acting user so DIS can log who triggered the call in its
        // activity log (used by read-only endpoints that don't carry
        // acting_user_id in the body).
        $userId = auth()->id();
        if ($userId) {
            $headers['X-Acting-User-Id'] = (string) $userId;
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
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
