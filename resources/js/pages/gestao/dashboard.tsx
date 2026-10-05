import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { RevealLink, Surface } from '@/components/motion';
import { chartPalette } from '@/components/ui/chart/chart-palette';
import Chart from '@/components/ui/chart/chart';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import { ExportMenu } from '@/components/ui/data-table/export-menu';
import GestaoLayout from '@/layouts/gestao-layout';

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

interface AcaoOperacao {
    chave: string;
    rotulo: string;
    valor: number;
    nota: string;
    acao: string;
    tom: 'critico' | 'alerta' | 'acao' | 'neutro' | 'falha';
}

interface MotivoExpresso {
    rotulo: string;
    total: number;
}

interface EtapaPainel {
    chave: string;
    label: string;
    media_dias: number | null;
    meta_dias: number | null;
    amostras: number;
}

interface FaixaAging {
    faixa: string;
    label: string;
    total: number;
}

interface CargaSetor {
    nome: string;
    analistas: number;
    em_maos: number;
    por_analista: number | null;
    atrasados: number;
    decididos: number;
}

interface CargaAnalista {
    rotulo: string;
    setor: string | null;
    em_maos: number;
    atrasados: number;
    decididas: number;
    deferidas: number;
    indeferidas: number;
}

interface OperacaoKpis {
    janela_dias: number;
    janelas: number[];
    data_de: string;
    data_ate: string;
    gerado_em: string;
    protocolos: number;
    protocolos_periodo_anterior: number;
    variacao_protocolos: number | null;
    decisoes: DecisoesKpi;
    estoque_total: number;
    atrasados: number;
    saldo_periodo: number;
    pico_entrada: number | null;
    projecao_30_dias: number | null;
    taxa_expressa: number | null;
    meta_expressa: number | null;
    serie_fluxo: SerieFluxoPonto[];
    expresso: {
        respondidas: number;
        elegiveis: number;
        taxa: number | null;
        meta: number | null;
        motivos: MotivoExpresso[];
    };
    tempo_decisao: {
        media_minutos: number | null;
        amostras: number;
        meta_dias: number;
        dentro_prazo_percentual: number | null;
    };
    acoes: AcaoOperacao[];
    etapas: EtapaPainel[];
    aging: FaixaAging[];
    carga: {
        nominal: boolean;
        setores: CargaSetor[];
        analistas: CargaAnalista[];
    };
}

interface DashboardProps {
    kpis: { operacao: OperacaoKpis | null };
}

type TomDelta = 'alta' | 'baixa' | 'neutro' | 'marca';

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
const decimalFormat = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

const TOM_ACAO = {
    critico: {
        card: 'border-error-200 bg-error-50 dark:border-error-500/30 dark:bg-error-500/10',
        rotulo: 'text-error-700 dark:text-error-300',
        valor: 'text-error-700 dark:text-error-300',
        ponto: 'bg-error-500',
    },
    alerta: {
        card: 'border-warning-200 bg-warning-25 dark:border-warning-500/30 dark:bg-warning-500/10',
        rotulo: 'text-warning-700 dark:text-warning-300',
        valor: 'text-gray-800 dark:text-white/90',
        ponto: 'bg-warning-500',
    },
    acao: {
        card: 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]',
        rotulo: 'text-brand-600 dark:text-brand-300',
        valor: 'text-gray-800 dark:text-white/90',
        ponto: 'bg-brand-500',
    },
    neutro: {
        card: 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]',
        rotulo: 'text-gray-600 dark:text-gray-300',
        valor: 'text-gray-800 dark:text-white/90',
        ponto: 'bg-gray-500',
    },
    falha: {
        card: 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]',
        rotulo: 'text-gray-600 dark:text-gray-300',
        valor: 'text-gray-800 dark:text-white/90',
        ponto: 'bg-error-500',
    },
} as const;

const COR_AGING: Record<string, string> = {
    '0_5': chartPalette.success,
    '6_10': chartPalette.brandSoft,
    '11_20': chartPalette.warning,
    acima_20: chartPalette.error,
};

const TOM_AGING: Record<string, string> = {
    '0_5': 'bg-success-500',
    '6_10': 'bg-brand-400',
    '11_20': 'bg-warning-500',
    acima_20: 'bg-error-500',
};

