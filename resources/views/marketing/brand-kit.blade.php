@extends('_layouts.app')

@section('styles')
<style>
    .brand-kit-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 18px;
        align-items: start;
    }
    .brand-kit-main {
        display: grid;
        gap: 14px;
    }
    .brand-kit-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }
    .brand-kit-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .brand-kit-panel-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0;
    }
    .brand-kit-panel-subtitle {
        margin-top: 2px;
        font-size: 12px;
        color: #64748b;
        letter-spacing: 0;
    }
    .brand-kit-panel-body {
        padding: 16px;
    }
    .brand-kit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .brand-kit-grid-5 {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
    }
    .brand-kit-field {
        display: grid;
        gap: 6px;
    }
    .brand-kit-label {
        font-size: 12px;
        font-weight: 650;
        color: #334155;
        letter-spacing: 0;
    }
    .brand-kit-input,
    .brand-kit-textarea,
    .brand-kit-select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #fff;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.4;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .brand-kit-input,
    .brand-kit-select {
        height: 38px;
        padding: 0 10px;
    }
    .brand-kit-textarea {
        min-height: 116px;
        resize: vertical;
        padding: 10px;
    }
    .brand-kit-input:focus,
    .brand-kit-textarea:focus,
    .brand-kit-select:focus {
        border-color: #e11d48;
        box-shadow: 0 0 0 3px rgba(225, 29, 72, .12);
    }
    .brand-kit-color-row {
        display: grid;
        grid-template-columns: 42px 1fr;
        gap: 8px;
        align-items: center;
    }
    .brand-kit-color {
        width: 42px;
        height: 38px;
        padding: 2px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #fff;
    }
    .brand-kit-sticky {
        position: sticky;
        top: 72px;
        display: grid;
        gap: 14px;
    }
    .brand-kit-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-bottom: 14px;
    }
    .brand-kit-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        height: 36px;
        padding: 0 12px;
        border-radius: 7px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        font-size: 13px;
        font-weight: 650;
        transition: background .15s ease, color .15s ease, border-color .15s ease;
    }
    .brand-kit-button:hover {
        background: #f8fafc;
        color: #0f172a;
    }
    .brand-kit-button-primary {
        border-color: #e11d48;
        background: #e11d48;
        color: #fff;
    }
    .brand-kit-button-primary:hover {
        background: #be123c;
        color: #fff;
    }
    .brand-kit-preview {
        min-height: 190px;
        display: grid;
        align-content: end;
        gap: 12px;
        padding: 18px;
        border-bottom: 1px solid #e2e8f0;
        color: var(--bk-text, #0f172a);
        background:
            linear-gradient(135deg, color-mix(in srgb, var(--bk-secondary, #f5f5f4) 88%, #fff), #fff 58%),
            var(--bk-secondary, #f5f5f4);
    }
    .brand-kit-preview-mark {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: var(--bk-primary, #111827);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        letter-spacing: 0;
    }
    .brand-kit-preview-title {
        font-size: 20px;
        line-height: 1.15;
        font-weight: 800;
        letter-spacing: 0;
    }
    .brand-kit-preview-copy {
        color: var(--bk-neutral, #64748b);
        font-size: 13px;
        line-height: 1.45;
    }
    .brand-kit-preview-cta {
        display: inline-flex;
        width: fit-content;
        height: 32px;
        align-items: center;
        padding: 0 11px;
        border-radius: 7px;
        color: #fff;
        background: var(--bk-accent, #e11d48);
        font-size: 12px;
        font-weight: 700;
    }
    .brand-kit-meta {
        padding: 14px 16px;
        display: grid;
        gap: 10px;
    }
    .brand-kit-chip-row {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .brand-kit-chip {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
    }
    .brand-kit-assets {
        display: grid;
        gap: 8px;
    }
    .brand-kit-asset {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr) auto;
        gap: 10px;
        align-items: center;
        padding: 9px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    .brand-kit-asset-icon {
        width: 34px;
        height: 34px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        color: #475569;
    }
    .brand-kit-asset-name {
        font-size: 13px;
        font-weight: 650;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .brand-kit-asset-path {
        font-size: 11px;
        color: #64748b;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .brand-kit-danger {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #be123c;
        border: 1px solid #fecdd3;
        background: #fff1f2;
    }
    @media (max-width: 1180px) {
        .brand-kit-shell {
            grid-template-columns: minmax(0, 1fr);
        }
        .brand-kit-sticky {
            position: static;
        }
    }
    @media (max-width: 760px) {
        .brand-kit-grid,
        .brand-kit-grid-5 {
            grid-template-columns: 1fr;
        }
        .brand-kit-actions {
            justify-content: stretch;
        }
        .brand-kit-actions .brand-kit-button {
            flex: 1;
        }
    }
</style>
@endsection

@section('header-actions')
    <button type="button" class="brand-kit-button" id="refreshBrandKitBtn">
        <iconify-icon icon="heroicons-outline:arrow-path" width="15"></iconify-icon>
        Refresh
    </button>
    <button type="button" class="brand-kit-button brand-kit-button-primary" id="saveBrandKitBtn">
        <iconify-icon icon="heroicons-outline:check" width="15"></iconify-icon>
        Save
    </button>
@endsection

@section('content')
<div id="brandKitPage">
    <div class="brand-kit-actions md:hidden">
        <button type="button" class="brand-kit-button" data-action="refresh">
            <iconify-icon icon="heroicons-outline:arrow-path" width="15"></iconify-icon>
            Refresh
        </button>
        <button type="button" class="brand-kit-button brand-kit-button-primary" data-action="save">
            <iconify-icon icon="heroicons-outline:check" width="15"></iconify-icon>
            Save
        </button>
    </div>

    <div class="brand-kit-shell">
        <div class="brand-kit-main">
            <section class="brand-kit-panel">
                <div class="brand-kit-panel-header">
                    <div>
                        <div class="brand-kit-panel-title">
                            <iconify-icon icon="heroicons-outline:swatch" width="17"></iconify-icon>
                            Colors
                        </div>
                        <div class="brand-kit-panel-subtitle">Primary palette used by Studio, templates, and AI previews.</div>
                    </div>
                </div>
                <div class="brand-kit-panel-body brand-kit-grid-5">
                    @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'neutral' => 'Neutral', 'text' => 'Text'] as $key => $label)
                        <label class="brand-kit-field">
                            <span class="brand-kit-label">{{ $label }}</span>
                            <span class="brand-kit-color-row">
                                <input class="brand-kit-color" type="color" id="color_{{ $key }}">
                                <input class="brand-kit-input" type="text" id="colorText_{{ $key }}" placeholder="#000000">
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="brand-kit-panel">
                <div class="brand-kit-panel-header">
                    <div>
                        <div class="brand-kit-panel-title">
                            <iconify-icon icon="heroicons-outline:language" width="17"></iconify-icon>
                            Typography
                        </div>
                        <div class="brand-kit-panel-subtitle">Font families and weights injected into image and video templates.</div>
                    </div>
                </div>
                <div class="brand-kit-panel-body brand-kit-grid">
                    @foreach(['display' => 'Display', 'body' => 'Body', 'mono' => 'Mono'] as $key => $label)
                        <label class="brand-kit-field">
                            <span class="brand-kit-label">{{ $label }} family</span>
                            <input class="brand-kit-input" type="text" id="font_{{ $key }}" placeholder="Inter">
                        </label>
                        <label class="brand-kit-field">
                            <span class="brand-kit-label">{{ $label }} weights</span>
                            <input class="brand-kit-input" type="text" id="weights_{{ $key }}" placeholder="400, 500, 600">
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="brand-kit-panel">
                <div class="brand-kit-panel-header">
                    <div>
                        <div class="brand-kit-panel-title">
                            <iconify-icon icon="heroicons-outline:sparkles" width="17"></iconify-icon>
                            Voice and captions
                        </div>
                        <div class="brand-kit-panel-subtitle">Source of truth for Claude caption prompts and rewrite tone.</div>
                    </div>
                </div>
                <div class="brand-kit-panel-body brand-kit-grid">
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Voice SQ</span>
                        <textarea class="brand-kit-textarea" id="voice_sq" placeholder="Short, commercial, natural Albanian tone..."></textarea>
                    </label>
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Voice EN</span>
                        <textarea class="brand-kit-textarea" id="voice_en" placeholder="Short, premium, direct English tone..."></textarea>
                    </label>
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Hook patterns</span>
                        <textarea class="brand-kit-textarea" id="hook_patterns" placeholder="One hook per line"></textarea>
                    </label>
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">CTA patterns</span>
                        <textarea class="brand-kit-textarea" id="cta_patterns" placeholder="One CTA per line"></textarea>
                    </label>
                    <label class="brand-kit-field" style="grid-column:1 / -1;">
                        <span class="brand-kit-label">Default hashtags</span>
                        <textarea class="brand-kit-textarea" id="default_hashtags" placeholder="#zeroabsolute&#10;#newdrop"></textarea>
                    </label>
                </div>
            </section>

            <section class="brand-kit-panel">
                <div class="brand-kit-panel-header">
                    <div>
                        <div class="brand-kit-panel-title">
                            <iconify-icon icon="heroicons-outline:photo" width="17"></iconify-icon>
                            Logos, watermark, aspect ratios
                        </div>
                        <div class="brand-kit-panel-subtitle">Asset references and format defaults used when Studio opens a new brief.</div>
                    </div>
                </div>
                <div class="brand-kit-panel-body brand-kit-grid">
                    @foreach(['dark' => 'Dark logo path', 'light' => 'Light logo path', 'transparent' => 'Transparent logo path', 'icon' => 'Icon path'] as $key => $label)
                        <label class="brand-kit-field">
                            <span class="brand-kit-label">{{ $label }}</span>
                            <input class="brand-kit-input" type="text" id="logo_{{ $key }}" placeholder="marketing/assets/logo/...">
                        </label>
                    @endforeach

                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Watermark path</span>
                        <input class="brand-kit-input" type="text" id="watermark_path" placeholder="marketing/assets/watermark/...">
                    </label>
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Watermark position</span>
                        <select class="brand-kit-select" id="watermark_position">
                            <option value="bottom-right">Bottom right</option>
                            <option value="bottom-left">Bottom left</option>
                            <option value="top-right">Top right</option>
                            <option value="top-left">Top left</option>
                            <option value="center">Center</option>
                        </select>
                    </label>
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Opacity</span>
                        <input class="brand-kit-input" type="number" step="0.01" min="0" max="1" id="watermark_opacity">
                    </label>
                    <label class="brand-kit-field">
                        <span class="brand-kit-label">Scale</span>
                        <input class="brand-kit-input" type="number" step="0.01" min="0.01" max="1" id="watermark_scale">
                    </label>

                    @foreach(['photo' => 'Photo', 'carousel' => 'Carousel', 'story' => 'Story', 'reel' => 'Reel', 'video' => 'Video'] as $key => $label)
                        <label class="brand-kit-field">
                            <span class="brand-kit-label">{{ $label }} aspect</span>
                            <select class="brand-kit-select" data-aspect="{{ $key }}">
                                <option value="1:1">1:1</option>
                                <option value="4:5">4:5</option>
                                <option value="9:16">9:16</option>
                                <option value="16:9">16:9</option>
                            </select>
                        </label>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="brand-kit-sticky">
            <section class="brand-kit-panel">
                <div class="brand-kit-preview" id="brandPreview">
                    <div class="brand-kit-preview-mark">ZA</div>
                    <div>
                        <div class="brand-kit-preview-title">Zero Absolute Studio</div>
                        <div class="brand-kit-preview-copy" id="previewVoice">Brand settings will feed Polotno, Remotion, and AI captions.</div>
                    </div>
                    <span class="brand-kit-preview-cta">New drop</span>
                </div>
                <div class="brand-kit-meta">
                    <div>
                        <div class="brand-kit-label">Default hashtags</div>
                        <div class="brand-kit-chip-row" id="hashtagPreview"></div>
                    </div>
                    <div>
                        <div class="brand-kit-label">Aspect defaults</div>
                        <div class="brand-kit-chip-row" id="aspectPreview"></div>
                    </div>
                </div>
            </section>

            <section class="brand-kit-panel">
                <div class="brand-kit-panel-header">
                    <div>
                        <div class="brand-kit-panel-title">
                            <iconify-icon icon="heroicons-outline:folder-arrow-down" width="17"></iconify-icon>
                            Assets
                        </div>
                        <div class="brand-kit-panel-subtitle">Logos, watermark, music, fonts, stickers.</div>
                    </div>
                </div>
                <div class="brand-kit-panel-body">
                    <form id="assetUploadForm" class="brand-kit-main" enctype="multipart/form-data">
                        <div class="brand-kit-grid">
                            <label class="brand-kit-field">
                                <span class="brand-kit-label">Kind</span>
                                <select class="brand-kit-select" name="kind">
                                    <option value="logo">Logo</option>
                                    <option value="watermark">Watermark</option>
                                    <option value="music">Music</option>
                                    <option value="font">Font</option>
                                    <option value="sticker">Sticker</option>
                                    <option value="template-asset">Template asset</option>
                                </select>
                            </label>
                            <label class="brand-kit-field">
                                <span class="brand-kit-label">Name</span>
                                <input class="brand-kit-input" type="text" name="name" required placeholder="Asset name">
                            </label>
                        </div>
                        <label class="brand-kit-field">
                            <span class="brand-kit-label">File</span>
                            <input class="brand-kit-input" type="file" name="file" required>
                        </label>
                        <button class="brand-kit-button" type="submit">
                            <iconify-icon icon="heroicons-outline:arrow-up-tray" width="15"></iconify-icon>
                            Upload asset
                        </button>
                    </form>

                    <div class="brand-kit-assets" id="assetList" style="margin-top:14px;"></div>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const endpoints = {
        show: @json(route('marketing.api.brand-kit.show')),
        update: @json(route('marketing.api.brand-kit.update')),
        uploadAsset: @json(route('marketing.api.brand-kit.assets.store')),
        deleteAsset: @json(route('marketing.api.brand-kit.assets.destroy', ['asset' => '__ID__'])),
    };

    let brandKit = @json($kitPayload);
    let assets = @json($assetPayload);

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const lineList = (value) => String(value || '').split('\n').map(v => v.trim()).filter(Boolean);
    const csvList = (value) => String(value || '').split(',').map(v => v.trim()).filter(Boolean);

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value ?? '';
    }

    function fillForm() {
        const colors = brandKit.colors || {};
        ['primary', 'secondary', 'accent', 'neutral', 'text'].forEach(key => {
            const value = colors[key] || '#000000';
            setValue(`color_${key}`, value);
            setValue(`colorText_${key}`, value);
        });

        const typography = brandKit.typography || {};
        ['display', 'body', 'mono'].forEach(key => {
            setValue(`font_${key}`, typography[key]?.family || '');
            setValue(`weights_${key}`, (typography[key]?.weights || []).join(', '));
        });

        const logos = brandKit.logo_variants || {};
        ['dark', 'light', 'transparent', 'icon'].forEach(key => setValue(`logo_${key}`, logos[key] || ''));

        const watermark = brandKit.watermark || {};
        setValue('watermark_path', watermark.path || '');
        setValue('watermark_position', watermark.position || 'bottom-right');
        setValue('watermark_opacity', watermark.opacity ?? 0.72);
        setValue('watermark_scale', watermark.scale ?? 0.18);

        setValue('voice_sq', brandKit.voice_sq || '');
        setValue('voice_en', brandKit.voice_en || '');
        setValue('hook_patterns', (brandKit.caption_templates?.hook_patterns || []).join('\n'));
        setValue('cta_patterns', (brandKit.caption_templates?.cta_patterns || []).join('\n'));
        setValue('default_hashtags', (brandKit.default_hashtags || []).join('\n'));

        const aspects = Object.fromEntries((brandKit.aspect_defaults || []).map(row => [row.post_type, row.aspect]));
        $$('[data-aspect]').forEach(select => {
            select.value = aspects[select.dataset.aspect] || select.value;
        });

        renderPreview();
    }

    function collectPayload() {
        const musicAssets = assets
            .filter(asset => asset.kind === 'music')
            .map(asset => ({
                id: asset.id,
                name: asset.name,
                path: asset.path,
                duration_seconds: asset.duration_seconds,
                metadata: asset.metadata || {},
            }));

        return {
            colors: {
                primary: $('#colorText_primary').value,
                secondary: $('#colorText_secondary').value,
                accent: $('#colorText_accent').value,
                neutral: $('#colorText_neutral').value,
                text: $('#colorText_text').value,
            },
            typography: {
                display: { family: $('#font_display').value, weights: csvList($('#weights_display').value) },
                body: { family: $('#font_body').value, weights: csvList($('#weights_body').value) },
                mono: { family: $('#font_mono').value, weights: csvList($('#weights_mono').value) },
            },
            logo_variants: {
                dark: $('#logo_dark').value || null,
                light: $('#logo_light').value || null,
                transparent: $('#logo_transparent').value || null,
                icon: $('#logo_icon').value || null,
            },
            watermark: {
                path: $('#watermark_path').value || null,
                position: $('#watermark_position').value,
                opacity: Number($('#watermark_opacity').value || 0.72),
                scale: Number($('#watermark_scale').value || 0.18),
            },
            voice_sq: $('#voice_sq').value || null,
            voice_en: $('#voice_en').value || null,
            caption_templates: {
                hook_patterns: lineList($('#hook_patterns').value),
                cta_patterns: lineList($('#cta_patterns').value),
            },
            default_hashtags: lineList($('#default_hashtags').value),
            music_library: musicAssets,
            aspect_defaults: $$('[data-aspect]').map(select => ({
                post_type: select.dataset.aspect,
                aspect: select.value,
            })),
        };
    }

    async function saveBrandKit() {
        const button = $('#saveBrandKitBtn');
        button.disabled = true;

        try {
            const response = await fetch(endpoints.update, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(collectPayload()),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Save failed.');
            brandKit = data.brand_kit;
            fillForm();
            toastr.success(data.message || 'Brand kit saved.');
        } catch (error) {
            toastr.error(error.message || 'Brand kit save failed.');
        } finally {
            button.disabled = false;
        }
    }

    async function refreshBrandKit() {
        try {
            const response = await fetch(endpoints.show, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Refresh failed.');
            brandKit = data.brand_kit;
            assets = data.assets || [];
            fillForm();
            renderAssets();
            toastr.success('Brand kit refreshed.');
        } catch (error) {
            toastr.error(error.message || 'Refresh failed.');
        }
    }

    async function uploadAsset(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = $('button[type="submit"]', form);
        button.disabled = true;

        try {
            const response = await fetch(endpoints.uploadAsset, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: new FormData(form),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Upload failed.');
            assets = [...assets.filter(asset => asset.id !== data.asset.id), data.asset]
                .sort((a, b) => `${a.kind}-${a.name}`.localeCompare(`${b.kind}-${b.name}`));
            form.reset();
            renderAssets();
            renderPreview();
            toastr.success(data.message || 'Asset uploaded.');
        } catch (error) {
            toastr.error(error.message || 'Upload failed.');
        } finally {
            button.disabled = false;
        }
    }

    async function deleteAsset(assetId) {
        try {
            const response = await fetch(endpoints.deleteAsset.replace('__ID__', assetId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Delete failed.');
            assets = assets.filter(asset => asset.id !== assetId);
            renderAssets();
            renderPreview();
            toastr.success(data.message || 'Asset deleted.');
        } catch (error) {
            toastr.error(error.message || 'Delete failed.');
        }
    }

    function renderAssets() {
        const target = $('#assetList');
        if (!assets.length) {
            target.innerHTML = '<div class="text-sm text-slate-500">No brand assets uploaded yet.</div>';
            return;
        }

        target.innerHTML = assets.map(asset => `
            <div class="brand-kit-asset">
                <div class="brand-kit-asset-icon">
                    <iconify-icon icon="${assetIcon(asset.kind)}" width="17"></iconify-icon>
                </div>
                <div style="min-width:0;">
                    <div class="brand-kit-asset-name">${escapeHtml(asset.name)}</div>
                    <div class="brand-kit-asset-path">${escapeHtml(asset.kind)} · ${escapeHtml(asset.path)}</div>
                </div>
                <button type="button" class="brand-kit-danger" data-delete-asset="${asset.id}" title="Delete asset">
                    <iconify-icon icon="heroicons-outline:trash" width="15"></iconify-icon>
                </button>
            </div>
        `).join('');

        $$('[data-delete-asset]', target).forEach(button => {
            button.addEventListener('click', () => deleteAsset(Number(button.dataset.deleteAsset)));
        });
    }

    function renderPreview() {
        const payload = collectPayload();
        const preview = $('#brandPreview');
        Object.entries(payload.colors).forEach(([key, value]) => {
            preview.style.setProperty(`--bk-${key}`, value || '');
        });

        $('#previewVoice').textContent = payload.voice_sq || payload.voice_en || 'Brand settings will feed Polotno, Remotion, and AI captions.';
        $('#hashtagPreview').innerHTML = (payload.default_hashtags.length ? payload.default_hashtags : ['#zeroabsolute'])
            .map(tag => `<span class="brand-kit-chip">${escapeHtml(tag)}</span>`)
            .join('');
        $('#aspectPreview').innerHTML = payload.aspect_defaults
            .map(row => `<span class="brand-kit-chip">${escapeHtml(row.post_type)} ${escapeHtml(row.aspect)}</span>`)
            .join('');
    }

    function assetIcon(kind) {
        return {
            logo: 'heroicons-outline:photo',
            watermark: 'heroicons-outline:shield-check',
            music: 'heroicons-outline:musical-note',
            font: 'heroicons-outline:language',
            sticker: 'heroicons-outline:sparkles',
            'template-asset': 'heroicons-outline:squares-plus',
        }[kind] || 'heroicons-outline:paper-clip';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    ['primary', 'secondary', 'accent', 'neutral', 'text'].forEach(key => {
        $(`#color_${key}`).addEventListener('input', event => {
            $(`#colorText_${key}`).value = event.target.value;
            renderPreview();
        });
        $(`#colorText_${key}`).addEventListener('input', event => {
            if (/^#[0-9a-fA-F]{6}$/.test(event.target.value)) {
                $(`#color_${key}`).value = event.target.value;
            }
            renderPreview();
        });
    });

    ['input', 'change'].forEach(type => {
        document.addEventListener(type, event => {
            if (event.target.closest('#brandKitPage')) renderPreview();
        });
    });

    $('#saveBrandKitBtn')?.addEventListener('click', saveBrandKit);
    $('#refreshBrandKitBtn')?.addEventListener('click', refreshBrandKit);
    $('[data-action="save"]')?.addEventListener('click', saveBrandKit);
    $('[data-action="refresh"]')?.addEventListener('click', refreshBrandKit);
    $('#assetUploadForm').addEventListener('submit', uploadAsset);

    fillForm();
    renderAssets();
})();
</script>
@endsection
