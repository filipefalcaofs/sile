import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { AlertIcon } from '@/components/icons';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Chart from '@/components/ui/chart/chart';
import { chartPalette } from '@/components/ui/chart/chart-palette';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';

interface Competencia {
    ano: number;
    mes: number;
}

interface Posicao {
    posicao: number | null;
    total: number;
    media: number | null;
    ufs_ausentes: string[];
    cobertura_completa: boolean;
}

interface LinhaCapital {
    uf: string;
    municipio: string;
    media: number | null;
    p50: number | null;
    p90: number | null;
    n: number;
    posicao: number | null;
    e_salvador: boolean;
}

interface PontoMensal {
    ano: number;
    mes: number;
    media: number | null;
    p50: number | null;
    p90: number | null;
    n: number;
}

interface Evolucao {
    anterior: PontoMensal;
    atual: PontoMensal;
    redesim_atual: PontoMensal;
    diferenca_horas: number | null;
    diferenca_percentual: number | null;
    diferenca_mesmo_mes_horas: number | null;
    diferenca_mesmo_mes_percentual: number | null;
    ressalva: string;
}

interface BenchmarkProps {
    competencia: Competencia | null;
    posicao: Posicao;
    capitais: LinhaCapital[];
    evolucao: Evolucao;
}

const numberFormat = new Intl.NumberFormat('pt-BR');
const horasFormat = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

function rotuloCompetencia(ano: number, mes: number): string {
    return `${String(mes).padStart(2, '0')}/${ano}`;
}

function formatarHoras(horas: number | null): string {
    return horas === null ? '—' : `${horasFormat.format(horas)} h`;
}

function evolucaoChartOption({ anterior, atual, redesim_atual }: Evolucao): EChartsOption {
    const barras = [
        {
            nome: `REDESIM\n${rotuloCompetencia(anterior.ano, anterior.mes)}`,
            valor: anterior.media,
            cor: chartPalette.gray400,
        },
        {
            nome: `Viabiliza\n${rotuloCompetencia(atual.ano, atual.mes)} (até hoje)`,
            valor: atual.media,
            cor: chartPalette.brand,
        },
        {
            nome: `REDESIM\n${rotuloCompetencia(redesim_atual.ano, redesim_atual.mes)}${redesim_atual.media === null ? '\n(aguarda importação)' : ''}`,
            valor: redesim_atual.media,
            cor: chartPalette.gray400,
        },
    ];

    return {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            valueFormatter: (valor) => (typeof valor === 'number' ? formatarHoras(valor) : 'sem dado'),
        },
        grid: { left: 16, right: 24, top: 32, bottom: 16, containLabel: true },
        xAxis: {
            type: 'category',
            data: barras.map((b) => b.nome),
            axisTick: { show: false },
        },
        yAxis: { type: 'value', name: 'horas úteis' },
        series: [
            {
                type: 'bar',
                name: 'Tempo médio de viabilidade',
                barWidth: '38%',
                data: barras.map((b) => ({
                    value: b.valor,
                    itemStyle: { color: b.cor, borderRadius: [8, 8, 0, 0] },
                })),
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => (typeof value === 'number' ? formatarHoras(value) : ''),
                },
            },
        ],
    };
}

function descreverDiferenca(horas: number | null, percentual: number | null, referencia: string): string | null {
    if (horas === null) {
        return null;
    }

    const sinal = horas > 0 ? '+' : '';
    const variacao = percentual === null ? '' : ` (${sinal}${horasFormat.format(percentual)}%)`;

    return `${sinal}${horasFormat.format(horas)} h${variacao} frente à ${referencia}`;
}

