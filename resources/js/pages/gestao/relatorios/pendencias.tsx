import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, CheckCircleIcon, ListIcon, SearchIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import { ExportMenu } from '@/components/ui/data-table/export-menu';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ServerTableParams } from '@/components/ui/data-table/use-server-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

/**
 * Pendências/exigências (relatório operacional): abertas, vencidas, respondidas
 * e expiradas com o tempo médio de resposta do requerente (minutos corridos —
 * o prazo é do cidadão). Campos anuláveis degradam para travessão — nunca um
 * dado inventado.
 */
interface PendenciaRow {
    id: number;
    processo: string | null;
    descricao: string;
    status: 'aberta' | 'respondida' | 'expirada' | 'cancelada';
    status_label: string;
    analista: string | null;
    aberta_em: string | null;
    limite_em: string | null;
    respondida_em: string | null;
    tempo_resposta_minutos: number | null;
}

interface RelatorioPaginator {
    data: PendenciaRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface ResumoPendencias {
    abertas: number;
    vencidas: number;
    respondidas: number;
    expiradas: number;
    tempo_medio_resposta_minutos: number | null;
}

interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
    status_pendencia?: string;
}

interface PendenciasProps {
    resumo: ResumoPendencias;
    relatorio: RelatorioPaginator;
    filtros: FiltrosAplicados;
    perPageOptions: number[];
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
    status_pendencia: string;
}

const URL_PENDENCIAS = '/gestao/relatorios/pendencias';

const STATUS_OPTIONS = [
    { value: 'aberta', label: 'Aberta' },
    { value: 'respondida', label: 'Respondida' },
    { value: 'expirada', label: 'Expirada' },
    { value: 'cancelada', label: 'Cancelada' },
];

const dateTimeFormat = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/** Formata a data/hora ISO8601 no padrão brasileiro; null/inválida → travessão. */
function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    return Number.isNaN(data.getTime()) ? '—' : dateTimeFormat.format(data);
}

/** Minutos corridos → "Xd Xh" legível; null → travessão (sem tempo inventado). */
function formatarDuracao(minutos: number | null): string {
    if (minutos === null || Number.isNaN(minutos)) {
        return '—';
    }

    const dias = Math.floor(minutos / 1440);
    const horas = Math.floor((minutos % 1440) / 60);

    if (dias > 0) {
        return `${dias} ${dias === 1 ? 'dia' : 'dias'}${horas > 0 ? ` ${horas}h` : ''}`;
    }

    return horas > 0 ? `${horas}h ${minutos % 60}m` : `${minutos} min`;
}

/** Badge da situação da pendência. */
function StatusBadge({ status, label }: { status: PendenciaRow['status']; label: string }) {
    const cor = status === 'aberta' ? 'warning' : status === 'respondida' ? 'success' : status === 'expirada' ? 'error' : 'light';

    return (
        <Badge variant="light" color={cor} size="sm">
            {label}
        </Badge>
    );
}

const colunas: ColumnDef<PendenciaRow>[] = [
    {
        id: 'processo',
        header: 'Processo',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.processo ?? '—',
    },
    {
        id: 'descricao',
        header: 'Exigência',
        cellClassName: 'max-w-xs truncate',
        cell: (linha) => <span title={linha.descricao}>{linha.descricao}</span>,
    },
    {
        id: 'status',
        header: 'Situação',
        cell: (linha) => <StatusBadge status={linha.status} label={linha.status_label} />,
    },
    {
        id: 'analista',
        header: 'Analista',
        cell: (linha) => linha.analista ?? '—',
    },
    {
        id: 'aberta_em',
        header: 'Aberta em',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDataHora(linha.aberta_em),
    },
    {
        id: 'limite_em',
        header: 'Prazo-limite',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDataHora(linha.limite_em),
    },
    {
        id: 'tempo_resposta',
        header: 'Tempo de resposta',
        align: 'end',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDuracao(linha.tempo_resposta_minutos),
    },
];

