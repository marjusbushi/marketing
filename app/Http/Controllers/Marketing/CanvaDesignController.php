<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\CanvaConnection;
use App\Models\Marketing\CreativeBrief;
use App\Services\Marketing\BrandKitCanvaSyncService;
use App\Services\Marketing\BrandKitService;
use App\Services\Marketing\CanvaConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin design-flow API that sits between the Visual Studio SPA and the
 * Canva Connect service.
 *
 *   POST /marketing/api/canva/designs
 *        Create a design from a brand template. Returns the Canva edit_url
 *        so the SPA can open it in a popup / new tab.
 *
 *   GET  /marketing/api/canva/designs/{designId}
 *        Passthrough for polling design state.
 *
 *   POST /marketing/api/canva/designs/{designId}/export
 *        Start an export job. Returns Canva's job payload.
 *
 *   GET  /marketing/api/canva/exports/{jobId}
 *        Poll an export job. On success, returns the download URLs.
 *
 *   POST /marketing/api/creative-briefs/{brief}/attach-canva-design
 *        Attach an exported design's URL to a creative brief's `state`
 *        (and mirror into `media_slots` so the brief renders in the grid).
 */
class CanvaDesignController extends Controller
{
    public function __construct(
        protected CanvaConnectService $canva,
        protected BrandKitService $brandKit,
        protected BrandKitCanvaSyncService $canvaSync,
    ) {
    }

    /**
     * Create a design from a Canva brand template, optionally autofilled
     * with the values the SPA sends (caption, product name, etc.).
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand_template_id' => ['required', 'string'],
            'fields'            => ['nullable', 'array'],
        ]);

        $connection = $this->activeConnection($request->user()->id);

        try {
            $token   = $this->canva->getValidAccessToken($connection);
            $payload = $this->canva->createDesignFromBrandTemplate(
                $token,
                $validated['brand_template_id'],
                $validated['fields'] ?? [],
            );
        } catch (RuntimeException $e) {
            return $this->apiError($e, 'create Canva design');
        }

        return response()->json($payload);
    }

    /**
     * Passthrough for the SPA's polling loop while the user edits the
     * design in Canva.
     */
    public function show(Request $request, string $designId): JsonResponse
    {
        $connection = $this->activeConnection($request->user()->id);

        try {
            $token = $this->canva->getValidAccessToken($connection);
            return response()->json($this->canva->getDesign($token, $designId));
        } catch (RuntimeException $e) {
            return $this->apiError($e, 'get Canva design');
        }
    }

    /**
     * Start an export job. Does not block — the SPA polls `exportStatus()`.
     */
    public function startExport(Request $request, string $designId): JsonResponse
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string', 'in:png,jpg,pdf'],
        ]);

        $connection = $this->activeConnection($request->user()->id);

        try {
            $token = $this->canva->getValidAccessToken($connection);
            return response()->json(
                $this->canva->startExport(
                    $token,
                    $designId,
                    $validated['format'] ?? (string) config('canva.export.default_format', 'png'),
                )
            );
        } catch (RuntimeException $e) {
            return $this->apiError($e, 'start Canva export');
        }
    }

    /**
     * Poll an export job — the SPA calls this on an interval until
     * `status = success` (and `urls` is populated).
     */
    public function exportStatus(Request $request, string $jobId): JsonResponse
    {
        $connection = $this->activeConnection($request->user()->id);

        try {
            $token = $this->canva->getValidAccessToken($connection);
            return response()->json($this->canva->getExportJob($token, $jobId));
        } catch (RuntimeException $e) {
            return $this->apiError($e, 'poll Canva export job');
        }
    }

    /**
     * Attach a finished Canva design's URL to a creative brief. The SPA
     * sends the download URL after the export polling completes.
     */
    public function attachToBrief(Request $request, CreativeBrief $creativeBrief): JsonResponse
    {
        $validated = $request->validate([
            'design_id'    => ['required', 'string'],
            'asset_url'    => ['required', 'url'],
            'thumbnail_url' => ['nullable', 'url'],
            'format'       => ['required', 'string', 'in:png,jpg,pdf'],
        ]);

        $state = $creativeBrief->state ?? [];
        $state['canva'] = [
            'design_id'     => $validated['design_id'],
            'asset_url'     => $validated['asset_url'],
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'format'        => $validated['format'],
            'attached_at'   => now()->toIso8601String(),
        ];

        $slots = $creativeBrief->media_slots ?? [];
        $slots[] = [
            'kind'      => 'canva',
            'url'       => $validated['asset_url'],
            'thumbnail' => $validated['thumbnail_url'] ?? null,
            'format'    => $validated['format'],
            'design_id' => $validated['design_id'],
        ];

        $creativeBrief->forceFill([
            'state'       => $state,
            'media_slots' => $slots,
        ])->save();

        return response()->json([
            'ok'             => true,
            'creative_brief' => $creativeBrief->fresh(),
        ]);
    }

    /**
     * Push the current brand kit (colors, fonts, logos) into the user's
     * Canva brand kit. One-way sync; the marketing app stays source of truth.
     */
    public function syncBrandKit(Request $request): JsonResponse
    {
        $connection = $this->activeConnection($request->user()->id);

        try {
            $result = $this->canvaSync->pushBrandKit(
                $connection,
                $this->brandKit->get(),
            );
        } catch (RuntimeException $e) {
            return $this->apiError($e, 'sync brand kit to Canva');
        }

        return response()->json($result);
    }

    // ─── helpers ─────────────────────────────────────────────────

    protected function activeConnection(int $userId): CanvaConnection
    {
        $connection = CanvaConnection::query()
            ->active()
            ->where('user_id', $userId)
            ->first();

        abort_if($connection === null, 428, 'No active Canva connection. Call /marketing/canva/authorize first.');

        return $connection;
    }

    protected function apiError(RuntimeException $e, string $operation): JsonResponse
    {
        Log::warning("Canva API error ({$operation}): " . $e->getMessage());

        return response()->json([
            'ok'        => false,
            'operation' => $operation,
            'message'   => $e->getMessage(),
        ], 502);
    }
}
