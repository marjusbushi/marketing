<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Dis\DisInfluencer;
use App\Models\Dis\InfluencerProduct as DisInfluencerProduct;
use App\Services\DisApiClient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Influencer profile CRUD.
 *
 * Reads go directly against the 'dis' connection via DisInfluencer.
 * Writes route through DisApiClient → DIS internal API so that DIS
 * stays the single source of truth for influencer records (their IDs
 * are referenced by influencer_products.influencer_id in the same DB).
 */
class InfluencersController extends Controller
{
    public function __construct(
        protected DisApiClient $service,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->dataTable($request);
        }

        return view('influencers.index');
    }

    protected function dataTable(Request $request): JsonResponse
    {
        $query = DisInfluencer::query()->with(['createdBy:id,full_name']);

        if ($request->filled('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        // Active product counts batch-loaded from DIS once per table draw.
        $activeCounts = DisInfluencerProduct::whereIn('status', ['active', 'partially_returned'])
            ->selectRaw('influencer_id, COUNT(*) as c')
            ->groupBy('influencer_id')
            ->pluck('c', 'influencer_id');

        return DataTables::eloquent($query)
            ->addColumn('platform_label', fn (DisInfluencer $i) => $i->platform->label())
            ->addColumn('platform_icon', fn (DisInfluencer $i) => $i->platform->icon())
            ->addColumn('active_products_count', fn (DisInfluencer $i) => (int) ($activeCounts[$i->id] ?? 0))
            ->addColumn('created_by_name', fn (DisInfluencer $i) => $i->createdBy?->full_name ?? '-')
            ->addColumn('created_at_formatted', fn (DisInfluencer $i) => $i->created_at?->format('d/m/Y') ?? '-')
            ->addColumn('status_badge', fn (DisInfluencer $i) => $i->is_active ? 'success' : 'danger')
            ->addColumn('actions', fn (DisInfluencer $i) => view('influencers.datatable.actions', ['influencer' => $i])->render())
            ->filterColumn('name', function ($query, $keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('handle', 'like', "%{$keyword}%");
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:instagram,tiktok,youtube,other'],
            'handle'   => [
                'nullable', 'string', 'max:255',
                // Fast-feedback client-side check; DIS repeats the check.
                function ($attribute, $value, $fail) use ($request) {
                    if (empty($value)) {
                        return;
                    }
                    $exists = DisInfluencer::where('platform', $request->input('platform'))
                        ->where('handle', $value)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        $fail('Ky handle është tashmë i regjistruar për këtë platformë.');
                    }
                },
            ],
            'phone'    => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255'],
            'notes'    => ['nullable', 'string'],
        ]);

        try {
            $result = $this->service->createInfluencer($validated, auth()->id() ?? 0);

            if ($request->ajax()) {
                return response()->json([
                    'success'    => true,
                    'influencer' => $result,
                ]);
            }

            return redirect()->route('marketing.influencers.index')
                ->with('success', __('influencer.messages.created'));
        } catch (Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(DisInfluencer $influencer): View|JsonResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'influencer' => [
                    'id'        => $influencer->id,
                    'name'      => $influencer->name,
                    'platform'  => $influencer->platform->value,
                    'handle'    => $influencer->handle,
                    'phone'     => $influencer->phone,
                    'email'     => $influencer->email,
                    'notes'     => $influencer->notes,
                    'is_active' => $influencer->is_active,
                    'label'     => $influencer->label,
                ],
            ]);
        }

        $influencer->load([
            'createdBy:id,full_name',
            'influencerProducts' => fn ($q) => $q->latest()->with([
                'items.item:id,name,sku',
                'branch:id,name',
                'createdBy:id,full_name',
            ]),
        ]);

        return view('influencers.show', compact('influencer'));
    }

    public function update(Request $request, DisInfluencer $influencer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'platform'  => ['required', 'string', 'in:instagram,tiktok,youtube,other'],
            'handle'    => [
                'nullable', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($request, $influencer) {
                    if (empty($value)) {
                        return;
                    }
                    $exists = DisInfluencer::where('platform', $request->input('platform'))
                        ->where('handle', $value)
                        ->where('id', '!=', $influencer->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        $fail('Ky handle është tashmë i regjistruar për këtë platformë.');
                    }
                },
            ],
            'phone'     => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
            'notes'     => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $this->service->updateInfluencer($influencer->id, $validated);

            if ($request->ajax()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('marketing.influencers.show', $influencer)
                ->with('success', __('influencer.messages.updated'));
        } catch (Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function search(Request $request): JsonResponse
    {
        $search = (string) $request->input('q', '');

        $influencers = DisInfluencer::active()
            ->search($search)
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'handle', 'platform']);

        return response()->json([
            'results' => $influencers->map(fn (DisInfluencer $i) => [
                'id'   => $i->id,
                'text' => $i->label,
            ]),
        ]);
    }
}
