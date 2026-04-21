import { PropsWithChildren, ReactNode } from 'react';

/**
 * Visual Studio shell — top bar + left sidebar + center canvas + right
 * properties panel + bottom timeline area. Regions are exposed as props so
 * route components (task #1243/#1244) can slot in their editors without
 * the layout having to know about Polotno or Remotion.
 */
interface LayoutProps {
    title: string;
    leftSidebar?: ReactNode;
    rightSidebar?: ReactNode;
    timeline?: ReactNode;
    actions?: ReactNode;
}

export function StudioLayout({
    title,
    leftSidebar,
    rightSidebar,
    timeline,
    actions,
    children,
}: PropsWithChildren<LayoutProps>) {
    return (
        <div className="flex h-[calc(100vh-3rem)] w-full flex-col bg-zinc-950 text-zinc-100">
            <header className="flex h-12 shrink-0 items-center justify-between border-b border-zinc-800 bg-zinc-900 px-4">
                <div className="flex items-center gap-3">
                    <span className="rounded-md bg-gradient-to-br from-violet-500 to-pink-500 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide">
                        Studio
                    </span>
                    <h1 className="text-sm font-medium text-zinc-200">{title}</h1>
                </div>
                <div className="flex items-center gap-2">{actions}</div>
            </header>

            <div className="flex flex-1 min-h-0">
                {leftSidebar ? (
                    <aside className="w-60 shrink-0 overflow-y-auto border-r border-zinc-800 bg-zinc-900/60">
                        {leftSidebar}
                    </aside>
                ) : null}

                <main className="flex flex-1 min-w-0 flex-col">
                    <div className="flex-1 min-h-0 overflow-hidden bg-zinc-950/90 p-4">
                        <div className="h-full w-full rounded-lg border border-zinc-800 bg-black/40">
                            {children}
                        </div>
                    </div>

                    {timeline ? (
                        <div className="h-40 shrink-0 border-t border-zinc-800 bg-zinc-900/60 p-3">
                            {timeline}
                        </div>
                    ) : null}
                </main>

                {rightSidebar ? (
                    <aside className="w-72 shrink-0 overflow-y-auto border-l border-zinc-800 bg-zinc-900/60">
                        {rightSidebar}
                    </aside>
                ) : null}
            </div>
        </div>
    );
}
