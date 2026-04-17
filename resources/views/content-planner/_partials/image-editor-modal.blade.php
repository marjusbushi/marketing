{{-- Filerobot Image Editor modal.
     React + Filerobot are lazy-loaded from a CDN the first time the
     editor opens so the main app bundle stays ~0 KB heavier. All tools
     except the ones the user asked for (Crop, Rotate, Flip, Filter,
     Resize) are hidden via config. On save, the editor emits a blob
     which we upload via the existing /api/media/upload endpoint.
--}}
<div id="imgEditorOverlay" style="display:none; position:fixed; inset:0; z-index:9995; background:rgba(15, 23, 42, 0.9);">
    <div style="position:absolute; top:12px; left:16px; display:flex; align-items:center; gap:10px; color:#fff; font-family:Inter,system-ui,sans-serif;">
        <iconify-icon icon="heroicons-outline:pencil-square" width="18"></iconify-icon>
        <span style="font-size:13px; font-weight:500;">Image editor</span>
    </div>
    <button id="imgEditorClose" onclick="closeImageEditor()" type="button" aria-label="Close image editor"
        style="position:absolute; top:8px; right:12px; width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.12); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;">
        <iconify-icon icon="heroicons-outline:x-mark" width="18" style="color:#fff;"></iconify-icon>
    </button>
    <div id="imgEditorLoading" style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; gap:12px; pointer-events:none;">
        <div class="cp-spinner" aria-hidden="true"></div>
        <span style="font-size:12px; opacity:0.85;">Loading editor…</span>
    </div>
    <div id="imgEditorHost" style="position:absolute; inset:52px 12px 12px 12px; background:#1e293b; border-radius:10px; overflow:hidden;"></div>
</div>

<style>
    .cp-spinner {
        width: 32px; height: 32px;
        border: 3px solid rgba(255,255,255,0.2);
        border-top-color: #fff;
        border-radius: 50%;
        animation: cp-spin 0.8s linear infinite;
    }
    @keyframes cp-spin { to { transform: rotate(360deg); } }
</style>

<script>
(function () {
    // State lives on window so composer code and this module can coordinate
    // without having to share a bundle.
    window.__imgEditor = window.__imgEditor || {
        loaded: false,
        loading: null, // Promise while scripts load
        instance: null,
        onSave: null,  // callback(file) when user saves
    };

    // CDN URLs — pinned to avoid silent major-version breaks.
    const REACT_URL     = 'https://unpkg.com/react@18.3.1/umd/react.production.min.js';
    const REACT_DOM_URL = 'https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js';
    const FIE_URL       = 'https://scaleflex.cloudimg.io/v7/plugins/filerobot-image-editor/latest/filerobot-image-editor.min.js';

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[data-src="${src}"]`);
            if (existing) { existing.addEventListener('load', () => resolve()); return; }
            const s = document.createElement('script');
            s.src = src;
            s.dataset.src = src;
            s.async = true;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('Failed to load ' + src));
            document.head.appendChild(s);
        });
    }

    async function ensureFilerobotLoaded() {
        if (window.__imgEditor.loaded) return;
        if (window.__imgEditor.loading) return window.__imgEditor.loading;

        window.__imgEditor.loading = (async () => {
            // React must come before react-dom, react-dom before FIE.
            await loadScript(REACT_URL);
            await loadScript(REACT_DOM_URL);
            await loadScript(FIE_URL);
            window.__imgEditor.loaded = true;
        })();

        return window.__imgEditor.loading;
    }

    window.openImageEditor = async function (imageUrl, onSave) {
        const overlay  = document.getElementById('imgEditorOverlay');
        const host     = document.getElementById('imgEditorHost');
        const loading  = document.getElementById('imgEditorLoading');

        window.__imgEditor.onSave = typeof onSave === 'function' ? onSave : null;
        overlay.style.display = 'block';
        loading.style.display = 'flex';
        host.textContent = '';

        try {
            await ensureFilerobotLoaded();
        } catch (e) {
            loading.style.display = 'none';
            const err = document.createElement('div');
            err.style.cssText = 'color:#fecaca;font-size:13px;padding:24px;';
            err.textContent = 'Could not load the image editor: ' + (e.message || 'unknown error');
            host.appendChild(err);
            return;
        }

        loading.style.display = 'none';

        // FilerobotImageEditor global exposed by the CDN script.
        const Ctor = window.FilerobotImageEditor?.default || window.FilerobotImageEditor;
        if (!Ctor) {
            const err = document.createElement('div');
            err.style.cssText = 'color:#fecaca;font-size:13px;padding:24px;';
            err.textContent = 'Image editor failed to initialise (global missing).';
            host.appendChild(err);
            return;
        }

        const config = {
            source: imageUrl,
            // Faza 3 scope: crop, rotate, finetune, resize. Hide the rest.
            tabsIds: ['Adjust', 'Finetune', 'Filters', 'Resize'],
            defaultTabId: 'Adjust',
            defaultToolId: 'Crop',
            // UI / branding
            theme: {
                palette: { 'accent-primary': '#6366f1' },
            },
            // Save -> give us a blob, don't auto-download
            savingPixelRatio: 4,
            previewPixelRatio: 2,
            useBackendTranslations: false,
            useZoomPresetsMenu: true,
            onBeforeSave: () => false, // we handle save ourselves
            onSave: async (savedImageData, designState) => {
                try {
                    // savedImageData.imageBase64 is a data URL.
                    const dataUrl = savedImageData.imageBase64;
                    const res = await fetch(dataUrl);
                    const blob = await res.blob();
                    const filename = savedImageData.fullName || 'edited.jpg';
                    if (typeof window.__imgEditor.onSave === 'function') {
                        await window.__imgEditor.onSave(blob, filename);
                    }
                    closeImageEditor();
                } catch (e) {
                    console.error('Image editor save failed', e);
                }
            },
            onClose: () => closeImageEditor(),
        };

        try {
            if (window.__imgEditor.instance && typeof window.__imgEditor.instance.terminate === 'function') {
                window.__imgEditor.instance.terminate();
            }
            window.__imgEditor.instance = new Ctor(host, config);
            window.__imgEditor.instance.render();
        } catch (e) {
            console.error(e);
            const err = document.createElement('div');
            err.style.cssText = 'color:#fecaca;font-size:13px;padding:24px;';
            err.textContent = 'Failed to open the editor: ' + e.message;
            host.appendChild(err);
        }
    };

    window.closeImageEditor = function () {
        const overlay = document.getElementById('imgEditorOverlay');
        const host    = document.getElementById('imgEditorHost');
        if (window.__imgEditor.instance && typeof window.__imgEditor.instance.terminate === 'function') {
            window.__imgEditor.instance.terminate();
        }
        window.__imgEditor.instance = null;
        window.__imgEditor.onSave = null;
        host.textContent = '';
        overlay.style.display = 'none';
    };

    // ESC closes the editor overlay.
    document.addEventListener('keydown', (e) => {
        const overlay = document.getElementById('imgEditorOverlay');
        if (e.key === 'Escape' && overlay && overlay.style.display === 'block') {
            closeImageEditor();
        }
    });
})();
</script>