const colunasCapitais: ColumnDef<LinhaCapital>[] = [
    {
        id: 'posicao',
        header: 'Posição',
        align: 'end',
        cellClassName: 'whitespace-nowrap tabular-nums text-gray-500 dark:text-gray-400',
        cell: (linha) => (linha.posicao === null ? '—' : `${linha.posicao}º`),
    },
    {
        id: 'capital',
        header: 'Capital',
        cellClassName: 'font-medium text-gray-800 dark:text-white/90',
        cell: (linha) =>
            linha.e_salvador ? (
                <span className="font-semibold text-brand-600 dark:text-brand-400">
                    {linha.municipio}/{linha.uf}
                </span>
            ) : (
                `${linha.municipio}/${linha.uf}`
            ),
    },
    {
        id: 'media',
        header: 'Tempo médio',
        align: 'end',
        cellClassName: 'whitespace-nowrap tabular-nums text-gray-800 dark:text-white/90',
        cell: (linha) => formatarHoras(linha.media),
    },
    {
        id: 'p90',
        header: 'P90',
        align: 'end',
        cellClassName: 'whitespace-nowrap tabular-nums text-gray-600 dark:text-gray-300',
        cell: (linha) => formatarHoras(linha.p90),
    },
    {
        id: 'n',
        header: 'Solicitações',
        align: 'end',
        cellClassName: 'whitespace-nowrap tabular-nums text-gray-500 dark:text-gray-400',
        cell: (linha) => (linha.n === 0 ? 'sem dado' : numberFormat.format(linha.n)),
    },
];

