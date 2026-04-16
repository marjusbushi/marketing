<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\DisApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
