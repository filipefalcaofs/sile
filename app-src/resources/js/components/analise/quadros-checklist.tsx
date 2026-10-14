import { AlertIcon, CheckCircleIcon, CloseIcon, InfoIcon } from '@/components/icons';

export interface QuadroChecklist {
    key: string;
    label: string;
    estado: 'permitido' | 'nao_permitido' | 'condicionado' | 'pendente';
    detalhe: string;
}

/** Checklist dos quadros da LOUOS por CNAE: permitido, não permitido, condicionado ou pendente. */
export function QuadrosChecklist({ quadros }: { quadros: QuadroChecklist[] }) {
    if (quadros.length === 0) {
        return null;
    }

    return (
        <div className="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                Enquadramento por quadro
            </p>
            <ul className="mt-2 space-y-1.5">
                {quadros.map((quadro) => (
                    <li key={quadro.key} className="flex items-start gap-2">
                        <QuadroEstadoIcone estado={quadro.estado} />
                        <div className="min-w-0">
                            <span className="text-theme-xs font-medium text-gray-700 dark:text-gray-200">{quadro.label}</span>
                            <span className="block text-theme-xs text-gray-500 dark:text-gray-400">{quadro.detalhe}</span>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function QuadroEstadoIcone({ estado }: { estado: QuadroChecklist['estado'] }) {
    if (estado === 'permitido') {
        return <CheckCircleIcon className="mt-0.5 size-4 shrink-0 fill-current text-success-500" aria-label="Permitido" />;
    }

    if (estado === 'nao_permitido') {
        return <CloseIcon className="mt-0.5 size-4 shrink-0 fill-current text-error-500" aria-label="Não permitido" />;
    }

    if (estado === 'condicionado') {
        return <AlertIcon className="mt-0.5 size-4 shrink-0 fill-current text-warning-500" aria-label="Condicionado" />;
    }

    return <InfoIcon className="mt-0.5 size-4 shrink-0 fill-current text-gray-400" aria-label="Pendente" />;
}
