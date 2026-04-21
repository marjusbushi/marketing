import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { Home } from '@studio/routes/Home';
import { BriefEditor } from '@studio/routes/BriefEditor';
import { ToastProvider } from '@studio/components/ToastHost';
import { StudioProps } from '@studio/types/props';

interface AppProps {
    studio: StudioProps;
}

/**
 * Router root. All routes live under `/marketing/studio` (basename set in
 * main.tsx) so the SPA can co-exist with the rest of the Blade app.
 *
 * ToastProvider wraps the router so every route can call `useToast()`
 * without each route wiring its own feedback system.
 */
export function App({ studio }: AppProps) {
    const startRoute = studio.creative_brief_id ? `/${studio.creative_brief_id}` : '/';

    return (
        <ToastProvider>
            <BrowserRouter basename="/marketing/studio">
                <Routes>
                    <Route path="/" element={<Home studio={studio} />} />
                    <Route path="/new" element={<BriefEditor studio={studio} />} />
                    <Route path="/:id" element={<BriefEditor studio={studio} />} />
                    <Route path="*" element={<Navigate to={startRoute} replace />} />
                </Routes>
            </BrowserRouter>
        </ToastProvider>
    );
}
