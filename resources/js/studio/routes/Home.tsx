import { Link } from 'react-router-dom';
import { StudioLayout } from '@studio/components/Layout';
import { StudioProps } from '@studio/types/props';

interface HomeProps {
    studio: StudioProps;
}

/**
 * Studio home screen — surfaces a small set of entry points until the
 * editor integrations land in tasks #1243/#1244.
 */
export function Home({ studio }: HomeProps) {
    return (
        <StudioLayout
            title="Visual Studio"
            actions={
                <Link
                    to="/new"
                    className="rounded-md bg-emerald-500 px-3 py-1.5 text-xs font-medium text-emerald-950 hover:bg-emerald-400"
                >
                    + Brief i ri
                </Link>
            }
        >
            <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                <div>
                    <h2 className="text-2xl font-semibold text-zinc-100">
                        Mirë se vjen, {studio.user.name || 'User'}
                    </h2>
                    <p className="mt-2 max-w-md text-sm text-zinc-400">
                        Visual Studio-ja është hapësira jote për krijim të postave me
                        brand kit-in e Zero Absolute. Zgjedh një veprim për të filluar.
                    </p>
                </div>

                <div className="mt-4 grid w-full max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
                    <ActionCard
                        to="/new"
                        title="Post i ri"
                        description="Krijon brief nga zero; editor zgjidhet sipas post_type."
                    />
                    <ActionCard
                        to="/new"
                        title="Nga template"
                        description="Nis me një template të brand-it (7 default)."
                    />
                    <ActionCard
                        to="/"
                        title="Draft-et e mia"
                        description="Vazhdon nga një brief i ruajtur më parë."
                        disabled
                    />
                </div>

                <div className="mt-6 text-xs text-zinc-500">
                    Brand kit:{' '}
                    <span className="text-zinc-300">
                        {studio.brand_kit.voice_sq ? 'i konfiguruar' : 'ende pa konfiguruar'}
                    </span>
                    {' · '}
                    CSRF: <span className="text-zinc-300">{studio.csrf_token.slice(0, 8)}…</span>
                </div>
            </div>
        </StudioLayout>
    );
}

interface ActionCardProps {
    to: string;
    title: string;
    description: string;
    disabled?: boolean;
}

function ActionCard({ to, title, description, disabled }: ActionCardProps) {
    const className =
        'rounded-lg border p-4 text-left transition ' +
        (disabled
            ? 'border-zinc-800 bg-zinc-900/40 text-zinc-500 cursor-not-allowed'
            : 'border-zinc-800 bg-zinc-900/60 hover:border-violet-500 hover:bg-zinc-800/60');

    if (disabled) {
        return (
            <div className={className} aria-disabled>
                <div className="text-sm font-medium text-zinc-400">{title}</div>
                <div className="mt-1 text-xs text-zinc-500">{description}</div>
            </div>
        );
    }

    return (
        <Link to={to} className={className}>
            <div className="text-sm font-medium text-zinc-100">{title}</div>
            <div className="mt-1 text-xs text-zinc-400">{description}</div>
        </Link>
    );
}
