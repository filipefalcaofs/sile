import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import { ArrowRightIcon, ListIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Chart from '@/components/ui/chart/chart';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import DataTable from '@/components/ui/data-table/data-table';
import { ExportMenu } from '@/components/ui/data-table/export-menu';
import type { ServerTableParams } from '@/components/ui/data-table/use-server-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';

/** Taxa de resposta expressa (HU-145): respondidas ÷ elegíveis + meta (RN-004). */
interface TaxaQuedas {
    respondidas: number;
    elegiveis: number;
    /** % ou null sem base no período (honesto, jamais 0% fabricado). */
    taxa: number | null;
    /** Meta parametrizável (relatorios.expresso.meta_taxa) ou null — nunca inventada. */
    meta: number | null;
}

/** Ponto da série temporal da taxa por dia. */
interface SeriePonto {
    dia: string;
    respondidas: number;
    elegiveis: number;
    taxa: number | null;
}

/** Linha do ranking de motivos da queda; o gatilho null já vem rotulado. */
interface RankingMotivo {
    tipo_gatilho: string | null;
    rotulo: string;
    total: number;
}

/** Filtros aplicados ecoados pelo backend (bag normalizado — só os preenchidos). */
interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
    bairro?: string;
    cnae?: string;
    categoria?: string;
    setor?: number;
    analista?: number;
}

interface QuedasProps {
    taxa: TaxaQuedas;
    serie: SeriePonto[];
    ranking: RankingMotivo[];
    filtros: FiltrosAplicados;
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
}

const URL_QUEDAS = '/gestao/relatorios/quedas';
const URL_PROCESSOS = '/gestao/processos';

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Percentual honesto: null (sem base no período) vira travessão, jamais 0%. */
function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Formata o dia ISO (YYYY-MM-DD) no padrão brasileiro dd/mm. */
function formatarDia(dia: string): string {
    const partes = dia.split('-');

    return partes.length === 3 ? `${partes[2]}/${partes[1]}` : dia;
}

/** Série temporal da taxa por dia (linha). Com meta, desenha a linha de meta. */
function serieChartOption(serie: SeriePonto[], meta: number | null): EChartsOption {
    const series: NonNullable<EChartsOption['series']> = [
        {
            type: 'line',
            name: 'Taxa de resposta expressa (%)',
            smooth: true,
            connectNulls: false,
            showSymbol: serie.length === 1,
            data: serie.map((ponto) => ponto.taxa),
            ...(meta !== null
                ? {
                      markLine: {
                          symbol: 'none',
                          data: [{ yAxis: meta, name: 'Meta' }],
                          label: { formatter: `Meta ${percentFormat.format(meta)}%` },
                      },
                  }
                : {}),
        },
    ];

    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 16, right: 24, top: 24, bottom: 24, containLabel: true },
        xAxis: { type: 'category', data: serie.map((ponto) => formatarDia(ponto.dia)) },
        yAxis: { type: 'value', name: '%', max: 100, minInterval: 10 },
        series,
    };
}

/** Estado vazio honesto de um gráfico. */
function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem base no período.
        </div>
    );
}

const colunasRanking: ColumnDef<RankingMotivo>[] = [
    {
        id: 'motivo',
        header: 'Motivo / gatilho',
        cellClassName: 'text-gray-800 dark:text-white/90',
        cell: (linha) =>
            linha.tipo_gatilho === null ? (
                <span className="inline-flex items-center gap-1.5">
                    <Badge color="light" size="sm">
                        {linha.rotulo}
                    </Badge>
                </span>
            ) : (
                linha.rotulo
            ),
    },
    {
        id: 'total',
        header: 'Quedas',
        align: 'end',
        cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
        cell: (linha) => numberFormat.format(linha.total),
    },
];

