import type { AxiosInstance } from 'axios';
import type { StudioEndpoints } from '@studio/types/props';

/**
 * Typed wrapper around the creative-brief API. Pulled out of BriefEditor
 * so the component stays focused on UI and the save/load contract lives
 * in one place.
 *
 * `state` is the canonical JSON blob that carries the editor's full
 * context (currently: Canva design refs and CapCut upload slots). It is
 * always returned by the show endpoint and accepted by update.
 */

export type PostType = 'photo' | 'carousel' | 'reel' | 'video' | 'story';

export interface CanvaStateEntry {
    design_id: string;
    asset_url: string;
    thumbnail_url?: string | null;
    format: 'png' | 'jpg' | 'pdf';
    attached_at: string;
}

export interface CapcutStateEntry {
    kind: 'video';
    source: 'capcut';
    path: string;
    thumbnail_path?: string | null;
    duration_seconds: number | null;
    width: number | null;
    height: number | null;
    mime_type: string;
    size_bytes: number;
    media_id: number | null;
    uploaded_at: string;
}

export interface PhotoStateEntry {
    kind: 'photo';
    source: 'upload';
    path: string;
    width: number | null;
    height: number | null;
    mime_type: string;
    size_bytes: number;
    media_id: number | null;
    uploaded_at: string;
}

export interface MediaSlot {
    kind: string;
    [key: string]: unknown;
}

export interface CreativeBriefPayload {
    id: number;
    daily_basket_post_id: number | null;
    template_id: number | null;
    template_slug: string | null;
    post_type: PostType;
    aspect: string | null;
    duration_sec: number | null;
    caption_sq: string | null;
    caption_en: string | null;
    hashtags: string[] | null;
    music_id: string | null;
    script: unknown[] | null;
    media_slots: MediaSlot[] | null;
    suggested_time: string | null;
    source: string;
    ai_prompt_version: string | null;
    state: BriefState | null;
    created_at: string | null;
    updated_at: string | null;
    /** Populated only by `show` — first item group on the linked post. */
    primary_item_group_id?: number | null;
    primary_item_group_name?: string | null;
}

export interface BriefState {
    canva?: CanvaStateEntry | null;
    capcut?: CapcutStateEntry[];
    photos?: PhotoStateEntry[];
    editor?: Record<string, unknown>;
    [key: string]: unknown;
}

export interface BriefUpdatePayload {
    aspect?: string | null;
    duration_sec?: number | null;
    caption_sq?: string | null;
    caption_en?: string | null;
    hashtags?: string[] | null;
    music_id?: string | null;
    script?: unknown[] | null;
    media_slots?: MediaSlot[] | null;
    suggested_time?: string | null;
    ai_prompt_version?: string | null;
    state?: BriefState | null;
}

export class CreativeBriefClient {
    constructor(
        private readonly http: AxiosInstance,
        private readonly endpoints: StudioEndpoints,
    ) {}

    async load(briefId: number | string): Promise<CreativeBriefPayload> {
        const url = `${this.endpoints.creative_briefs}/${encodeURIComponent(String(briefId))}`;
        const { data } = await this.http.get<{ creative_brief: CreativeBriefPayload }>(url);
        return data.creative_brief;
    }

    async update(briefId: number | string, payload: BriefUpdatePayload): Promise<CreativeBriefPayload> {
        const url = `${this.endpoints.creative_briefs}/${encodeURIComponent(String(briefId))}`;
        const { data } = await this.http.put<{ creative_brief: CreativeBriefPayload }>(url, payload);
        return data.creative_brief;
    }
}
