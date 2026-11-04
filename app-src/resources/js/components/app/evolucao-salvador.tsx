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

export interface PontoSerie {
    ano: number;
    mes: number;
    parcial: boolean;
    redesim: Omit<PontoMensal, 'ano' | 'mes'>;
    viabiliza: Omit<PontoMensal, 'ano' | 'mes'>;
}

export interface Competencia {
    ano: number;
    mes: number;
}

export interface AntesDaOperacao {
    media: number;
    n: number;
    meses: number;
    de: Competencia;
    ate: Competencia;
}

export interface SerieEvolucao {
    inicio_operacao: Competencia | null;
    antes_da_operacao: AntesDaOperacao | null;
    pontos: PontoSerie[];
    ressalva: string;
}

export interface Evolucao {
    serie: SerieEvolucao;
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

type Medida = PontoSerie['redesim'];

function detalheDaMedida(nome: string, cor: string, medida: Medida): string {
    if (medida.media === null) {
        return `<div style="margin-top:6px"><strong style="color:${cor}">●</strong> ${nome}: sem dado</div>`;
    }

    return [
        `<div style="margin-top:6px"><strong style="color:${cor}">●</strong> <strong>${nome}</strong></div>`,
        `<div>Tempo médio: ${formatarHoras(medida.media)}</div>`,
        `<div>Metade sai em até: ${formatarHoras(medida.p50)}</div>`,
        `<div>9 em 10 saem em até: ${formatarHoras(medida.p90)}</div>`,
        `<div>Processos: ${numberFormat.format(medida.n)}</div>`,
    ].join('');
}

function evolucaoChartOption({ pontos, antes_da_operacao }: SerieEvolucao): EChartsOption {
    const rotulo = (p: PontoSerie) => `${rotuloCompetencia(p.ano, p.mes)}${p.parcial ? '\n(até hoje)' : ''}`;
    const rotuloValor = ({ value }: { value?: unknown }) => (typeof value === 'number' ? formatarHoras(value) : '');
    const ponto = (media: number | null, parcial: boolean) =>
        parcial ? { value: media, symbol: 'emptyCircle', symbolSize: 10 } : { value: media, symbol: 'circle', symbolSize: 8 };

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const lista = Array.isArray(params) ? params : [params];
                const p = pontos[lista[0]?.dataIndex ?? 0];

                if (p === undefined) {
                    return '';
                }

                return [
                    `<div><strong>${rotuloCompetencia(p.ano, p.mes)}${p.parcial ? ' (até hoje)' : ''}</strong></div>`,
                    detalheDaMedida('REDESIM (Salvador)', chartPalette.gray400, p.redesim),
                    detalheDaMedida('VIABILIZA', chartPalette.brand, p.viabiliza),
                ].join('');
            },
        },
        legend: { top: 0 },
        grid: { left: 16, right: 24, top: 48, bottom: 16, containLabel: true },
        xAxis: {
            type: 'category',
            data: pontos.map(rotulo),
            axisTick: { show: false },
            boundaryGap: true,
        },
        yAxis: { type: 'value', name: 'horas corridas', min: 0 },
        series: [
            {
                type: 'line',
                name: 'REDESIM (Salvador)',
                data: pontos.map((p) => ponto(p.redesim.media, false)),
                itemStyle: { color: chartPalette.gray400 },
                lineStyle: { color: chartPalette.gray400, width: 2 },
                label: { show: pontos.length <= 6, position: 'top', formatter: rotuloValor },
                markLine:
                    antes_da_operacao === null
                        ? undefined
                        : {
                              symbol: 'none',
                              silent: true,
                              lineStyle: { type: 'dashed', color: chartPalette.gray400 },
                              label: {
                                  position: 'insideEndTop',
                                  formatter: `Antes do VIABILIZA: ${formatarHoras(antes_da_operacao.media)}`,
                              },
                              data: [{ yAxis: antes_da_operacao.media }],
                          },
            },
            {
                type: 'line',
                name: 'VIABILIZA',
                data: pontos.map((p) => ponto(p.viabiliza.media, p.parcial)),
                itemStyle: { color: chartPalette.brand },
                lineStyle: { color: chartPalette.brand, width: 3 },
                label: { show: pontos.length <= 6, position: 'top', formatter: rotuloValor },
            },
        ],
    };
}

