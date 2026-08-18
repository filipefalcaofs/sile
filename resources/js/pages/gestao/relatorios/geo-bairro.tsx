import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import { GridIcon, ListIcon, MapPinIcon } from '@/components/icons';
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

/** Linha agregada por bairro (Geo BI interno — Módulo 1). */
interface BairroItem {
    bairro: string;
    total: number;
    deferidas: number;
    indeferidas: number;
    taxa_deferimento: number | null;
}

/** Recorte por bairro — a zona urbanística oficial (GIS) está pendente na SEDUR. */
interface PorBairro {
    degradacao: string;
    rotulo: string;
    itens: BairroItem[];
}

interface Resumo {
    total: number;
    bairros_distintos: number;
    sem_bairro: number;
}

/** Filtros aplicados ecoados pelo backend (bag normalizado — só os preenchidos). */
interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
    bairro?: string;
    cnae?: string;
    categoria?: string;
}

interface GeoBairroProps {
    resumo: Resumo;
    porBairro: PorBairro;
    filtros: FiltrosAplicados;
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
    bairro: string;
    cnae: string;
    categoria: string;
}

const URL_GEO_BAIRRO = '/gestao/relatorios/geo-bairro';

const FILTER_KEYS: (keyof FiltrosForm)[] = ['data_de', 'data_ate', 'bairro', 'cnae', 'categoria'];

/** Categorias de consulta (espelham ProcessoQueryService::CATEGORIAS). */
const CATEGORIA_OPTIONS = [
    { value: 'expresso', label: 'Expresso' },
    { value: 'semi_expresso', label: 'Semi-expresso' },
    { value: 'malha_fina', label: 'Malha Fina' },
    { value: 'sede_escritorio', label: 'Sede de Escritório' },
];

