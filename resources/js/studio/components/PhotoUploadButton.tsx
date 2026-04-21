import { useRef, useState } from 'react';
import type { AxiosInstance, AxiosProgressEvent } from 'axios';
import { useToast } from '@studio/components/ToastHost';
import type { StudioEndpoints, StudioLimits } from '@studio/types/props';

interface PhotoUploadButtonProps {
    http: AxiosInstance;
    endpoints: StudioEndpoints;
    limits: StudioLimits;
    creativeBriefId: number | string | null;
    onUploaded?: (payload: UploadedPhotoSlot) => void;
}

export interface UploadedPhotoSlot {
    kind: 'photo';
    source: 'upload';
    disk: string;
    path: string;
    width: number | null;
    height: number | null;
    mime_type: string;
    size_bytes: number;
    media_id: number | null;
    uploaded_at: string;
}

/**
 * Rruga C fallback: direct photo upload when Canva brand-template flow
 * isn't available. Mirrors VideoUploadButton's UX (drag-drop + picker +
 * progress) but skips the HTML5-video probe — for images we just read
 * naturalWidth/Height from an off-DOM Image element.
 */
export function PhotoUploadButton({
    http,
    endpoints,
    limits,
    creativeBriefId,
    onUploaded,
}: PhotoUploadButtonProps) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const toast = useToast();

    const [stage, setStage] = useState<'idle' | 'uploading' | 'done'>('idle');
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [dragOver, setDragOver] = useState(false);

    if (creativeBriefId === null || creativeBriefId === undefined) {
        return (
            <span className="text-[11px] text-zinc-500">
                Ruaj brief-in për të aktivizuar upload-in e fotove.
            </span>
        );
    }

    async function upload(file: File) {
        setError(null);
        setProgress(0);

        if (!file.type.startsWith('image/')) {
            setError('Vetëm foto (jpg / png / webp) pranohen.');
            return;
        }

        const maxBytes = limits.photo_max_size_mb * 1024 * 1024;
        if (file.size > maxBytes) {
            setError(`File më i madh se ${limits.photo_max_size_mb} MB.`);
            return;
        }

        let dims: { width: number | null; height: number | null } = { width: null, height: null };
        try {
            dims = await probeImage(file);
        } catch {
            // Dimensions are nice-to-have — the server can still accept
            // the file without them. Fall through silently.
        }

        const form = new FormData();
        form.append('file', file);
        if (dims.width !== null) form.append('width', String(dims.width));
        if (dims.height !== null) form.append('height', String(dims.height));

        const url = endpoints.upload_photo_brief.replace('__ID__', encodeURIComponent(String(creativeBriefId)));

        setStage('uploading');
        try {
            const response = await http.post<{ slot: UploadedPhotoSlot }>(url, form, {
                onUploadProgress: (event: AxiosProgressEvent) => {
                    if (!event.total) return;
                    setProgress(Math.round((event.loaded / event.total) * 100));
                },
            });

            onUploaded?.(response.data.slot);
            setStage('done');
            toast.success(`Foto u ngarkua (${formatSize(file.size)}).`);
        } catch (e) {
            setStage('idle');
            const msg = friendly(e);
            setError(msg);
            toast.error('Upload foto: ' + msg);
        }
    }

    function onPick(files: FileList | null) {
        if (!files || files.length === 0) return;
        void upload(files[0]);
    }

    return (
        <div className="flex flex-col gap-2">
            <div
                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    onPick(e.dataTransfer.files);
                }}
                className={[
                    'flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs',
                    dragOver
                        ? 'border-violet-500 bg-violet-900/30 text-violet-200'
                        : 'border-zinc-700 bg-zinc-900 text-zinc-200 hover:border-violet-500',
                ].join(' ')}
            >
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={stage === 'uploading'}
                    className="font-medium disabled:cursor-wait disabled:opacity-60"
                    title="Ngarko foto (PNG / JPG / WEBP)"
                >
                    {stage === 'idle' && '⇪ Upload foto'}
                    {stage === 'uploading' && `Duke ngarkuar ${progress}%`}
                    {stage === 'done' && '✓ Ngarkuar'}
                </button>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(e) => onPick(e.target.files)}
                    className="hidden"
                />
            </div>

            {stage === 'uploading' ? (
                <div className="h-1 overflow-hidden rounded bg-zinc-800">
                    <div
                        className="h-full bg-violet-500 transition-[width]"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            ) : null}

            {error ? <div className="text-[11px] text-rose-400">{error}</div> : null}
        </div>
    );
}

function probeImage(file: File): Promise<{ width: number | null; height: number | null }> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const w = img.naturalWidth || null;
            const h = img.naturalHeight || null;
            URL.revokeObjectURL(url);
            resolve({ width: w, height: h });
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('image-load-failed'));
        };
        img.src = url;
    });
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function friendly(e: unknown): string {
    if (e && typeof e === 'object' && 'response' in e) {
        const res = (e as { response?: { status?: number; data?: { message?: string } } }).response;
        if (res?.status === 413) {
            return 'Foto tepër e madhe për server-in. Eksportoje me rezolucion më të ulët.';
        }
        if (res?.data?.message) return res.data.message;
        if (res?.status) return `HTTP ${res.status}`;
    }
    if (e instanceof Error) return e.message;
    return 'Upload dështoi.';
}
