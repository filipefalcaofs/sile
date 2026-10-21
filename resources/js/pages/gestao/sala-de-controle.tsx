import { Head, router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import DicasTempoCard, { type PainelDicasTempo } from '@/components/app/dicas-tempo-card';
import PageHeader from '@/components/app/page-header';
import IsoDateInput from '@/components/form/iso-date-input';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Chart from '@/components/ui/chart/chart';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';

interface Tempo {
    n: number;
    media: number | null;
    p50: number | null;
    p75: number | null;
    p90: number | null;
    p95: number | null;
    p99: number | null;
    maximo: number | null;
}

interface Operacao {
    total: number;
    expresso: number;
    semi_expresso: number;
    taxa_automacao: number | null;
}

interface Distancia {
    interno: number | null;
    referencia: number | null;
    competencia: { ano: number; mes: number } | null;
    diferenca_horas: number | null;
    diferenca_percentual: number | null;
    motivo: string | null;
    ressalva: string;
}

interface PontoEvolucao {
    ano: number;
    mes: number;
    media: number | null;
    n: number;
}

interface Gargalo {
    faixa: string;
    rotulo: string;
    horas: number;
    percentual: number;
}

interface SalaProps {
    tempo: Tempo;
    operacao: Operacao;
    distancia: Distancia;
    evolucao: PontoEvolucao[];
    gargalos: Gargalo[];
    filtros: { data_de?: string; data_ate?: string };
    dicas: PainelDicasTempo;
}

const numberFormat = new Intl.NumberFormat('pt-BR');
const decimalFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

function formatarHoras(horas: number | null): string {
    if (horas === null) {
        return '—';
    }

    if (horas < 1) {
        return `${Math.round(horas * 60)} min`;
    }

    return `${decimalFormat.format(horas)} h`;
}

function rotuloCompetencia(ano: number, mes: number): string {
    return `${String(mes).padStart(2, '0')}/${ano}`;
}

function evolucaoChartOption(evolucao: PontoEvolucao[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 16, right: 24, top: 24, bottom: 16, containLabel: true },
        xAxis: { type: 'category', data: evolucao.map((p) => rotuloCompetencia(p.ano, p.mes)) },
        yAxis: { type: 'value', name: 'horas úteis' },
        series: [{ type: 'line', name: 'Tempo de viabilidade', smooth: true, data: evolucao.map((p) => p.media) }],
    };
}

function gargalosChartOption(gargalos: Gargalo[]): EChartsOption {
    const ordenado = [...gargalos].reverse();

    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 32, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'value', name: '% do tempo' },
        yAxis: { type: 'category', data: ordenado.map((item) => item.rotulo) },
        series: [
            {
                type: 'bar',
                name: 'Participação no tempo total',
                data: ordenado.map((item) => item.percentual),
                itemStyle: { borderRadius: [0, 6, 6, 0] },
            },
        ],
    };
}

