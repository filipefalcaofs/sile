import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import { AlertIcon, CheckCircleIcon, ListIcon } from '@/components/icons';
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

/** Tempo médio de uma etapa da timeline (HU-129): minutos ÚTEIS e amostras. */
interface EtapaTempo {
    etapa: string;
    media_minutos: number | null;
    amostras: number;
}

/** Detalhamento por etapa preenchimento/espera/análise/pendência. */
interface TempoPorEtapa {
    etapas: EtapaTempo[];
}

/** Tempo de emissão do TVL (SAPS, RN-006) em minutos úteis. */
interface TempoEmissaoTvl {
    media_minutos: number | null;
    amostras: number;
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

interface TempoProps {
    tempoPorEtapa: TempoPorEtapa;
    tempoEmissaoTvl: TempoEmissaoTvl;
    filtros: FiltrosAplicados;
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
}

const URL_TEMPO = '/gestao/relatorios/tempo';

/** Rótulos legíveis das etapas; a espera agrega encaminhamento e Junta/BAP. */
const ETAPA_LABELS: Record<string, string> = {
    preenchimento: 'Preenchimento',
    espera: 'Espera (encaminhamento/Junta)',
    analise: 'Análise técnica',
    pendencia: 'Convite',
};

const numberFormat = new Intl.NumberFormat('pt-BR');
const horasFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Um dia útil = 1440 minutos (o calculator soma os minutos dos dias úteis). */
const MINUTOS_POR_DIA_UTIL = 1440;

function rotuloEtapa(etapa: string): string {
    return ETAPA_LABELS[etapa] ?? etapa;
}

/**
 * Converte minutos úteis numa duração legível (dias úteis/horas/minutos),
 * honrando a semântica do BusinessDeadlineCalculator. null (sem amostras no
 * período) vira travessão — jamais "0" disfarçado de tempo real (CA-03).
 */
function formatarDuracao(minutos: number | null): string {
    if (minutos === null) {
        return '—';
    }

    if (minutos < 60) {
        return `${numberFormat.format(minutos)} min`;
    }

    const dias = Math.floor(minutos / MINUTOS_POR_DIA_UTIL);
    const restanteMin = minutos % MINUTOS_POR_DIA_UTIL;
    const horas = Math.floor(restanteMin / 60);
    const min = restanteMin % 60;

    const partes: string[] = [];

    if (dias > 0) {
        partes.push(`${numberFormat.format(dias)} ${dias === 1 ? 'dia útil' : 'dias úteis'}`);
    }

    if (horas > 0) {
        partes.push(`${horas} h`);
    }

    if (min > 0 && dias === 0) {
        partes.push(`${min} min`);
    }

    return partes.join(' ');
}

/** Minutos úteis em horas (1 casa), unidade do eixo do gráfico de etapas. */
function emHoras(minutos: number): number {
    return Math.round((minutos / 60) * 10) / 10;
}

/** Gráfico de barras do tempo médio por etapa, em horas úteis. */
function etapasChartOption(etapas: EtapaTempo[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 24, top: 24, bottom: 16, containLabel: true },
        xAxis: { type: 'category', data: etapas.map((item) => rotuloEtapa(item.etapa)) },
        yAxis: { type: 'value', name: 'horas úteis', minInterval: 1 },
        series: [
            {
                type: 'bar',
                name: 'Horas úteis (média)',
                data: etapas.map((item) => emHoras(item.media_minutos ?? 0)),
                itemStyle: { borderRadius: [6, 6, 0, 0] },
            },
        ],
    };
}

/** Estado vazio honesto de um gráfico. */
function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem amostras no período.
        </div>
    );
}

const colunasEtapas: ColumnDef<EtapaTempo>[] = [
    {
        id: 'etapa',
        header: 'Etapa',
        cellClassName: 'font-medium text-gray-800 dark:text-white/90',
        cell: (linha) => rotuloEtapa(linha.etapa),
    },
    {
        id: 'media',
        header: 'Tempo médio (dias úteis)',
        align: 'end',
        cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => formatarDuracao(linha.media_minutos),
    },
    {
        id: 'amostras',
        header: 'Amostras',
        align: 'end',
        cellClassName: 'whitespace-nowrap text-gray-500 dark:text-gray-400',
        cell: (linha) => numberFormat.format(linha.amostras),
    },
];

