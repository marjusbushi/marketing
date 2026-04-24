<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\InfluencerProductStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Dis\InfluencerProduct;
use App\Models\Influencer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Influencer analytics and reports.
 *
 * Aggregations span two databases: marketing-owned `influencers` and
 * DIS-owned `influencer_products`. Cross-connection Eloquent relations
 * (withCount / withSum / whereHas) don't work reliably across separate
 * MySQL schemas, so everything here runs as two targeted queries that
 * are merged in PHP.
 */
class InfluencerReportsController extends Controller
{
    public function dashboard(): View
    {
        $stats = [
            'total_influencers'    => Influencer::count(),
            'active_influencers'   => Influencer::active()->count(),
            'inactive_influencers' => Influencer::where('is_active', false)->count(),

            'total_products'    => InfluencerProduct::count(),
            'active_products'   => InfluencerProduct::activeOrPartial()->count(),
            'draft_products'    => InfluencerProduct::draft()->count(),
            'overdue_products'  => InfluencerProduct::overdue()->count(),

            'total_value_out'   => InfluencerProduct::activeOrPartial()
                ->with('items')
                ->get()
                ->sum(fn ($p) => $p->total_value),
        ];

        $recentProducts = InfluencerProduct::with(['branch'])
            ->with('items')
            ->latest()
            ->limit(10)
            ->get();
        $this->attachInfluencers($recentProducts);

        $overdueProducts = InfluencerProduct::overdue()
            ->with(['branch'])
            ->orderBy('expected_return_date')
            ->limit(10)
            ->get();
        $this->attachInfluencers($overdueProducts);

        // Top influencers: aggregate product activity on DIS side, resolve
        // names from marketing side, merge and rank in PHP.
        $aggregates = InfluencerProduct::query()
            ->whereIn('status', ['active', 'partially_returned'])
            ->selectRaw('influencer_id, COUNT(*) as active_products_count')
            ->groupBy('influencer_id')
            ->orderByDesc('active_products_count')
            ->limit(10)
            ->get()
            ->keyBy('influencer_id');

        $topInfluencers = Influencer::active()
            ->whereIn('id', $aggregates->keys())
            ->get()
            ->map(function (Influencer $i) use ($aggregates) {
                $i->setAttribute('active_products_count', (int) ($aggregates[$i->id]->active_products_count ?? 0));
                return $i;
            })
            ->sortByDesc('active_products_count')
            ->values();

        return view('influencer-reports.dashboard', compact(
            'stats',
            'recentProducts',
            'overdueProducts',
            'topInfluencers',
        ));
    }

    public function overdueProducts(Request $request): View|JsonResponse
    {
        $query = InfluencerProduct::overdue()
            ->with(['branch', 'items.item']);

        if ($request->filled('influencer_id')) {
            $query->where('influencer_id', $request->input('influencer_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('source_branch_id', $request->input('branch_id'));
        }

        if ($request->ajax()) {
            $products = $query->orderBy('expected_return_date')->get();
            $this->attachInfluencers($products);
            return response()->json(['products' => $products]);
        }

        $products = $query->orderBy('expected_return_date')->paginate(25);
        $this->attachInfluencers($products->getCollection());

        $influencers = Influencer::active()->orderBy('name')->get(['id', 'name']);

        return view('influencer-reports.overdue', compact('products', 'influencers'));
    }

    public function valueByInfluencer(Request $request): View
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subMonths(6)->format('Y-m-d'));
        $dateTo   = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        // Pull all products in range + their items (DIS connection) in one query.
        $products = InfluencerProduct::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->with('items')
            ->get();

        if ($products->isEmpty()) {
            $influencers = collect();
            return view('influencer-reports.value-by-influencer', compact(
                'influencers',
                'dateFrom',
                'dateTo',
            ));
        }

        // Group by influencer_id, compute per-influencer totals in PHP.
        $productsByInfluencer = $products->groupBy('influencer_id');

        $influencers = Influencer::whereIn('id', $productsByInfluencer->keys())
            ->get()
            ->map(function (Influencer $i) use ($productsByInfluencer) {
                $influencerProducts = $productsByInfluencer[$i->id] ?? collect();

                $i->setRelation('influencerProducts', $influencerProducts);
                $i->setAttribute('total_products', $influencerProducts->count());

                $i->setAttribute('total_given_value', $influencerProducts
                    ->sum(fn ($p) => $p->items->sum('product_value')));

                $i->setAttribute('total_returned_value', $influencerProducts
                    ->sum(fn ($p) => $p->items->sum(fn ($item) =>
                        ($item->quantity_returned / max(1, $item->quantity_given)) * $item->product_value
                    )));

                $i->setAttribute('total_kept_value', $influencerProducts
                    ->sum(fn ($p) => $p->items->where('is_kept', true)->sum('product_value')));

                return $i;
            })
            ->sortByDesc('total_products')
            ->values();

        return view('influencer-reports.value-by-influencer', compact(
            'influencers',
            'dateFrom',
            'dateTo',
        ));
    }