const DELTA: Record<TomDelta, string> = {
    alta: 'text-success-700 dark:text-success-400',
    baixa: 'text-error-700 dark:text-error-400',
    neutro: 'text-gray-500 dark:text-gray-400',
    marca: 'text-brand-500 dark:text-brand-400',
};

function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

function formatarSinal(valor: number, sufixo = ''): string {
    const absoluto = numberFormat.format(Math.abs(valor));
    const texto = `${absoluto}${sufixo}`;

    if (valor > 0) {
        return `+${texto}`;
    }

    if (valor < 0) {
        return `−${texto}`;
    }

    return texto;
}

function formatarVariacao(valor: number | null): string | null {
    if (valor === null) {
        return null;
    }

    const sinal = valor > 0 ? '+' : valor < 0 ? '−' : '';

    return `${sinal}${percentFormat.format(Math.abs(valor))}%`;
}

function formatarDiasUteis(minutos: number | null): string {
    if (minutos === null) {
        return '—';
    }

    if (minutos >= 1440) {
        return `${decimalFormat.format(minutos / 1440)} d`;
    }

    if (minutos >= 60) {
        return `${decimalFormat.format(minutos / 60)} h`;
    }

    return `${numberFormat.format(minutos)} min`;
}

function formatarAtualizado(iso: string): string {
    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return 'agora';
    }

    return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function formatarDiaCurto(iso: string): string {
    const partes = iso.split('-');

    if (partes.length !== 3) {
        return iso;
    }

    return `${partes[2]}/${partes[1]}`;
}

function dataLocal(iso: string): Date | null {
    const partes = iso.split('-').map(Number);

    if (partes.length !== 3 || partes.some((parte) => Number.isNaN(parte))) {
        return null;
    }

    return new Date(partes[0], partes[1] - 1, partes[2]);
}

function chaveLocal(data: Date): string {
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');

    return `${data.getFullYear()}-${mes}-${dia}`;
}

/** Preenche os dias sem protocolo nem decisão com zero, para a linha não pular o vão. */
function serieDiaria(operacao: OperacaoKpis): SerieFluxoPonto[] {
    const inicio = dataLocal(operacao.data_de);
    const fim = dataLocal(operacao.data_ate);

    if (!inicio || !fim || inicio > fim) {
        return operacao.serie_fluxo;
    }

    const mapa = new Map(operacao.serie_fluxo.map((ponto) => [ponto.dia, ponto]));
    const dias: SerieFluxoPonto[] = [];
    const cursor = new Date(inicio);

    while (cursor <= fim && dias.length < 400) {
        const chave = chaveLocal(cursor);
        dias.push(mapa.get(chave) ?? { dia: chave, entrada: 0, saida: 0 });
        cursor.setDate(cursor.getDate() + 1);
    }

    return dias;
}

function hrefPeriodo(caminho: string, operacao: OperacaoKpis): string {
    return `${caminho}?data_de=${operacao.data_de}&data_ate=${operacao.data_ate}`;
}

function hrefAcao(chave: string, operacao: OperacaoKpis): string {
    switch (chave) {
        case 'atrasados':
        case 'vencendo':
            return '/gestao/relatorios/sla';
        case 'distribuir':
            return '/gestao/caixa-setor';
        case 'pendencias':
            return '/gestao/relatorios/pendencias';
        case 'falhas':
            return hrefPeriodo('/gestao/relatorios/comunicacoes-falhas', operacao);
        default:
            return '/gestao';
    }
}

