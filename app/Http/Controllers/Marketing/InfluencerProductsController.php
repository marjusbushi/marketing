<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Dis\DisBranch;
use App\Models\Dis\DisInfluencer;
use App\Models\Dis\DisItem;
use App\Models\Dis\DisWarehouse;
use App\Models\Dis\InfluencerProduct;
use App\Services\DisApiClient;
use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Lists, shows, and mutates influencer product assignments.
 *
 * Reads go directly against the 'dis' connection via Dis\InfluencerProduct.
 * Writes route through DisApiClient → DIS internal HTTP API so that stock
 * movements, transfer orders, and Zoho sync stay in DIS.
 */
class InfluencerProductsController extends Controller
{
    public function __construct(
        protected DisApiClient $service,
    ) {}

    // =========================================================================
    // Listings
    // =========================================================================

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->dataTable($request);
        }

        $branches = DisBranch::where('is_branch_active', true)
            ->whereNotIn('name', ['Tailor X', 'Craft X'])
            ->get(['id', 'name']);

        return view('influencer-products.index', compact('branches'));
    }

    protected function dataTable(Request $request): JsonResponse
    {
        $query = InfluencerProduct::query()
            ->with([
                'influencer:id,name,handle,platform',
                'branch:id,name',
                'createdBy:id,full_name',
                'items.item:id,name,sku',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('source_branch_id', $request->input('branch_id'));
        }

        if ($request->filled('influencer_id')) {
            $query->where('influencer_id', $request->input('influencer_id'));
        }

        if ($request->filled('agreement_type')) {
            $query->where('agreement_type', $request->input('agreement_type'));
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // The influencer belongsTo lives across connections (DIS product →
        // marketing influencer). DataTables will still render it through the
        // accessor, but name/handle join-filtering needs to happen in PHP.
        // Fetch the page, then resolve marketing-side influencers in one
        // batch and attach.
        $influencerIds = (clone $query)->pluck('influencer_id')->unique()->values();
        $influencers = DisInfluencer::withTrashed()
            ->whereIn('id', $influencerIds)
            ->get(['id', 'name', 'handle', 'platform'])
            ->keyBy('id');

        return DataTables::eloquent($query)
            ->addColumn('influencer_name', fn (InfluencerProduct $ip) => $influencers[$ip->influencer_id]->name ?? '-')
            ->addColumn('influencer_handle', fn (InfluencerProduct $ip) => $influencers[$ip->influencer_id]->handle ?? '-')
            ->addColumn('branch_name', fn (InfluencerProduct $ip) => $ip->branch?->name ?? '-')
            ->addColumn('status_label', fn (InfluencerProduct $ip) => $ip->status?->label() ?? '-')
            ->addColumn('status_color', fn (InfluencerProduct $ip) => $ip->status?->color() ?? 'secondary')
            ->addColumn('agreement_label', fn (InfluencerProduct $ip) => $ip->agreement_type?->label() ?? '-')
            ->addColumn('agreement_color', fn (InfluencerProduct $ip) => $ip->agreement_type?->color() ?? 'secondary')
            ->addColumn('items_count', fn (InfluencerProduct $ip) => $ip->items->count())
            ->addColumn('total_value_formatted', fn (InfluencerProduct $ip) => number_format($ip->total_value, 0, ',', '.') . ' L')
            ->addColumn('expected_return_formatted', fn (InfluencerProduct $ip) => $ip->expected_return_date?->format('d/m/Y') ?? '-')
            ->addColumn('is_overdue', fn (InfluencerProduct $ip) => $ip->is_overdue)
            ->addColumn('created_at_formatted', fn (InfluencerProduct $ip) => $ip->created_at?->format('d/m/Y') ?? '-')
            ->addColumn('created_by_name', fn (InfluencerProduct $ip) => $ip->createdBy?->full_name ?? '-')
            ->addColumn('actions', fn (InfluencerProduct $ip) => view('influencer-products.datatable.actions', ['influencerProduct' => $ip])->render())
            ->filterColumn('influencer_name', function ($query, $keyword) {
                $ids = DisInfluencer::where('name', 'like', "%{$keyword}%")
                    ->orWhere('handle', 'like', "%{$keyword}%")
                    ->pluck('id');
                $query->whereIn('influencer_id', $ids);
            })
            ->filterColumn('branch_name', function ($query, $keyword) {
                $query->whereHas('branch', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    // =========================================================================
    // Create
    // =========================================================================

    public function create(): View
    {
        $branches = DisBranch::where('is_branch_active', true)
            ->whereNotIn('name', ['Tailor X', 'Craft X'])
            ->with('warehouses:id,name,branch_id')
            ->get(['id', 'name']);

        return view('influencer-products.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'influencer_id'          => ['required', 'integer', $this->existsOnDis('influencers')],
            'source_branch_id'       => ['required', 'integer', $this->existsOnDis('branches')],
            'source_warehouse_id'    => ['required', 'integer', $this->existsOnDis('warehouses')],
            'agreement_type'         => ['required', 'in:loan,gift,tbd'],
            'expected_return_date'   => ['nullable', 'date', 'after_or_equal:today'],
            'notes'                  => ['nullable', 'string'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.item_id'        => ['required', 'integer', $this->existsOnDis('items')],
            'items.*.quantity_given' => ['required', 'integer', 'min:1'],
            'items.*.product_value'  => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            Log::info('Creating influencer product via DIS API', [
                'influencer_id' => $validated['influencer_id'],
                'items_count'   => count($validated['items']),
            ]);

            $payload = collect($validated)->except('items')->toArray();
            // DIS expects product_value required on each item; coerce nullable to 0.
            $items = array_map(
                fn ($item) => array_merge($item, ['product_value' => $item['product_value'] ?? 0]),
                $validated['items'],
            );

            $result = $this->service->createInfluencerProduct(
                $payload,
                $items,
                auth()->id() ?? 0,
            );

            $createdId = $result['id'] ?? null;

            Log::info('Influencer product created', [
                'id'     => $createdId,
                'serial' => $result['serial'] ?? null,
            ]);

            return $createdId
                ? redirect()->route('marketing.influencer-products.show', $createdId)
                    ->with('success', __('influencer_product.messages.created'))
                : redirect()->route('marketing.influencer-products.index')
                    ->with('success', __('influencer_product.messages.created'));
        } catch (Exception $e) {
            Log::error('Failed to create influencer product', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function show(InfluencerProduct $influencerProduct): View
    {
        $influencerProduct->load([
            'branch:id,name',
            'warehouse:id,name',
            'createdBy:id,full_name',
            'items.item:id,name,sku,rate',
            'transferOrder:id,serial',
            'returnTransferOrder:id,serial',
            'invoice:id,serial',
        ]);

        // Override the dis-bound influencer relation with the authoritative
        // marketing-side record so blades can use $product->influencer.
        $influencer = DisInfluencer::withTrashed()->find($influencerProduct->influencer_id);
        $influencerProduct->setRelation('influencer', $influencer);

        return view('influencer-products.show', [
            'influencerProduct' => $influencerProduct,
            'influencer'        => $influencer,
        ]);
    }

    // =========================================================================
    // State transitions (delegated to DIS)
    // =========================================================================

    public function activate(InfluencerProduct $influencerProduct): RedirectResponse
    {
        try {
            Log::info('Activating influencer product', [
                'id'     => $influencerProduct->id,
                'serial' => $influencerProduct->serial,
            ]);

            $this->service->activateInfluencerProduct($influencerProduct->id);

            return redirect()->route('marketing.influencer-products.show', $influencerProduct->id)
                ->with('success', __('influencer_product.messages.activated'));
        } catch (Exception $e) {
            Log::error('Failed to activate influencer product', [
                'id'    => $influencerProduct->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function registerReturn(Request $request, InfluencerProduct $influencerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'return_items'                              => ['required', 'array', 'min:1'],
            'return_items.*.influencer_product_item_id' => ['required', 'integer', $this->existsOnDis('influencer_product_items')],
            'return_items.*.quantity_returned'          => ['required', 'integer', 'min:1'],
            'return_items.*.return_condition'           => ['nullable', 'in:good,damaged,missing'],
            'return_warehouse_id'                       => ['nullable', 'integer', $this->existsOnDis('warehouses')],
        ]);

        try {
            // DIS requires return_condition on every item; default missing/null to 'good'.
            $items = array_map(
                fn ($item) => array_merge($item, ['return_condition' => $item['return_condition'] ?? 'good']),
                $validated['return_items'],
            );

            $this->service->registerReturn(
                $influencerProduct->id,
                $items,
                $validated['return_warehouse_id'] ?? null,
            );

            return redirect()->route('marketing.influencer-products.show', $influencerProduct->id)
                ->with('success', __('influencer_product.messages.return_registered'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function convertToExpense(Request $request, InfluencerProduct $influencerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'kept_items'                              => ['required', 'array', 'min:1'],
            'kept_items.*.influencer_product_item_id' => ['required', 'integer', $this->existsOnDis('influencer_product_items')],
            'kept_items.*.product_value'              => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->convertToExpense(
                $influencerProduct->id,
                $validated['kept_items'],
            );

            return redirect()->route('marketing.influencer-products.show', $influencerProduct->id)
                ->with('success', __('influencer_product.messages.converted'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(InfluencerProduct $influencerProduct): RedirectResponse
    {
        try {
            $this->service->cancelInfluencerProduct($influencerProduct->id);

            return redirect()->route('marketing.influencer-products.show', $influencerProduct->id)
                ->with('success', __('influencer_product.messages.cancelled'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function extend(Request $request, InfluencerProduct $influencerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'expected_return_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $this->service->extendDeadline(
                $influencerProduct->id,
                $validated['expected_return_date'],
            );

            return redirect()->route('marketing.influencer-products.show', $influencerProduct->id)
                ->with('success', __('influencer_product.messages.deadline_extended'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // =========================================================================
    // AJAX helpers (Select2, dynamic warehouse list)
    // =========================================================================

    public function searchItems(Request $request): JsonResponse
    {
        $search = $request->input('q', '');

        $items = DisItem::where('status', 'active')
            ->where('product_type', 'goods')
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku', 'rate', 'r2_thumbnail_url', 'r2_image_url']);

        $fallback = asset('assets/images/users/user-1.jpg');

        return response()->json([
            'results' => $items->map(fn ($item) => [
                'id'         => $item->id,
                'text'       => $item->name,
                'sku'        => $item->sku,
                'rate'       => $item->rate ?? 0,
                'thumbnail'  => $item->r2_thumbnail_url ?: $item->r2_image_url ?: $fallback,
                'full_image' => $item->r2_image_url ?: $item->r2_thumbnail_url ?: $fallback,
            ]),
        ]);
    }

    public function getWarehousesForBranch(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');

        $warehouses = DisWarehouse::where('branch_id', $branchId)
            ->get(['id', 'name']);

        return response()->json($warehouses);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Validation rule that checks the id exists on the DIS connection.
     * Needed because Laravel's `exists:` looks at the request's default
     * connection, but branches/warehouses/items/influencer_product_items
     * live in the DIS database, not the marketing one.
     */
    protected function existsOnDis(string $table): Closure
    {
        return function ($attribute, $value, $fail) use ($table) {
            if (! DB::connection('dis')->table($table)->where('id', $value)->exists()) {
                $fail("The selected {$attribute} is invalid.");
            }
        };
    }
}
