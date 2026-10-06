import { useId, type TextareaHTMLAttributes } from 'react';

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    success?: boolean;
    error?: boolean;
    hint?: string;
}

/**
 * Área de texto no mesmo contrato visual do Input: erro, hint e
 * associação do texto de apoio ao campo.
 */
export default function Textarea({
    className = '',
    disabled = false,
    success = false,
    error = false,
    hint,
    id,
    rows = 4,
    ...rest
}: TextareaProps) {
    const hintId = useId();

    let classes = `min-h-24 w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:scheme-dark dark:placeholder:text-white/30 ${className}`;

    if (disabled) {
        classes += ` cursor-not-allowed border-gray-300 bg-gray-100 text-gray-500 opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400`;
    } else if (error) {
        classes += ` border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:border-error-500 dark:text-error-400 dark:focus:border-error-800`;
    } else if (success) {
        classes += ` border-success-500 focus:border-success-300 focus:ring-success-500/20 dark:border-success-500 dark:text-success-400 dark:focus:border-success-800`;
    } else {
        classes += ` border-gray-300 bg-transparent text-gray-800 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800`;
    }

    return (
        <div className="relative">
            <textarea
                id={id}
                rows={rows}
                disabled={disabled}
                className={classes}
                {...rest}
                aria-invalid={error || undefined}
                aria-describedby={hint ? hintId : undefined}
            />
            {hint && (
                <p id={hintId} role={error ? 'alert' : undefined} className={`pt-1.5 text-xs ${error ? 'text-error-500' : success ? 'text-success-500' : 'text-gray-500'}`}>
                    {hint}
                </p>
            )}
        </div>
    );
}
