<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Template;
use App\Services\Marketing\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Visual Studio templates — list/show for the editor, CRUD for admins.
 *
 * The editor calls GET /marketing/api/templates?kind=reel&engine=remotion to
 * fill its templates panel. Claude (Faza 2) calls the same endpoint to pick
 * an appropriate template based on product and post type metadata.
 */
class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Template::query()->active();

        if ($kind = $request->query('kind')) {
            $query->ofKind($kind);
        }

        if ($engine = $request->query('engine')) {
            $query->forEngine($engine);
        }

        $templates = $query
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return response()->json([
            'templates' => $templates->map(fn (Template $t) => $this->serialize($t))->values(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $template = $this->templates->findBySlug($slug);

        if ($template === null || ! $template->is_active) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json(['template' => $this->serialize($template, includeSource: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:120'],
            'slug'                    => ['nullable', 'string', 'max:120', 'unique:marketing_templates,slug'],
            'kind'                    => ['required', 'in:photo,carousel,reel,video,story'],
            'engine'                  => ['required', 'in:polotno,remotion,canva,capcut'],
            'source'                  => ['required', 'array'],
            'canva_brand_template_id' => ['nullable', 'string', 'max:120'],
            'metadata'                => ['nullable', 'array'],
            'thumbnail_path'          => ['nullable', 'string', 'max:500'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']) . '-' . Str::random(6);
        $validated['is_system'] = false;
        $validated['is_active'] = true;
        $validated['created_by'] = $request->user()?->id;

        $template = $this->templates->create($validated);

        return response()->json(['template' => $this->serialize($template, includeSource: true)], 201);
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        if ($template->is_system) {
            return response()->json(['message' => 'System templates cannot be edited'], 403);
        }

        $validated = $request->validate([
            'name'                    => ['sometimes', 'string', 'max:120'],
            'kind'                    => ['sometimes', 'in:photo,carousel,reel,video,story'],
            'engine'                  => ['sometimes', 'in:polotno,remotion,canva,capcut'],
            'source'                  => ['sometimes', 'array'],
            'canva_brand_template_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'metadata'                => ['sometimes', 'array'],
            'thumbnail_path'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active'               => ['sometimes', 'boolean'],
        ]);

        $this->templates->update($template, $validated);

        return response()->json(['template' => $this->serialize($template->refresh(), includeSource: true)]);
    }

    public function destroy(Template $template): JsonResponse
    {
        if ($template->is_system) {
            return response()->json(['message' => 'System templates cannot be deleted'], 403);
        }

        $this->templates->deactivate($template);

        return response()->json(['message' => 'Template deactivated']);
    }

    private function serialize(Template $template, bool $includeSource = false): array
    {
        $data = [
            'id'                      => $template->id,
            'name'                    => $template->name,
            'slug'                    => $template->slug,
            'kind'                    => $template->kind,
            'engine'                  => $template->engine,
            'canva_brand_template_id' => $template->canva_brand_template_id,
            'metadata'                => $template->metadata ?? [],
            'thumbnail_path'          => $template->thumbnail_path,
            'is_system'               => $template->is_system,
            'is_active'               => $template->is_active,
        ];

        if ($includeSource) {
            $data['source'] = $template->source;
        }

        return $data;
    }
}
