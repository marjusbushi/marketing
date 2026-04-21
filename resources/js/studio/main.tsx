import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '@studio/App';
import { StudioProps } from '@studio/types/props';

/**
 * Visual Studio entrypoint.
 *
 * Mounts the React SPA into `#studio-app` with the Laravel-provided props
 * passed through `data-props` JSON. A single mount per page — switching
 * between briefs happens via the internal router without full reloads.
 */
const mountEl = document.getElementById('studio-app');

if (!mountEl) {
    throw new Error('Visual Studio: #studio-app mount node not found.');
}

const raw = mountEl.getAttribute('data-props') ?? '{}';
let props: StudioProps;

try {
    props = JSON.parse(raw) as StudioProps;
} catch (e) {
    throw new Error('Visual Studio: failed to parse initial props JSON.');
}

createRoot(mountEl).render(
    <StrictMode>
        <App studio={props} />
    </StrictMode>,
);