export default function Benchmark({ competencia, posicao, capitais, evolucao }: BenchmarkProps) {
    const salvador = capitais.find((linha) => linha.e_salvador) ?? null;

    return (
        <>
            <Head title="Benchmark nacional" />
            <PageHeader
                title="Benchmark nacional"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Indicadores' }, { label: 'Benchmark nacional' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <RessalvaDeOrigem competencia={competencia} />

                {competencia === null ? (
                    <Card>
                        <CardContent>
                            <EmptyState
                                title="Nenhuma competência com cobertura nacional completa"
                                description={
                                    posicao.ufs_ausentes.length > 0
                                        ? `Faltam dados de: ${posicao.ufs_ausentes.join(', ')}. A posição no ranking só é publicada com as 27 unidades da federação importadas — um recorte parcial não representa o país.`
                                        : 'Nenhuma competência foi importada ainda. Execute a importação do benchmark para que a comparação apareça.'
                                }
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
                            <KpiCard
                                label="Posição nacional de Salvador"
                                value={posicao.posicao === null ? '—' : `${posicao.posicao}º`}
                                note={
                                    posicao.posicao === null
                                        ? 'cobertura incompleta'
                                        : `entre ${numberFormat.format(posicao.total)} municípios com dado`
                                }
                                tone="brand"
                            />
                            <KpiCard
                                label="Posição entre as capitais"
                                value={salvador?.posicao === null || salvador === null ? '—' : `${salvador.posicao}º`}
                                note="das 27 capitais"
                                tone="brand"
                            />
                            <KpiCard
                                label="Tempo de viabilidade de Salvador"
                                value={formatarHoras(posicao.media)}
                                note={`competência ${rotuloCompetencia(competencia.ano, competencia.mes)}`}
                                tone="success"
                            />
                        </div>

                        <Card>
                            <CardHeader
                                title="Ranking das capitais"
                                description="Tempo médio da análise de viabilidade de endereço, em horas úteis, na competência de referência. Capital sem dado aparece como lacuna — nenhuma posição é estimada."
                            />
                            <CardContent>
                                <DataTable<LinhaCapital>
                                    columns={colunasCapitais}
                                    rows={capitais}
                                    rowKey={(linha) => linha.uf}
                                    density="compact"
                                    emptyState={
                                        <EmptyState
                                            title="Sem capitais na competência"
                                            description="Importe o benchmark para ver o ranking."
                                        />
                                    }
                                />
                            </CardContent>
                        </Card>
                    </>
                )}

                <Card>
                    <CardHeader
                        title="Evolução de Salvador"
                        description="REDESIM do mês anterior, VIABILIZA do mês corrente em tempo real e REDESIM do mês corrente assim que for importada, na mesma régua: horas úteis de viabilidade, sem fins de semana, feriados nem o tempo com o cidadão."
                    />
                    <CardContent>
                        <EvolucaoSalvador evolucao={evolucao} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function EvolucaoSalvador({ evolucao }: { evolucao: Evolucao }) {
    const { anterior, atual, redesim_atual } = evolucao;
    const diferencas = [
        descreverDiferenca(
            evolucao.diferenca_horas,
            evolucao.diferenca_percentual,
            `REDESIM de ${rotuloCompetencia(anterior.ano, anterior.mes)}`,
        ),
        descreverDiferenca(
            evolucao.diferenca_mesmo_mes_horas,
            evolucao.diferenca_mesmo_mes_percentual,
            `REDESIM do mesmo mês, ${rotuloCompetencia(redesim_atual.ano, redesim_atual.mes)}`,
        ),
    ].filter((texto): texto is string => texto !== null);

    if (anterior.media === null && atual.media === null) {
        return (
            <EmptyState
                title="Sem dados para comparar"
                description="Falta a competência anterior da REDESIM e não há processos decididos no mês corrente."
            />
        );
    }

    return (
        <>
            {diferencas.length > 0 && (
                <div className="mb-2 space-y-0.5 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                    {diferencas.map((texto) => (
                        <p key={texto}>{texto}</p>
                    ))}
                    <p className="text-theme-xs font-normal text-gray-500 dark:text-gray-400">Menos horas é melhor.</p>
                </div>
            )}
            <Chart option={evolucaoChartOption(evolucao)} className="h-72 w-full" />
            <ul className="mt-3 space-y-1 text-theme-xs text-gray-500 dark:text-gray-400">
                {anterior.media === null && (
                    <li>
                        A REDESIM ainda não publicou a competência {rotuloCompetencia(anterior.ano, anterior.mes)} para Salvador. A origem
                        consolida o mês ao longo dos primeiros dias do seguinte.
                    </li>
                )}
                {redesim_atual.media === null && (
                    <li>
                        A REDESIM de {rotuloCompetencia(redesim_atual.ano, redesim_atual.mes)} entra no gráfico quando a importação mensal
                        trouxer essa competência, no mês seguinte.
                    </li>
                )}
                {atual.media === null && <li>Nenhum processo foi decidido no VIABILIZA neste mês até agora.</li>}
                {atual.media !== null && (
                    <li>
                        O mês corrente é parcial: considera {numberFormat.format(atual.n)} processos decididos de 1º até hoje, e o valor
                        muda até o fim do mês.
                    </li>
                )}
                <li>{evolucao.ressalva}</li>
            </ul>
        </>
    );
}

/**
 * Identifica a origem e o que exatamente está sendo medido. Fica no topo, e
 * não em rodapé, porque confundir esta medida com a telemetria interna do
 * VIABILIZA é o erro que esta tela existe para evitar.
 */
function RessalvaDeOrigem({ competencia }: { competencia: Competencia | null }) {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
            <AlertIcon className="mt-0.5 size-5 shrink-0 text-brand-600 dark:text-brand-400" />
            <p className="text-theme-sm text-brand-700 dark:text-brand-300">
                Estes números vêm dos <strong>microdados da REDESIM</strong>, não do VIABILIZA — a única exceção é a barra do mês corrente
                na evolução de Salvador, identificada no gráfico. Medem as horas úteis da análise de viabilidade de endereço, descontando
                fins de semana, feriados e o tempo em que o processo está com o cidadão.
                {competencia !== null && ` Competência de referência: ${rotuloCompetencia(competencia.ano, competencia.mes)}.`} O desempenho
                em tempo real do sistema é outra medida e está em Tempo de análise.
            </p>
        </div>
    );
}

Benchmark.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
