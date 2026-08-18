import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function formatAppRelease(version: string, revision: string): string {
    return revision && revision !== 'dev' ? `v${version} · ${revision}` : `v${version}`;
}

export function AppVersion({ className }: { className?: string }) {
    const { appVersion, appRevision } = usePage<SharedProps>().props;

    return (
        <span className={className} title="Versão desta instância">
            {formatAppRelease(appVersion, appRevision)}
        </span>
    );
}