function variacao(horas: number, referencia: number): string {
    if (referencia <= 0) {
        return '';
    }

    const percentual = ((horas - referencia) / referencia) * 100;

    if (Math.abs(percentual) < 0.5) {
        return ', igual a antes do VIABILIZA';
    }

    return `, ${horasFormat.format(Math.abs(percentual))}% ${percentual < 0 ? 'menos' : 'mais'} que antes do VIABILIZA`;
}

export default function EvolucaoSalvador({ evolucao }: { evolucao: Evolucao }) {
    const { serie } = evolucao;
    const { pontos, antes_da_operacao: antes } = serie;
    const corrente = pontos[pontos.length - 1];
    const ultimaRedesim = [...pontos].reverse().find((p) => p.redesim.media !== null);
    const mesesSemRedesim = pontos.filter((p) => p.redesim.media === null);
    const semNenhumDado = antes === null && pontos.every((p) => p.redesim.media === null && p.viabiliza.media === null);

    if (semNenhumDado) {
        return (
            <EmptyState
                title="Sem dados para comparar"
                description="Ainda não há medição da REDESIM para Salvador nem processos deferidos no VIABILIZA."
            />
        );
    }

    const destaques = [
        antes !== null &&
            `Antes do VIABILIZA: ${formatarHoras(antes.media)} em média (REDESIM, ${rotuloCompetencia(antes.de.ano, antes.de.mes)} a ${rotuloCompetencia(antes.ate.ano, antes.ate.mes)}).`,
        ultimaRedesim !== undefined &&
            ultimaRedesim.redesim.media !== null &&
            `REDESIM de ${rotuloCompetencia(ultimaRedesim.ano, ultimaRedesim.mes)}: ${formatarHoras(ultimaRedesim.redesim.media)}${antes === null ? '' : variacao(ultimaRedesim.redesim.media, antes.media)}.`,
        corrente !== undefined &&
            corrente.viabiliza.media !== null &&
            `VIABILIZA em ${rotuloCompetencia(corrente.ano, corrente.mes)}, até hoje: ${formatarHoras(corrente.viabiliza.media)}${antes === null ? '' : variacao(corrente.viabiliza.media, antes.media)}.`,
    ].filter((texto): texto is string => typeof texto === 'string');

    return (
        <>
            {destaques.length > 0 && (
                <div className="mb-2 space-y-0.5 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                    {destaques.map((texto) => (
                        <p key={texto}>{texto}</p>
                    ))}
                    <p className="text-theme-xs font-normal text-gray-500 dark:text-gray-400">Menos horas é melhor.</p>
                </div>
            )}
            <Chart option={evolucaoChartOption(serie)} className="h-72 w-full" />
            <ul className="mt-3 space-y-1 text-theme-xs text-gray-500 dark:text-gray-400">
                {serie.inicio_operacao !== null && (
                    <li>
                        Série a partir de {rotuloCompetencia(serie.inicio_operacao.ano, serie.inicio_operacao.mes)}, mês em que o VIABILIZA
                        começou a operar, limitada aos últimos 12 meses.
                    </li>
                )}
                {antes !== null && (
                    <li>
                        A linha tracejada é a média da REDESIM para Salvador nos {antes.meses} meses com dado antes do VIABILIZA (
                        {numberFormat.format(antes.n)} solicitações), quando a viabilidade era feita no sistema anterior.
                    </li>
                )}
                {mesesSemRedesim.length > 0 && (
                    <li>
                        A REDESIM de {mesesSemRedesim.map((p) => rotuloCompetencia(p.ano, p.mes)).join(', ')} entra no gráfico quando a
                        importação trouxer o mês, nos primeiros dias do mês seguinte.
                    </li>
                )}
                {corrente !== undefined && corrente.viabiliza.media === null && (
                    <li>Nenhum processo foi deferido no VIABILIZA neste mês até agora.</li>
                )}
                {corrente !== undefined && corrente.viabiliza.media !== null && (
                    <li>
                        O mês corrente é parcial (ponto vazado): considera {numberFormat.format(corrente.viabiliza.n)} processos deferidos
                        de 1º até hoje, e o valor muda até o fim do mês.
                    </li>
                )}
                <li>{serie.ressalva}</li>
            </ul>
        </>
    );
}
