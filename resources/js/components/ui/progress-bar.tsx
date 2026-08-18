type ProgressTone = 'brand' | 'success' | 'warning' | 'error';

interface ProgressBarProps {
    /** Percentual de 0 a 100. */
    value: number;
    tone?: ProgressTone;
    /** Rótulo acessível do que está sendo medido. */
    label: string;
    showValue?: boolean;
}

const toneStyles: Record<ProgressTone, string> = {
    brand: 'bg-brand-500',
    success: 'bg-success-500',
    warning: 'bg-warning-500',
    error: 'bg-error-500',
};

/**
 * Barra de progresso fina do design system — distribuições e
 * percentuais em listagens e painéis.
 */
export default function ProgressBar({ value, tone = 'brand', label, showValue = false }: ProgressBarProps) {
    const clamped = Math.min(100, Math.max(0, value));

    return (
        <div className="flex items-center gap-3">
            <div
                role="progressbar"
                aria-label={label}
                aria-valuenow={Math.round(clamped)}
                aria-valuemin={0}
                aria-valuemax={100}
                className="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
            >
                <div
                    className={`h-full rounded-full transition-[width] duration-300 ${toneStyles[tone]}`}
                    style={{ width: `${clamped}%` }}
                />
            </div>
            {showValue && (
                <span className="shrink-0 text-theme-xs font-medium text-gray-700 dark:text-gray-300">
                    {Math.round(clamped)}%
                </span>
            )}
        </div>
    );
}
