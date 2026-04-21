import { useRef, useState } from 'react';
import type { AxiosInstance, AxiosProgressEvent } from 'axios';
import { useToast } from '@studio/components/ToastHost';
import type { StudioEndpoints, StudioLimits } from '@studio/types/props';

interface VideoUploadButtonProps {
    http: AxiosInstance;
    endpoints: StudioEndpoints;
    limits: StudioLimits;
    creativeBriefId: number | string | null;
    onUploaded?: (payload: UploadedSlot) => void;
    onQuickTrimRequested?: (blob: Blob, name: string) => void;
}

export interface UploadedSlot {
    kind: 'video';
    source: 'capcut';
    disk: string;
    path: string;
    thumbnail_path: string | null;
    duration_seconds: number | null;
    width: number | null;
    height: number | null;
    mime_type: string;
    size_bytes: number;
    media_id: number | null;
    uploaded_at: string;
}

/**
 * Post-pivot video flow (#1244): staff exports MP4 from CapCut, drops it
 * here, and the server stores it alongside the brief. Before we send the
 * file we probe it locally with HTML5 `<video>` to read duration +
 * resolution, and capture one frame via a canvas for a thumbnail. No
 * server-side ffmpeg dependency — CapCut already produced a clean render.
 */
export function VideoUploadButton({
    http,
    endpoints,
    limits,
    creativeBriefId,
    onUploaded,
    onQuickTrimRequested,
}: VideoUploadButtonProps) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const toast = useToast();

    const [stage, setStage] = useState<'idle' | 'probing' | 'uploading' | 'done'>('idle');
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [dragOver, setDragOver] = useState(false);
    const [lastFile, setLastFile] = useState<{ blob: Blob; name: string } | null>(null);
    const [lastSlot, setLastSlot] = useState<UploadedSlot | null>(null);

    if (creativeBriefId === null || creativeBriefId === undefined) {
        return (
            <span className="text-[11px] text-zinc-500">
                Ruaj brief-in për të aktivizuar upload-in e videos.
            </span>
        );
    }

    async function upload(file: File) {
        setError(null);
        setProgress(0);

        if (!file.type.startsWith('video/')) {
            setError('Vetëm video (mp4 / mov) pranohen.');
            return;
        }

        const maxBytes = limits.video_max_size_mb * 1024 * 1024;
        if (file.size > maxBytes) {
            setError(`File më i madh se ${limits.video_max_size_mb} MB.`);
            return;
        }

        setStage('probing');
        let meta: ProbedMetadata;
        try {
            meta = await probeVideo(file);
        } catch (e) {
            setError('Nuk mund të lexoja metadata-n e videos.');
            setStage('idle');
            return;
        }

        const form = new FormData();
        form.append('file', file);
        if (meta.thumbnail) form.append('thumbnail', meta.thumbnail, 'thumbnail.jpg');
        if (meta.duration !== null) form.append('duration_seconds', String(meta.duration));
        if (meta.width !== null) form.append('width', String(meta.width));
        if (meta.height !== null) form.append('height', String(meta.height));

        const url = endpoints.upload_video_brief.replace('__ID__', encodeURIComponent(String(creativeBriefId)));

        setStage('uploading');
        try {
            const response = await http.post<{ slot: UploadedSlot }>(url, form, {
                onUploadProgress: (event: AxiosProgressEvent) => {
                    if (!event.total) return;
                    setProgress(Math.round((event.loaded / event.total) * 100));
                },
            });

            setLastFile({ blob: file, name: file.name });
            setLastSlot(response.data.slot);
            onUploaded?.(response.data.slot);
            setStage('done');
            toast.success(`Video u ngarkua (${formatSize(file.size)}).`);
        } catch (e) {
            setStage('idle');
            const msg = friendly(e);
            setError(msg);
            toast.error('Upload video: ' + msg);
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
                        ? 'border-emerald-500 bg-emerald-900/30 text-emerald-200'
                        : 'border-zinc-700 bg-zinc-900 text-zinc-200 hover:border-emerald-500',
                ].join(' ')}
            >
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={stage === 'probing' || stage === 'uploading'}
                    className="font-medium disabled:cursor-wait disabled:opacity-60"
                    title="Ngarko video të eksportuar nga CapCut"
                >
                    {stage === 'idle' && '⇪ Upload video (CapCut)'}
                    {stage === 'probing' && 'Duke lexuar metadata…'}
                    {stage === 'uploading' && `Duke ngarkuar ${progress}%`}
                    {stage === 'done' && '✓ Ngarkuar'}
                </button>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="video/mp4,video/quicktime,video/x-m4v,video/webm"
                    onChange={(e) => onPick(e.target.files)}
                    className="hidden"
                />
                {stage === 'done' && lastFile && onQuickTrimRequested ? (
                    <button
                        type="button"
                        onClick={() => onQuickTrimRequested(lastFile.blob, lastFile.name)}
                        className="rounded border border-zinc-700 px-2 py-0.5 text-[10px] text-zinc-300 hover:border-violet-500"
                    >
                        ✂︎ Trim
                    </button>
                ) : null}
            </div>

            {stage === 'uploading' ? (
                <div className="h-1 overflow-hidden rounded bg-zinc-800">
                    <div
                        className="h-full bg-emerald-500 transition-[width]"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            ) : null}

            {lastSlot ? (
                <div className="flex items-center gap-2 text-[11px] text-zinc-400">
                    {lastSlot.thumbnail_path ? (
                        <img
                            src={toPublicUrl(lastSlot.thumbnail_path)}
                            alt=""
                            className="h-10 w-16 rounded object-cover"
                        />
                    ) : null}
                    <div>
                        <div>{lastSlot.duration_seconds ? `${lastSlot.duration_seconds}s` : '—'} · {lastSlot.width}×{lastSlot.height}</div>
                        <div>{formatSize(lastSlot.size_bytes)}</div>
                    </div>
                </div>
            ) : null}

            {error ? <div className="text-[11px] text-rose-400">{error}</div> : null}
        </div>
    );
}

