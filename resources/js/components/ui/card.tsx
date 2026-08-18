import type { ReactNode } from 'react';

interface CardProps {
    children: ReactNode;
    className?: string;
}

interface CardHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
}

interface CardContentProps {
    children: ReactNode;
    /** Remove o padding interno — para tabelas coladas nas bordas do card. */
    flush?: boolean;
    className?: string;
}

/**
 * Contêiner padrão do design system: superfície branca, hairline
 * gray-200 e cantos 16px. CardContent assume que segue um CardHeader
 * (divisor hairline no topo).
 */
export function Card({ children, className = '' }: CardProps) {
    return (
        <div
            className={`rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] ${className}`}
        >
            {children}
        </div>
    );
}

export function CardHeader({ title, description, actions }: CardHeaderProps) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3 px-6 py-5">
            <div>
                <h3 className="text-base font-medium text-gray-800 dark:text-white/90">{title}</h3>
                {description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-3">{actions}</div>}
        </div>
    );
}

export function CardContent({ children, flush = false, className = '' }: CardContentProps) {
    return (
        <div
            className={`border-t border-gray-100 dark:border-gray-800 ${flush ? '' : 'p-4 sm:p-6'} ${className}`}
        >
            {children}
        </div>
    );
}
