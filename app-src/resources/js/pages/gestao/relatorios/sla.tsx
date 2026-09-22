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
import Chart from '@/components/ui/chart/chart';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
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
 * SLA e vencimentos da análise (relatório operacional): processos EM ANDAMENTO
 * com prazo materializado, o mais urgente primeiro. O semáforo é calculado no
 * backend (AnalysisSlaService — on-the-fly, nunca persistido); a janela de
 * "vencendo" é parametrizável e vem ecoada no resumo. Campos anuláveis
 * degradam para travessão — nunca um dado inventado.
 */
interface SlaRow {
    id: number;
    processo: string | null;
    etapa: string;
    setor: string | null;
    analista: string | null;
    iniciado_em: string | null;
    limite_em: string | null;
    situacao: 'verde' | 'amarelo' | 'vermelho' | null;
    situacao_label: string | null;
    restante: string | null;
}

interface RelatorioPaginator {
    data: SlaRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface AgingFaixa {
    faixa: '0_50' | '50_80' | '80_100' | 'acima_100' | 'indeterminada';
    label: string;
    total: number;
}

interface AtrasadoEtapa {
    etapa: 'distribuicao' | 'analise';
    label: string;
    total: number;
}

interface CumprimentoSla {
    dentro_sla: number;
    com_prazo: number;
    taxa: number | null;
    data_de: string | null;
    data_ate: string | null;
}

interface ResumoSla {
    em_andamento: number;
    vencidos: number;
    vencendo: number;
    janela_vencimento_dias: number;
    aging: AgingFaixa[];
    atrasados_por_etapa: AtrasadoEtapa[];
    cumprimento: CumprimentoSla;
}

interface Opcao {
    value: number;
    label: string;
}

interface FiltrosAplicados {
    setor?: number | string;
    analista?: number | string;
    data_de?: string;
    data_ate?: string;
}

interface SlaProps {
    resumo: ResumoSla;
    relatorio: RelatorioPaginator;
    setores: Opcao[];
    analistas: Opcao[];
    filtros: FiltrosAplicados;
    perPageOptions: number[];
}

interface FiltrosForm {
    setor: string;
    analista: string;
    data_de: string;
    data_ate: string;
}

const URL_SLA = '/gestao/relatorios/sla';

const dateTimeFormat = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Percentual honesto: null (sem decisões humanas com prazo) vira travessão. */
function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Aging empilhado: uma categoria Estoque, uma barra por faixa. */
function agingChartOption(aging: AgingFaixa[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: { bottom: 0, type: 'scroll' },
        grid: { left: 16, right: 16, top: 16, bottom: 32, containLabel: true },
        xAxis: { type: 'category', data: ['Estoque'] },
        yAxis: { type: 'value', minInterval: 1 },
        series: aging.map((faixa) => ({
            type: 'bar',
            name: faixa.label,
            stack: 'aging',
            data: [faixa.total],
        })),
    };
}

/** Atrasados vencidos agrupados por etapa do SLA. */
function atrasadosChartOption(itens: AtrasadoEtapa[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 16, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'category', data: itens.map((item) => item.label) },
        yAxis: { type: 'value', minInterval: 1 },
        series: [{ type: 'bar', name: 'Atrasados', data: itens.map((item) => item.total) }],
    };
}

/** Estado vazio honesto de um gráfico. */
function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem dados no período.
        </div>
    );
}

/** Formata a data/hora ISO8601 no padrão brasileiro; null/inválida → travessão. */
function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    return Number.isNaN(data.getTime()) ? '—' : dateTimeFormat.format(data);
}

/** Badge do semáforo do SLA (cores reais do backend — nunca presumidas). */
function SituacaoBadge({ situacao, label }: { situacao: SlaRow['situacao']; label: string | null }) {
    if (situacao === null || label === null) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    const cor = situacao === 'vermelho' ? 'error' : situacao === 'amarelo' ? 'warning' : 'success';

    return (
        <Badge variant="light" color={cor} size="sm">
            {label}
        </Badge>
    );
}

const colunas: ColumnDef<SlaRow>[] = [
    {
        id: 'processo',
        header: 'Processo',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.processo ?? '—',
    },
    {
        id: 'etapa',
        header: 'Etapa',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => linha.etapa,
    },
    {
        id: 'setor',
        header: 'Setor',
        cell: (linha) => linha.setor ?? '—',
    },
    {
        id: 'analista',
        header: 'Analista',
        cell: (linha) => linha.analista ?? '—',
    },
    {
        id: 'limite_em',
        header: 'Prazo-limite',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDataHora(linha.limite_em),
    },
    {
        id: 'situacao',
        header: 'Situação',
        cell: (linha) => <SituacaoBadge situacao={linha.situacao} label={linha.situacao_label} />,
    },
    {
        id: 'restante',
        header: 'Tempo restante',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => linha.restante ?? '—',
    },
];

