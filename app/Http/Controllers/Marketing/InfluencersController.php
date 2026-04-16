<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Influencer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class InfluencersController extends Controller
{
    /**
     * Lista e influencerave
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->dataTable($request);
        }

        return view('influencers.index');
    }

    /**
     * DataTable AJAX handler
     */
    protected function dataTable(Request $request): JsonResponse
    {
        $query = Influencer::query()
            ->with(['createdBy:id,full_name'])
            ->withCount(['influencerProducts as active_products_count' => function ($q) {
                $q->whereIn('status', ['active', 'partially_returned']);
            }]);

        return DataTables::eloquent($query)
            ->addColumn('platform_label', fn(Influencer $i) => $i->platform->label())
            ->addColumn('platform_icon', fn(Influencer $i) => $i->platform->icon())
            ->addColumn('created_by_name', fn(Influencer $i) => $i->createdBy?->full_name ?? '-')
            ->addColumn('created_at_formatted', fn(Influencer $i) => $i->created_at?->format('d/m/Y') ?? '-')
            ->addColumn('status_badge', fn(Influencer $i) => $i->is_active ? 'success' : 'danger')
            ->addColumn('actions', fn(Influencer $i) => view('influencers.datatable.actions', ['influencer' => $i])->render())
            ->filterColumn('name', function ($query, $keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('handle', 'like', "%{$keyword}%");
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * Krijo influencer te ri
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:instagram,tiktok,youtube,other'],
            'handle'   => [
                'nullable', 
                'string', 
                'max:255',
                // Unique handle per platform (excluding soft deletes)
                function ($attribute, $value, $fail) use ($request) {
                    if (empty($value)) return;
                    
                    $exists = Influencer::where('platform', $request->input('platform'))
                        ->where('handle', $value)
                        ->whereNull('deleted_at')
                        ->exists();
                    
                    if ($exists) {
                        $fail('Ky handle është tashmë i regjistruar për këtë platformë.');
                    }
                }
            ],
            'phone'    => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255'],
            'notes'    => ['nullable', 'string'],
        ]);

        $validated['created_by_user_id'] = auth()->id();
        $validated['is_active'] = true;

        $influencer = Influencer::create($validated);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'influencer' => $influencer,
            ]);
        }

        return redirect()->route('management.influencers.index')
            ->with('success', __('influencer.messages.created'));
    }

    /**
     * Shfaq profilin e influencerit me historine e produkteve
     * Ose kthe JSON per AJAX (edit modal)
     */
    public function show(Influencer $influencer): View|JsonResponse
    {
        // Nese eshte AJAX request, kthe JSON per edit
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'influencer' => [
                    'id' => $influencer->id,
                    'name' => $influencer->name,
                    'platform' => $influencer->platform->value,
                    'handle' => $influencer->handle,
                    'phone' => $influencer->phone,
                    'email' => $influencer->email,
                    'notes' => $influencer->notes,
                    'is_active' => $influencer->is_active,
                    'label' => $influencer->label,
                ],
            ]);
        }

        $influencer->load([
            'createdBy:id,full_name',
            'influencerProducts' => fn($q) => $q->latest()->with([
                'items.item:id,name,sku',
                'branch:id,name',
                'createdBy:id,full_name',
            ]),
        ]);

        return view('influencers.show', compact('influencer'));
    }

    /**
     * Përditëso të dhënat e influencerit
     */
    public function update(Request $request, Influencer $influencer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'platform'  => ['required', 'string', 'in:instagram,tiktok,youtube,other'],
            'handle'    => [
                'nullable', 
                'string', 
                'max:255',
                // Unique handle per platform (excluding current influencer and soft deletes)
                function ($attribute, $value, $fail) use ($request, $influencer) {
                    if (empty($value)) return;
                    
                    $exists = Influencer::where('platform', $request->input('platform'))
                        ->where('handle', $value)
                        ->where('id', '!=', $influencer->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    
                    if ($exists) {
                        $fail('Ky handle është tashmë i regjistruar për këtë platformë.');
                    }
                }
            ],
            'phone'     => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
            'notes'     => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $influencer->update($validated);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('management.influencers.show', $influencer)
            ->with('success', __('influencer.messages.updated'));
    }

    /**
     * Kërko influencer (për select2 AJAX)
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->input('q', '');

        $influencers = Influencer::active()
            ->search($search)
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'handle', 'platform']);

        return response()->json([
            'results' => $influencers->map(fn(Influencer $i) => [
                'id'   => $i->id,
                'text' => $i->label,
            ]),
        ]);
    }
}
