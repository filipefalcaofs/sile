import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { AlertIcon, ArrowRightIcon, CheckCircleIcon, FileIcon, GroupIcon, InfoIcon, ListIcon, LockIcon, PlugInIcon, TableIcon } from '@/components/icons';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Chart from '@/components/ui/chart/chart';
import { echarts } from '@/components/ui/chart/echarts-core';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface DecisoesKpi {
    total: number;
    expresso: number;
    humano: number;
}

interface SerieFluxoPonto {
    dia: string;
    entrada: number;
    saida: number;
}

interface EstoqueStatusItem {
    status: string | null;
    label: string;
    grupo: string | null;
    total: number;
}

interface OperacaoKpis {
    janela_dias: number;
    protocolos: number;
    decisoes: DecisoesKpi;
    estoque_total: number;
    atrasados: number;
    taxa_expressa: number | null;
    meta_expressa: number | null;
    serie_fluxo: SerieFluxoPonto[];
    estoque_por_status: EstoqueStatusItem[];
}

interface DashboardProps {
    kpis: { operacao: OperacaoKpis | null };
}

interface ModuleCard {
    name: string;
    label: string;
    href: string;
    icon: ReactNode;
    visible: boolean;
}

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Percentual honesto: null (sem base no período) vira travessão, nunca 0% fabricado. */
function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Data local YYYY-MM-DD — mesma regra de Carbon::format('Y-m-d') no fuso do cliente. */
function formatarIsoLocal(data: Date): string {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');

    return `${ano}-${mes}-${dia}`;
}

/** Recorte da janela da home: hoje − N dias / hoje, alinhado a subDays no servidor. */
function janelaProtocolos(janelaDias: number): { de: string; ate: string } {
    const ate = new Date();
    const de = new Date();
    de.setDate(de.getDate() - janelaDias);

    return { de: formatarIsoLocal(de), ate: formatarIsoLocal(ate) };
}

function hrefProtocolos(janelaDias: number): string {
    const { de, ate } = janelaProtocolos(janelaDias);

    return `/gestao/processos?data_de=${de}&data_ate=${ate}`;
}

function hrefEstoqueStatus(status: string | null): string {
    return status === null ? '/gestao/processos' : `/gestao/processos?analysis_status=${encodeURIComponent(status)}`;
}

/** Entrada (protocolos) × saída (decisões) por dia — eixo Y inteiro. */
function fluxoChartOption(serie: SerieFluxoPonto[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        legend: { bottom: 0 },
        grid: { left: 44, right: 16, top: 24, bottom: 32, containLabel: true },
        xAxis: { type: 'category', data: serie.map((ponto) => ponto.dia) },
        yAxis: { type: 'value', minInterval: 1 },
        series: [
            {
                type: 'line',
                name: 'Entrada',
                smooth: true,
                showSymbol: serie.length === 1,
                data: serie.map((ponto) => ponto.entrada),
            },
            {
                type: 'line',
                name: 'Saída',
                smooth: true,
                showSymbol: serie.length === 1,
                data: serie.map((ponto) => ponto.saida),
            },
        ],
    };
}

/** Estoque operacional em barras horizontais (API já omite total = 0). */
function estoqueChartOption(itens: EstoqueStatusItem[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 24, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', data: itens.map((item) => item.label) },
        series: [
            {
                type: 'bar',
                name: 'Estoque',
                cursor: 'pointer',
                data: itens.map((item) => item.total),
            },
        ],
    };
}

/** Estado vazio honesto de um gráfico (diferencia "sem dados" de erro). */
function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem dados no período.
        </div>
    );
}

/**
 * O wrapper Chart não expõe onEvents. Amarra o clique na instância ECharts
 * já montada (mesmo core registrado) para o drill-down por analysis_status.
 */
function EstoquePorStatusChart({ itens }: { itens: EstoqueStatusItem[] }) {
    const wrapperRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const wrapper = wrapperRef.current;

        if (!wrapper) {
            return;
        }

        let disposed = false;
        let attached: ReturnType<typeof echarts.getInstanceByDom> = undefined;

        const onClick = (params: { dataIndex?: number }) => {
            const item = typeof params.dataIndex === 'number' ? itens[params.dataIndex] : undefined;

            if (!item) {
                return;
            }

            router.visit(hrefEstoqueStatus(item.status));
        };

        const tryAttach = (): boolean => {
            const nos = wrapper.querySelectorAll('div');

            for (const no of nos) {
                const instancia = echarts.getInstanceByDom(no);

                if (instancia) {
                    instancia.off('click');
                    instancia.on('click', onClick);
                    attached = instancia;

                    return true;
                }
            }

            return false;
        };

        const id = window.setInterval(() => {
            if (!disposed && tryAttach()) {
                window.clearInterval(id);
            }
        }, 50);

        return () => {
            disposed = true;
            window.clearInterval(id);
            attached?.off('click');
        };
    }, [itens]);

    return (
        <div ref={wrapperRef}>
            <Chart option={estoqueChartOption(itens)} className="h-72 w-full" />
        </div>
    );
}

