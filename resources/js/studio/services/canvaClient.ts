import type { AxiosInstance } from 'axios';
import type { StudioEndpoints } from '@studio/types/props';

/**
 * Typed wrapper around the Canva flow endpoints. Keeps the component
 * layer free of URL manipulation and retry/backoff bookkeeping.
 *
 * The server issues URL templates with an `__ID__` placeholder for the
 * dynamic segments (design id, export job id, creative brief id). We
 * substitute at call time — avoids us building URLs client-side.
 */

export interface CanvaStatusResponse {
    connected: boolean;
    feature_enabled: boolean;
    canva_user_id?: string | null;
    canva_display_name?: string | null;
    expires_at?: string | null;
    expired?: boolean;
}

export interface CanvaDesignPayload {
    design?: {
        id: string;
        title?: string;
        urls?: { edit_url?: string; view_url?: string };
        thumbnail?: { url?: string };
    };
    // Canva's autofill endpoint wraps the design object this way today.
    job?: { status: string; id?: string };
    [key: string]: unknown;
}

export interface CanvaExportJob {
    job: {
        id: string;
        status: 'in_progress' | 'success' | 'failed' | string;
        urls?: string[];
        error?: { code?: string; message?: string };
    };
}

export class CanvaClient {
    constructor(
        private readonly http: AxiosInstance,
        private readonly endpoints: StudioEndpoints,
    ) {}

    async status(): Promise<CanvaStatusResponse> {
        const { data } = await this.http.get<CanvaStatusResponse>(this.endpoints.canva_status);
        return data;
    }

    /** Full-page OAuth redirect — pops us out of the SPA until callback. */
    redirectToAuthorize(): void {
        window.location.assign(this.endpoints.canva_authorize);
    }

    async disconnect(): Promise<void> {
        await this.http.post(this.endpoints.canva_disconnect);
    }

    async createDesign(brandTemplateId: string, fields: Record<string, unknown> = {}): Promise<CanvaDesignPayload> {
        const { data } = await this.http.post<CanvaDesignPayload>(this.endpoints.canva_designs, {
            brand_template_id: brandTemplateId,
            fields,
        });
        return data;
    }

    async getDesign(designId: string): Promise<CanvaDesignPayload> {
        const url = this.endpoints.canva_design_show.replace('__ID__', encodeURIComponent(designId));
        const { data } = await this.http.get<CanvaDesignPayload>(url);
        return data;
    }

    async startExport(designId: string, format: 'png' | 'jpg' | 'pdf' = 'png'): Promise<CanvaExportJob> {
        const url = this.endpoints.canva_design_export.replace('__ID__', encodeURIComponent(designId));
        const { data } = await this.http.post<CanvaExportJob>(url, { format });
        return data;
    }

    async getExport(jobId: string): Promise<CanvaExportJob> {
        const url = this.endpoints.canva_export_status.replace('__ID__', encodeURIComponent(jobId));
        const { data } = await this.http.get<CanvaExportJob>(url);
        return data;
    }

    /**
     * Block until an export job finishes or the budget runs out.
     * Backoff mirrors the server's `canva.polling` defaults.
     */
    async waitForExport(
        jobId: string,
        { initialDelayMs = 3000, maxAttempts = 15, backoffFactor = 1.5, maxDelayMs = 15000 } = {},
    ): Promise<CanvaExportJob> {
        let delay = initialDelayMs;

        for (let attempt = 0; attempt < maxAttempts; attempt++) {
            const result = await this.getExport(jobId);

            if (result.job.status === 'success' || result.job.status === 'failed') {
                return result;
            }

            await new Promise((r) => setTimeout(r, delay));
            delay = Math.min(delay * backoffFactor, maxDelayMs);
        }

        throw new Error('Canva export timed out after ' + maxAttempts + ' polls');
    }

    async attachToBrief(briefId: number | string, payload: {
        design_id: string;
        asset_url: string;
        thumbnail_url?: string | null;
        format: 'png' | 'jpg' | 'pdf';
    }): Promise<void> {
        const url = this.endpoints.canva_attach_brief.replace('__ID__', encodeURIComponent(String(briefId)));
        await this.http.post(url, payload);
    }

    async syncBrandKit(): Promise<{ colors: number; logos: number; errors: string[] }> {
        const { data } = await this.http.post<{ colors: number; logos: number; errors: string[] }>(
            this.endpoints.canva_brand_sync,
        );
        return data;
    }
}
