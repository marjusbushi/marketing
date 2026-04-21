import { useParams } from 'react-router-dom';
import { StudioLayout } from '@studio/components/Layout';
import { StudioProps } from '@studio/types/props';

interface BriefEditorProps {
    studio: StudioProps;
}

/**
 * Placeholder route that will mount Polotno (photo/carousel/story) or
 * Remotion + RVE (reel/video) in tasks #1243/#1244. For now it renders
 * the shell so the full-screen layout, routing, and prop propagation
 * are testable end-to-end.
 */
export function BriefEditor({ studio }: BriefEditorProps) {
    const { id } = useParams<{ id: string }>();
    const briefId = id ?? studio.creative_brief_id;

    return (
        <StudioLayout
            title={briefId ? `Brief #${briefId}` : 'Brief i ri'}
            actions={
                <>
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
                        Editor i foto-s (Polotno) ose videos (Remotion) mount-ohet në tasks #1243/#1244.
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
                        </div>
                    </section>
                    <section>
                        <div className="mb-1 text-[10px] uppercase tracking-widest text-zinc-500">
                            API endpoints
                        </div>
                        <ul className="space-y-1 text-[11px] text-zinc-500">
                            <li>POST {new URL(studio.endpoints.ai_caption, window.location.origin).pathname}</li>
                            <li>PUT {new URL(studio.endpoints.creative_briefs, window.location.origin).pathname}/{briefId ?? '{id}'}</li>
                        </ul>
                    </section>
                </div>
            }
            timeline={
                <div className="flex h-full items-center justify-center text-xs text-zinc-500">
                    Timeline për reel/video do të mount-ohet në #1244
                </div>
            }
        >
            <div className="flex h-full items-center justify-center text-sm text-zinc-500">
                Canvas për Polotno (#1243) / Remotion Player (#1244) vjen këtu.
            </div>
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
