import { Link } from '@inertiajs/react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationMeta {
    from: number | null;
    to: number | null;
    total: number;
}

interface PaginationProps {
    links: PaginationLink[];
    meta?: PaginationMeta;
}

/**
 * Paginação do Laravel paginator no padrão do design system, com
 * contador "Mostrando X–Y de Z" quando a página fornece o meta.
 */
export default function Pagination({ links, meta }: PaginationProps) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {meta && meta.from !== null && meta.to !== null ? (
                <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                    Mostrando <span className="font-medium text-gray-700 dark:text-gray-300">{meta.from}</span>
                    {'–'}
                    <span className="font-medium text-gray-700 dark:text-gray-300">{meta.to}</span> de{' '}
                    <span className="font-medium text-gray-700 dark:text-gray-300">{meta.total}</span>
                </p>
            ) : (
                <span />
            )}
            <nav className="flex flex-wrap items-center gap-1" aria-label="Paginação">
                {links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={`inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm font-medium transition ${
                                link.active
                                    ? 'bg-brand-500 text-white'
                                    : 'text-gray-700 hover:bg-brand-50 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-brand-500/[0.12] dark:hover:text-brand-400'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm text-gray-400 dark:text-gray-600"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </nav>
        </div>
    );
}
