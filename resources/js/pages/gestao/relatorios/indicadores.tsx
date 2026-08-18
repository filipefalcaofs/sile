import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import { CheckCircleIcon, ListIcon, MapPinIcon } from '@/components/icons';
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

/** Solicitações protocoladas por dia (HU-123). */
interface SeriePonto {
    dia: string;
    total: number;
}

/** Recorte por bairro (HU-124): a zona urbanística oficial está bloqueada. */
interface PorZona {
    degradacao: string;
    rotulo: string;
    itens: { bairro: string; total: number }[];
}

interface PorCnaeItem {
    cnae: string;
    total: number;
}

/** Nível de risco com a `fonte` (real do Decreto, derivada ou indefinida) — HU-126. */
interface PorRiscoItem {
    nivel: string;
    fonte: string;
    total: number;
}

interface TaxaDeferimento {
    deferidas: number;
    total: number;
    taxa: number | null;
}

interface TaxaIndeferimento {
    indeferidas: number;
    total: number;
    taxa: number | null;
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

interface IndicadoresProps {
    porPeriodo: SeriePonto[];
    porZona: PorZona;
    porCnae: PorCnaeItem[];
    porRisco: PorRiscoItem[];
    taxaDeferimento: TaxaDeferimento;
    taxaIndeferimento: TaxaIndeferimento;
    filtros: FiltrosAplicados;
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
    bairro: string;
    cnae: string;
    categoria: string;
}

const URL_INDICADORES = '/gestao/relatorios/indicadores';

const FILTER_KEYS: (keyof FiltrosForm)[] = ['data_de', 'data_ate', 'bairro', 'cnae', 'categoria'];

/** Campos textuais com visita debounced (datas e categoria são imediatos). */
const TEXTO_KEYS: (keyof FiltrosForm)[] = ['bairro', 'cnae'];

/** Categorias de consulta (espelham ProcessoQueryService::CATEGORIAS). */
const CATEGORIA_OPTIONS = [
    { value: 'expresso', label: 'Expresso' },
    { value: 'semi_expresso', label: 'Semi-expresso' },
    { value: 'malha_fina', label: 'Malha Fina' },
    { value: 'sede_escritorio', label: 'Sede de Escritório' },
];

const RISCO_LABELS: Record<string, string> = {
    baixo_a: 'Baixo A',
    baixo_b: 'Baixo B',
    medio: 'Médio',
    alto: 'Alto',
    expresso: 'Expresso',
    semi_expresso: 'Semi-expresso',
    analise: 'Análise',
    nao_classificado: 'Não classificado',
};

const FONTE_LABELS: Record<string, string> = {
    real: 'Decreto 32.636/2020',
    derivada: 'categoria derivada',
    indefinida: 'sem classificação',
};

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Percentual honesto: null (sem decisões no período) vira travessão, jamais 0% fabricado. */
function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Formata o código CNAE de 7 dígitos no padrão 9999-9/99; mantém o bruto se não casar. */
function formatarCnae(code: string): string {
    return /^\d{7}$/.test(code) ? `${code.slice(0, 4)}-${code.slice(4, 5)}/${code.slice(5, 7)}` : code;
}

function rotuloRisco(nivel: string): string {
    return RISCO_LABELS[nivel] ?? nivel;
}

/** Série de volume protocolado por dia (linha). */
function periodoChartOption(serie: SeriePonto[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 16, right: 16, top: 24, bottom: 24, containLabel: true },
        xAxis: { type: 'category', data: serie.map((ponto) => ponto.dia) },
        yAxis: { type: 'value', minInterval: 1 },
        series: [
            {
                type: 'line',
                name: 'Solicitações',
                smooth: true,
                showSymbol: serie.length === 1,
                areaStyle: {},
                data: serie.map((ponto) => ponto.total),
            },
        ],
    };
}

/** Distribuição por nível de risco (rosca). */
function riscoChartOption(itens: PorRiscoItem[]): EChartsOption {
    return {
        tooltip: { trigger: 'item' },
        legend: { bottom: 0, type: 'scroll' },
        series: [
            {
                type: 'pie',
                name: 'Solicitações',
                radius: ['45%', '70%'],
                avoidLabelOverlap: true,
                itemStyle: { borderRadius: 6, borderWidth: 2 },
                label: { show: false },
                data: itens.map((item) => ({ name: rotuloRisco(item.nivel), value: item.total })),
            },
        ],
    };
}

