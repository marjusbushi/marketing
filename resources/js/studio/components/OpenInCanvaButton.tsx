import { useEffect, useRef, useState } from 'react';
import type { AxiosInstance } from 'axios';
import { CanvaClient, type CanvaExportJob } from '@studio/services/canvaClient';
import { useToast } from '@studio/components/ToastHost';
import type { StudioEndpoints } from '@studio/types/props';

type Format = 'png' | 'jpg' | 'pdf';

interface OpenInCanvaButtonProps {
    http: AxiosInstance;
    endpoints: StudioEndpoints;
    featureEnabled: boolean;
    brandTemplateId: string;
    autofillFields?: Record<string, unknown>;
    creativeBriefId: number | string | null;
    exportFormat?: Format;
    onAttached?: (result: {
        design_id: string;
        asset_url: string;
        thumbnail_url?: string | null;
        format: Format;
    }) => void;
}

/**
 * "Open in Canva" — the single button that carries the whole pivoted flow.
 *
 *   1. Check /api/canva/status. If not connected → redirect to authorize.
 *   2. Create a design from the brand template (autofill).
 *   3. Open Canva's edit_url in a new tab.
 *   4. Poll for design status and — once the user hits Publish in Canva —
 *      start an export, wait for it, then attach the asset URL to the
 *      active creative brief.
 *
 * The component intentionally renders nothing when the feature flag is
 * off; the editor simply doesn't surface it, matching the "no embed"
 * decision from Decision #14.
 */
export function OpenInCanvaButton({
    http,
    endpoints,
    featureEnabled,
    brandTemplateId,
    autofillFields = {},
    creativeBriefId,
    exportFormat = 'png',
    onAttached,
}: OpenInCanvaButtonProps) {
    const clientRef = useRef<CanvaClient>();
    if (!clientRef.current) {
        clientRef.current = new CanvaClient(http, endpoints);
    }
    const client = clientRef.current;
    const toast = useToast();

    const [stage, setStage] = useState<'idle' | 'checking' | 'creating' | 'editing' | 'exporting' | 'attaching'>('idle');
    const [designId, setDesignId] = useState<string | null>(null);
    const [editUrl, setEditUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!featureEnabled) return;
        client.status().catch(() => {
            /* tolerate — button handles auth on click. */
        });
    }, [client, featureEnabled]);

    if (!featureEnabled) {
        return null;
    }

    async function handleClick() {
        setError(null);

        try {
            setStage('checking');
            const status = await client.status();

            if (!status.connected || status.expired) {
                client.redirectToAuthorize();
                return;
            }

            setStage('creating');
            const designPayload = await client.createDesign(brandTemplateId, autofillFields);
            const newDesignId = designPayload.design?.id ?? null;
            const editHref    = designPayload.design?.urls?.edit_url ?? null;

            if (!newDesignId || !editHref) {
                throw new Error('Canva did not return a design id / edit URL.');
            }

            setDesignId(newDesignId);
            setEditUrl(editHref);
            window.open(editHref, '_blank', 'noopener');

            // From here the user works inside Canva. We surface a second
            // action ("Unë mbarova — merre në shportë") so the user tells
            // us when to export. Automatic polling on design state is
            // possible but noisy — explicit confirm is less error-prone.
            setStage('editing');
        } catch (e) {
            setStage('idle');
            const msg = friendly(e);
            setError(msg);
            toast.error('Canva: ' + msg);
        }
    }

    async function handleExport() {
        if (!designId) return;

        try {
            setStage('exporting');
            const { job } = await client.startExport(designId, exportFormat);

            const final: CanvaExportJob = await client.waitForExport(job.id);

            if (final.job.status !== 'success' || !final.job.urls?.length) {
                throw new Error(final.job.error?.message ?? 'Canva export failed.');
            }

            if (creativeBriefId !== null && creativeBriefId !== undefined) {
                setStage('attaching');
                await client.attachToBrief(creativeBriefId, {
                    design_id: designId,
                    asset_url: final.job.urls[0],
                    format: exportFormat,
                });
            }

            onAttached?.({
                design_id: designId,
                asset_url: final.job.urls[0],
                format: exportFormat,
            });
            toast.success('Dizajni i Canva-s u bashkangjit te posti.');

            setStage('idle');
            setDesignId(null);
            setEditUrl(null);
        } catch (e) {
            setStage('editing');
            const msg = friendly(e);
            setError(msg);
            toast.error('Canva: ' + msg);
        }
    }

    if (stage === 'editing' && editUrl) {
        return (
            <div className="flex items-center gap-2">
                <a
                    href={editUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="rounded-md border border-violet-700 bg-violet-900/40 px-3 py-1.5 text-xs font-medium text-violet-200 hover:bg-violet-900"
                >
                    Kthehu në Canva
                </a>
                <button
                    type="button"
                    onClick={handleExport}
                    className="rounded-md bg-violet-500 px-3 py-1.5 text-xs font-medium text-violet-950 hover:bg-violet-400"
                >
                    Unë mbarova — merre në shportë
                </button>
                {error && <span className="text-[11px] text-rose-400">{error}</span>}
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={handleClick}
                disabled={stage !== 'idle'}
                className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:border-violet-500 disabled:cursor-wait disabled:opacity-60"
                title="Hap këtë post në Canva me brand kit + template të ngarkuar"
            >
                {stage === 'idle' && '✦ Hap në Canva'}
                {stage === 'checking' && 'Duke kontrolluar…'}
                {stage === 'creating' && 'Duke hapur Canva…'}
                {stage === 'exporting' && 'Duke eksportuar…'}
                {stage === 'attaching' && 'Duke e lidhur me postin…'}
            </button>
            {error && <span className="text-[11px] text-rose-400">{error}</span>}
        </div>
    );
}

function friendly(e: unknown): string {
    if (e && typeof e === 'object' && 'response' in e) {
        const response = (e as { response?: { data?: { message?: string } } }).response;
        if (response?.data?.message) return response.data.message;
    }
    if (e instanceof Error) return e.message;
    return 'Ndodhi një gabim i panjohur.';
}
