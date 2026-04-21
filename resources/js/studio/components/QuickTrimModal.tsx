import { ChangeEvent, useEffect, useMemo, useRef, useState } from 'react';
import {
    FFmpegDurationExceededError,
    FFmpegUnavailableError,
    MAX_CLIENT_DURATION_SEC,
    probeDuration,
    trimVideo,
} from '@studio/media/ffmpeg';

/**
 * Quick-trim modal — lets the user crop a video before handing it to the
 * main editor. Keeps the heavy WASM core out of the editor critical path
 * by lazy-loading only when the user actually needs to trim.
 *
 * If the source is longer than {@link MAX_CLIENT_DURATION_SEC} we block
 * client-side processing and tell the user it'll be rendered server-side.
 */
interface QuickTrimModalProps {
    open: boolean;
    onClose: () => void;
    onTrimmed: (blob: Blob, filename: string) => void;
}

export function QuickTrimModal({ open, onClose, onTrimmed }: QuickTrimModalProps) {
    const [file, setFile] = useState<File | null>(null);
    const [duration, setDuration] = useState(0);
    const [start, setStart] = useState(0);
    const [end, setEnd] = useState(0);
    const [progress, setProgress] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);

    useEffect(() => {
        if (!open) {
            setFile(null);
            setDuration(0);
            setStart(0);
            setEnd(0);
            setProgress(null);
            setError(null);
        }
    }, [open]);

    const fileUrl = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);

    useEffect(() => {
        return () => {
            if (fileUrl) {
                URL.revokeObjectURL(fileUrl);
            }
        };
    }, [fileUrl]);

    async function handleFile(e: ChangeEvent<HTMLInputElement>) {
        const next = e.target.files?.[0] ?? null;
        setError(null);
        setFile(next);

        if (!next) {
            setDuration(0);
            return;
        }

        try {
            const d = await probeDuration(next);
            setDuration(d);
            setStart(0);
            setEnd(Math.min(d, MAX_CLIENT_DURATION_SEC));

            if (d > MAX_CLIENT_DURATION_SEC) {
                setError(
                    `Videoja është ${d.toFixed(1)}s — më shumë se ${MAX_CLIENT_DURATION_SEC}s. ` +
                        'Ngarkoje te editor-i dhe do renderohet server-side.',
                );
            }
        } catch {
            setError('S\u2019u lexua dot metadata e videos');
        }
    }

    async function runTrim() {
        if (!file) return;

        setError(null);
        setProgress(0);

        try {
            const blob = await trimVideo(file, start, end, setProgress);
            onTrimmed(blob, suggestOutputName(file.name));
            onClose();
        } catch (e) {
            if (e instanceof FFmpegDurationExceededError) {
                setError(
                    `Video ${e.actualSeconds.toFixed(1)}s tejkalon kufirin ` +
                        `${MAX_CLIENT_DURATION_SEC}s. Render-imi do behet ne server.`,
                );
            } else if (e instanceof FFmpegUnavailableError) {
                setError('FFmpeg nuk u ngarkua. Provo perseri ose perdor server render.');
            } else {
                setError(`Trim deshtoi: ${(e as Error).message}`);
            }
        } finally {
            setProgress(null);
        }
    }

    if (!open) return null;

    const disabled = !file || end <= start || progress !== null || duration > MAX_CLIENT_DURATION_SEC;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
            <div className="w-full max-w-xl rounded-lg border border-zinc-800 bg-zinc-900 p-5 text-zinc-100 shadow-2xl">
                <div className="flex items-center justify-between pb-3">
                    <h2 className="text-sm font-semibold text-zinc-100">Quick Trim</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1 text-xs text-zinc-300 hover:border-zinc-500"
                    >
                        Mbylle
                    </button>
                </div>

                <label className="mb-3 block cursor-pointer rounded-md border border-dashed border-zinc-700 bg-zinc-950 p-3 text-center text-xs text-zinc-400 hover:border-violet-500">
                    <input type="file" accept="video/*" className="hidden" onChange={handleFile} />
                    {file ? file.name : 'Zgjedh video (≤ 30s client-side)'}
                </label>

                {fileUrl ? (
                    <video
                        ref={videoRef}
                        src={fileUrl}
                        controls
                        className="mb-3 w-full rounded-md bg-black"
                    />
                ) : null}

                {duration > 0 ? (
                    <div className="space-y-2 text-xs text-zinc-300">
                        <RangeRow
                            label="Start (s)"
                            value={start}
                            onChange={setStart}
                            min={0}
                            max={Math.max(0, end - 0.1)}
                            step={0.1}
                        />
                        <RangeRow
                            label="End (s)"
                            value={end}
                            onChange={setEnd}
                            min={start + 0.1}
                            max={Math.min(duration, MAX_CLIENT_DURATION_SEC)}
                            step={0.1}
                        />
                        <div className="text-[11px] text-zinc-500">
                            Durata: {duration.toFixed(1)}s · Do prodhohet: {(end - start).toFixed(1)}s
                        </div>
                    </div>
                ) : null}

                {error ? (
                    <div className="mt-3 rounded-md border border-rose-500/30 bg-rose-500/10 p-2 text-xs text-rose-200">
                        {error}
                    </div>
                ) : null}

                {progress !== null ? (
                    <div className="mt-3">
                        <div className="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
                            <div
                                className="h-full bg-gradient-to-r from-violet-500 to-pink-500 transition-all"
                                style={{ width: `${Math.round(progress * 100)}%` }}
                            />
                        </div>
                        <div className="mt-1 text-right text-[11px] text-zinc-400">
                            {Math.round(progress * 100)}% — po proceson…
                        </div>
                    </div>
                ) : null}

                <div className="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-300 hover:border-zinc-500"
                    >
                        Anulo
                    </button>
                    <button
                        type="button"
                        onClick={runTrim}
                        disabled={disabled}
                        className="rounded-md bg-emerald-500 px-3 py-1.5 text-xs font-medium text-emerald-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-zinc-700 disabled:text-zinc-500"
                    >
                        {progress === null ? 'Bej trim' : 'Po proceson…'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function RangeRow({
    label,
    value,
    onChange,
    min,
    max,
    step,
}: {
    label: string;
    value: number;
    onChange: (v: number) => void;
    min: number;
    max: number;
    step: number;
}) {
    return (
        <label className="flex items-center gap-3 text-xs">
            <span className="w-16 shrink-0 text-zinc-400">{label}</span>
            <input
                type="range"
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                min={min}
                max={max}
                step={step}
                className="flex-1"
            />
            <span className="w-12 shrink-0 text-right font-mono text-zinc-200">{value.toFixed(1)}</span>
        </label>
    );
}

function suggestOutputName(name: string): string {
    const dot = name.lastIndexOf('.');
    if (dot < 0) return `${name}-trim.mp4`;
    return `${name.slice(0, dot)}-trim${name.slice(dot)}`;
}