export default function QuedasExpresso({ taxa, serie, ranking, filtros }: QuedasProps) {
    const [form, setForm] = useState<FiltrosForm>({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
    });
    const stateRef = useRef(form);
    stateRef.current = form;

    const visitar = useCallback((estado: FiltrosForm) => {
        const params: Record<string, string> = {};

        if (estado.data_de.trim() !== '') {
            params.data_de = estado.data_de;
        }

        if (estado.data_ate.trim() !== '') {
            params.data_ate = estado.data_ate;
        }

        router.get(URL_QUEDAS, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function setData(key: keyof FiltrosForm, valor: string) {
        const proximo = { ...stateRef.current, [key]: valor };
        setForm(proximo);
        visitar(proximo);
    }

    function limparFiltros() {
        const vazio: FiltrosForm = { data_de: '', data_ate: '' };
        setForm(vazio);
        visitar(vazio);
    }

    const filtrando = form.data_de.trim() !== '' || form.data_ate.trim() !== '';

    const currentParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        if (form.data_de.trim() !== '') {
            params.data_de = form.data_de;
        }

        if (form.data_ate.trim() !== '') {
            params.data_ate = form.data_ate;
        }

        return params;
    }, [form]);

    // Drill-down: leva à consulta de processos do MESMO recorte. As divergências
    // analista×motor (HU-140) ficam na ficha de cada processo.
    const drillDownHref = useMemo(() => {
        const query = new URLSearchParams();

        if (form.data_de.trim() !== '') {
            query.set('data_de', form.data_de);
        }

        if (form.data_ate.trim() !== '') {
            query.set('data_ate', form.data_ate);
        }

        const qs = query.toString();

        return qs === '' ? URL_PROCESSOS : `${URL_PROCESSOS}?${qs}`;
    }, [form]);

    const metaDefinida = taxa.meta !== null;

    return (
        <>
            <Head title="Quedas do fluxo expresso" />
            <PageHeader
                title="Quedas do fluxo expresso"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Relatórios' },
                    { label: 'Quedas do fluxo expresso' },
                ]}
            />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período. Os números são reais sobre o fluxo expresso — sem base no período, a taxa fica vazia (nunca um percentual inventado)."
                        actions={<ExportMenu url={URL_QUEDAS} params={currentParams} label="Exportar quedas" />}
                    />
                    <CardContent>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <FiltroCampo label="Período de">
                                <Input
                                    type="date"
                                    value={form.data_de}
                                    onChange={(e) => setData('data_de', e.target.value)}
                                    aria-label="Período de"
                                />
                            </FiltroCampo>
                            <FiltroCampo label="Período até">
                                <Input
                                    type="date"
                                    value={form.data_ate}
                                    onChange={(e) => setData('data_ate', e.target.value)}
                                    aria-label="Período até"
                                />
                            </FiltroCampo>
                        </div>

                        {filtrando && (
                            <button
                                type="button"
                                onClick={limparFiltros}
                                className="mt-3 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                            >
                                Limpar filtros
                            </button>
                        )}
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
                    <KpiCard
                        label="Taxa de resposta expressa"
                        value={formatarPercentual(taxa.taxa)}
                        note={
                            taxa.taxa === null
                                ? 'sem elegíveis no período'
                                : `${numberFormat.format(taxa.respondidas)} de ${numberFormat.format(taxa.elegiveis)} elegíveis`
                        }
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Meta da taxa"
                        value={metaDefinida ? formatarPercentual(taxa.meta) : 'meta não definida'}
                        note={metaDefinida ? 'parâmetro relatorios.expresso.meta_taxa' : 'parametrize a meta para acompanhar'}
                        icon={<ListIcon className="size-6" />}
                        tone={metaDefinida ? 'success' : 'warning'}
                    />
                    <KpiCard
                        label="Elegíveis no período"
                        value={numberFormat.format(taxa.elegiveis)}
                        note={`${numberFormat.format(taxa.respondidas)} respondidas pelo expresso`}
                        icon={<ListIcon className="size-6" />}
                        tone="info"
                    />
                </div>

                <Card>
                    <CardHeader
                        title="Série temporal da taxa"
                        description="Taxa de resposta expressa por dia na janela. Quando a meta está parametrizada, a linha de meta aparece no gráfico."
                    />
                    <CardContent>
                        {serie.length > 0 ? (
                            <Chart option={serieChartOption(serie, taxa.meta)} className="h-72 w-full" />
                        ) : (
                            <GraficoSemDados />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Ranking de motivos da queda"
                        description="Gatilhos que tiraram o processo do expresso. Quedas sem gatilho de contexto aparecem como “não classificado” — nunca somadas a um motivo real."
                    />
                    <CardContent>
                        <DataTable<RankingMotivo>
                            columns={colunasRanking}
                            rows={ranking}
                            rowKey={(linha) => linha.tipo_gatilho ?? 'nao-classificado'}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title="Nenhuma queda no período"
                                    description="Quando houver quedas do fluxo expresso, os motivos aparecem aqui ranqueados."
                                />
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Drill-down dos processos" description="Aprofunde até os processos do período e suas divergências analista × motor (HU-140)." />
                    <CardContent>
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            As divergências entre a análise humana e a decisão do motor ficam na ficha de cada processo. Abra a consulta de
                            processos do período para investigar caso a caso.
                        </p>
                        <Link
                            href={drillDownHref}
                            className="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                        >
                            Abrir processos do período
                            <ArrowRightIcon className="size-4" />
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/** Campo de filtro com rótulo acessível acima do controle. */
function FiltroCampo({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            {children}
        </label>
    );
}

QuedasExpresso.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
