/**
 * Shape of the `data-props` JSON blob Laravel hands us on page load.
 *
 * Keeping this in one place makes it cheap to add fields (AI quota, beta
 * flags, etc.) without chasing types across the SPA.
 */
export interface StudioEndpoints {
    brand_kit: string;
    templates: string;
    creative_briefs: string;
    ai_caption: string;
    ai_rewrite: string;
    canva_authorize: string;
    canva_disconnect: string;
    canva_status: string;
    canva_designs: string;
    /** URL template with `__ID__` placeholder for the design id. */
    canva_design_show: string;
    /** URL template with `__ID__` placeholder for the design id. */
    canva_design_export: string;
    /** URL template with `__ID__` placeholder for the export job id. */
    canva_export_status: string;
    canva_brand_sync: string;
    /** URL template with `__ID__` placeholder for the creative brief id. */
    canva_attach_brief: string;
    /** URL template with `__ID__` placeholder for the creative brief id. */
    upload_video_brief: string;
    /** URL template with `__ID__` placeholder for the creative brief id. */
    upload_photo_brief: string;
}

export interface StudioFeatures {
    canva_connect: boolean;
}

export interface StudioLimits {
    video_max_size_mb: number;
    photo_max_size_mb: number;
}

export interface StudioUser {
    id: number | null;
    name: string;
    email: string | null;
}

export interface StudioPermissions {
    'content_planner.view': boolean;
    'content_planner.create': boolean;
    'content_planner.edit': boolean;
    'content_planner.manage': boolean;
}

export interface BrandKitColors {
    primary?: string;
    secondary?: string;
    accent?: string;
    neutral?: string;
    text?: string;
}

export interface BrandKitPayload {
    id: number;
    colors: BrandKitColors | null;
    typography: Record<string, unknown> | null;
    logo_variants: Record<string, unknown> | null;
    watermark: Record<string, unknown> | null;
    voice_sq: string | null;
    voice_en: string | null;
    caption_templates: Record<string, unknown> | null;
    default_hashtags: string[] | null;
    music_library: unknown[] | null;
    aspect_defaults: unknown[] | null;
}

export interface StudioProps {
    brand_kit: BrandKitPayload;
    creative_brief_id: number | null;
    user: StudioUser;
    permissions: StudioPermissions;
    csrf_token: string;
    endpoints: StudioEndpoints;
    features: StudioFeatures;
    limits: StudioLimits;
}