function iniciais(nome: string): string {
    const partes = nome.split(' ').filter((parte) => parte.length > 2);
    const base = partes.length > 0 ? partes : nome.split(' ').filter(Boolean);

    return base
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

function textoGapExpresso(operacao: OperacaoKpis): { texto: string; tom: TomDelta } {
    const { taxa, meta, elegiveis } = operacao.expresso;

    if (taxa === null) {
        return { texto: 'Sem base no período para calcular a taxa.', tom: 'neutro' };
    }

    if (meta === null) {
        return { texto: 'Meta não definida.', tom: 'neutro' };
    }

    if (taxa >= meta) {
        return {
            texto: `Meta atingida. ${numberFormat.format(operacao.expresso.respondidas)} decisões sem toque humano no período.`,
            tom: 'alta',
        };
    }

    const faltam = meta - taxa;
    const estimativa = elegiveis > 0 ? Math.round((elegiveis * faltam) / 100) : null;
    const base = `Faltam ${formatarPercentual(faltam)} para a meta`;

    return {
        texto:
            estimativa === null
                ? `${base}.`
                : `${base} — cerca de ${numberFormat.format(estimativa)} processos que ainda chegam ao analista.`,
        tom: 'baixa',
    };
}

function fluxoChartOption(pontos: SerieFluxoPonto[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        legend: { top: 0 },
        grid: { left: 8, right: 16, top: 36, bottom: 8, containLabel: true },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: pontos.map((ponto) => formatarDiaCurto(ponto.dia)),
        },
        yAxis: { type: 'value', minInterval: 1 },
        series: [
            {
                type: 'line',
                name: 'Protocolos',
                smooth: true,
                showSymbol: pontos.length === 1,
                data: pontos.map((ponto) => ponto.entrada),
                itemStyle: { color: chartPalette.brand },
                lineStyle: { width: 2.5, color: chartPalette.brand },
                areaStyle: { color: 'rgba(19,81,180,0.08)' },
            },
            {
                type: 'line',
                name: 'Decisões',
                smooth: true,
                showSymbol: pontos.length === 1,
                data: pontos.map((ponto) => ponto.saida),
                itemStyle: { color: chartPalette.success },
                lineStyle: { width: 2.5, color: chartPalette.success },
            },
        ],
    };
}

function corEtapa(etapa: EtapaPainel): string {
    if (etapa.media_dias === null) {
        return '#eaecf0';
    }

    if (etapa.meta_dias !== null && etapa.media_dias > etapa.meta_dias) {
        return chartPalette.error;
    }

    return etapa.meta_dias === null ? chartPalette.brand : chartPalette.success;
}

function etapasChartOption(etapas: EtapaPainel[]): EChartsOption {
    return {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = Array.isArray(params) ? params[0] : params;
                const indice = typeof item === 'object' && item && 'dataIndex' in item ? Number(item.dataIndex) : 0;
                const etapa = etapas[indice];

                if (!etapa || etapa.media_dias === null) {
                    return `${etapa?.label ?? ''} — sem amostra`;
                }

                const meta = etapa.meta_dias === null ? '' : `<br/>meta ${numberFormat.format(etapa.meta_dias)} d`;

                return `${etapa.label}<br/>${decimalFormat.format(etapa.media_dias)} d úteis${meta}`;
            },
        },
        grid: { left: 8, right: 24, top: 8, bottom: 8, containLabel: true },
        xAxis: { type: 'value', name: 'dias úteis', minInterval: 1 },
        yAxis: { type: 'category', inverse: true, data: etapas.map((etapa) => etapa.label) },
        series: [
            {
                type: 'bar',
                name: 'Tempo médio',
                data: etapas.map((etapa) => ({
                    value: etapa.media_dias ?? 0,
                    itemStyle: { color: corEtapa(etapa), borderRadius: [0, 6, 6, 0] },
                })),
                markPoint: {
                    symbol: 'rect',
                    symbolSize: [2, 16],
                    itemStyle: { color: chartPalette.gray400 },
                    label: { show: false },
                    tooltip: { show: false },
                    data: etapas.flatMap((etapa) =>
                        etapa.meta_dias === null
                            ? []
                            : [{ name: 'Prazo', coord: [etapa.meta_dias, etapa.label] as [number, string] }],
                    ),
                },
            },
        ],
    };
}

function motivosChartOption(motivos: MotivoExpresso[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 8, right: 24, top: 8, bottom: 8, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', inverse: true, data: motivos.map((motivo) => motivo.rotulo) },
        series: [
            {
                type: 'bar',
                name: 'Quedas',
                cursor: 'pointer',
                data: motivos.map((motivo) => motivo.total),
                itemStyle: { color: chartPalette.brandSoft, borderRadius: [0, 6, 6, 0] },
            },
        ],
    };
}

