import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface PageHeaderProps {
    title: string;
    /** Ancestrais da trilha — o item final é sempre o próprio título. */
    breadcrumbs?: BreadcrumbItem[];
    actions?: ReactNode;
}

function BreadcrumbChevron() {
    return (
        <svg
            className="stroke-current"
            width="17"
            height="16"
            viewBox="0 0 17 16"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                strokeWidth="1.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * Cabeçalho de página do app shell: título à esquerda, trilha de
 * navegação à direita e ações opcionais — substitui os PageBreadcrumb
 * duplicados por página.
 */
export default function PageHeader({ title, breadcrumbs = [], actions }: PageHeaderProps) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-3">
                <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{title}</h2>
                {actions}
            </div>
            <nav aria-label="Trilha de navegação">
                <ol className="flex flex-wrap items-center gap-1.5">
                    {breadcrumbs.map((item) => (
                        <li key={item.label}>
                            {item.href ? (
                                <Link
                                    className="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                    href={item.href}
                                >
                                    {item.label}
                                    <BreadcrumbChevron />
                                </Link>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    {item.label}
                                    <BreadcrumbChevron />
                                </span>
                            )}
                        </li>
                    ))}
                    <li className="text-sm text-gray-800 dark:text-white/90" aria-current="page">
                        {title}
                    </li>
                </ol>
            </nav>
        </div>
    );
}
