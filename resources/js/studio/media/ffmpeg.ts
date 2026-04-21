import { FFmpeg } from '@ffmpeg/ffmpeg';
import { fetchFile, toBlobURL } from '@ffmpeg/util';

/**
 * Client-side FFmpeg wrapper for quick media operations — trim, merge,
 * text overlay, thumbnail extraction. The WASM core is ~30 MB so it is
 * loaded lazily on first use and reused across calls.
 *
 * Anything longer than {@link MAX_CLIENT_DURATION_SEC} is rejected and
 * must be routed through the server-side Remotion worker (task #1241)
 * where a native FFmpeg binary handles the heavy lifting.
 *
 * All methods accept an optional `onProgress` callback so the caller can
 * show a progress bar; progress values are 0..1.
 */

export const MAX_CLIENT_DURATION_SEC = 30;

const CORE_VERSION = '0.12.6';
const CORE_BASE_URL = `https://unpkg.com/@ffmpeg/core@${CORE_VERSION}/dist/umd`;

let sharedInstance: FFmpeg | null = null;
let loadingPromise: Promise<FFmpeg> | null = null;

export type ProgressCb = (value: number) => void;

export class FFmpegUnavailableError extends Error {
    constructor(message: string, public readonly cause?: unknown) {
        super(message);
        this.name = 'FFmpegUnavailableError';
    }
}

export class FFmpegDurationExceededError extends Error {
    constructor(public readonly actualSeconds: number) {
        super(
            `Video is ${actualSeconds.toFixed(1)}s long; client-side FFmpeg is capped ` +
                `at ${MAX_CLIENT_DURATION_SEC}s. Route through the server render worker instead.`,
        );
        this.name = 'FFmpegDurationExceededError';
    }
}

/**
 * Lazy singleton loader. First call fetches the ~30MB WASM core via
 * Blob URLs (works around CORS / CSP when `unpkg.com` is reachable);
 * subsequent calls reuse the same instance.
 */
export async function getFFmpeg(): Promise<FFmpeg> {
    if (sharedInstance) {
        return sharedInstance;
    }

    if (loadingPromise) {
        return loadingPromise;
    }

    loadingPromise = (async () => {
        const ffmpeg = new FFmpeg();

        try {
            await ffmpeg.load({
                coreURL: await toBlobURL(`${CORE_BASE_URL}/ffmpeg-core.js`, 'text/javascript'),
                wasmURL: await toBlobURL(`${CORE_BASE_URL}/ffmpeg-core.wasm`, 'application/wasm'),
            });
        } catch (e) {
            loadingPromise = null;
            throw new FFmpegUnavailableError('Failed to load FFmpeg WASM core', e);
        }

        sharedInstance = ffmpeg;
        return ffmpeg;
    })();

    return loadingPromise;
}

/** Attach/detach a progress listener around a single operation. */
function withProgress<T>(
    ffmpeg: FFmpeg,
    onProgress: ProgressCb | undefined,
    run: () => Promise<T>,
): Promise<T> {
    const listener = onProgress
        ? (e: { progress: number }) => onProgress(Math.min(1, Math.max(0, e.progress)))
        : null;

    if (listener) {
        ffmpeg.on('progress', listener);
    }

    return run().finally(() => {
        if (listener) {
            ffmpeg.off('progress', listener);
        }
    });
}

/**
 * Probe a video's duration using the HTMLVideoElement API — avoids an
 * FFmpeg call for a cheap guard before loading the WASM core.
 */
export function probeDuration(file: File | Blob): Promise<number> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.src = url;
        video.onloadedmetadata = () => {
            const duration = video.duration;
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(duration) ? duration : 0);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Could not read video metadata'));
        };
    });
}

function sanitizeExt(name: string, fallback = 'mp4'): string {
    const match = name.match(/\.([a-z0-9]+)$/i);
    return (match?.[1] ?? fallback).toLowerCase();
}

async function guardDuration(file: File | Blob, seconds?: number): Promise<number> {
    const duration = seconds ?? (await probeDuration(file));

    if (duration > MAX_CLIENT_DURATION_SEC) {
        throw new FFmpegDurationExceededError(duration);
    }

    return duration;
}

// ── Operations ───────────────────────────────────────────────────────

