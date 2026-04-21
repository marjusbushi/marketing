import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Visual Studio — React SPA bundle, code-split at route level
                // so Polotno/Remotion load on demand (see task #1243/#1244).
                'resources/js/studio/main.tsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
            '@studio': '/resources/js/studio',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
