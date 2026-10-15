import FieldError from '@/components/form/field-error';
import { MailIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import EmptyState from '@/components/ui/empty-state';
import { formatDateTime } from '@/lib/datas';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

type ComunicacaoStatus = 'na_fila' | 'enviado' | 'falhou' | 'bloqueado' | 'desativado';

export interface Comunicacao {
    id: number;
    channel: 'email' | 'in_app' | 'whatsapp';
    channel_label: string;
    type: string;
    type_label: string;
    status: ComunicacaoStatus;
    status_label: string;
    title: string;
    summary: string | null;
    recipient: { id: number; name: string } | null;
    queued_at: string | null;
    sent_at: string | null;
    failed_at: string | null;
    created_at: string | null;
    /** Diagnóstico interno do canal — presente só na gestão (LGPD). */
    error_message?: string | null;
}

interface HistoricoComunicacoesProps {
    comunicacoes: Comunicacao[];
}

/**
 * Cor do selo conforme o status HONESTO do envio (não inventa "enviado"): o
 * status vem do ledger communications — bloqueado/desativado (ex.: WhatsApp atrás
 * de toggle) jamais aparece como enviado.
 */
function statusColor(status: ComunicacaoStatus): BadgeColor {
    switch (status) {
        case 'enviado':
            return 'success';
        case 'falhou':
            return 'error';
        case 'bloqueado':
            return 'warning';
        case 'na_fila':
            return 'info';
        default:
            return 'light';
    }
}

/** Carimbo mais relevante para a linha do tempo, conforme o status. */
function dataRelevante(comunicacao: Comunicacao): string | null {
    return comunicacao.sent_at ?? comunicacao.failed_at ?? comunicacao.queued_at ?? comunicacao.created_at;
}

/**
 * Histórico unificado de comunicações de um processo (HU-096): linha do tempo
 * sobre o ledger `communications` (fonte ÚNICA, todos os canais e tipos) com
 * canal, tipo, status HONESTO, título/resumo, destinatário e data. Estado vazio
 * honesto; o error_message (diagnóstico interno) só chega no payload da gestão.
 */
export default function HistoricoComunicacoes({ comunicacoes }: HistoricoComunicacoesProps) {
    if (comunicacoes.length === 0) {
        return (
            <div id="historico-comunicacoes">
                <EmptyState
                    icon={<MailIcon className="size-6" />}
                    title="Sem comunicações registradas"
                    description="As notificações deste processo (e-mail, in-app e demais canais) aparecerão aqui conforme forem disparadas."
                />
            </div>
        );
    }

    return (
        <ol id="historico-comunicacoes" className="relative space-y-6 border-l border-gray-200 pl-6 dark:border-gray-800">
            {comunicacoes.map((comunicacao) => (
                <li key={comunicacao.id} className="relative">
                    <span className="absolute top-1 -left-[1.4rem] size-3 rounded-full border-2 border-white bg-brand-500 dark:border-gray-900" />
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                            {comunicacao.type_label}
                        </span>
                        <Badge size="sm" color="light">
                            {comunicacao.channel_label}
                        </Badge>
                        <Badge size="sm" color={statusColor(comunicacao.status)}>
                            {comunicacao.status_label}
                        </Badge>
                    </div>
                    <p className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{comunicacao.title}</p>
                    {comunicacao.summary && (
                        <p className="mt-0.5 text-theme-sm text-gray-600 dark:text-gray-300">{comunicacao.summary}</p>
                    )}
                    <p className="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">
                        {formatDateTime(dataRelevante(comunicacao))}
                        {comunicacao.recipient ? ` · para ${comunicacao.recipient.name}` : ''}
                    </p>
                    {comunicacao.error_message && (
                        <FieldError>
                            Diagnóstico: {comunicacao.error_message}
                        </FieldError>
                    )}
                </li>
            ))}
        </ol>
    );
}
