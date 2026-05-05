<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\DisApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MerchCalendarController extends Controller
{
    public function __construct(
        private DisApiClient $disApi,
    ) {}

    // ─── Page Views ──────────────────────────────

    public function calendar(): View
    {
        return view('merch-calendar.calendar');
    }

    public function timeline(): View
    {
        return view('merch-calendar.timeline');
    }

    public function gantt(): View
    {
        return view('merch-calendar.gantt');
    }

    public function quickScan(): View
    {
        return view('merch-calendar.quick-scan');
    }

    /**
     * Dedicated full-page view of a collection (replaces the sidebar).
     * Fetches week detail server-side so the page renders without a
     * subsequent API round-trip.
     */
    public function collectionDetail(int $id): View
    {
        try {
            // Use the enriched variant so item_groups whose stock/variants
            // are missing from /weeks/{id} get back-filled via searchItemGroups
            // (the same source DIS UI uses on /management/items/items-grouped).
            $collection = $this->disApi->getWeekEnriched($id);
        } catch (\RuntimeException $e) {
            abort($e->getCode() === 404 ? 404 : 502, $e->getMessage());
        }

        return view('merch-calendar.collection-detail', [
            'collection' => $collection,
        ]);
    }

    // ─── API Proxies (JSON) ──────────────────────

    /**
     * List weeks for FullCalendar event source.
     */
    public function weeksJson(Request $request): JsonResponse
    {
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->addMonths(2)->toDateString());

        $weeks = $this->disApi->listWeeks($start, $end);

        // Transform to FullCalendar event format
        $events = [];
        foreach ($weeks as $week) {
            $endPlusOne = date('Y-m-d', strtotime($week['week_end'] . ' +1 day'));

            // Background bar
            $events[] = [
                'id' => 'bg_' . $week['id'],
                'title' => '',
                'start' => $week['week_start'],
                'end' => $endPlusOne,
                'display' => 'background',
                'color' => '#EEF2FF',
                'extendedProps' => [
                    'type' => 'collection_bg',
                    'distribution_week_id' => $week['id'],
                ],
            ];

            // Label bar
            $events[] = [
                'id' => 'label_' . $week['id'],
                'title' => $week['name'] . ' (' . ($week['item_groups_count'] ?? 0) . ')',
                'start' => $week['week_start'],
                'end' => $endPlusOne,
                'display' => 'block',
                'color' => '#4F46E5',
                'textColor' => '#FFFFFF',
                'backgroundColor' => '#4F46E5',
                'eventOrder' => 0,
                'extendedProps' => [
                    'type' => 'collection',
                    'distribution_week_id' => $week['id'],
                    'status' => $week['status'],
                    'notes' => $week['notes'],
                    'item_group_count' => $week['item_groups_count'] ?? 0,
                    'week_start' => $week['week_start'],
                    'week_end' => $week['week_end'],
                    'cover_image_url' => $week['cover_image_url'],
                ],
            ];
        }

        return response()->json($events);
    }

    /**
     * Get detail of a single week (for sidebar).
     */
    public function weekDetail(int $id): JsonResponse
    {
        $week = $this->disApi->getWeek($id);

        return response()->json($week);
    }

    /**
     * Get lightweight summaries for timeline/gantt.
     */
    public function weeksSummaryJson(Request $request): JsonResponse
    {
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->addMonths(2)->toDateString());

        $weeks = $this->disApi->listWeekSummaries($start, $end);

        return response()->json($weeks);
    }

    /**
     * Create a new distribution week.
     */
    public function storeWeek(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'week_start' => 'required|date',
            'week_end' => 'required|date|after_or_equal:week_start',
            'status' => 'in:planned,active,completed',
            'notes' => 'nullable|string',
            'item_group_ids' => 'array',
            'price_list_id' => 'nullable|integer',
        ]);

        $result = $this->disApi->createWeek($data);

        return response()->json($result, 201);
    }

    /**
     * Update a distribution week.
     */
    public function updateWeek(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'week_start' => 'sometimes|date',
            'week_end' => 'sometimes|date',
            'status' => 'sometimes|in:planned,active,completed',
            'notes' => 'nullable|string',
            'item_group_ids' => 'sometimes|array',
            'price_list_id' => 'nullable|integer',
        ]);

        $result = $this->disApi->updateWeek($id, $data);

        return response()->json($result);
    }

    /**
     * Delete a distribution week.
     */
    public function deleteWeek(int $id): JsonResponse
    {
        $result = $this->disApi->deleteWeek($id);

        return response()->json($result);
    }

    /**
     * Update status only.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:planned,active,completed',
        ]);

        $result = $this->disApi->updateWeekStatus($id, $validated['status']);

        return response()->json($result);
    }

    /**
     * Cakto nje produkt te kolekcionit per nje dite specifike.
     * Proxy ne DIS internal API — ruan ne pivot-in distribution_week_item_group_dates.
     */
    public function assignGroupDate(Request $request, int $weekId, int $groupId): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'is_primary' => 'sometimes|boolean',
        ]);

        $result = $this->disApi->assignGroupDate(
            $weekId,
            $groupId,
            $validated['date'],
            $validated['is_primary'] ?? true,
        );

        $this->invalidateBasketCache($weekId);

        return response()->json($result);
    }

    /**
     * Hiq caktimin e nje produkti per nje dite specifike.
     */
    public function removeGroupDate(int $weekId, int $groupId, int $dateId): JsonResponse
    {
        $result = $this->disApi->removeGroupDate($weekId, $groupId, $dateId);

        $this->invalidateBasketCache($weekId);

        return response()->json($result);
    }

    /**
     * Drop the Daily Basket cache entries that depend on this week so the
     * next /coverage request reads the freshly-saved DIS state instead of
     * a stale snapshot. Without this invalidation, products assigned via
     * Merch Calendar would not appear in Shporta Ditore for up to 60s.
     */
    private function invalidateBasketCache(int $weekId): void
    {
        Cache::forget('daily_basket:collection_products:'.$weekId);
    }

    /**
     * Lookup nje produkt nga barcode (per Quick Scan input).
     */
    public function lookupBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:128',
            'week_id' => 'sometimes|integer',
        ]);

        $result = $this->disApi->lookupItemByBarcode(
            $validated['barcode'],
            $validated['week_id'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Quick Scan bulk save — proxy ne DIS qe ruan te gjitha skanimet ne nje thirrje atomic.
     */
    public function quickScanSave(Request $request, int $weekId): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'item_group_ids' => 'required|array|min:1',
            'item_group_ids.*' => 'integer',
        ]);

        $result = $this->disApi->quickScanSave(
            $weekId,
            $validated['date'],
            $validated['item_group_ids'],
        );

        return response()->json($result);
    }

    /**
     * Search item groups (autocomplete proxy).
     */
    public function searchGroups(Request $request): JsonResponse
    {
        $groups = $this->disApi->searchItemGroups($request->input('q', ''));

        return response()->json($groups);
    }

    /**
     * List price lists for dropdown.
     */
    public function priceLists(): JsonResponse
    {
        $lists = $this->disApi->listPriceLists();

        return response()->json($lists);
    }
}