const TOP_BAIRROS = 15;

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Percentual honesto: null (sem decisões no bairro) vira travessão, jamais 0% fabricado. */
function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Top bairros por volume (barras horizontais) — maior no topo. */
function bairroChartOption(itens: BairroItem[]): EChartsOption {
    const top = itens.slice(0, TOP_BAIRROS);
    const ordenado = [...top].reverse();

    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 24, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', data: ordenado.map((item) => item.bairro) },
        series: [{ type: 'bar', name: 'Solicitações', data: ordenado.map((item) => item.total) }],
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

const colunasBairro: ColumnDef<BairroItem>[] = [
    {
        id: 'bairro',
        header: 'Bairro',
        cellClassName: 'text-gray-800 dark:text-white/90',
        cell: (linha) => linha.bairro,
    },
    {
        id: 'total',
        header: 'Solicitações',
        align: 'end',
        cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
        cell: (linha) => numberFormat.format(linha.total),
    },
    {
        id: 'deferidas',
        header: 'Deferidas',
        align: 'end',
        cellClassName: 'whitespace-nowrap text-success-600 dark:text-success-400',
        cell: (linha) => numberFormat.format(linha.deferidas),
    },
    {
        id: 'indeferidas',
        header: 'Indeferidas',
        align: 'end',
        cellClassName: 'whitespace-nowrap text-error-600 dark:text-error-400',
        cell: (linha) => numberFormat.format(linha.indeferidas),
    },
    {
        id: 'taxa',
        header: 'Taxa de deferimento',
        align: 'end',
        cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
        cell: (linha) => formatarPercentual(linha.taxa_deferimento),
    },
];

export default function GeoBairroPainel({ resumo, porBairro, filtros }: GeoBairroProps) {
    const [form, setForm] = useState<FiltrosForm>({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
        bairro: filtros.bairro ?? '',
        cnae: filtros.cnae ?? '',
        categoria: filtros.categoria ?? '',
    });
    const [processing, setProcessing] = useState(false);

    const stateRef = useRef(form);
    stateRef.current = form;
    const hasMounted = useRef(false);

    const visitar = useCallback((estado: FiltrosForm) => {
        const params: Record<string, string> = {};

        for (const key of FILTER_KEYS) {
            const valor = estado[key];

            if (valor && valor.trim() !== '') {
                params[key] = valor;
            }
        }

        router.get(URL_GEO_BAIRRO, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    }, []);

    // Campos textuais: debounce para não disparar uma visita por tecla.
    useEffect(() => {
        if (!hasMounted.current) {
            return;
        }

        const timeout = setTimeout(() => visitar(stateRef.current), 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.bairro, form.cnae, visitar]);

    useEffect(() => {
        hasMounted.current = true;

        return () => {
            hasMounted.current = false;
        };
    }, []);

    function setImediato(key: keyof FiltrosForm, valor: string) {
        const proximo = { ...stateRef.current, [key]: valor };
        setForm(proximo);
        visitar(proximo);
    }

    function setTexto(key: keyof FiltrosForm, valor: string) {
        setForm((anterior) => ({ ...anterior, [key]: valor }));
    }

    function limparFiltros() {
        const vazio: FiltrosForm = { data_de: '', data_ate: '', bairro: '', cnae: '', categoria: '' };
        setForm(vazio);
        visitar(vazio);
    }

    const filtrando = FILTER_KEYS.some((key) => (form[key] ?? '').trim() !== '');

    // Snapshot dos filtros atuais para a exportação (?formato=): o conjunto
    // exportado é exatamente o filtrado na tela (RN-005). O backend recorta as
    // colunas no SolicitacoesReportSource.
    const currentParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        for (const key of FILTER_KEYS) {
            const valor = form[key];

            if (valor && valor.trim() !== '') {
                params[key] = valor;
            }
        }

        return params;
    }, [form]);

    return (
        <>
            <Head title="Painel geoeconômico por bairro" />
            <PageHeader
                title="Painel geoeconômico por bairro"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Painel por bairro' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Distribuição das solicitações protocoladas por bairro, com decisões e taxa de deferimento. Números reais — sem dados no recorte, o painel fica vazio (nunca um número inventado)."
                        actions={<ExportMenu url={URL_GEO_BAIRRO} params={currentParams} label="Exportar solicitações" />}
                    />
                    <CardContent>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                            <FiltroCampo label="Período de">
                                <Input type="date" value={form.data_de} onChange={(e) => setImediato('data_de', e.target.value)} aria-label="Período de" />
                            </FiltroCampo>
                            <FiltroCampo label="Período até">
                                <Input type="date" value={form.data_ate} onChange={(e) => setImediato('data_ate', e.target.value)} aria-label="Período até" />
                            </FiltroCampo>
                            <FiltroCampo label="Bairro">
                                <Input type="text" value={form.bairro} onChange={(e) => setTexto('bairro', e.target.value)} placeholder="ex.: Pituba" aria-label="Bairro" />
                            </FiltroCampo>
                            <FiltroCampo label="CNAE">
                                <Input type="text" value={form.cnae} onChange={(e) => setTexto('cnae', e.target.value)} placeholder="ex.: 4712100" aria-label="CNAE" />
                            </FiltroCampo>
                            <FiltroCampo label="Categoria">
                                <Select options={CATEGORIA_OPTIONS} placeholder="Todas" value={form.categoria} onChange={(valor) => setImediato('categoria', valor)} />
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
                        label="Solicitações no período"
                        value={numberFormat.format(resumo.total)}
                        note="protocoladas no recorte"
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Bairros distintos"
                        value={numberFormat.format(resumo.bairros_distintos)}
                        note="com ao menos uma solicitação"
                        icon={<MapPinIcon className="size-6" />}
                        tone="success"
                    />
                    <KpiCard
                        label="Sem bairro informado"
                        value={numberFormat.format(resumo.sem_bairro)}
                        note="fora do recorte por bairro (degradação honesta)"
                        icon={<GridIcon className="size-6" />}
                        tone={resumo.sem_bairro > 0 ? 'warning' : 'brand'}
                    />
                </div>

                <Card>
                    <CardHeader
                        title="Concentração por bairro"
                        description={`Top ${TOP_BAIRROS} bairros por volume de solicitações no recorte.`}
                        actions={
                            <span className="inline-flex items-center gap-1.5">
                                <MapPinIcon className="size-4 text-warning-500" />
                                <Badge color="warning" size="sm">
                                    {porBairro.rotulo}
                                </Badge>
                            </span>
                        }
                    />
                    <CardContent>
                        {porBairro.itens.length > 0 ? <Chart option={bairroChartOption(porBairro.itens)} className="h-96 w-full" /> : <GraficoSemDados />}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Detalhamento por bairro" description="Solicitações, decisões e taxa de deferimento por bairro." />
                    <CardContent>
                        <DataTable<BairroItem>
                            columns={colunasBairro}
                            rows={porBairro.itens}
                            rowKey={(linha) => linha.bairro}
                            loading={processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtrando ? 'Nenhum bairro para os filtros' : 'Nenhuma solicitação no período'}
                                    description={
                                        filtrando
                                            ? 'Ajuste o período ou os filtros e tente novamente.'
                                            : 'A zona urbanística oficial (Quadro LOUOS) está pendente da SEDUR; até lá o recorte é por bairro.'
                                    }
                                />
                            }
                        />
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

GeoBairroPainel.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
