import type { ReactNode } from 'react';

type DeltaTone = 'up' | 'down' | 'neutral';

export type KpiTone = 'brand' | 'success' | 'warning' | 'error' | 'info';

interface KpiCardProps {
    label: string;
    value: string | number;
    icon?: ReactNode;
    /** Cor do well do ícone — diferenciação visual, não semântica de estado. */
    tone?: KpiTone;
    /** Variação real (nunca inventada) — tom controla a cor semântica. */
    delta?: { value: string; tone: DeltaTone };
    note?: string;
}

const deltaStyles: Record<DeltaTone, string> = {
    up: 'text-success-600 dark:text-success-500',
    down: 'text-error-600 dark:text-error-500',
    neutral: 'text-gray-500 dark:text-gray-400',
};

const toneStyles: Record<KpiTone, string> = {
    brand: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
    success: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
    warning: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    error: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
    info: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
};

/**
 * Card de indicador (KPI) no padrão mini-statistics: well colorido com
 * ícone à esquerda, rótulo e valor em destaque à direita, variação e
 * nota de contexto opcionais.
 */
export default function KpiCard({ label, value, icon, tone = 'brand', delta, note }: KpiCardProps) {
    return (
        <div className="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            {icon && (
                <div
                    className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-full ${toneStyles[tone]}`}
                >
                    {icon}
                </div>
            )}
            <div className="min-w-0">
                <span className="block truncate text-sm text-gray-500 dark:text-gray-400">{label}</span>
                <h4 className="mt-0.5 text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">{value}</h4>
                {(delta || note) && (
                    <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-theme-xs">
                        {delta && <span className={`font-semibold ${deltaStyles[delta.tone]}`}>{delta.value}</span>}
                        {note && <span className="text-gray-500 dark:text-gray-400">{note}</span>}
                    </p>
                )}
            </div>
        </div>
    );
}