interface ProbedMetadata {
    duration: number | null;
    width: number | null;
    height: number | null;
    thumbnail: Blob | null;
}

function probeVideo(file: File): Promise<ProbedMetadata> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.muted = true;
        video.playsInline = true;
        video.src = url;

        video.onloadedmetadata = () => {
            // Seek 1s in (or 10% of the duration, whichever is smaller) so the
            // thumbnail isn't a black frame on CapCut's fade-in.
            const seekTo = Math.min(1, Math.max(0, video.duration * 0.1));
            video.currentTime = seekTo;
        };

        video.onseeked = async () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                let thumbnail: Blob | null = null;
                if (ctx) {
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    thumbnail = await new Promise<Blob | null>((r) => canvas.toBlob((b) => r(b), 'image/jpeg', 0.85));
                }

                URL.revokeObjectURL(url);
                resolve({
                    duration: Number.isFinite(video.duration) ? Math.round(video.duration) : null,
                    width: video.videoWidth || null,
                    height: video.videoHeight || null,
                    thumbnail,
                });
            } catch (e) {
                URL.revokeObjectURL(url);
                reject(e);
            }
        };

        video.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('video-load-failed'));
        };
    });
}

function toPublicUrl(path: string): string {
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path.replace(/^\/+/, '')}`;
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function friendly(e: unknown): string {
    if (e && typeof e === 'object' && 'response' in e) {
        const res = (e as { response?: { status?: number; data?: { message?: string } } }).response;
        // nginx/PHP enforce the real ceiling before Laravel sees the request,
        // so 413 comes back without a JSON body. Translate it into the
        // actionable hint: trim the video with Quick Trim first.
        if (res?.status === 413) {
            return 'Video tepër e madhe për server-in. Provo ta shkurtosh me Quick Trim.';
        }
        if (res?.data?.message) return res.data.message;
        if (res?.status) return `HTTP ${res.status}`;
    }
    if (e instanceof Error) return e.message;
    return 'Upload dështoi.';
}
