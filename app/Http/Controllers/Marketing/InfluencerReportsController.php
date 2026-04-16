<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\InfluencerProductStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Influencer;
use App\Models\Dis\InfluencerProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class InfluencerReportsController extends Controller
{
    /**
     * Dashboard me statistika kryesore
     */
    public function dashboard(): View
    {
        $stats = [
            'total_influencers' => Influencer::count(),
            'active_influencers' => Influencer::active()->count(),
            'inactive_influencers' => Influencer::where('is_active', false)->count(),
            
            'total_products' => InfluencerProduct::count(),
            'active_products' => InfluencerProduct::activeOrPartial()->count(),
            'draft_products' => InfluencerProduct::draft()->count(),
            'overdue_products' => InfluencerProduct::overdue()->count(),
            
            'total_value_out' => InfluencerProduct::activeOrPartial()
                ->with('items')
                ->get()
                ->sum(fn($p) => $p->total_value),
        ];

        // Produktet më të fundit
        $recentProducts = InfluencerProduct::with(['influencer', 'branch'])
            ->latest()
            ->limit(10)
            ->get();

        // Produktet e vonuara
        $overdueProducts = InfluencerProduct::overdue()
            ->with(['influencer', 'branch'])
            ->orderBy('expected_return_date')
            ->limit(10)
            ->get();

        // Top influencer-at sipas vlerës
        $topInfluencers = Influencer::active()
            ->withSum('influencerProducts as total_value', 'id')
            ->withCount(['influencerProducts as active_products_count' => function ($q) {
                $q->whereIn('status', ['active', 'partially_returned']);
            }])
            ->orderByDesc('active_products_count')
            ->limit(10)
            ->get();

        return view('influencer-reports.dashboard', compact(
            'stats',
            'recentProducts',
            'overdueProducts',
            'topInfluencers'
        ));
    }

    /**
     * Raporti i produkteve të vonuara
     */
    public function overdueProducts(Request $request): View|JsonResponse
    {
        $query = InfluencerProduct::overdue()
            ->with(['influencer', 'branch', 'items.item']);

        // Filtra
        if ($request->filled('influencer_id')) {
            $query->where('influencer_id', $request->input('influencer_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('source_branch_id', $request->input('branch_id'));
        }

        if ($request->ajax()) {
            $products = $query->orderBy('expected_return_date')->get();
            return response()->json(['products' => $products]);
        }

        $products = $query->orderBy('expected_return_date')
            ->paginate(25);

        $influencers = Influencer::active()->orderBy('name')->get(['id', 'name']);

        return view('influencer-reports.overdue', compact('products', 'influencers'));
    }

    /**
     * Raporti i vlerave sipas influencer-ëve
     */
    public function valueByInfluencer(Request $request): View
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subMonths(6)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $influencers = Influencer::with(['influencerProducts' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->with('items');
        }])
            ->withCount(['influencerProducts as total_products' => function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            }])
            ->having('total_products', '>', 0)
            ->orderByDesc('total_products')
            ->get();

        // Llogarit vlerat
        foreach ($influencers as $influencer) {
            $influencer->total_given_value = $influencer->influencerProducts
                ->sum(fn($p) => $p->items->sum('product_value'));
            
            $influencer->total_returned_value = $influencer->influencerProducts
                ->sum(fn($p) => $p->items->sum(fn($i) => 
                    ($i->quantity_returned / max(1, $i->quantity_given)) * $i->product_value
                ));
            
            $influencer->total_kept_value = $influencer->influencerProducts
                ->sum(fn($p) => $p->items->where('is_kept', true)->sum('product_value'));
        }

        return view('influencer-reports.value-by-influencer', compact(
            'influencers',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Raporti i aktivitetit mujor
     */
    public function monthlyActivity(Request $request): View|JsonResponse
    {
        $year = $request->input('year', Carbon::now()->year);

        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth = $startOfMonth->copy()->endOfMonth();

            $monthlyData[$month] = [
                'month' => $startOfMonth->format('F'),
                'month_short' => $startOfMonth->format('M'),
                'new_products' => InfluencerProduct::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'returned_products' => InfluencerProduct::whereBetween('actual_return_date', [$startOfMonth, $endOfMonth])
                    ->where('status', InfluencerProductStatusEnum::RETURNED)
                    ->count(),
                'converted_products' => InfluencerProduct::whereBetween('updated_at', [$startOfMonth, $endOfMonth])
                    ->where('status', InfluencerProductStatusEnum::CONVERTED)
                    ->count(),
                'total_value' => InfluencerProduct::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->with('items')
                    ->get()
                    ->sum(fn($p) => $p->items->sum('product_value')),
            ];
        }

        if ($request->ajax()) {
            return response()->json(['data' => $monthlyData]);
        }

        // Statistika totale për vitin
        $yearStart = Carbon::create($year, 1, 1)->startOfYear();
        $yearEnd = $yearStart->copy()->endOfYear();

        $yearStats = [
            'total_new' => array_sum(array_column($monthlyData, 'new_products')),
            'total_returned' => array_sum(array_column($monthlyData, 'returned_products')),
            'total_converted' => array_sum(array_column($monthlyData, 'converted_products')),
            'total_value' => array_sum(array_column($monthlyData, 'total_value')),
        ];

        return view('influencer-reports.monthly-activity', compact(
            'monthlyData',
            'year',
            'yearStats'
        ));
    }

    /**
     * API endpoint për chart data
     */
    public function getChartData(Request $request): JsonResponse
    {
        $type = $request->input('type', 'status');

        switch ($type) {
            case 'status':
                $data = [
                    'labels' => ['Draft', 'Aktiv', 'Kthyer Pjesërisht', 'Kthyer', 'Konvertuar', 'Anulluar'],
                    'data' => [
                        InfluencerProduct::draft()->count(),
                        InfluencerProduct::where('status', InfluencerProductStatusEnum::ACTIVE)->count(),
                        InfluencerProduct::where('status', InfluencerProductStatusEnum::PARTIALLY_RETURNED)->count(),
                        InfluencerProduct::where('status', InfluencerProductStatusEnum::RETURNED)->count(),
                        InfluencerProduct::where('status', InfluencerProductStatusEnum::CONVERTED)->count(),
                        InfluencerProduct::where('status', InfluencerProductStatusEnum::CANCELLED)->count(),
                    ],
                    'colors' => ['#F59E0B', '#3B82F6', '#7C3AED', '#10B981', '#4F46E5', '#EF4444'],
                ];
                break;

            case 'platform':
                $platforms = Influencer::selectRaw('platform, count(*) as count')
                    ->groupBy('platform')
                    ->pluck('count', 'platform');
                
                $data = [
                    'labels' => $platforms->keys()->map(fn($p) => ucfirst($p))->values(),
                    'data' => $platforms->values(),
                    'colors' => ['#E4405F', '#000000', '#FF0000', '#6B7280'],
                ];
                break;

            default:
                $data = [];
        }

        return response()->json($data);
    }
}
