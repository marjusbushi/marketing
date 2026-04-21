import { useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { StudioLayout } from '@studio/components/Layout';
import { QuickTrimModal } from '@studio/components/QuickTrimModal';
import { OpenInCanvaButton } from '@studio/components/OpenInCanvaButton';
import { VideoUploadButton } from '@studio/components/VideoUploadButton';
import { createApiClient } from '@studio/services/api';
import { StudioProps } from '@studio/types/props';

interface BriefEditorProps {
    studio: StudioProps;
}

/**
 * Visual Studio brief editor.
 *
 * Post-pivot (Decision #14): we do not embed Polotno/Remotion. Photo/
 * carousel/story work happens in Canva via the "Open in Canva" button,
 * and video work happens in CapCut via the upload + Quick Trim path
 * (task #1244 wires the upload control into this same shell).
 */
export function BriefEditor({ studio }: BriefEditorProps) {
    const { id } = useParams<{ id: string }>();
    const briefId = id ?? studio.creative_brief_id;

    const http = useMemo(() => createApiClient(studio.csrf_token), [studio.csrf_token]);

    const [quickTrimOpen, setQuickTrimOpen] = useState(false);
    const [lastTrim, setLastTrim] = useState<{ url: string; name: string } | null>(null);
    const [brandTemplateId, setBrandTemplateId] = useState<string>('');
    const [templates, setTemplates] = useState<Array<{ id: string; name: string }>>([]);

    useEffect(() => {
        // Pull the set of Canva brand templates available to the user.
        // Templates without a `canva_brand_template_id` are skipped — they
        // belong to the old Polotno path and are being phased out.
        http.get(studio.endpoints.templates)
            .then((res) => {
                const list = (res.data?.templates ?? res.data ?? []) as Array<{
                    id: string | number;
                    name: string;
                    canva_brand_template_id?: string | null;
                }>;
                const canvaOnly = list
                    .filter((t) => Boolean(t.canva_brand_template_id))
                    .map((t) => ({ id: String(t.canva_brand_template_id), name: t.name }));
                setTemplates(canvaOnly);
                if (canvaOnly.length > 0 && !brandTemplateId) {
                    setBrandTemplateId(canvaOnly[0].id);
                }
            })
            .catch(() => {
                /* ignore — feature flag gates the button anyway. */
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [http, studio.endpoints.templates]);

    return (
        <StudioLayout
            title={briefId ? `Brief #${briefId}` : 'Brief i ri'}
            actions={
                <>
                    <button
                        type="button"
                        onClick={() => setQuickTrimOpen(true)}
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:border-violet-500"
                    >
                        ✂︎ Quick Trim
                    </button>
                    {studio.features.canva_connect && templates.length > 0 ? (
                        <select
                            value={brandTemplateId}
                            onChange={(e) => setBrandTemplateId(e.target.value)}
                            className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-xs text-zinc-200"
                            title="Zgjidh Canva brand template"
                        >
                            {templates.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    ) : null}
                    {brandTemplateId && (
                        <OpenInCanvaButton
                            http={http}
                            endpoints={studio.endpoints}
                            featureEnabled={studio.features.canva_connect}
                            brandTemplateId={brandTemplateId}
                            creativeBriefId={briefId ?? null}
                        />
                    )}
                    <VideoUploadButton
                        http={http}
                        endpoints={studio.endpoints}
                        limits={studio.limits}
                        creativeBriefId={briefId ?? null}
                        onQuickTrimRequested={(blob, name) => {
                            if (lastTrim?.url) URL.revokeObjectURL(lastTrim.url);
                            setLastTrim({ url: URL.createObjectURL(blob), name });
                            setQuickTrimOpen(true);
                        }}
                    />
                    <span className="text-xs text-zinc-500">AI</span>
                    <button
                        type="button"
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:border-violet-500"
                    >
                        Gjenero Caption
                    </button>
                    <button
                        type="button"
                        className="rounded-md bg-emerald-500 px-3 py-1.5 text-xs font-medium text-emerald-950 hover:bg-emerald-400"
                    >
                        Save
                    </button>
                </>
            }
            leftSidebar={
                <div className="p-3 text-xs text-zinc-400">
                    <div className="mb-2 font-semibold uppercase tracking-wider text-zinc-500">
                        Shtresat
                    </div>
                    <p className="text-zinc-600">
                        Foto/carousel → Canva Connect (butoni "Hap në Canva"). Video → CapCut (butoni "Upload video").
                    </p>
                </div>
            }
            rightSidebar={
                <div className="space-y-4 p-3 text-xs text-zinc-300">
                    <section>
                        <div className="mb-1 text-[10px] uppercase tracking-widest text-zinc-500">
                            Properties
                        </div>
                        <div className="space-y-1">
                            <Row label="Brief" value={briefId ?? '—'} />
                            <Row label="Brand voice" value={studio.brand_kit.voice_sq ? 'e vendosur' : '—'} />
                            <Row
                                label="Permissions"
                                value={studio.permissions['content_planner.edit'] ? 'edit' : 'view'}
                            />
                            <Row
                                label="Canva"
                                value={studio.features.canva_connect ? 'on' : 'off'}
                            />
                        </div>
                    </section>
                    {lastTrim ? (
                        <section>
                            <div className="mb-1 text-[10px] uppercase tracking-widest text-zinc-500">
                                Trim i fundit
                            </div>
                            <video src={lastTrim.url} controls className="w-full rounded bg-black" />
                            <div className="mt-1 truncate text-[11px] text-zinc-400">{lastTrim.name}</div>
                        </section>
                    ) : null}
                </div>
            }
            timeline={
                <div className="flex h-full items-center justify-center text-xs text-zinc-500">
                    Timeline për video/reel mount-ohet në #1244 kur aktivizohet CapCut upload.
                </div>
            }
        >
            <div className="flex h-full items-center justify-center text-sm text-zinc-500">
                {studio.features.canva_connect
                    ? 'Kliko "Hap në Canva" për të filluar një dizajn. Pas publikimit, ai vjen automatikisht këtu.'
                    : 'Pas integrimit me Canva Connect, dizajnet do të hapen këtu.'}
            </div>

            <QuickTrimModal
                open={quickTrimOpen}
                onClose={() => setQuickTrimOpen(false)}
                onTrimmed={(blob, name) => {
                    if (lastTrim?.url) URL.revokeObjectURL(lastTrim.url);
                    setLastTrim({ url: URL.createObjectURL(blob), name });
                }}
            />
        </StudioLayout>
    );
}

function Row({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="flex items-center justify-between border-b border-zinc-800 py-1">
            <span className="text-zinc-500">{label}</span>
            <span className="truncate text-zinc-200">{value}</span>
        </div>
    );
}
