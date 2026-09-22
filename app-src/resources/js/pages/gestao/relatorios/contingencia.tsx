import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { ListIcon, SearchIcon } from '@/components/icons';
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
 * Atendimento em contingência (relatório gerencial — HU-148): quanto do volume
 * entra pelo canal de operador e por quê. A participação é null sem base no
 * período (a tela mostra travessão — nunca 0% fabricado). Campos anuláveis
 * degradam para travessão.
 */
interface ContingenciaRow {
    id: number;
    processo: string | null;
    motivo: string | null;
    operador: string | null;
    protocolado_em: string | null;
    status: string;
    status_label: string;
}

interface RelatorioPaginator {
    data: ContingenciaRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface ResumoContingencia {
    contingencia: number;
    protocoladas: number;
    participacao: number | null;
    por_motivo: { motivo: string; total: number }[];
}

interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
}

interface ContingenciaProps {
    resumo: ResumoContingencia;
    relatorio: RelatorioPaginator;
    filtros: FiltrosAplicados;
    perPageOptions: number[];
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
}

const URL_CONTINGENCIA = '/gestao/relatorios/contingencia';

const numberFormat = new Intl.NumberFormat('pt-BR');
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

const colunas: ColumnDef<ContingenciaRow>[] = [
    {
        id: 'processo',
        header: 'Processo',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.processo ?? '—',
    },
    {
        id: 'motivo',
        header: 'Motivo',
        cell: (linha) => linha.motivo ?? '—',
    },
    {
        id: 'operador',
        header: 'Operador',
        cell: (linha) => linha.operador ?? '—',
    },
    {
        id: 'protocolado_em',
        header: 'Protocolado em',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDataHora(linha.protocolado_em),
    },
    {
        id: 'status',
        header: 'Situação',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => linha.status_label,
    },
];

export default function ContingenciaRelatorio({ resumo, relatorio, filtros, perPageOptions }: ContingenciaProps) {
    const [form, setForm] = useState<FiltrosForm>({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
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

        router.get(URL_CONTINGENCIA, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio: FiltrosForm = { data_de: '', data_ate: '' };
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

        return params;
    }, [filtros]);

    const filtrando = Boolean(filtros.data_de) || Boolean(filtros.data_ate);

    return (
        <>
            <Head title="Atendimento em contingência" />
            <PageHeader
                title="Atendimento em contingência"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Contingência' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
                    <KpiCard
                        label="Pela contingência"
                        value={numberFormat.format(resumo.contingencia)}
                        note="protocoladas pelo canal de operador"
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Total protocoladas"
                        value={numberFormat.format(resumo.protocoladas)}
                        note="todas as origens, no recorte"
                        icon={<ListIcon className="size-6" />}
                        tone="success"
                    />
                    <KpiCard
                        label="Participação da contingência"
                        value={resumo.participacao === null ? '—' : `${numberFormat.format(resumo.participacao)}%`}
                        note={resumo.participacao === null ? 'sem protocolos no recorte' : 'do volume protocolado no recorte'}
                        icon={<ListIcon className="size-6" />}
                        tone="warning"
                    />
                </div>

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período de protocolo. A exportação (CSV/Excel/PDF) entrega exatamente o recorte aplicado."
                        actions={<ExportMenu url={URL_CONTINGENCIA} params={exportParams} />}
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="filtro-data-de">Protocoladas de</Label>
                                    <Input
                                        id="filtro-data-de"
                                        type="date"
                                        value={form.data_de}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_de: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-data-ate">Protocoladas até</Label>
                                    <Input
                                        id="filtro-data-ate"
                                        type="date"
                                        value={form.data_ate}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_ate: e.target.value }))}
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

                {resumo.por_motivo.length > 0 && (
                    <Card>
                        <CardHeader title="Motivos da contingência" description="Ranking do recorte — o motivo é texto livre registrado pelo operador." />
                        <CardContent>
                            <ul className="flex flex-col divide-y divide-gray-100 dark:divide-white/[0.05]">
                                {resumo.por_motivo.map((item) => (
                                    <li key={item.motivo} className="flex items-center justify-between gap-4 py-2.5">
                                        <span className="text-theme-sm text-gray-800 dark:text-white/90">{item.motivo}</span>
                                        <span className="text-theme-sm font-medium text-gray-500 dark:text-gray-400">
                                            {numberFormat.format(item.total)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader
                        title="Solicitações pela contingência"
                        description="As mais recentes primeiro."
                        actions={<PerPageSelect value={perPage} options={perPageOptions} onChange={alterarPerPage} />}
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable<ContingenciaRow>
                                columns={colunas}
                                rows={relatorio.data}
                                rowKey={(linha) => String(linha.id)}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtrando ? 'Nenhuma solicitação de contingência para os filtros' : 'Nenhuma solicitação de contingência no recorte'}
                                        description={
                                            filtrando
                                                ? 'Ajuste o período e tente novamente.'
                                                : 'Quando o operador registrar solicitações pela contingência, elas aparecem aqui.'
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

ContingenciaRelatorio.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
