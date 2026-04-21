import { useState } from 'react';
import type { AxiosInstance } from 'axios';
import type { StudioEndpoints } from '@studio/types/props';
import type { CreativeBriefPayload, PostType } from '@studio/services/creativeBriefClient';

type Language = 'sq' | 'en' | 'both';

interface AiCaptionButtonsProps {
    http: AxiosInstance;
    endpoints: StudioEndpoints;
    brief: CreativeBriefPayload | null;
    onCaptionGenerated: (payload: { caption_sq: string | null; caption_en: string | null; hashtags: string[] }) => void;
    onRewriteCompleted: (language: 'sq' | 'en', text: string) => void;
}

/**
 * AI Light controls: "Gjenero Caption" + "Rewrite" (#1249).
 *
 * Generate hits POST /marketing/api/ai/caption with the brief's product
 * context (populated on `show` as primary_item_group_id). Rewrite hits
 * POST /marketing/api/ai/rewrite on the current caption_sq or caption_en.
 *
 * Both surfaces are disabled when the pre-conditions aren't met (no
 * product id, empty caption) so the user never clicks into a 422.
 */
export function AiCaptionButtons({ http, endpoints, brief, onCaptionGenerated, onRewriteCompleted }: AiCaptionButtonsProps) {
    const [language, setLanguage] = useState<Language>('both');
    const [generating, setGenerating] = useState(false);
    const [rewritingSq, setRewritingSq] = useState(false);
    const [rewritingEn, setRewritingEn] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const productId = brief?.primary_item_group_id ?? null;
    const postType: PostType | null = brief?.post_type ?? null;

    async function generate() {
        if (!productId || !postType) return;
        setError(null);
        setGenerating(true);
        try {
            const { data } = await http.post<{ caption_sq: string | null; caption_en: string | null; hashtags: string[] }>(
                endpoints.ai_caption,
                { product_id: productId, post_type: postType, language },
            );
            onCaptionGenerated({
                caption_sq: data.caption_sq,
                caption_en: data.caption_en,
                hashtags: data.hashtags ?? [],
            });
        } catch (e) {
            setError(friendly(e));
        } finally {
            setGenerating(false);
        }
    }

    async function rewrite(lang: 'sq' | 'en') {
        const current = lang === 'sq' ? brief?.caption_sq : brief?.caption_en;
        if (!current) return;
        setError(null);
        lang === 'sq' ? setRewritingSq(true) : setRewritingEn(true);
        try {
            const { data } = await http.post<{ text: string }>(
                endpoints.ai_rewrite,
                { text: current, language: lang, tone: 'brand' },
            );
            onRewriteCompleted(lang, data.text);
        } catch (e) {
            setError(friendly(e));
        } finally {
            lang === 'sq' ? setRewritingSq(false) : setRewritingEn(false);
        }
    }

    const canGenerate = Boolean(productId && postType && !generating);

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2">
                <span className="text-[10px] uppercase tracking-widest text-zinc-500">AI</span>
                <select
                    value={language}
                    onChange={(e) => setLanguage(e.target.value as Language)}
                    className="rounded border border-zinc-800 bg-zinc-900 px-1.5 py-0.5 text-[11px] text-zinc-200"
                    disabled={generating}
                >
                    <option value="both">sq + en</option>
                    <option value="sq">vetëm sq</option>
                    <option value="en">only en</option>
                </select>
                <button
                    type="button"
                    onClick={generate}
                    disabled={!canGenerate}
                    className="rounded-md border border-violet-700 bg-violet-900/40 px-2 py-1 text-[11px] font-medium text-violet-200 hover:bg-violet-900 disabled:cursor-not-allowed disabled:opacity-50"
                    title={productId ? 'Gjenero caption + hashtags me AI' : 'Brief-i duhet të jetë i lidhur me një produkt për AI'}
                >
                    {generating ? '◉ Po mendon…' : '✦ Gjenero Caption'}
                </button>
            </div>

            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => void rewrite('sq')}
                    disabled={!brief?.caption_sq || rewritingSq}
                    className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[10px] text-zinc-300 hover:border-violet-600 disabled:opacity-40"
                    title="Rishkruaj caption-in sq me Claude"
                >
                    {rewritingSq ? '…' : 'Rewrite sq'}
                </button>
                <button
                    type="button"
                    onClick={() => void rewrite('en')}
                    disabled={!brief?.caption_en || rewritingEn}
                    className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 text-[10px] text-zinc-300 hover:border-violet-600 disabled:opacity-40"
                    title="Rewrite English caption with Claude"
                >
                    {rewritingEn ? '…' : 'Rewrite en'}
                </button>
                {brief?.primary_item_group_name ? (
                    <span className="truncate text-[10px] text-zinc-500">
                        Produkti: {brief.primary_item_group_name}
                    </span>
                ) : (
                    <span className="text-[10px] text-zinc-600">S'ka produkt të lidhur.</span>
                )}
            </div>

            {error ? <div className="text-[11px] text-rose-400">{error}</div> : null}
        </div>
    );
}

function friendly(e: unknown): string {
    if (e && typeof e === 'object' && 'response' in e) {
        const res = (e as { response?: { status?: number; data?: { message?: string } } }).response;
        if (res?.data?.message) return res.data.message;
        if (res?.status === 429) return 'Quota e AI u arrit. Prit një minutë.';
        if (res?.status) return `HTTP ${res.status}`;
    }
    if (e instanceof Error) return e.message;
    return 'AI dështoi.';
}