export default function SalaDeControle({ tempo, operacao, distancia, evolucao, gargalos, filtros, dicas }: SalaProps) {
    const [form, setForm] = useState({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
    });
    const stateRef = useRef(form);
    stateRef.current = form;

    const visitar = useCallback((estado: { data_de: string; data_ate: string }) => {
        const params: Record<string, string> = {};

        if (estado.data_de.trim() !== '') {
            params.data_de = estado.data_de;
        }

        if (estado.data_ate.trim() !== '') {
            params.data_ate = estado.data_ate;
        }

        router.get('/gestao/sala-de-controle', params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function setData(key: 'data_de' | 'data_ate', valor: string) {
        const proximo = { ...stateRef.current, [key]: valor };
        setForm(proximo);
        visitar(proximo);
    }

    return (
        <>
            <Head title="Sala de Controle" />
            <PageHeader
                title="Sala de Controle"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Indicadores' }, { label: 'Sala de Controle' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader title="Período" description="Recorte pela data da decisão. Os blocos abaixo respondem ao mesmo recorte." />
                    <CardContent>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label className="flex flex-col gap-1.5">
                                <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Período de</span>
                                <IsoDateInput value={form.data_de} onChange={(v) => setData('data_de', v)} aria-label="Período de" />
                            </label>
                            <label className="flex flex-col gap-1.5">
                                <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Período até</span>
                                <IsoDateInput value={form.data_ate} onChange={(v) => setData('data_ate', v)} aria-label="Período até" />
                            </label>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6">
                    <KpiCard label="Tempo médio" value={formatarHoras(tempo.media)} note={`${numberFormat.format(tempo.n)} decididos`} tone="brand" />
                    <KpiCard label="9 em 10 saem em até" value={formatarHoras(tempo.p90)} note="só os 10% mais lentos passam disso" tone="brand" />
                    <KpiCard
                        label="Automáticas"
                        value={operacao.taxa_automacao === null ? '—' : `${decimalFormat.format(operacao.taxa_automacao)}%`}
                        note={`${numberFormat.format(operacao.expresso)} pelo fluxo expresso`}
                        tone="success"
                    />
                    <KpiCard
                        label="Volume no período"
                        value={numberFormat.format(operacao.total)}
                        note={`${numberFormat.format(operacao.semi_expresso)} semiexpressas`}
                        tone="brand"
                    />
                </div>

                <BlocoDistancia distancia={distancia} />

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 md:gap-6">
                    <Card>
                        <CardHeader
                            title="Evolução de Salvador"
                            description="Tempo de viabilidade medido pela REDESIM, uma competência por ponto. Salvador contra ela mesma, com metodologia constante."
                        />
                        <CardContent>
                            {evolucao.length > 0 ? (
                                <Chart option={evolucaoChartOption(evolucao)} className="h-64 w-full" />
                            ) : (
                                <EmptyState
                                    title="Sem série importada"
                                    description="A evolução aparece conforme as competências do benchmark vão sendo importadas."
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader title="Principais gargalos" description="Participação de cada etapa no tempo total dos processos decididos no período." />
                        <CardContent>
                            {gargalos.length > 0 ? (
                                <Chart option={gargalosChartOption(gargalos)} className="h-64 w-full" />
                            ) : (
                                <EmptyState title="Sem tempo apurado" description="Os gargalos aparecem quando há processos decididos no recorte." />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <DicasTempoCard dicas={dicas} />
            </div>
        </>
    );
}

/**
 * Distância para a referência nacional.
 *
 * O número e a ressalva ficam na MESMA moldura, e a ressalva vem do backend
 * junto do valor: é o bloco mais fácil de ler errado do painel, porque
 * compara telemetria contínua com apuração periódica.
 */
function BlocoDistancia({ distancia }: { distancia: Distancia }) {
    const temComparacao = distancia.diferenca_horas !== null;
    const abaixo = (distancia.diferenca_horas ?? 0) < 0;

    return (
        <Card>
            <CardHeader title="Distância para a referência nacional" />
            <CardContent>
                {temComparacao ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">VIABILIZA no período</p>
                            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">
                                {formatarHoras(distancia.interno)}
                            </p>
                        </div>
                        <div>
                            <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                Referência REDESIM
                                {distancia.competencia !== null &&
                                    ` · ${rotuloCompetencia(distancia.competencia.ano, distancia.competencia.mes)}`}
                            </p>
                            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">
                                {formatarHoras(distancia.referencia)}
                            </p>
                        </div>
                        <div>
                            <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Diferença</p>
                            <p
                                className={`mt-1 text-title-sm font-semibold ${
                                    abaixo ? 'text-success-600 dark:text-success-400' : 'text-warning-600 dark:text-orange-400'
                                }`}
                            >
                                {abaixo ? '↓' : '↑'} {formatarHoras(Math.abs(distancia.diferenca_horas ?? 0))}
                                {distancia.diferenca_percentual !== null &&
                                    ` (${decimalFormat.format(Math.abs(distancia.diferenca_percentual))}%)`}
                            </p>
                        </div>
                    </div>
                ) : (
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">{distancia.motivo}</p>
                )}

                <p className="mt-4 border-t border-gray-200 pt-3 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    {distancia.ressalva}
                </p>
            </CardContent>
        </Card>
    );
}

SalaDeControle.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