function expressoChartOption(taxa: number | null, meta: number | null): EChartsOption {
    return {
        grid: { left: 0, right: 8, top: 8, bottom: 8 },
        xAxis: { type: 'value', min: 0, max: 100, show: false },
        yAxis: { type: 'category', data: ['Taxa'], show: false },
        tooltip: { trigger: 'item', formatter: () => `Taxa ${formatarPercentual(taxa)} · meta ${formatarPercentual(meta)}` },
        series: [
            {
                type: 'bar',
                name: 'Taxa expressa',
                data: [taxa ?? 0],
                barWidth: 10,
                showBackground: true,
                backgroundStyle: { color: '#f2f4f7', borderRadius: 8 },
                itemStyle: { color: chartPalette.brand, borderRadius: 8 },
                markLine:
                    meta === null
                        ? undefined
                        : {
                              symbol: 'none',
                              label: { show: false },
                              lineStyle: { color: '#1d2939', width: 2, type: 'solid' },
                              data: [{ xAxis: meta }],
                          },
            },
        ],
    };
}

function agingChartOption(faixas: FaixaAging[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 8, right: 16, top: 8, bottom: 8, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', inverse: true, data: faixas.map((faixa) => faixa.label) },
        series: [
            {
                type: 'bar',
                name: 'Processos',
                cursor: 'pointer',
                data: faixas.map((faixa) => ({
                    value: faixa.total,
                    itemStyle: { color: COR_AGING[faixa.faixa] ?? chartPalette.gray400, borderRadius: [0, 6, 6, 0] },
                })),
            },
        ],
    };
}

function CartaoAcao({ acao, operacao }: { acao: AcaoOperacao; operacao: OperacaoKpis }) {
    const tom = TOM_ACAO[acao.tom];

    return (
        <RevealLink
            href={hrefAcao(acao.chave, operacao)}
            className={`flex flex-col gap-2.5 rounded-2xl border p-5 transition hover:border-brand-500 ${tom.card}`}
        >
            <span className={`flex items-center gap-2 text-[13px] font-semibold tracking-wide uppercase ${tom.rotulo}`}>
                <span className={`inline-block size-2 rounded-full ${tom.ponto}`} />
                {acao.rotulo}
            </span>
            <span className={`text-[34px] leading-[38px] font-bold tracking-tight tabular-nums ${tom.valor}`}>
                {numberFormat.format(acao.valor)}
            </span>
            <span className="text-[13px] leading-[18px] text-gray-500 dark:text-gray-400">{acao.nota}</span>
            <span className="text-[13px] font-semibold text-brand-500 dark:text-brand-400">{acao.acao} →</span>
        </RevealLink>
    );
}

function CartaoResultado({
    href,
    rotulo,
    valor,
    delta,
    tomDelta,
    nota,
}: {
    href: string;
    rotulo: string;
    valor: string;
    delta: string | null;
    tomDelta: TomDelta;
    nota: string;
}) {
    return (
        <RevealLink
            href={href}
            className="rounded-2xl border border-gray-200 bg-white px-5 py-4 transition hover:border-brand-500 dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <span className="block text-[13px] text-gray-500 dark:text-gray-400">{rotulo}</span>
            <span className="mt-1.5 flex items-baseline gap-2.5">
                <strong className="text-[28px] leading-8 font-bold tracking-tight text-gray-800 tabular-nums dark:text-white/90">
                    {valor}
                </strong>
                {delta && <span className={`text-[13px] font-semibold ${DELTA[tomDelta]}`}>{delta}</span>}
            </span>
            <p className="mt-1.5 text-[13px] leading-[18px] text-gray-500 dark:text-gray-400">{nota}</p>
        </RevealLink>
    );
}

function GraficoFluxo({ operacao }: { operacao: OperacaoKpis }) {
    const pontos = useMemo(() => serieDiaria(operacao), [operacao]);
    const option = useMemo(() => fluxoChartOption(pontos), [pontos]);

    if (pontos.length === 0) {
        return (
            <div className="flex h-60 items-center justify-center rounded-2xl bg-gray-50 text-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
                Sem dados no período.
            </div>
        );
    }

    return <Chart option={option} className="mt-3.5 h-60 w-full" />;
}

