<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Dis\DisBranch;
use App\Models\Dis\InfluencerProduct;
use App\Models\Dis\DisItem;
use App\Models\Dis\DisWarehouse;
use App\Services\DisApiClient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InfluencerProductsController extends Controller
{
    public function __construct(
        protected DisApiClient $service
    ) {}

    /**
     * Lista e produkteve të dhëna influencerave
     */
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

    /**
     * DataTable AJAX handler
     */
    protected function dataTable(Request $request): JsonResponse
    {
        $query = InfluencerProduct::query()
            ->with([
                'influencer:id,name,handle,platform',
                'branch:id,name',
                'createdBy:id,full_name',
                'items.item:id,name,sku',
            ]);

        // Filtra
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

        return DataTables::eloquent($query)
            ->addColumn('influencer_name', fn(InfluencerProduct $ip) => $ip->influencer?->name ?? '-')
            ->addColumn('influencer_handle', fn(InfluencerProduct $ip) => $ip->influencer?->handle ?? '-')
            ->addColumn('branch_name', fn(InfluencerProduct $ip) => $ip->branch?->name ?? '-')
            ->addColumn('status_label', fn(InfluencerProduct $ip) => $ip->status->label())
            ->addColumn('status_color', fn(InfluencerProduct $ip) => $ip->status->color())
            ->addColumn('agreement_label', fn(InfluencerProduct $ip) => $ip->agreement_type->label())
            ->addColumn('agreement_color', fn(InfluencerProduct $ip) => $ip->agreement_type->color())
            ->addColumn('items_count', fn(InfluencerProduct $ip) => $ip->items->count())
            ->addColumn('total_value_formatted', fn(InfluencerProduct $ip) => number_format($ip->total_value, 0, ',', '.') . ' L')
            ->addColumn('expected_return_formatted', fn(InfluencerProduct $ip) => $ip->expected_return_date?->format('d/m/Y') ?? '-')
            ->addColumn('is_overdue', fn(InfluencerProduct $ip) => $ip->is_overdue)
            ->addColumn('created_at_formatted', fn(InfluencerProduct $ip) => $ip->created_at?->format('d/m/Y') ?? '-')
            ->addColumn('created_by_name', fn(InfluencerProduct $ip) => $ip->createdBy?->full_name ?? '-')
            ->addColumn('actions', fn(InfluencerProduct $ip) => view('influencer-products.datatable.actions', ['influencerProduct' => $ip])->render())
            ->filterColumn('influencer_name', function ($query, $keyword) {
                $query->whereHas('influencer', fn($q) => $q->where('name', 'like', "%{$keyword}%"));
            })
            ->filterColumn('branch_name', function ($query, $keyword) {
                $query->whereHas('branch', fn($q) => $q->where('name', 'like', "%{$keyword}%"));
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * Form për dhënie të re
     */
    public function create(): View
    {
        $branches = DisBranch::where('is_branch_active', true)
            ->whereNotIn('name', ['Tailor X', 'Craft X'])
            ->with('warehouses:id,name,branch_id')
            ->get(['id', 'name']);

        return view('influencer-products.create', compact('branches'));
    }

    /**
     * Ruaj dhënie të re
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'influencer_id'        => ['required', 'exists:influencers,id'],
            'source_branch_id'     => ['required', 'exists:branches,id'],
            'source_warehouse_id'  => ['required', 'exists:warehouses,id'],
            'agreement_type'       => ['required', 'in:loan,gift,tbd'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes'                => ['nullable', 'string'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.item_id'      => ['required', 'exists:items,id'],
            'items.*.quantity_given' => ['required', 'integer', 'min:1'],
            'items.*.product_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            Log::info('Creating influencer product', [
                'data' => $validated,
                'items_count' => count($validated['items']),
            ]);
            
            $influencerProduct = $this->service->createProduct(
                $validated,
                $validated['items']
            );

            Log::info('Influencer product created successfully', [
                'id' => $influencerProduct->id,
                'serial' => $influencerProduct->serial,
            ]);

            FlashNotification::success(__('influencer_product.messages.created'));
            return redirect()->route('management.influencer-products.show', $influencerProduct);
        } catch (Exception $e) {
            Log::error('Failed to create influencer product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            FlashNotification::error($e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Detajet e dhënies
     */
    public function show(InfluencerProduct $influencerProduct): View
    {
        $influencerProduct->load([
            'influencer',
            'branch:id,name',
            'warehouse:id,name',
            'createdBy:id,full_name',
            'transferOrder',
            'returnTransferOrder',
            'invoice',
            'items.item:id,name,sku,rate',
        ]);

        return view('influencer-products.show', compact('influencerProduct'));
    }

    /**
     * Aktivizo dhënien (sync me Zoho, stock lëviz)
     */
    public function activate(InfluencerProduct $influencerProduct): RedirectResponse
    {
        try {
            Log::info('Activating influencer product', [
                'id' => $influencerProduct->id,
                'serial' => $influencerProduct->serial,
                'status' => $influencerProduct->status->value,
            ]);
            
            $result = $this->service->activate($influencerProduct);
            
            Log::info('Influencer product activated successfully', [
                'id' => $result->id,
                'status' => $result->status->value,
            ]);

            FlashNotification::success(__('influencer_product.messages.activated'));
            return redirect()->route('management.influencer-products.show', $influencerProduct);
        } catch (Exception $e) {
            Log::error('Failed to activate influencer product', [
                'id' => $influencerProduct->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            FlashNotification::error($e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Regjistro kthim
     */
    public function registerReturn(Request $request, InfluencerProduct $influencerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'return_items'                       => ['required', 'array', 'min:1'],
            'return_items.*.influencer_product_item_id' => ['required', 'exists:influencer_product_items,id'],
            'return_items.*.quantity_returned'    => ['required', 'integer', 'min:1'],
            'return_items.*.return_condition'     => ['nullable', 'in:good,damaged,missing'],
            'return_warehouse_id'                => ['nullable', 'exists:warehouses,id'],
        ]);

        try {
            $this->service->registerReturn(
                $influencerProduct,
                $validated['return_items'],
                $validated['return_warehouse_id'] ?? null
            );

            FlashNotification::success(__('influencer_product.messages.return_registered'));
            return redirect()->route('management.influencer-products.show', $influencerProduct);
        } catch (Exception $e) {
            FlashNotification::error($e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Konverto në expense (influenceri mban produktin)
     */
    public function convertToExpense(Request $request, InfluencerProduct $influencerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'kept_items'                       => ['required', 'array', 'min:1'],
            'kept_items.*.influencer_product_item_id' => ['required', 'exists:influencer_product_items,id'],
            'kept_items.*.product_value'       => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->convertToExpense(
                $influencerProduct,
                $validated['kept_items']
            );

            FlashNotification::success(__('influencer_product.messages.converted'));
            return redirect()->route('management.influencer-products.show', $influencerProduct);
        } catch (Exception $e) {
            FlashNotification::error($e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Anulo dhënien
     */
    public function cancel(InfluencerProduct $influencerProduct): RedirectResponse
    {
        try {
            $this->service->cancel($influencerProduct);

            FlashNotification::success(__('influencer_product.messages.cancelled'));
            return redirect()->route('management.influencer-products.show', $influencerProduct);
        } catch (Exception $e) {
            FlashNotification::error($e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Zgjat afatin
     */
    public function extend(Request $request, InfluencerProduct $influencerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'expected_return_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $this->service->extendDeadline($influencerProduct, $validated['expected_return_date']);

        FlashNotification::success(__('influencer_product.messages.deadline_extended'));
        return redirect()->route('management.influencer-products.show', $influencerProduct);
    }

    /**
     * Kërko items (per AJAX select2)
     */
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
            ->with('media') // Ngarko media për getFirstMediaUrl
            ->get(['id', 'name', 'sku', 'rate', 'r2_thumbnail_url', 'r2_image_url']);

        return response()->json([
            'results' => $items->map(function ($item) {
                // Merr thumbnail me prioritet: R2 thumbnail > R2 full image > Media Library > default
                $mediaThumbUrl = $item->getFirstMediaUrl('item_featured_image', 'preview');
                $mediaThumbUrl = $mediaThumbUrl && !str_starts_with($mediaThumbUrl, 'http') 
                    ? asset($mediaThumbUrl) 
                    : $mediaThumbUrl;
                
                // Merr full image me prioritet: R2 full > R2 thumbnail > Media Library > null
                $mediaFullUrl = $item->getFirstMediaUrl('item_featured_image');
                $mediaFullUrl = $mediaFullUrl && !str_starts_with($mediaFullUrl, 'http') 
                    ? asset($mediaFullUrl) 
                    : $mediaFullUrl;
                
                $thumbnail = $item->r2_thumbnail_url 
                    ?: $item->r2_image_url 
                    ?: $mediaThumbUrl
                    ?: asset('assets/images/users/user-1.jpg');
                    
                $fullImage = $item->r2_image_url 
                    ?: $item->r2_thumbnail_url 
                    ?: $mediaFullUrl 
                    ?: asset('assets/images/users/user-1.jpg');

                return [
                    'id'          => $item->id,
                    'text'        => $item->name,
                    'sku'         => $item->sku,
                    'rate'        => $item->rate ?? 0,
                    'thumbnail'   => $thumbnail,
                    'full_image'  => $fullImage,
                ];
            }),
        ]);
    }

    /**
     * Merr warehouses per nje branch (AJAX)
     */
    public function getWarehousesForBranch(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');

        $warehouses = Warehouse::where('branch_id', $branchId)
            ->get(['id', 'name']);

        return response()->json($warehouses);
    }
}
