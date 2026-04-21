import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

/**
 * Minimal in-app toast system for the Studio SPA.
 *
 * Motivation: pivot buttons (Canva, CapCut upload, AI caption) perform
 * slow network actions; the user needs positive confirmation when they
 * succeed and a clear surface for recoverable errors. Inline messages
 * per-component already cover the "stuck" states — toasts are the
 * punctuation at the end of a successful or failed action.
 *
 * Keep dependencies to zero. No react-hot-toast, sonner, or friends —
 * we render a fixed container and push into an array.
 */

export type ToastKind = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    kind: ToastKind;
    message: string;
    /** Optional TTL in ms; defaults to 4000 for success/info, 6000 for error. */
    ttl?: number;
}

interface ToastContextValue {
    notify: (input: Omit<Toast, 'id'>) => number;
    dismiss: (id: number) => void;
    success: (message: string, ttl?: number) => number;
    error: (message: string, ttl?: number) => number;
    info: (message: string, ttl?: number) => number;
}

const ToastContext = createContext<ToastContextValue | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const idRef = useRef(1);

    const dismiss = useCallback((id: number) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const notify = useCallback(
        (input: Omit<Toast, 'id'>) => {
            const id = idRef.current++;
            const ttl = input.ttl ?? (input.kind === 'error' ? 6000 : 4000);
            setToasts((prev) => [...prev, { ...input, id }]);
            window.setTimeout(() => dismiss(id), ttl);
            return id;
        },
        [dismiss],
    );

    const value = useMemo<ToastContextValue>(
        () => ({
            notify,
            dismiss,
            success: (message, ttl) => notify({ kind: 'success', message, ttl }),
            error: (message, ttl) => notify({ kind: 'error', message, ttl }),
            info: (message, ttl) => notify({ kind: 'info', message, ttl }),
        }),
        [notify, dismiss],
    );

    return (
        <ToastContext.Provider value={value}>
            {children}
            <div
                className="pointer-events-none fixed right-4 top-4 z-[9999] flex max-w-sm flex-col gap-2"
                aria-live="polite"
                aria-atomic="false"
            >
                {toasts.map((t) => (
                    <ToastItem key={t.id} toast={t} onDismiss={() => dismiss(t.id)} />
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastContextValue {
    const ctx = useContext(ToastContext);
    if (!ctx) {
        throw new Error('useToast must be used inside a <ToastProvider />');
    }
    return ctx;
}

function ToastItem({ toast, onDismiss }: { toast: Toast; onDismiss: () => void }) {
    // Small fade-in on mount (CSS transition via mounted state).
    const [visible, setVisible] = useState(false);
    useEffect(() => {
        const t = window.setTimeout(() => setVisible(true), 10);
        return () => window.clearTimeout(t);
    }, []);

    const palette =
        toast.kind === 'success'
            ? 'border-emerald-600 bg-emerald-900/80 text-emerald-100'
            : toast.kind === 'error'
                ? 'border-rose-600 bg-rose-900/85 text-rose-100'
                : 'border-zinc-600 bg-zinc-900/85 text-zinc-100';

    const icon = toast.kind === 'success' ? '✓' : toast.kind === 'error' ? '✕' : 'ⓘ';

    return (
        <div
            role={toast.kind === 'error' ? 'alert' : 'status'}
            className={[
                'pointer-events-auto flex items-start gap-3 rounded-md border px-3 py-2 text-xs shadow-lg backdrop-blur',
                palette,
                visible ? 'translate-x-0 opacity-100' : 'translate-x-4 opacity-0',
                'transition-all duration-150',
            ].join(' ')}
        >
            <span className="text-sm leading-none">{icon}</span>
            <span className="flex-1 whitespace-pre-line">{toast.message}</span>
            <button
                type="button"
                onClick={onDismiss}
                aria-label="Mbyll"
                className="ml-2 shrink-0 rounded px-1 text-xs leading-none opacity-60 hover:opacity-100"
            >
                ×
            </button>
        </div>
    );
}
