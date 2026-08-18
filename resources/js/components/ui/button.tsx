import type { ReactNode } from 'react';

interface ButtonProps {
    children: ReactNode;
    size?: 'xs' | 'sm' | 'md';
    variant?: 'primary' | 'outline' | 'ghost' | 'danger';
    type?: 'button' | 'submit' | 'reset';
    startIcon?: ReactNode;
    endIcon?: ReactNode;
    onClick?: () => void;
    disabled?: boolean;
    /** Estado de submissão (ex.: `processing` do Inertia). Desabilita o botão e exibe um spinner. */
    loading?: boolean;
    className?: string;
}

/**
 * Indicador de foco via outline (e não ring) para não conflitar com a
 * variante "outline", que usa ring-1 inset como borda. A cor do outline
 * fica em cada variante para evitar disputa de cascata.
 */
const focusClasses = 'focus:outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2';

function Spinner() {
    return (
        <svg
            className="size-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
        </svg>
    );
}

export default function Button({
    children,
    size = 'md',
    variant = 'primary',
    type = 'button',
    startIcon,
    endIcon,
    onClick,
    className = '',
    disabled = false,
    loading = false,
}: ButtonProps) {
    const sizeClasses = {
        xs: 'px-3 py-2 text-theme-xs',
        sm: 'px-4 py-3 text-sm',
        md: 'px-5 py-3.5 text-sm',
    };

    /*
     * Esmaecimento de desabilitado em camada única: variantes sólidas usam
     * a cor -300 da própria escala; outline/ghost usam opacity. Nunca os dois.
     */
    const variantClasses = {
        primary:
            'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300 focus-visible:outline-brand-500',
        outline:
            'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:hover:bg-white focus-visible:outline-brand-500 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300 dark:disabled:hover:bg-gray-800',
        ghost: 'text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:hover:bg-transparent focus-visible:outline-brand-500 dark:text-gray-400 dark:hover:bg-white/[0.05] dark:hover:text-gray-300',
        danger: 'bg-error-500 text-white shadow-theme-xs hover:bg-error-600 disabled:bg-error-300 focus-visible:outline-error-500',
    };

    const isDisabled = disabled || loading;

    return (
        <button
            type={type}
            className={`inline-flex items-center justify-center gap-2 rounded-lg transition disabled:cursor-not-allowed ${
                sizeClasses[size]
            } ${variantClasses[variant]} ${focusClasses} ${className}`}
            onClick={onClick}
            disabled={isDisabled}
            aria-busy={loading || undefined}
        >
            {loading ? <Spinner /> : startIcon && <span className="flex items-center">{startIcon}</span>}
            {children}
            {endIcon && <span className="flex items-center">{endIcon}</span>}
        </button>
    );
}
