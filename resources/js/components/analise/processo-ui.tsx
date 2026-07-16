import Badge from '@/components/ui/badge';

/** Semáforo do SLA da fila (HU-144) — espelha o enum SlaStatus do backend. */
export interface ProcessoSla {
    status: string;
    status_label: string;
    restante: string;
}

export interface ProcessoCategoria {
    value: string;
    label: string;
}

/**
 * Shape do ProcessoResource (10-14), consumido pela fila, consulta e detalhe.
 * Os três identificadores do processo (RN-007) são protocol_number, bap e
 * tvl_product_number.
 */
export interface ProcessoItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    tvl_product_number: string | null;
    status: string;
    status_label: string;
    empresa: string | null;
    cnpj: string | null;
    imovel: string;
    endereco_completo: string;
    inscricao: string | null;
    categorias: ProcessoCategoria[];
    categoria: string | null;
    analista: string | null;
    assigned_user_id: number | null;
    setor: string | null;
    sector_id: number | null;
    analysis_stage: string | null;
    analysis_stage_label: string | null;
    analysis_status: string | null;
    analysis_status_label: string | null;
    analysis_due_at: string | null;
    sla: ProcessoSla | null;
    protocoled_at: string | null;
}

/** Cor do badge por estado do semáforo: verde=no prazo, amarelo=alerta, vermelho=vencido. */
const SEMAFORO_COLOR: Record<string, 'success' | 'warning' | 'error'> = {
    verde: 'success',
    amarelo: 'warning',
    vermelho: 'error',
};

/**
 * Badge do semáforo de SLA (HU-144). Exibe o rótulo do estado e o tempo
 * restante calculado on-the-fly no backend. Sem prazo materializado, mostra um
 * traço (honesto — o processo ainda não entrou na fila com prazo).
 */
export function SemaforoBadge({ sla }: { sla: ProcessoSla | null }) {
    if (!sla) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    return (
        <Badge color={SEMAFORO_COLOR[sla.status] ?? 'light'} size="sm">
            {sla.status_label}
            {sla.restante ? ` · ${sla.restante}` : ''}
        </Badge>
    );
}

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
export function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** Lista de badges das categorias derivadas do processo (RN-005). */
export function CategoriaBadges({ categorias }: { categorias: ProcessoCategoria[] }) {
    if (categorias.length === 0) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {categorias.map((categoria) => (
                <Badge key={categoria.value} color="light" size="sm">
                    {categoria.label}
                </Badge>
            ))}
        </div>
    );
}
