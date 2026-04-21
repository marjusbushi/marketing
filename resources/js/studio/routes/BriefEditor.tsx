import { useEffect, useMemo, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { StudioLayout } from '@studio/components/Layout';
import { QuickTrimModal } from '@studio/components/QuickTrimModal';
import { OpenInCanvaButton } from '@studio/components/OpenInCanvaButton';
import { VideoUploadButton } from '@studio/components/VideoUploadButton';
import { PhotoUploadButton } from '@studio/components/PhotoUploadButton';
import { AiCaptionButtons } from '@studio/components/AiCaptionButtons';
import { useToast } from '@studio/components/ToastHost';
import { createApiClient } from '@studio/services/api';
import { CreativeBriefClient, type CapcutStateEntry, type CanvaStateEntry, type PhotoStateEntry } from '@studio/services/creativeBriefClient';
import { useAutoSaveBrief, type SaveStatus } from '@studio/hooks/useAutoSaveBrief';
import { StudioProps } from '@studio/types/props';

interface BriefEditorProps {
    studio: StudioProps;
}

/**
 * Visual Studio brief editor.
 *
 * Post-pivot (Decision #14): no Polotno/Remotion mount. Photo/carousel/
 * story work happens in Canva via the "Open in Canva" button; video
 * work happens in CapCut via the upload + Quick Trim path.
 *
 * This shell is the single place where editor state is loaded and
 * saved. Loading and 2s-debounced saving are delegated to
 * `useAutoSaveBrief`; the UI only hydrates from the returned `brief`
 * payload and calls `patch(...)` on field edits.
 */
export function BriefEditor({ studio }: BriefEditorProps) {
    const { id } = useParams<{ id: string }>();
    const briefId = id ?? (studio.creative_brief_id ? String(studio.creative_brief_id) : null);

    const http = useMemo(() => createApiClient(studio.csrf_token), [studio.csrf_token]);
    const briefClient = useMemo(() => new CreativeBriefClient(http, studio.endpoints), [http, studio.endpoints]);

    const {
        brief,
        loading,
        loadError,
        saveStatus,
        saveError,
        lastSavedAt,
        patch,
        reload,
    } = useAutoSaveBrief(briefClient, briefId);

    // Toast once on each transition into the error state. Without the
    // ref guard the pill and the toast would double-fire on every render.
    const toast = useToast();
    const lastSaveStatusRef = useRef<SaveStatus>('idle');
    useEffect(() => {
        if (saveStatus === 'error' && lastSaveStatusRef.current !== 'error' && saveError) {
            toast.error('Ruajtja dështoi: ' + saveError);
        }
        lastSaveStatusRef.current = saveStatus;
    }, [saveStatus, saveError, toast]);

    const [quickTrimOpen, setQuickTrimOpen] = useState(false);
    const [lastTrim, setLastTrim] = useState<{ url: string; name: string } | null>(null);
    const [brandTemplateId, setBrandTemplateId] = useState<string>('');
    const [templates, setTemplates] = useState<Array<{ id: string; name: string }>>([]);

    useEffect(() => {
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

    const canvaAttached: CanvaStateEntry | null = brief?.state?.canva ?? null;
    const capcutAttached: CapcutStateEntry[] = brief?.state?.capcut ?? [];
    const photosAttached: PhotoStateEntry[] = brief?.state?.photos ?? [];

    return (
        <StudioLayout
            title={briefId ? `Brief #${briefId}` : 'Brief i ri'}
            actions={
                <>
                    <SaveStatusPill status={saveStatus} lastSavedAt={lastSavedAt} error={saveError} />
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
                            onAttached={() => { void reload(); }}
                        />
                    )}
                    <PhotoUploadButton
                        http={http}
                        endpoints={studio.endpoints}
                        limits={studio.limits}
                        creativeBriefId={briefId ?? null}
                        onUploaded={() => { void reload(); }}
                    />
                    <VideoUploadButton
                        http={http}
                        endpoints={studio.endpoints}
                        limits={studio.limits}
                        creativeBriefId={briefId ?? null}
                        onUploaded={() => { void reload(); }}
                        onQuickTrimRequested={(blob, name) => {
                            if (lastTrim?.url) URL.revokeObjectURL(lastTrim.url);
                            setLastTrim({ url: URL.createObjectURL(blob), name });
                            setQuickTrimOpen(true);
                        }}
                    />
                </>
            }
            leftSidebar={
                <div className="p-3 text-xs text-zinc-400">
                    <div className="mb-2 font-semibold uppercase tracking-wider text-zinc-500">
                        Media e lidhura
                    </div>
                    {!brief && !loading && !loadError ? (
                        <p className="text-zinc-600">Brief i ri — ngarko foto ose video për të filluar.</p>
                    ) : null}
                    {loading ? <p className="text-zinc-500">Duke ngarkuar…</p> : null}
                    {loadError ? <p className="text-rose-400">{loadError}</p> : null}

                    {canvaAttached ? (
                        <section className="mb-3 rounded-md border border-zinc-800 bg-zinc-900/50 p-2">
                            <div className="mb-1 text-[10px] uppercase tracking-widest text-violet-400">Canva</div>
                            {canvaAttached.thumbnail_url ? (
                                <img
                                    src={canvaAttached.thumbnail_url}
                                    alt=""
                                    className="mb-2 w-full rounded object-cover"
                                />
                            ) : null}
                            <a
                                href={canvaAttached.asset_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block truncate text-[11px] text-violet-300 underline"
                            >
                                {canvaAttached.format.toUpperCase()} · open asset
                            </a>
                            <div className="mt-1 text-[10px] text-zinc-500">
                                {new Date(canvaAttached.attached_at).toLocaleString()}
                            </div>
                        </section>
                    ) : null}

                    {photosAttached.length > 0 ? (
                        <section className="mb-3">
                            <div className="mb-1 text-[10px] uppercase tracking-widest text-sky-400">
                                Foto ({photosAttached.length})
                            </div>
                            <ul className="space-y-2">
                                {photosAttached.map((slot, idx) => (
                                    <li key={idx} className="rounded-md border border-zinc-800 bg-zinc-900/50 p-2">
                                        <img
                                            src={toPublicUrl(slot.path)}
                                            alt=""
                                            className="mb-1 h-20 w-full rounded object-cover"
                                        />
                                        <div className="text-[11px] text-zinc-300">
                                            {slot.width ?? '—'}×{slot.height ?? '—'}
                                        </div>
                                        <div className="text-[10px] text-zinc-500">{formatBytes(slot.size_bytes)}</div>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}

                    {capcutAttached.length > 0 ? (
                        <section>
                            <div className="mb-1 text-[10px] uppercase tracking-widest text-emerald-400">
                                CapCut ({capcutAttached.length})
                            </div>
                            <ul className="space-y-2">
                                {capcutAttached.map((slot, idx) => (
                                    <li key={idx} className="rounded-md border border-zinc-800 bg-zinc-900/50 p-2">
                                        {slot.thumbnail_path ? (
                                            <img
                                                src={toPublicUrl(slot.thumbnail_path)}
                                                alt=""
                                                className="mb-1 h-16 w-full rounded object-cover"
                                            />
                                        ) : null}
                                        <div className="text-[11px] text-zinc-300">
                                            {slot.duration_seconds ?? '—'}s · {slot.width}×{slot.height}
                                        </div>
                                        <div className="text-[10px] text-zinc-500">{formatBytes(slot.size_bytes)}</div>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}
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
                            <Row label="Post type" value={brief?.post_type ?? '—'} />
                            <Row label="Aspect" value={brief?.aspect ?? '—'} />
                            <Row label="Duration" value={brief?.duration_sec ? `${brief.duration_sec}s` : '—'} />
                        </div>
                    </section>

                    <section>
                        <div className="mb-1 text-[10px] uppercase tracking-widest text-zinc-500">
                            Caption
                        </div>
                        <textarea
                            value={brief?.caption_sq ?? ''}
                            onChange={(e) => patch({ caption_sq: e.target.value })}
                            placeholder="Caption në shqip…"
                            rows={4}
                            className="w-full rounded border border-zinc-800 bg-zinc-900 p-2 text-zinc-200 outline-none focus:border-violet-500"
                            disabled={!brief || !studio.permissions['content_planner.edit']}
                        />
                        <textarea
                            value={brief?.caption_en ?? ''}
                            onChange={(e) => patch({ caption_en: e.target.value })}
                            placeholder="Caption in English…"
                            rows={3}
                            className="mt-2 w-full rounded border border-zinc-800 bg-zinc-900 p-2 text-zinc-200 outline-none focus:border-violet-500"
                            disabled={!brief || !studio.permissions['content_planner.edit']}
                        />

                        {studio.permissions['content_planner.edit'] ? (
                            <div className="mt-2">
                                <AiCaptionButtons
                                    http={http}
                                    endpoints={studio.endpoints}
                                    brief={brief}
                                    onCaptionGenerated={({ caption_sq, caption_en, hashtags }) => {
                                        patch({
                                            caption_sq: caption_sq ?? brief?.caption_sq ?? null,
                                            caption_en: caption_en ?? brief?.caption_en ?? null,
                                            hashtags: hashtags && hashtags.length > 0 ? hashtags : brief?.hashtags ?? null,
                                        });
                                    }}
                                    onRewriteCompleted={(language, text) => {
                                        patch(language === 'sq' ? { caption_sq: text } : { caption_en: text });
                                    }}
                                />
                            </div>
                        ) : null}

                        {brief?.hashtags && brief.hashtags.length > 0 ? (
                            <div className="mt-2 flex flex-wrap gap-1">
                                {brief.hashtags.map((tag, i) => (
                                    <span key={i} className="rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] text-zinc-300">
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        ) : null}
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
                <div className="flex h-full items-center justify-center gap-4 text-xs text-zinc-500">
                    {photosAttached.length > 0 ? (
                        <span>{photosAttached.length} foto</span>
                    ) : null}
                    {capcutAttached.length > 0 ? (
                        <span>{capcutAttached.length} video</span>
                    ) : null}
                    {photosAttached.length === 0 && capcutAttached.length === 0 ? (
                        <span>Asnjë media e ngarkuar ende.</span>
                    ) : null}
                </div>
            }
        >
            <div className="flex h-full flex-col items-center justify-center gap-2 text-sm text-zinc-500">
                {canvaAttached?.thumbnail_url ? (
                    <>
                        <img
                            src={canvaAttached.thumbnail_url}
                            alt=""
                            className="max-h-[70%] max-w-[70%] rounded shadow-lg"
                        />
                        <p className="text-zinc-400">Dizajni i ngarkuar nga Canva. Kliko "Hap në Canva" për ta përditësuar.</p>
                    </>
                ) : photosAttached.length > 0 ? (
                    <>
                        <img
                            src={toPublicUrl(photosAttached[0].path)}
                            alt=""
                            className="max-h-[70%] max-w-[70%] rounded shadow-lg"
                        />
                        <p className="text-zinc-400">
                            {photosAttached.length === 1
                                ? 'Foto e ngarkuar.'
                                : `${photosAttached.length} foto të ngarkuara (carousel).`}
                        </p>
                    </>
                ) : capcutAttached.length > 0 && capcutAttached[0].thumbnail_path ? (
                    <>
                        <img
                            src={toPublicUrl(capcutAttached[0].thumbnail_path)}
                            alt=""
                            className="max-h-[70%] max-w-[70%] rounded shadow-lg"
                        />
                        <p className="text-zinc-400">{capcutAttached.length} video e ngarkuar nga CapCut.</p>
                    </>
                ) : (
                    <p>Ngarko foto (PNG/JPG) ose video (MP4 nga CapCut) për të filluar.</p>
                )}
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

function SaveStatusPill({
    status,
    lastSavedAt,
    error,
}: {
    status: SaveStatus;
    lastSavedAt: Date | null;
    error: string | null;
}) {
    const base = 'flex items-center gap-1 rounded-md border px-2 py-1 text-[11px]';
    if (status === 'saving') {
        return <span className={`${base} border-amber-700 bg-amber-900/30 text-amber-200`} title="Duke ruajtur…">◉ Duke ruajtur</span>;
    }
    if (status === 'error') {
        return <span className={`${base} border-rose-700 bg-rose-900/30 text-rose-200`} title={error ?? ''}>✕ Dështoi</span>;
    }
    if (status === 'saved') {
        const when = lastSavedAt ? lastSavedAt.toLocaleTimeString() : '';
        return <span className={`${base} border-emerald-700 bg-emerald-900/30 text-emerald-200`} title={`U ruajt ${when}`}>✓ Ruajtur</span>;
    }
    return <span className={`${base} border-zinc-700 bg-zinc-900 text-zinc-500`}>— Pa ndryshime</span>;
}

function toPublicUrl(path: string): string {
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path.replace(/^\/+/, '')}`;
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}