    public function monthlyActivity(Request $request): View|JsonResponse
    {
        $year = (int) $request->input('year', Carbon::now()->year);

        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth   = $startOfMonth->copy()->endOfMonth();

            $monthlyData[$month] = [
                'month'       => $startOfMonth->format('F'),
                'month_short' => $startOfMonth->format('M'),
                'new_products'       => InfluencerProduct::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'returned_products'  => InfluencerProduct::whereBetween('actual_return_date', [$startOfMonth, $endOfMonth])
                    ->where('status', InfluencerProductStatusEnum::RETURNED)
                    ->count(),
                'converted_products' => InfluencerProduct::whereBetween('updated_at', [$startOfMonth, $endOfMonth])
                    ->where('status', InfluencerProductStatusEnum::CONVERTED)
                    ->count(),
                'total_value' => InfluencerProduct::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->with('items')
                    ->get()
                    ->sum(fn ($p) => $p->items->sum('product_value')),
            ];
        }

        if ($request->ajax()) {
            return response()->json(['data' => $monthlyData]);
        }

        $yearStats = [
            'total_new'       => array_sum(array_column($monthlyData, 'new_products')),
            'total_returned'  => array_sum(array_column($monthlyData, 'returned_products')),
            'total_converted' => array_sum(array_column($monthlyData, 'converted_products')),
            'total_value'     => array_sum(array_column($monthlyData, 'total_value')),
        ];

        return view('influencer-reports.monthly-activity', compact(
            'monthlyData',
            'year',
            'yearStats',
        ));
    }

    public function getChartData(Request $request): JsonResponse
    {
        $type = $request->input('type', 'status');

        $data = match ($type) {
            'status' => [
                'labels' => ['Draft', 'Aktiv', 'Kthyer Pjesërisht', 'Kthyer', 'Konvertuar', 'Anulluar'],
                'data'   => [
                    InfluencerProduct::draft()->count(),
                    InfluencerProduct::where('status', InfluencerProductStatusEnum::ACTIVE)->count(),
                    InfluencerProduct::where('status', InfluencerProductStatusEnum::PARTIALLY_RETURNED)->count(),
                    InfluencerProduct::where('status', InfluencerProductStatusEnum::RETURNED)->count(),
                    InfluencerProduct::where('status', InfluencerProductStatusEnum::CONVERTED)->count(),
                    InfluencerProduct::where('status', InfluencerProductStatusEnum::CANCELLED)->count(),
                ],
                'colors' => ['#F59E0B', '#3B82F6', '#7C3AED', '#10B981', '#4F46E5', '#EF4444'],
            ],
            'platform' => $this->platformChart(),
            default    => [],
        };

        return response()->json($data);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Attach marketing-side Influencer models onto a collection of DIS-side
     * InfluencerProducts. We do a single WHERE-IN query and map by id so
     * blades can access $product->influencer without per-record lookups.
     */
    protected function attachInfluencers(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $ids = $products->pluck('influencer_id')->unique()->values();

        $influencers = Influencer::withTrashed()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $products->each(function (InfluencerProduct $p) use ($influencers) {
            $p->setRelation('influencer', $influencers[$p->influencer_id] ?? null);
        });
    }

    protected function platformChart(): array
    {
        $platforms = Influencer::selectRaw('platform, count(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform');

        return [
            'labels' => $platforms->keys()->map(fn ($p) => ucfirst((string) $p))->values(),
            'data'   => $platforms->values(),
            'colors' => ['#E4405F', '#000000', '#FF0000', '#6B7280'],
        ];
    }
}