export default function Pendencias({ resumo, relatorio, filtros, perPageOptions }: PendenciasProps) {
    const [form, setForm] = useState<FiltrosForm>({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
        status_pendencia: filtros.status_pendencia ?? '',
    });
    const [perPage, setPerPage] = useState<number>(relatorio.per_page);

    // Navegação server-side: filtros vazios são omitidos da URL; qualquer
    // mudança volta à página 1 (comportamento do paginator com withQueryString).
    const visitar = useCallback((estado: FiltrosForm, itensPorPagina: number) => {
        const params: Record<string, string | number> = { per_page: itensPorPagina };

        if (estado.data_de.trim() !== '') {
            params.data_de = estado.data_de;
        }

        if (estado.data_ate.trim() !== '') {
            params.data_ate = estado.data_ate;
        }

        if (estado.status_pendencia.trim() !== '') {
            params.status_pendencia = estado.status_pendencia;
        }

        router.get(URL_PENDENCIAS, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio: FiltrosForm = { data_de: '', data_ate: '', status_pendencia: '' };
        setForm(vazio);
        visitar(vazio, perPage);
    }

    function alterarPerPage(valor: number) {
        setPerPage(valor);
        visitar(form, valor);
    }

    // Snapshot da exportação: o arquivo sai exatamente sobre o recorte APLICADO.
    const exportParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        if (filtros.data_de) {
            params.data_de = filtros.data_de;
        }

        if (filtros.data_ate) {
            params.data_ate = filtros.data_ate;
        }

        if (filtros.status_pendencia) {
            params.status_pendencia = filtros.status_pendencia;
        }

        return params;
    }, [filtros]);

    const filtrando = Boolean(filtros.data_de) || Boolean(filtros.data_ate) || Boolean(filtros.status_pendencia);

    return (
        <>
            <Head title="Pendências e exigências" />
            <PageHeader
                title="Pendências e exigências"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Pendências e exigências' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6">
                    <KpiCard
                        label="Abertas"
                        value={String(resumo.abertas)}
                        note="aguardando o requerente"
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Vencidas"
                        value={String(resumo.vencidas)}
                        note="abertas com prazo ultrapassado"
                        icon={<AlertIcon className="size-6" />}
                        tone="error"
                    />
                    <KpiCard
                        label="Respondidas no recorte"
                        value={String(resumo.respondidas)}
                        note={`${resumo.expiradas} ${resumo.expiradas === 1 ? 'expirada' : 'expiradas'} no recorte`}
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="success"
                    />
                    <KpiCard
                        label="Tempo médio de resposta"
                        value={formatarDuracao(resumo.tempo_medio_resposta_minutos)}
                        note={
                            resumo.tempo_medio_resposta_minutos === null
                                ? 'sem respostas no recorte'
                                : 'do requerente, em tempo corrido'
                        }
                        icon={<ListIcon className="size-6" />}
                        tone="warning"
                    />
                </div>

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período de abertura e situação. A exportação (CSV/Excel/PDF) entrega exatamente o recorte aplicado."
                        actions={<ExportMenu url={URL_PENDENCIAS} params={exportParams} />}
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <Label htmlFor="filtro-data-de">Abertas de</Label>
                                    <Input
                                        id="filtro-data-de"
                                        type="date"
                                        value={form.data_de}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_de: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-data-ate">Abertas até</Label>
                                    <Input
                                        id="filtro-data-ate"
                                        type="date"
                                        value={form.data_ate}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_ate: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-status">Situação</Label>
                                    <Select
                                        id="filtro-status"
                                        options={STATUS_OPTIONS}
                                        placeholder="Todas as situações"
                                        value={form.status_pendencia}
                                        onChange={(valor) => setForm((atual) => ({ ...atual, status_pendencia: valor }))}
                                    />
                                </div>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <Button type="submit" size="sm" startIcon={<SearchIcon className="size-5" />}>
                                    Pesquisar
                                </Button>
                                <Button type="button" size="sm" variant="outline" onClick={limpar}>
                                    Limpar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Exigências do recorte"
                        description="Abertas pelo prazo primeiro, depois as demais pela abertura mais recente."
                        actions={<PerPageSelect value={perPage} options={perPageOptions} onChange={alterarPerPage} />}
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable<PendenciaRow>
                                columns={colunas}
                                rows={relatorio.data}
                                rowKey={(linha) => String(linha.id)}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtrando ? 'Nenhuma exigência para os filtros' : 'Nenhuma exigência no recorte'}
                                        description={
                                            filtrando
                                                ? 'Ajuste o período ou a situação e tente novamente.'
                                                : 'Quando o analista abrir convites ao requerente, eles aparecem aqui.'
                                        }
                                    />
                                }
                            />

                            <Pagination
                                links={relatorio.links}
                                meta={{ from: relatorio.from, to: relatorio.to, total: relatorio.total }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Pendencias.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