export default function Dashboard({ kpis }: DashboardProps) {
    const operacao = kpis.operacao;
    const [visaoCarga, setVisaoCarga] = useState<'setor' | 'analista'>('setor');
    const optionEtapas = useMemo(() => etapasChartOption(operacao?.etapas ?? []), [operacao]);
    const optionMotivos = useMemo(() => motivosChartOption(operacao?.expresso.motivos ?? []), [operacao]);
    const optionExpresso = useMemo(
        () => expressoChartOption(operacao?.expresso.taxa ?? null, operacao?.expresso.meta ?? null),
        [operacao],
    );
    const optionAging = useMemo(() => agingChartOption(operacao?.aging ?? []), [operacao]);

    if (!operacao) {
        return (
            <>
                <Head title="Painel da operação" />
                <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">Painel da operação</h2>
                <p className="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                    Os indicadores da operação ficam disponíveis para quem consulta relatórios. Cadastros e parâmetros continuam no menu lateral.
                </p>
            </>
        );
    }

    const variacao = formatarVariacao(operacao.variacao_protocolos);
    const gap = textoGapExpresso(operacao);
    const totalAging = operacao.aging.reduce((soma, faixa) => soma + faixa.total, 0);
    const saldoPositivo = operacao.saldo_periodo > 0;
    const notaTempo =
        operacao.tempo_decisao.media_minutos === null
            ? `sem decisão no período · prazo da análise: ${numberFormat.format(operacao.tempo_decisao.meta_dias)} dias úteis`
            : operacao.tempo_decisao.dentro_prazo_percentual === null
              ? `prazo da análise: ${numberFormat.format(operacao.tempo_decisao.meta_dias)} dias úteis`
              : `prazo da análise: ${numberFormat.format(operacao.tempo_decisao.meta_dias)} dias úteis · ${formatarPercentual(operacao.tempo_decisao.dentro_prazo_percentual)} dentro do prazo`;

    function selecionarJanela(dias: number) {
        router.get('/gestao', { janela: dias }, { preserveScroll: true, preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Painel da operação" />

            <div className="flex flex-col gap-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">Painel da operação</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Últimos {operacao.janela_dias} dias · atualizado às {formatarAtualizado(operacao.gerado_em)}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="inline-flex items-center gap-0.5 rounded-[10px] border border-gray-200 bg-white p-0.5 dark:border-gray-800 dark:bg-gray-900">
                            {operacao.janelas.map((dias) => {
                                const ativo = dias === operacao.janela_dias;

                                return (
                                    <button
                                        key={dias}
                                        type="button"
                                        aria-pressed={ativo}
                                        onClick={() => selecionarJanela(dias)}
                                        className={`rounded-lg px-3.5 py-2 text-sm font-medium transition ${
                                            ativo
                                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'
                                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                        }`}
                                    >
                                        {dias} dias
                                    </button>
                                );
                            })}
                        </div>
                        <ExportMenu
                            url="/gestao/relatorios/indicadores"
                            params={{ data_de: operacao.data_de, data_ate: operacao.data_ate }}
                            label="Exportar indicadores"
                        />
                    </div>
                </div>

                <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-baseline gap-2.5">
                        <h3 className="text-[15px] font-semibold text-gray-800 dark:text-white/90">Exige ação agora</h3>
                        <span className="text-[13px] text-gray-500 dark:text-gray-400">posição de hoje — cada cartão abre o relatório correspondente</span>
                    </div>
                    <div className="grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-4">
                        {operacao.acoes.map((acao) => (
                            <CartaoAcao key={acao.chave} acao={acao} operacao={operacao} />
                        ))}
                    </div>
                </section>

                <section className="grid grid-cols-[repeat(auto-fit,minmax(230px,1fr))] gap-4">
                    <CartaoResultado
                        href={hrefPeriodo('/gestao/processos', operacao)}
                        rotulo="Protocolos no período"
                        valor={numberFormat.format(operacao.protocolos)}
                        delta={variacao}
                        tomDelta={operacao.variacao_protocolos !== null && operacao.variacao_protocolos < 0 ? 'baixa' : 'alta'}
                        nota={variacao === null ? `sem base nos ${operacao.janela_dias} dias anteriores` : `vs. ${operacao.janela_dias} dias anteriores`}
                    />
                    <CartaoResultado
                        href={hrefPeriodo('/gestao/relatorios/indicadores', operacao)}
                        rotulo="Decisões no período"
                        valor={numberFormat.format(operacao.decisoes.total)}
                        delta={operacao.taxa_expressa === null ? null : `${formatarPercentual(operacao.taxa_expressa)} expresso`}
                        tomDelta="marca"
                        nota={
                            operacao.decisoes.total === 0
                                ? 'nenhuma decisão no período'
                                : `${numberFormat.format(operacao.decisoes.humano)} passaram por análise técnica`
                        }
                    />
                    <CartaoResultado
                        href="/gestao/processos?grupo=em_andamento"
                        rotulo="Em andamento"
                        valor={numberFormat.format(operacao.estoque_total)}
                        delta={formatarSinal(operacao.saldo_periodo)}
                        tomDelta={saldoPositivo ? 'baixa' : operacao.saldo_periodo < 0 ? 'alta' : 'neutro'}
                        nota={saldoPositivo ? 'entrou mais do que saiu' : operacao.saldo_periodo < 0 ? 'saiu mais do que entrou' : 'protocolos e decisões empataram'}
                    />
                    <CartaoResultado
                        href={hrefPeriodo('/gestao/relatorios/tempo', operacao)}
                        rotulo="Tempo médio até a decisão"
                        valor={formatarDiasUteis(operacao.tempo_decisao.media_minutos)}
                        delta={null}
                        tomDelta="neutro"
                        nota={notaTempo}
                    />
                </section>

                <section className="flex flex-wrap items-stretch gap-5">
                    <Surface className="min-w-0 flex-[3_1_460px] rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Entrada, saída e processos em andamento</h3>
                                <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                                    O saldo só cai quando as decisões ficam acima dos protocolos. Não há série diária do estoque — a posição em andamento é a de agora.
                                </p>
                            </div>
                        </div>
                        <GraficoFluxo operacao={operacao} />
                        <div className="mt-3.5 flex flex-wrap gap-3 border-t border-gray-100 pt-3.5 dark:border-gray-800">
                            <div className="min-w-40 flex-1">
                                <span className="block text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Saldo do período</span>
                                <strong className={`text-lg font-semibold ${saldoPositivo ? 'text-error-700 dark:text-error-400' : 'text-success-700 dark:text-success-400'}`}>
                                    {formatarSinal(operacao.saldo_periodo)} processos
                                </strong>
                            </div>
                            <div className="min-w-40 flex-1">
                                <span className="block text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Pico de entrada</span>
                                <strong className="text-lg font-semibold text-gray-800 dark:text-white/90">
                                    {operacao.pico_entrada === null
                                        ? '—'
                                        : `${numberFormat.format(operacao.pico_entrada)} protocolos em um dia`}
                                </strong>
                            </div>
                            <div className="min-w-52 flex-1">
                                <span className="block text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Projeção para 30 dias</span>
                                <strong className="text-lg font-semibold text-gray-800 dark:text-white/90">
                                    {operacao.projecao_30_dias === null
                                        ? '—'
                                        : `${numberFormat.format(operacao.projecao_30_dias)} no ritmo atual`}
                                </strong>
                            </div>
                        </div>
                    </Surface>

                    <Surface className="min-w-0 flex-[1_1_320px] rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Automação (fluxo expresso)</h3>
                        <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">Quanto a LOUOS resolve sem analista — e o que está derrubando.</p>
                        <div className="mt-4 flex items-baseline gap-2.5">
                            <strong className="text-[40px] leading-[44px] font-bold tracking-tight text-gray-800 tabular-nums dark:text-white/90">
                                {formatarPercentual(operacao.expresso.taxa)}
                            </strong>
                            <span className="text-[13px] text-gray-500 dark:text-gray-400">
                                meta {formatarPercentual(operacao.expresso.meta)}
                            </span>
                        </div>
                        {operacao.expresso.taxa !== null && <Chart option={optionExpresso} className="mt-3 h-12 w-full" />}
                        <p className={`mt-2.5 text-[13px] leading-[18px] ${DELTA[gap.tom]}`}>{gap.texto}</p>

                        <div className="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-3.5 dark:border-gray-800">
                            <span className="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Por que caiu para análise humana</span>
                            {operacao.expresso.motivos.length === 0 ? (
                                <p className="text-sm text-gray-500 dark:text-gray-400">Nenhuma queda registrada no período.</p>
                            ) : (
                                <Chart
                                    option={optionMotivos}
                                    className="h-52 w-full"
                                    onClick={() => router.visit(hrefPeriodo('/gestao/relatorios/quedas', operacao))}
                                />
                            )}
                        </div>
                    </Surface>
                </section>

                <section className="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <Surface className="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Onde o processo para</h3>
                        <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                            Tempo médio por etapa contra o prazo parametrizado, em dias úteis.
                        </p>
                        <Chart option={optionEtapas} className="mt-2 h-56 w-full" />
                        <p className="mt-4 border-t border-gray-100 pt-3 text-[13px] leading-[18px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Marcador cinza = prazo parametrizado. A espera pelo requerente é medida à parte e não conta como atraso da SEDUR. Sem amostras, a etapa fica sem tempo médio.
                        </p>
                    </Surface>

                    <Surface className="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Há quanto tempo estão em andamento</h3>
                        <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                            Processos em aberto por tempo desde o protocolo. O clique abre a lista em andamento.
                        </p>
                        <Chart
                            option={optionAging}
                            className="mt-2 h-48 w-full"
                            onClick={() => router.visit('/gestao/processos?grupo=em_andamento')}
                        />
                        <div className="mt-4 flex flex-col">
                            {operacao.aging.map((faixa) => (
                                <Link
                                    key={faixa.faixa}
                                    href="/gestao/processos?grupo=em_andamento"
                                    className="flex items-center justify-between gap-3 border-b border-gray-100 py-2.5 dark:border-gray-800"
                                >
                                    <span className="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                                        <span className={`inline-block size-2.5 rounded-[3px] ${TOM_AGING[faixa.faixa] ?? 'bg-gray-300'}`} />
                                        {faixa.label}
                                    </span>
                                    <span className="inline-flex items-baseline gap-2">
                                        <strong className="text-base font-semibold text-gray-800 tabular-nums dark:text-white/90">
                                            {numberFormat.format(faixa.total)}
                                        </strong>
                                        <span className="text-[13px] text-gray-500 dark:text-gray-400">
                                            {totalAging === 0 ? '0%' : `${Math.round((faixa.total / totalAging) * 100)}%`}
                                        </span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </Surface>
                </section>

                <Surface className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div className="flex flex-wrap items-end justify-between gap-3 px-5 pt-5 pb-3.5">
                        <div>
                            <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Carga e produtividade</h3>
                            <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                                {visaoCarga === 'setor'
                                    ? 'Onde distribuir o próximo lote e onde o atraso está concentrado.'
                                    : 'Quem está com processo em mãos e o resultado da análise técnica no período.'}
                            </p>
                        </div>
                        <div className="inline-flex items-center gap-0.5 rounded-[10px] border border-gray-200 bg-white p-0.5 dark:border-gray-800 dark:bg-gray-900">
                            {(
                                [
                                    ['setor', 'Por setor'],
                                    ['analista', 'Por analista'],
                                ] as const
                            ).map(([id, label]) => (
                                <button
                                    key={id}
                                    type="button"
                                    aria-pressed={visaoCarga === id}
                                    onClick={() => setVisaoCarga(id)}
                                    className={`rounded-lg px-3.5 py-2 text-sm font-medium transition ${
                                        visaoCarga === id
                                            ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {visaoCarga === 'setor' ? (
                        operacao.carga.setores.length === 0 ? (
                            <p className="border-t border-gray-100 px-5 py-8 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                Nenhum setor com analista, carga ou decisão no período.
                            </p>
                        ) : (
                            <div className="overflow-x-auto border-t border-gray-100 dark:border-gray-800">
                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 bg-gray-25 dark:border-gray-800 dark:bg-white/[0.02]">
                                            {['Setor', 'Analistas', 'Em mãos', 'Por analista', 'Atrasados', 'Decididos no período'].map((coluna, indice) => (
                                                <th
                                                    key={coluna}
                                                    scope="col"
                                                    className={`px-4 py-2.5 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400 ${indice === 0 ? 'pl-5 text-left' : 'text-right'} ${indice === 5 ? 'pr-5' : ''}`}
                                                >
                                                    {coluna}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {operacao.carga.setores.map((setor) => (
                                            <tr key={setor.nome} className="border-b border-gray-100 dark:border-gray-800">
                                                <td className="py-3.5 pr-4 pl-5 font-medium text-gray-800 dark:text-white/90">{setor.nome}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-500 tabular-nums dark:text-gray-400">
                                                    {numberFormat.format(setor.analistas)}
                                                </td>
                                                <td className="px-4 py-3.5 text-right text-gray-800 tabular-nums dark:text-white/90">
                                                    {numberFormat.format(setor.em_maos)}
                                                </td>
                                                <td className="px-4 py-3.5 text-right font-semibold text-gray-700 tabular-nums dark:text-gray-200">
                                                    {setor.por_analista === null ? '—' : decimalFormat.format(setor.por_analista)}
                                                </td>
                                                <td
                                                    className={`px-4 py-3.5 text-right font-semibold tabular-nums ${setor.atrasados > 0 ? 'text-error-700 dark:text-error-400' : 'text-gray-700 dark:text-gray-200'}`}
                                                >
                                                    {numberFormat.format(setor.atrasados)}
                                                </td>
                                                <td className="py-3.5 pr-5 pl-4 text-right text-gray-500 tabular-nums dark:text-gray-400">
                                                    {numberFormat.format(setor.decididos)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )
                    ) : operacao.carga.analistas.length === 0 ? (
                        <p className="border-t border-gray-100 px-5 py-8 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Nenhuma análise técnica decidida nem processo em mãos no período.
                        </p>
                    ) : (
                        <>
                            <div className="overflow-x-auto border-t border-gray-100 dark:border-gray-800">
                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 bg-gray-25 dark:border-gray-800 dark:bg-white/[0.02]">
                                            <th scope="col" className="py-2.5 pr-4 pl-5 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Analista
                                            </th>
                                            <th scope="col" className="px-4 py-2.5 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Setor
                                            </th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Em mãos
                                            </th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Atrasados
                                            </th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Decididas
                                            </th>
                                            <th scope="col" className="px-4 py-2.5 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Deferidas / indeferidas
                                            </th>
                                            <th scope="col" className="py-2.5 pr-5 pl-4 text-right text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                                Tempo médio
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {operacao.carga.analistas.map((analista, indice) => {
                                            const proporcao =
                                                analista.decididas > 0 ? Math.round((analista.deferidas / analista.decididas) * 100) : null;

                                            return (
                                                <tr key={`${analista.rotulo}-${indice}`} className="border-b border-gray-100 dark:border-gray-800">
                                                    <td className="py-3 pr-4 pl-5">
                                                        <span className="inline-flex items-center gap-2.5">
                                                            <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                                                {operacao.carga.nominal ? iniciais(analista.rotulo) : analista.rotulo.replace('Analista #', '')}
                                                            </span>
                                                            <span className="font-medium text-gray-800 dark:text-white/90">{analista.rotulo}</span>
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-500 dark:text-gray-400">{analista.setor ?? '—'}</td>
                                                    <td className="px-4 py-3 text-right font-semibold text-gray-800 tabular-nums dark:text-white/90">
                                                        {numberFormat.format(analista.em_maos)}
                                                    </td>
                                                    <td
                                                        className={`px-4 py-3 text-right font-semibold tabular-nums ${analista.atrasados > 0 ? 'text-error-700 dark:text-error-400' : 'text-gray-700 dark:text-gray-200'}`}
                                                    >
                                                        {numberFormat.format(analista.atrasados)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-gray-800 tabular-nums dark:text-white/90">
                                                        {numberFormat.format(analista.decididas)}
                                                    </td>
                                                    <td className="min-w-[190px] px-4 py-3">
                                                        <span className="flex items-center gap-2.5">
                                                            <span className="flex h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                                                {proporcao !== null && (
                                                                    <>
                                                                        <span className="block h-2 bg-success-500" style={{ width: `${proporcao}%` }} />
                                                                        <span className="block h-2 bg-warning-500" style={{ width: `${100 - proporcao}%` }} />
                                                                    </>
                                                                )}
                                                            </span>
                                                            <span className="shrink-0 text-[13px] text-gray-500 dark:text-gray-400">
                                                                {proporcao === null ? '—' : `${proporcao}% def.`}
                                                            </span>
                                                        </span>
                                                    </td>
                                                    <td className="py-3 pr-5 pl-4 text-right text-gray-500 dark:text-gray-400">—</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <p className="border-t border-gray-100 px-5 py-3 text-[13px] leading-[18px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                Decisões de análise técnica no período (o expresso não entra). O tempo médio por pessoa fica no relatório de tempo de análise.
                                {operacao.carga.nominal
                                    ? ' Os nomes aparecem porque esta conta consulta o relatório nominal.'
                                    : ' Os nomes ficam como Analista #N — a visão nominal exige permissão própria.'}
                            </p>
                        </>
                    )}
                </Surface>

                <p className="mb-2 text-[13px] text-gray-500 dark:text-gray-400">
                    Configuração do sistema (CNAEs, usuários, perfis, parâmetros) fica no menu lateral — não ocupa mais espaço no painel.
                </p>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