export default function SlaVencimentos({ resumo, relatorio, setores, analistas, filtros, perPageOptions }: SlaProps) {
    const [form, setForm] = useState<FiltrosForm>({
        setor: filtros.setor != null ? String(filtros.setor) : '',
        analista: filtros.analista != null ? String(filtros.analista) : '',
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
    });
    const [perPage, setPerPage] = useState<number>(relatorio.per_page);

    // Navegação server-side: filtros vazios são omitidos da URL; qualquer
    // mudança volta à página 1 (comportamento do paginator com withQueryString).
    const visitar = useCallback((estado: FiltrosForm, itensPorPagina: number) => {
        const params: Record<string, string | number> = { per_page: itensPorPagina };

        if (estado.setor.trim() !== '') {
            params.setor = estado.setor;
        }

        if (estado.analista.trim() !== '') {
            params.analista = estado.analista;
        }

        if (estado.data_de.trim() !== '') {
            params.data_de = estado.data_de;
        }

        if (estado.data_ate.trim() !== '') {
            params.data_ate = estado.data_ate;
        }

        router.get(URL_SLA, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio: FiltrosForm = { setor: '', analista: '', data_de: '', data_ate: '' };
        setForm(vazio);
        visitar(vazio, perPage);
    }

    function alterarPerPage(valor: number) {
        setPerPage(valor);
        visitar(form, valor);
    }

    // Snapshot da exportação: o arquivo sai exatamente sobre o recorte APLICADO
    // (o que a tela mostra), não sobre o que está digitado mas não pesquisado.
    const exportParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        if (filtros.setor) {
            params.setor = filtros.setor;
        }

        if (filtros.analista) {
            params.analista = filtros.analista;
        }

        return params;
    }, [filtros]);

    const filtrando = Boolean(filtros.setor) || Boolean(filtros.analista);
    const agingVazio = resumo.aging.every((faixa) => faixa.total === 0);

    const setorOptions = useMemo(() => setores.map((s) => ({ value: String(s.value), label: s.label })), [setores]);
    const analistaOptions = useMemo(() => analistas.map((a) => ({ value: String(a.value), label: a.label })), [analistas]);

    return (
        <>
            <Head title="SLA e vencimentos" />
            <PageHeader
                title="SLA e vencimentos da análise"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'SLA e vencimentos' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
                    <KpiCard
                        label="Em andamento"
                        value={String(resumo.em_andamento)}
                        note="com prazo materializado"
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Vencidos"
                        value={String(resumo.vencidos)}
                        note="prazo-limite ultrapassado"
                        icon={<AlertIcon className="size-6" />}
                        tone="error"
                    />
                    <KpiCard
                        label="Vencendo"
                        value={String(resumo.vencendo)}
                        note={`vencem em até ${resumo.janela_vencimento_dias} ${resumo.janela_vencimento_dias === 1 ? 'dia' : 'dias'}`}
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="warning"
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3 md:gap-6">
                    <KpiCard
                        label="Decisões no prazo"
                        value={formatarPercentual(resumo.cumprimento.taxa)}
                        note={
                            resumo.cumprimento.com_prazo > 0
                                ? `de ${resumo.cumprimento.com_prazo} decisões humanas com prazo`
                                : 'sem decisões humanas com prazo'
                        }
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="success"
                    />
                    <Card>
                        <CardHeader title="Aging do estoque" description="Consumo do prazo já materializado, neste momento." />
                        <CardContent>
                            {agingVazio ? <GraficoSemDados /> : <Chart option={agingChartOption(resumo.aging)} className="h-72 w-full" />}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader title="Atrasados por etapa" description="Somente vencidos (prazo-limite ultrapassado)." />
                        <CardContent>
                            <Chart option={atrasadosChartOption(resumo.atrasados_por_etapa)} className="h-72 w-full" />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="O período recorta só o cumprimento. Estoque, aging e lista são o momento atual."
                        actions={<ExportMenu url={URL_SLA} params={exportParams} />}
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <Label htmlFor="filtro-data-de">Período de</Label>
                                    <Input
                                        id="filtro-data-de"
                                        type="date"
                                        value={form.data_de}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_de: e.target.value }))}
                                        aria-label="Período de"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-data-ate">Período até</Label>
                                    <Input
                                        id="filtro-data-ate"
                                        type="date"
                                        value={form.data_ate}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_ate: e.target.value }))}
                                        aria-label="Período até"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-setor">Setor</Label>
                                    <Select
                                        id="filtro-setor"
                                        options={setorOptions}
                                        placeholder="Todos os setores"
                                        value={form.setor}
                                        onChange={(valor) => setForm((atual) => ({ ...atual, setor: valor }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-analista">Analista</Label>
                                    <Select
                                        id="filtro-analista"
                                        options={analistaOptions}
                                        placeholder="Todos os analistas"
                                        value={form.analista}
                                        onChange={(valor) => setForm((atual) => ({ ...atual, analista: valor }))}
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
                        title="Processos em andamento"
                        description="O mais urgente primeiro. A situação é o semáforo do SLA calculado na hora — nunca um valor gravado."
                        actions={<PerPageSelect value={perPage} options={perPageOptions} onChange={alterarPerPage} />}
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable<SlaRow>
                                columns={colunas}
                                rows={relatorio.data}
                                rowKey={(linha) => String(linha.id)}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtrando ? 'Nenhum processo em andamento para os filtros' : 'Nenhum processo em andamento'}
                                        description={
                                            filtrando
                                                ? 'Ajuste o setor ou o analista e tente novamente.'
                                                : 'Quando houver processos em análise com prazo, eles aparecem aqui.'
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

SlaVencimentos.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
