import { Link } from '@inertiajs/react';
import { ArrowRightIcon } from '@/components/icons';

export interface ConviteResumo {
    id: number;
    solicitacao_id: number;
    protocol_number: string | null;
    empresa: string | null;
    descricao: string;
    due_at: string | null;
}

/** 'YYYY-MM-DD HH:MM:SS' como 'DD/MM/YYYY' sem depender do fuso do navegador. */
export function dataCurta(value: string | null): string {
    const match = value?.match(/^(\d{4})-(\d{2})-(\d{2})/);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : '—';
}

/** Convites do requerente com o botão Responder (painel e lista de convites). */
export default function ConvitesLista({ convites }: { convites: ConviteResumo[] }) {
    return (
        <ul className="flex flex-col gap-3">
            {convites.map((convite) => (
                <li
                    key={convite.id}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-white px-4 py-3 dark:border-warning-500/20 dark:bg-white/[0.03]"
                >
                    <div className="min-w-0">
                        <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                            Processo {convite.protocol_number ?? '—'}
                            {convite.empresa ? ` · ${convite.empresa}` : ''}
                        </p>
                        <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">{convite.descricao}</p>
                        {convite.due_at && (
                            <p className="text-theme-xs font-medium text-warning-600 dark:text-warning-500">
                                Responda até {dataCurta(convite.due_at)}
                            </p>
                        )}
                    </div>
                    <Link
                        href={`/portal/solicitacoes/${convite.solicitacao_id}/pendencias`}
                        className="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-warning-500 px-4 text-theme-sm font-medium text-white transition hover:bg-warning-600"
                    >
                        Responder
                        <span className="sr-only"> o convite do processo {convite.protocol_number}</span>
                        <ArrowRightIcon className="size-4" aria-hidden="true" />
                    </Link>
                </li>
            ))}
        </ul>
    );
}
