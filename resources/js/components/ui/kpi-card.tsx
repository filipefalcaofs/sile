import type { ReactNode } from 'react';

type DeltaTone = 'up' | 'down' | 'neutral';

interface KpiCardProps {
    label: string;
    value: string | number;
    icon?: ReactNode;
    /** Variação real (nunca inventada) — tom controla a cor semântica. */
    delta?: { value: string; tone: DeltaTone };
    note?: string;
}

const deltaStyles: Record<DeltaTone, string> = {
    up: 'text-success-600 dark:text-success-500',
    down: 'text-error-600 dark:text-error-500',
    neutral: 'text-gray-500 dark:text-gray-400',
};

/**
 * Card de indicador (KPI): rótulo, valor em destaque, variação
 * opcional e nota de contexto, no padrão de métricas do design system.
 */
export default function KpiCard({ label, value, icon, delta, note }: KpiCardProps) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            {icon && (
                <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {icon}
                </div>
            )}
            <span className="text-sm text-gray-500 dark:text-gray-400">{label}</span>
            <div className="mt-2 flex flex-wrap items-end justify-between gap-2">
                <h4 className="text-title-sm font-bold tracking-tight text-gray-800 dark:text-white/90">{value}</h4>
                {(delta || note) && (
                    <p className="mb-1 flex items-center gap-1.5 text-theme-xs">
                        {delta && <span className={`font-semibold ${deltaStyles[delta.tone]}`}>{delta.value}</span>}
                        {note && <span className="text-gray-500 dark:text-gray-400">{note}</span>}
                    </p>
                )}
            </div>
        </div>
    );
}