/** Top CNAEs por volume (barras horizontais) — maior no topo. */
function cnaeChartOption(itens: PorCnaeItem[]): EChartsOption {
    const ordenado = [...itens].reverse();

    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 24, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', data: ordenado.map((item) => formatarCnae(item.cnae)) },
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

const colunasZona: ColumnDef<{ bairro: string; total: number }>[] = [
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
];

export default function IndicadoresViabilidade({
    porPeriodo,
    porZona,
    porCnae,
    porRisco,
    taxaDeferimento,
    taxaIndeferimento,
    filtros,
}: IndicadoresProps) {
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

        router.get(URL_INDICADORES, params, {
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

    // Datas e categoria disparam visita imediata (igual aos selects do projeto).
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
    // exportado é exatamente o filtrado na tela (RN-004). O backend recorta as
    // colunas/limites no SolicitacoesReportSource.
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
            <Head title="Indicadores de viabilidade" />
            <PageHeader
                title="Indicadores de viabilidade"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Indicadores de viabilidade' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período, bairro, CNAE e categoria. Os números são reais sobre as solicitações protocoladas — sem dados no recorte, os indicadores ficam vazios (nunca um número inventado)."
                        actions={
                            <ExportMenu url={URL_INDICADORES} params={currentParams} label="Exportar indicadores" />
                        }
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
                                <Select
                                    options={CATEGORIA_OPTIONS}
                                    placeholder="Todas"
                                    value={form.categoria}
                                    onChange={(valor) => setImediato('categoria', valor)}
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
                        label="Decisões no período"
                        value={numberFormat.format(taxaDeferimento.total)}
                        note="deferidas + indeferidas"
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Taxa de deferimento"
                        value={formatarPercentual(taxaDeferimento.taxa)}
                        note={taxaDeferimento.taxa === null ? 'sem decisões no período' : `${numberFormat.format(taxaDeferimento.deferidas)} de ${numberFormat.format(taxaDeferimento.total)} decisões`}
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="success"
                    />
                    <KpiCard
                        label="Taxa de indeferimento"
                        value={formatarPercentual(taxaIndeferimento.taxa)}
                        note={taxaIndeferimento.taxa === null ? 'sem decisões no período' : `${numberFormat.format(taxaIndeferimento.indeferidas)} de ${numberFormat.format(taxaIndeferimento.total)} decisões`}
                        icon={<ListIcon className="size-6" />}
                        tone="error"
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader title="Volume por período" description="Solicitações protocoladas por dia no recorte." />
                        <CardContent>
                            {porPeriodo.length > 0 ? (
                                <Chart option={periodoChartOption(porPeriodo)} className="h-72 w-full" />
                            ) : (
                                <GraficoSemDados />
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader title="Distribuição por risco" description="Por nível do Decreto nº 32.636/2020 (real) ou categoria derivada." />
                        <CardContent>
                            {porRisco.length > 0 ? (
                                <>
                                    <Chart option={riscoChartOption(porRisco)} className="h-72 w-full" />
                                    <FonteRiscoLegenda itens={porRisco} />
                                </>
                            ) : (
                                <GraficoSemDados />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader title="Top CNAEs" description="Atividades econômicas com maior volume de solicitações no recorte." />
                    <CardContent>
                        {porCnae.length > 0 ? (
                            <Chart option={cnaeChartOption(porCnae)} className="h-80 w-full" />
                        ) : (
                            <GraficoSemDados />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Por zona urbanística"
                        actions={
                            <span className="inline-flex items-center gap-1.5">
                                <MapPinIcon className="size-4 text-warning-500" />
                                <Badge color="warning" size="sm">
                                    {porZona.rotulo}
                                </Badge>
                            </span>
                        }
                    />
                    <CardContent>
                        <DataTable<{ bairro: string; total: number }>
                            columns={colunasZona}
                            rows={porZona.itens}
                            rowKey={(linha) => linha.bairro}
                            loading={processing}
                            skeletonRows={6}
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

/**
 * Legenda da fonte do nível de risco (HU-126): deixa explícito quando o nível é
 * o REAL do Decreto, derivado da categoria ou indefinido — transparência, sem
 * passar dado derivado por oficial.
 */
function FonteRiscoLegenda({ itens }: { itens: PorRiscoItem[] }) {
    const fontes = Array.from(new Set(itens.map((item) => item.fonte)));

    return (
        <p className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-theme-xs text-gray-400 dark:text-gray-500">
            <span>Fonte do nível:</span>
            {fontes.map((fonte) => (
                <Badge key={fonte} color="light" size="sm">
                    {FONTE_LABELS[fonte] ?? fonte}
                </Badge>
            ))}
        </p>
    );
}

IndicadoresViabilidade.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
