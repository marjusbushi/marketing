{{-- Reusable cover picker modal — included by both the planner composer
     and the daily-basket post sheet. Decoupled via a custom event
     `flare:cover-updated` so the two callers don't need to know about
     each other's state shape. --}}
<style>
    .cp-tab-active { color:#4f46e5; border-bottom-color:#4f46e5 !important; }
</style>
<div id="coverPickerOverlay" class="hidden fixed inset-0 z-[10001]" style="background:rgba(15,23,42,0.65);">
    <div class="absolute inset-0 flex items-center justify-center p-4" onclick="if(event.target===this)__cpClose()">
        <div class="bg-white rounded-xl shadow-2xl w-[680px] max-w-[95vw] max-h-[92vh] overflow-hidden flex flex-col" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
                <h3 class="text-sm font-semibold text-slate-800">Zgjidh cover-in e Reels</h3>
                <button type="button" onclick="__cpClose()" class="w-7 h-7 flex items-center justify-center rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100">×</button>
            </div>

            <div class="flex border-b border-slate-200">
                <button type="button" id="coverTabFrame" onclick="__cpSwitchTab('frame')" class="cp-tab-active flex-1 py-2.5 text-xs font-medium text-slate-500 border-b-2 border-transparent">Zgjidh nga video</button>
                <button type="button" id="coverTabUpload" onclick="__cpSwitchTab('upload')" class="flex-1 py-2.5 text-xs font-medium text-slate-500 border-b-2 border-transparent">Upload custom</button>
            </div>

            <div class="flex-1 overflow-auto">
                <div id="coverPaneFrame" class="p-4">
                    <div class="bg-black rounded-md overflow-hidden mb-3 flex items-center justify-center" style="max-height:380px;">
                        <video id="coverPickerVideo" controls muted playsinline crossorigin="anonymous" preload="metadata" style="max-width:100%;max-height:380px;display:block;"></video>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-slate-500 mb-1">
                        <span>Timestamp</span>
                        <span id="coverFrameTime" class="ml-auto tabular-nums text-slate-700 font-medium">0.00s</span>
                    </div>
                    <input id="coverFrameSlider" type="range" min="0" max="100" step="0.1" value="0" oninput="__cpSliderInput(event)" class="w-full mb-3">
                    <button type="button" onclick="__cpCaptureFrame()" class="w-full h-9 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700">Përdor këtë frame si cover</button>
                    <div id="coverFramePreview" class="mt-3" style="display:none;">
                        <div class="text-[10px] text-slate-500 mb-1 uppercase tracking-wide">Preview</div>
                        <img id="coverFramePreviewImg" alt="" class="max-w-full rounded-md border border-slate-200">
                    </div>
                </div>

                <div id="coverPaneUpload" class="p-4" style="display:none;">
                    <p class="text-[12px] text-slate-500 mb-3">Ngarko JPG ose PNG (max 8 MB). Aspect ratio i rekomanduar 9:16 për Reels.</p>
                    <input type="file" id="coverUploadFile" accept="image/jpeg,image/png" onchange="__cpUploadFileChanged(this)" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                    <div id="coverUploadPreview" class="mt-3" style="display:none;">
                        <div class="text-[10px] text-slate-500 mb-1 uppercase tracking-wide">Preview</div>
                        <img id="coverUploadPreviewImg" alt="" class="max-w-full rounded-md border border-slate-200">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 bg-slate-50">
                <button type="button" id="coverPickerClear" onclick="__cpClearCover()" class="text-xs text-red-600 hover:text-red-800 font-medium" style="display:none;">Hiq cover-in aktual</button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" onclick="__cpClose()" class="px-4 h-9 text-xs font-medium text-slate-600 hover:bg-slate-200 rounded-md">Mbyll</button>
                    <button type="button" id="coverPickerSave" onclick="__cpSave()" disabled class="px-4 h-9 text-xs font-semibold bg-primary-600 text-white rounded-md hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed">Ruaj</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Self-contained cover-picker controller. Callers invoke
    // window.openCoverPicker({ id, url, cover_path }) and listen for
    // the `flare:cover-updated` window event to refresh their own UI.
    (function () {
        let mediaId = null;
        let stagedDataUrl = null;
        let stagedFile = null;
        let hasExistingCover = false;

        const csrf = '{{ csrf_token() }}';
        const apiBase = '{{ url('/marketing/planner/api/media') }}';

        window.openCoverPicker = function (media) {
            if (!media || !media.id) return;
            mediaId = media.id;
            stagedDataUrl = null;
            stagedFile = null;
            hasExistingCover = !!media.cover_path;

            document.getElementById('coverPickerOverlay').classList.remove('hidden');
            __cpSwitchTab('frame');

            const v = document.getElementById('coverPickerVideo');
            v.crossOrigin = 'anonymous';
            v.src = media.url;
            v.currentTime = 0;
            v.load();

            document.getElementById('coverFrameTime').textContent = '0.00s';
            document.getElementById('coverFrameSlider').value = 0;
            const previewWrap = document.getElementById('coverFramePreview');
            previewWrap.style.display = 'none';
            document.getElementById('coverFramePreviewImg').removeAttribute('src');

            document.getElementById('coverUploadFile').value = '';
            document.getElementById('coverUploadPreview').style.display = 'none';
            document.getElementById('coverUploadPreviewImg').removeAttribute('src');

            document.getElementById('coverPickerClear').style.display = hasExistingCover ? 'inline-flex' : 'none';
            document.getElementById('coverPickerSave').disabled = true;
        };

        window.__cpClose = function () {
            document.getElementById('coverPickerOverlay').classList.add('hidden');
            const v = document.getElementById('coverPickerVideo');
            try { v.pause(); } catch (e) {}
            v.removeAttribute('src');
            v.load();
            mediaId = null;
            stagedDataUrl = null;
            stagedFile = null;
            hasExistingCover = false;
        };

        window.__cpSwitchTab = function (tab) {
            const isFrame = tab === 'frame';
            document.getElementById('coverTabFrame').classList.toggle('cp-tab-active', isFrame);
            document.getElementById('coverTabUpload').classList.toggle('cp-tab-active', !isFrame);
            document.getElementById('coverPaneFrame').style.display = isFrame ? 'block' : 'none';
            document.getElementById('coverPaneUpload').style.display = isFrame ? 'none' : 'block';
            stagedDataUrl = null;
            stagedFile = null;
            document.getElementById('coverPickerSave').disabled = true;
        };

        window.__cpSliderInput = function (e) {
            const v = document.getElementById('coverPickerVideo');
            if (!isFinite(v.duration) || v.duration <= 0) return;
            const pct = Number(e.target.value) / 100;
            const t = Math.max(0, Math.min(v.duration, pct * v.duration));
            v.currentTime = t;
            document.getElementById('coverFrameTime').textContent = t.toFixed(2) + 's';
        };

        window.__cpCaptureFrame = function () {
            const v = document.getElementById('coverPickerVideo');
            if (!v.videoWidth || !v.videoHeight) {
                alert('Video s\'u ngarkua mirë. Provo përsëri.');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = v.videoWidth;
            canvas.height = v.videoHeight;
            const ctx = canvas.getContext('2d');
            try {
                ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                stagedDataUrl = dataUrl;
                stagedFile = null;
                document.getElementById('coverFramePreviewImg').src = dataUrl;
                document.getElementById('coverFramePreview').style.display = 'block';
                document.getElementById('coverPickerSave').disabled = false;
            } catch (e) {
                alert('Frame-i s\'u kap: ' + (e.message || 'unknown error') + '. Kontrollo R2 CORS.');
            }
        };

        window.__cpUploadFileChanged = function (input) {
            const file = input.files && input.files[0];
            if (!file) {
                stagedFile = null;
                document.getElementById('coverPickerSave').disabled = true;
                document.getElementById('coverUploadPreview').style.display = 'none';
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
                alert('Vetëm JPG ose PNG.');
                input.value = '';
                return;
            }
            if (file.size > 8 * 1048576) {
                alert('File është më i madh se 8 MB.');
                input.value = '';
                return;
            }
            stagedFile = file;
            stagedDataUrl = null;
            const reader = new FileReader();
            reader.onload = (ev) => {
                document.getElementById('coverUploadPreviewImg').src = ev.target.result;
                document.getElementById('coverUploadPreview').style.display = 'block';
                document.getElementById('coverPickerSave').disabled = false;
            };
            reader.readAsDataURL(file);
        };

        window.__cpSave = async function () {
            if (!mediaId) return;
            const saveBtn = document.getElementById('coverPickerSave');
            const originalLbl = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Po ruhet…';

            try {
                const url = apiBase + '/' + encodeURIComponent(mediaId) + '/cover';
                let res;
                if (stagedDataUrl) {
                    res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ frame_data_url: stagedDataUrl }),
                    });
                } else if (stagedFile) {
                    const fd = new FormData();
                    fd.append('cover', stagedFile);
                    res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: fd,
                    });
                } else {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalLbl;
                    return;
                }

                if (!res.ok) {
                    const body = await res.json().catch(() => ({ message: 'HTTP ' + res.status }));
                    throw new Error(body.message || 'HTTP ' + res.status);
                }
                const data = await res.json();
                window.dispatchEvent(new CustomEvent('flare:cover-updated', {
                    detail: {
                        mediaId: data.id,
                        coverPath: data.cover_path,
                        coverUrl: data.cover_url,
                        thumbnailUrl: data.thumbnail_url,
                    },
                }));
                __cpClose();
            } catch (err) {
                alert('Cover s\'u ruajt: ' + err.message);
                saveBtn.disabled = false;
                saveBtn.textContent = originalLbl;
            }
        };

        window.__cpClearCover = async function () {
            if (!mediaId) return;
            if (!confirm('Hiq cover-in aktual? Meta do të kthehet te frame-i auto.')) return;
            try {
                const url = apiBase + '/' + encodeURIComponent(mediaId) + '/cover';
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                window.dispatchEvent(new CustomEvent('flare:cover-updated', {
                    detail: {
                        mediaId: data.id,
                        coverPath: null,
                        coverUrl: null,
                        thumbnailUrl: data.thumbnail_url,
                    },
                }));
                __cpClose();
            } catch (err) {
                alert('Cover s\'u hoq: ' + err.message);
            }
        };
    })();
</script>
