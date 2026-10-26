import { CheckCircleIcon } from '@/components/icons';
import { formatDateTime } from '@/lib/datas';

/** Tramitação pública: etapas concluídas e situação atual, sem dados pessoais. */
type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

export interface TimelineStep {
    rotulo: string;
    data: string | null;
}

interface PrazoEstimado {
    dias: number;
    ressalva: string;
}

export interface Timeline {
    status_atual: { value: string; public_label: string };
    etapas: TimelineStep[];
    pendencias: string[];
    prazo_estimado: PrazoEstimado | null;
}

export function statusColor(value: string): BadgeColor {
    switch (value) {
        case 'protocolada':
            return 'info';
        case 'deferida':
            return 'success';
        case 'indeferida':
        case 'cancelada':
            return 'error';
        case 'em_pendencia':
        case 'aguardando_bap':
            return 'warning';
        default:
            return 'light';
    }
}

export function TimelinePublica({ timeline }: { timeline: Timeline }) {
    return (
        <ol className="flex flex-col">
            {timeline.etapas.length === 0 && (
                <li className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Ainda não há etapas registradas para este protocolo.
                </li>
            )}
            {timeline.etapas.map((etapa, index) => {
                const isLast = index === timeline.etapas.length - 1;

                return (
                    <li key={`${etapa.rotulo}-${index}`} className="flex gap-4">
                        <div className="flex flex-col items-center">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                <CheckCircleIcon className="size-5 fill-current" />
                            </span>
                            {!isLast && <span className="my-1 w-px grow bg-gray-200 dark:bg-gray-700" aria-hidden="true" />}
                        </div>
                        <div className="pb-6">
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">{etapa.rotulo}</p>
                            <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                {formatDateTime(etapa.data)}
                            </p>
                        </div>
                    </li>
                );
            })}
            <li className="flex gap-4">
                <div className="flex flex-col items-center">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-500 ring-2 ring-brand-200 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500/30">
                        <span className="size-2.5 rounded-full bg-current" aria-hidden="true" />
                    </span>
                </div>
                <div>
                    <p className="text-theme-xs font-medium text-brand-600 dark:text-brand-400">Situação atual</p>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white/90">
                        {timeline.status_atual.public_label}
                    </p>
                </div>
            </li>
        </ol>
    );
}
