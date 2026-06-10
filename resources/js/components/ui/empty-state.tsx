import type { ReactNode } from 'react';

interface EmptyStateProps {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}

/**
 * Estado vazio para listagens e tabelas: ícone, mensagem e ação
 * opcional, no visual do design system.
 */
export default function EmptyState({ icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
            {icon && (
                <span className="mb-2 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                    {icon}
                </span>
            )}
            <p className="text-base font-semibold text-gray-800 dark:text-white/90">{title}</p>
            {description && <p className="max-w-md text-theme-sm text-gray-500 dark:text-gray-400">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