export default function Dashboard({ kpis }: DashboardProps) {
    const { auth } = usePage<SharedProps>().props;
    const operacao = kpis.operacao;

    const modules: ModuleCard[] = [
        {
            name: 'CNAEs',
            label: 'Cadastro de atividades',
            href: '/gestao/cnaes',
            icon: <TableIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('consultar-cnaes'),
        },
        {
            name: 'Usuários',
            label: 'Contas do sistema',
            href: '/gestao/usuarios',
            icon: <GroupIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('manter-usuarios'),
        },
        {
            name: 'Perfis',
            label: 'Perfis e permissões',
            href: '/gestao/perfis',
            icon: <LockIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('manter-perfis'),
        },
        {
            name: 'Parâmetros',
            label: 'Configurações do sistema',
            href: '/gestao/parametros',
            icon: <PlugInIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('manter-parametros'),
        },
    ].filter((module) => module.visible);

    const operacaoIndicators = operacao
        ? [
              {
                  key: 'protocolos',
                  href: hrefProtocolos(operacao.janela_dias),
                  label: 'Protocolos',
                  value: numberFormat.format(operacao.protocolos),
                  note: `nos últimos ${operacao.janela_dias} dias`,
                  icon: <FileIcon className="size-6" />,
                  tone: 'brand' as const,
              },
              {
                  key: 'decisoes',
                  href: '/gestao/relatorios/indicadores',
                  label: 'Decisões',
                  value: numberFormat.format(operacao.decisoes.total),
                  note: `${numberFormat.format(operacao.decisoes.expresso)} expresso · ${numberFormat.format(operacao.decisoes.humano)} análise`,
                  icon: <CheckCircleIcon className="size-6" />,
                  tone: 'success' as const,
              },
              {
                  key: 'estoque',
                  href: '/gestao/processos',
                  label: 'Estoque',
                  value: numberFormat.format(operacao.estoque_total),
                  note: 'em aberto neste momento',
                  icon: <InfoIcon className="size-6" />,
                  tone: 'info' as const,
              },
              {
                  key: 'atrasados',
                  href: '/gestao/relatorios/sla',
                  label: 'Atrasados',
                  value: numberFormat.format(operacao.atrasados),
                  note: 'prazo-limite ultrapassado',
                  icon: <AlertIcon className="size-6" />,
                  tone: 'error' as const,
              },
              {
                  key: 'expressa',
                  href: '/gestao/relatorios/quedas',
                  label: 'Resposta expressa',
                  value: formatarPercentual(operacao.taxa_expressa),
                  note: operacao.meta_expressa === null ? 'meta não definida' : `meta ${formatarPercentual(operacao.meta_expressa)}`,
                  icon: <ListIcon className="size-6" />,
                  tone: 'brand' as const,
              },
          ]
        : [];

    return (
        <>
            <Head title="Visão geral da operação" />
            <PageHeader title="Visão geral da operação" breadcrumbs={[{ label: 'Gestão' }]} />

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                {operacao && (
                    <div className="col-span-12 space-y-4 md:space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-5">
                            {operacaoIndicators.map((indicator) => (
                                <Link key={indicator.key} href={indicator.href} className="block rounded-2xl">
                                    <KpiCard
                                        label={indicator.label}
                                        value={indicator.value}
                                        note={indicator.note}
                                        icon={indicator.icon}
                                        tone={indicator.tone}
                                    />
                                </Link>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader
                                    title="Entrada e saída"
                                    description={`Protocolos e decisões por dia nos últimos ${operacao.janela_dias} dias.`}
                                />
                                <CardContent>
                                    {operacao.serie_fluxo.length > 0 ? (
                                        <Chart option={fluxoChartOption(operacao.serie_fluxo)} className="h-72 w-full" />
                                    ) : (
                                        <GraficoSemDados />
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader
                                    title="Estoque por status"
                                    description="Processos em aberto neste momento. Clique na barra para filtrar."
                                />
                                <CardContent>
                                    {operacao.estoque_por_status.length > 0 ? (
                                        <EstoquePorStatusChart itens={operacao.estoque_por_status} />
                                    ) : (
                                        <GraficoSemDados />
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}

                {modules.length > 0 && (
                    <div className="col-span-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            {modules.map((module) => (
                                <Link
                                    key={module.href}
                                    href={module.href}
                                    className="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                                >
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                                        {module.icon}
                                    </div>
                                    <div className="mt-5 flex items-end justify-between">
                                        <div>
                                            <span className="text-sm text-gray-500 dark:text-gray-400">{module.label}</span>
                                            <h4 className="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">{module.name}</h4>
                                        </div>
                                        <ArrowRightIcon className="mb-1.5 size-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
