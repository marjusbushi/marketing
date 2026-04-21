<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\CreativeBrief;
use App\Services\Marketing\BrandKitService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Visual Studio full-screen page — mounts the React SPA.
 *
 * The Blade view stays intentionally thin: it hands React the initial props
 * (brand kit, active brief id, permission flags) and gets out of the way.
 * All subsequent data flow goes through the JSON APIs already built in
 * tasks #1237–#1240.
 */
class StudioController extends Controller
{
    public function __construct(
        private readonly BrandKitService $brandKitService,
    ) {
    }

    public function index(Request $request, ?CreativeBrief $creativeBrief = null): View
    {
        $brief = $creativeBrief && $creativeBrief->exists ? $creativeBrief : null;

        // `?embedded=1` strips the app chrome (nav, sidebar, header) so the
        // Studio page can mount inside an iframe opened from the daily-basket
        // inline editor modal (#1247). The SPA itself is unchanged — only
        // the Blade shell swaps its layout.
        $embedded = $request->boolean('embedded');

        return view('marketing.studio', [
            'title'     => 'Visual Studio',
            'pageTitle' => $brief ? "Visual Studio — Brief #{$brief->id}" : 'Visual Studio',
            'embedded'  => $embedded,
            'props'     => [
                'brand_kit'         => $this->brandKitService->get()->toArray(),
                'creative_brief_id' => $brief?->id,
                'user' => [
                    'id'    => $request->user()?->id,
                    'name'  => trim(($request->user()?->first_name ?? '') . ' ' . ($request->user()?->last_name ?? '')),
                    'email' => $request->user()?->email,
                ],
                'permissions' => $this->resolvePermissions($request),
                'csrf_token'  => csrf_token(),
                'endpoints'   => [
                    'brand_kit'       => route('marketing.api.brand-kit.show'),
                    'templates'       => route('marketing.api.templates.index'),
                    'creative_briefs' => route('marketing.api.creative-briefs.index'),
                    'ai_caption'      => route('marketing.api.ai.caption'),
                    'ai_rewrite'      => route('marketing.api.ai.rewrite'),
                    'canva_authorize'     => route('marketing.canva.authorize'),
                    'canva_disconnect'    => route('marketing.canva.disconnect'),
                    'canva_status'        => route('marketing.api.canva.status'),
                    'canva_designs'       => route('marketing.api.canva.designs.create'),
                    'canva_design_show'   => route('marketing.api.canva.designs.show', ['designId' => '__ID__']),
                    'canva_design_export' => route('marketing.api.canva.designs.export', ['designId' => '__ID__']),
                    'canva_export_status' => route('marketing.api.canva.exports.show', ['jobId' => '__ID__']),
                    'canva_brand_sync'    => route('marketing.api.canva.brand-kit.sync'),
                    'canva_attach_brief'  => route('marketing.api.creative-briefs.attach-canva', ['creativeBrief' => '__ID__']),
                    'upload_video_brief'  => route('marketing.api.creative-briefs.upload-video', ['creativeBrief' => '__ID__']),
                ],
                'features' => [
                    'canva_connect' => (bool) config('canva.features.canva_connect', false),
                ],
                'limits' => [
                    'video_max_size_mb' => (int) config('content-planner.video_max_size_mb', 500),
                ],
                'embedded' => $embedded,
            ],
        ]);
    }

    private function resolvePermissions(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return [
            'content_planner.view'   => $user->hasMarketingPermission('content_planner.view'),
            'content_planner.create' => $user->hasMarketingPermission('content_planner.create'),
            'content_planner.edit'   => $user->hasMarketingPermission('content_planner.edit'),
            'content_planner.manage' => $user->hasMarketingPermission('content_planner.manage'),
        ];
    }
}