export default function TempoAnalise({ tempoPorEtapa, tempoEmissaoTvl, filtros }: TempoProps) {
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

        router.get(URL_TEMPO, params, { preserveState: true, preserveScroll: true, replace: true });
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

    // Snapshot dos filtros atuais para a exportação (?formato=): o conjunto
    // exportado é exatamente o filtrado na tela (RN-004).
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

    // Mesmo recorte, mas o ?relatorio=escritorio-virtual troca o ReportSource no
    // backend (sedes de escritório virtual — SAPS) sem mexer na URL base.
    const sedesParams = useMemo<ServerTableParams>(
        () => ({ ...currentParams, relatorio: 'escritorio-virtual' }),
        [currentParams],
    );

    const etapasComAmostra = tempoPorEtapa.etapas.filter((item) => item.media_minutos !== null);

    return (
        <>
            <Head title="Tempo de análise" />
            <PageHeader
                title="Tempo de análise"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Tempo de análise' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <RessalvaDiasUteis />

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período de protocolo. Os tempos são reais sobre os processos protocolados — sem amostras no recorte, a etapa fica sem média (nunca um tempo inventado)."
                        actions={<ExportMenu url={URL_TEMPO} params={currentParams} label="Exportar tempo por etapa" />}
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

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                    <KpiCard
                        label="Tempo médio de emissão do TVL"
                        value={formatarDuracao(tempoEmissaoTvl.media_minutos)}
                        note={
                            tempoEmissaoTvl.media_minutos === null
                                ? 'sem TVL emitido no período'
                                : `${numberFormat.format(tempoEmissaoTvl.amostras)} ${tempoEmissaoTvl.amostras === 1 ? 'documento' : 'documentos'}`
                        }
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="success"
                    />
                    <KpiCard
                        label="Etapas com amostra no período"
                        value={numberFormat.format(etapasComAmostra.length)}
                        note="das 4 etapas da timeline"
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                </div>

                <Card>
                    <CardHeader
                        title="Tempo médio por etapa"
                        description="Tempo útil (dias úteis, descontando fins de semana e feriados cadastrados) que o processo passa em cada etapa — isolando a espera que não é trabalho da SEDUR."
                    />
                    <CardContent>
                        {etapasComAmostra.length > 0 ? (
                            <Chart option={etapasChartOption(etapasComAmostra)} className="h-72 w-full" />
                        ) : (
                            <GraficoSemDados />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Detalhamento por etapa" description="Média e número de amostras (processos que passaram pela etapa) no recorte." />
                    <CardContent>
                        <DataTable<EtapaTempo>
                            columns={colunasEtapas}
                            rows={tempoPorEtapa.etapas}
                            rowKey={(linha) => linha.etapa}
                            density="compact"
                            emptyState={<EmptyState title="Sem etapas no período" description="Ajuste o período e tente novamente." />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Sedes de escritório virtual (SAPS)"
                        description="A relação das sedes de escritório virtual (RN-006) é servida pelo relatório exportável — empresa, CNPJ, protocolo e resultado — sobre o mesmo recorte de período."
                        actions={
                            <ExportMenu url={URL_TEMPO} params={sedesParams} label="Exportar sedes de escritório virtual" />
                        }
                    />
                    <CardContent>
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            Use a exportação acima (CSV/Excel/PDF) para obter a lista completa de sedes de escritório virtual do período filtrado.
                        </p>
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
 * Ressalva honesta (HU-129): o tempo é medido em dias úteis, descontando fins de
 * semana e os feriados CADASTRADOS. A lista oficial de feriados municipais de
 * Salvador é pendência SEDUR — até a carga oficial, só os feriados do sistema
 * são descontados (nunca feriado inventado). Cadastro em Gestão › Feriados.
 */
function RessalvaDiasUteis() {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <AlertIcon className="mt-0.5 size-5 shrink-0 text-warning-600 dark:text-orange-400" />
            <p className="text-theme-sm text-warning-700 dark:text-orange-300">
                Os tempos são medidos em <strong>dias úteis</strong>, descontando fins de semana e os feriados cadastrados no sistema. A lista
                oficial de feriados municipais de Salvador ainda é pendência da SEDUR — até a carga oficial, apenas os feriados já cadastrados
                em Feriados são descontados. Nenhum feriado é inventado.
            </p>
        </div>
    );
}

TempoAnalise.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
