import Chart from '@/components/ui/chart/chart';
import { chartPalette } from '@/components/ui/chart/chart-palette';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import EmptyState from '@/components/ui/empty-state';

export interface PontoMensal {
    ano: number;
    mes: number;
    media: number | null;
    p50: number | null;
    p90: number | null;
    n: number;
}

export interface Evolucao {
    anterior: PontoMensal;
    atual: PontoMensal;
    redesim_atual: PontoMensal;
    diferenca_horas: number | null;
    diferenca_percentual: number | null;
    diferenca_mesmo_mes_horas: number | null;
    diferenca_mesmo_mes_percentual: number | null;
    ressalva: string;
}

const numberFormat = new Intl.NumberFormat('pt-BR');
const horasFormat = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

export function rotuloCompetencia(ano: number, mes: number): string {
    return `${String(mes).padStart(2, '0')}/${ano}`;
}

export function formatarHoras(horas: number | null): string {
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

export default function EvolucaoSalvador({ evolucao }: { evolucao: Evolucao }) {
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
                description="Falta a competência anterior da REDESIM e não há processos deferidos no mês corrente."
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
                {atual.media === null && <li>Nenhum processo foi deferido no VIABILIZA neste mês até agora.</li>}
                {atual.media !== null && (
                    <li>
                        O mês corrente é parcial: considera {numberFormat.format(atual.n)} processos deferidos (com TVL) de 1º até hoje,
                        como na REDESIM, e o valor muda até o fim do mês.
                    </li>
                )}
                <li>{evolucao.ressalva}</li>
            </ul>
        </>
    );
}
