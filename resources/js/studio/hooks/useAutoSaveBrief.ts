import { useCallback, useEffect, useRef, useState } from 'react';
import {
    CreativeBriefClient,
    type BriefUpdatePayload,
    type CreativeBriefPayload,
} from '@studio/services/creativeBriefClient';

/**
 * Debounced-save state manager for a single creative brief.
 *
 * Responsibilities:
 *   • Load the brief on mount (or when `briefId` changes).
 *   • Expose the loaded brief so the UI can hydrate — callers never hold
 *     a local mutable copy; they patch via `patch()`.
 *   • Persist user edits with a 2s trailing debounce against
 *     PUT /api/creative-briefs/{id}. Pending changes survive remounts as
 *     long as the parent keeps the hook alive.
 *   • Surface a coarse save status (idle / saving / saved / error) for a
 *     status pill, plus the exact error message when relevant.
 *
 * Explicit non-goals (callers can add later without changing this hook):
 *   • Two-tab conflict resolution — would require If-Match / updated_at
 *     on the server. Documented on #1248 as a follow-up.
 *   • Optimistic rollback on network errors — we keep the local edits and
 *     retry on the next change; user loses nothing on transient failures.
 */

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

interface UseAutoSaveBriefResult {
    brief: CreativeBriefPayload | null;
    loading: boolean;
    loadError: string | null;
    saveStatus: SaveStatus;
    saveError: string | null;
    lastSavedAt: Date | null;
    patch: (payload: BriefUpdatePayload) => void;
    flush: () => Promise<void>;
    reload: () => Promise<void>;
}

export function useAutoSaveBrief(
    client: CreativeBriefClient,
    briefId: number | string | null,
    options: { debounceMs?: number } = {},
): UseAutoSaveBriefResult {
    const debounceMs = options.debounceMs ?? 2000;

    const [brief, setBrief] = useState<CreativeBriefPayload | null>(null);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [saveError, setSaveError] = useState<string | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

    const pendingRef = useRef<BriefUpdatePayload>({});
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const inflightRef = useRef<Promise<void> | null>(null);

    const reload = useCallback(async () => {
        if (briefId === null || briefId === undefined || briefId === '') {
            setBrief(null);
            return;
        }

        setLoading(true);
        setLoadError(null);
        try {
            const loaded = await client.load(briefId);
            setBrief(loaded);
        } catch (e) {
            setLoadError(friendlyMessage(e));
        } finally {
            setLoading(false);
        }
    }, [client, briefId]);

    useEffect(() => {
        void reload();
    }, [reload]);

    const flush = useCallback(async () => {
        if (briefId === null || briefId === undefined || briefId === '') return;
        if (Object.keys(pendingRef.current).length === 0) return;

        const payload = pendingRef.current;
        pendingRef.current = {};

        setSaveStatus('saving');
        setSaveError(null);

        const run = (async () => {
            try {
                const updated = await client.update(briefId, payload);
                setBrief(updated);
                setLastSavedAt(new Date());
                setSaveStatus('saved');
            } catch (e) {
                // Put the work back so a retry picks it up.
                pendingRef.current = { ...payload, ...pendingRef.current };
                setSaveError(friendlyMessage(e));
                setSaveStatus('error');
            }
        })();

        inflightRef.current = run;
        await run;
        inflightRef.current = null;
    }, [client, briefId]);

    const patch = useCallback(
        (payload: BriefUpdatePayload) => {
            // Locally reflect the change so the UI never lags behind user input.
            setBrief((prev) => (prev ? { ...prev, ...payload } : prev));
            pendingRef.current = { ...pendingRef.current, ...payload };

            if (timerRef.current) clearTimeout(timerRef.current);
            timerRef.current = setTimeout(() => {
                void flush();
            }, debounceMs);
        },
        [debounceMs, flush],
    );

    // On unmount, try to flush any pending edit so we don't silently drop
    // changes when the user navigates away inside the SPA.
    useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
            if (Object.keys(pendingRef.current).length > 0) {
                void flush();
            }
        };
    }, [flush]);

    return {
        brief,
        loading,
        loadError,
        saveStatus,
        saveError,
        lastSavedAt,
        patch,
        flush,
        reload,
    };
}

function friendlyMessage(e: unknown): string {
    if (e && typeof e === 'object' && 'response' in e) {
        const res = (e as { response?: { data?: { message?: string }, status?: number } }).response;
        if (res?.data?.message) return res.data.message;
        if (res?.status) return `HTTP ${res.status}`;
    }
    if (e instanceof Error) return e.message;
    return 'Dështoi kërkesa.';
}
