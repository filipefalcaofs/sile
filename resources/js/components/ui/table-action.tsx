import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type TableActionTone = 'brand' | 'warning' | 'success' | 'error' | 'neutral';

interface TableActionProps {
    children: ReactNode;
    tone?: TableActionTone;
    onClick?: () => void;
    /** Quando informado, renderiza um Link de navegação no lugar do botão. */
    href?: string;
    disabled?: boolean;
    title?: string;
}

const baseStyles =
    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-theme-xs font-medium ring-1 ring-inset transition disabled:cursor-not-allowed disabled:opacity-60';

const toneStyles: Record<TableActionTone, string> = {
    brand: 'text-brand-500 ring-brand-200 hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10',
    warning:
        'text-warning-600 ring-warning-300 hover:bg-warning-50 dark:text-orange-400 dark:ring-warning-500/30 dark:hover:bg-warning-500/10',
    success:
        'text-success-600 ring-success-300 hover:bg-success-50 dark:text-success-400 dark:ring-success-500/30 dark:hover:bg-success-500/10',
    error: 'text-error-600 ring-error-300 hover:bg-error-50 dark:text-error-400 dark:ring-error-500/30 dark:hover:bg-error-500/10',
    neutral:
        'text-gray-700 ring-gray-300 hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.05]',
};

/**
 * Ação contextual de linha de tabela (Editar, Desativar, Excluir…) no
 * padrão de CRUD do design system — texto explícito, nunca só ícone.
 */
export default function TableAction({
    children,
    tone = 'brand',
    onClick,
    href,
    disabled = false,
    title,
}: TableActionProps) {
    const className = `${baseStyles} ${toneStyles[tone]}`;

    if (href && !disabled) {
        return (
            <Link href={href} className={className} title={title}>
                {children}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} disabled={disabled} title={title} className={className}>
            {children}
        </button>
    );
}