export async function trimVideo(
    file: File,
    startSec: number,
    endSec: number,
    onProgress?: ProgressCb,
): Promise<Blob> {
    if (endSec <= startSec) {
        throw new Error('endSec must be greater than startSec');
    }

    const sourceDuration = await probeDuration(file);
    await guardDuration(file, Math.min(sourceDuration, endSec - startSec));

    const ffmpeg = await getFFmpeg();
    const ext = sanitizeExt(file.name);
    const input = `in.${ext}`;
    const output = `out.${ext}`;

    await ffmpeg.writeFile(input, await fetchFile(file));

    await withProgress(ffmpeg, onProgress, () =>
        ffmpeg.exec([
            '-ss', startSec.toString(),
            '-to', endSec.toString(),
            '-i', input,
            '-c', 'copy',
            output,
        ]),
    );

    const data = (await ffmpeg.readFile(output)) as Uint8Array;
    await ffmpeg.deleteFile(input);
    await ffmpeg.deleteFile(output);

    return new Blob([data], { type: file.type || 'video/mp4' });
}

export async function mergeVideos(
    files: File[],
    onProgress?: ProgressCb,
): Promise<Blob> {
    if (files.length < 2) {
        throw new Error('mergeVideos requires at least 2 inputs');
    }

    const totalDuration = (await Promise.all(files.map(probeDuration))).reduce((a, b) => a + b, 0);
    if (totalDuration > MAX_CLIENT_DURATION_SEC) {
        throw new FFmpegDurationExceededError(totalDuration);
    }

    const ffmpeg = await getFFmpeg();
    const inputs: string[] = [];
    const concatLines: string[] = [];

    for (const [i, file] of files.entries()) {
        const ext = sanitizeExt(file.name);
        const name = `clip_${i}.${ext}`;
        await ffmpeg.writeFile(name, await fetchFile(file));
        inputs.push(name);
        concatLines.push(`file '${name}'`);
    }

    const listFile = 'concat.txt';
    await ffmpeg.writeFile(listFile, new TextEncoder().encode(concatLines.join('\n')));

    const output = 'merged.mp4';

    await withProgress(ffmpeg, onProgress, () =>
        ffmpeg.exec(['-f', 'concat', '-safe', '0', '-i', listFile, '-c', 'copy', output]),
    );

    const data = (await ffmpeg.readFile(output)) as Uint8Array;
    for (const name of [...inputs, listFile, output]) {
        await ffmpeg.deleteFile(name).catch(() => undefined);
    }

    return new Blob([data], { type: 'video/mp4' });
}

export async function addTextOverlay(
    file: File,
    text: string,
    position: 'top' | 'bottom' | 'center',
    onProgress?: ProgressCb,
): Promise<Blob> {
    await guardDuration(file);

    const ffmpeg = await getFFmpeg();
    const ext = sanitizeExt(file.name);
    const input = `in.${ext}`;
    const output = `out.${ext}`;

    await ffmpeg.writeFile(input, await fetchFile(file));

    const safeText = text.replace(/:/g, '\\:').replace(/'/g, "\\\\'");
    const y = position === 'top' ? '80' : position === 'bottom' ? 'h-th-80' : '(h-text_h)/2';
    const filter =
        `drawtext=text='${safeText}':fontcolor=white:fontsize=56:` +
        `borderw=3:bordercolor=black:x=(w-text_w)/2:y=${y}`;

    await withProgress(ffmpeg, onProgress, () =>
        ffmpeg.exec(['-i', input, '-vf', filter, '-codec:a', 'copy', output]),
    );

    const data = (await ffmpeg.readFile(output)) as Uint8Array;
    await ffmpeg.deleteFile(input);
    await ffmpeg.deleteFile(output);

    return new Blob([data], { type: file.type || 'video/mp4' });
}

export async function extractThumbnail(
    file: File,
    timeSec: number,
): Promise<Blob> {
    const ffmpeg = await getFFmpeg();
    const ext = sanitizeExt(file.name);
    const input = `in.${ext}`;
    const output = 'thumb.jpg';

    await ffmpeg.writeFile(input, await fetchFile(file));

    await ffmpeg.exec([
        '-ss', timeSec.toString(),
        '-i', input,
        '-frames:v', '1',
        '-q:v', '3',
        output,
    ]);

    const data = (await ffmpeg.readFile(output)) as Uint8Array;
    await ffmpeg.deleteFile(input);
    await ffmpeg.deleteFile(output);

    return new Blob([data], { type: 'image/jpeg' });
}
