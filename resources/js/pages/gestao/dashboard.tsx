import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { AlertIcon, ArrowRightIcon, CheckCircleIcon, FileIcon, GroupIcon, InfoIcon, ListIcon, LockIcon, PlugInIcon, TableIcon, UserCircleIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Chart from '@/components/ui/chart/chart';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

/** Ponto da série de volume por dia (HU-123). */
interface SerieVolumePonto {
    dia: string;
    total: number;
}

/** Fatia da distribuição por nível de risco (HU-126). */
interface PorRiscoItem {
    nivel: string;
    fonte: string;
    total: number;
}

/**
 * KPIs operacionais do EP15 no painel (HU-122). As taxas são null quando não há
 * base no período (degradação honesta, CA-03); NÃO há campo `delta` — sem série
 * histórica não existe comparativo, nunca um "+X%" inventado.
 */
interface RelatoriosKpis {
    volume: number;
    taxa_deferimento: number | null;
    taxa_indeferimento: number | null;
    tempo_analise_minutos: number | null;
    taxa_expressa: number | null;
    meta_expressa: number | null;
    janela_dias: number;
    serie_volume: SerieVolumePonto[];
    por_risco: PorRiscoItem[];
}

interface DashboardKpis {
    cnaes: { ativos: number; total: number } | null;
    usuarios: { ativos: number; total: number } | null;
    perfis: { total: number; permissoes: number } | null;
    acessos: { logins: number; janela_dias: number } | null;
    relatorios: RelatoriosKpis | null;
}

interface DashboardProps {
    kpis: DashboardKpis;
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

/** Rótulos pt-BR dos níveis de risco/categoria; código desconhecido fica como veio. */
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

/** Percentual honesto: null (sem base no período) vira travessão, nunca 0% fabricado. */
function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Minutos úteis em formato legível; null (sem amostras) vira travessão. */
function formatarMinutos(valor: number | null): string {
    if (valor === null) {
        return '—';
    }

    if (valor < 60) {
        return `${numberFormat.format(valor)} min`;
    }

    const horas = Math.floor(valor / 60);
    const minutos = valor % 60;

    return minutos === 0 ? `${horas}h` : `${horas}h ${minutos}min`;
}

function rotuloRisco(nivel: string): string {
    return RISCO_LABELS[nivel] ?? nivel;
}

/** Série de volume protocolado por dia (linha) — eixo Y inteiro (contagem). */
function volumeChartOption(serie: SerieVolumePonto[]): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 44, right: 16, top: 24, bottom: 32, containLabel: true },
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

/** Estado vazio honesto de um gráfico (diferencia "sem dados" de erro). */
function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem dados no período.
        </div>
    );
}

export default function Dashboard({ kpis }: DashboardProps) {
    const { auth } = usePage<SharedProps>().props;

    const indicators = [
        kpis.cnaes && {
            key: 'cnaes',
            label: 'CNAEs ativos',
            value: numberFormat.format(kpis.cnaes.ativos),
            note: `de ${numberFormat.format(kpis.cnaes.total)} cadastrados`,
            icon: <TableIcon className="size-6" />,
            tone: 'brand' as const,
        },
        kpis.usuarios && {
            key: 'usuarios',
            label: 'Usuários ativos',
            value: numberFormat.format(kpis.usuarios.ativos),
            note: `de ${numberFormat.format(kpis.usuarios.total)} contas`,
            icon: <GroupIcon className="size-6" />,
            tone: 'success' as const,
        },
        kpis.perfis && {
            key: 'perfis',
            label: 'Perfis de acesso',
            value: numberFormat.format(kpis.perfis.total),
            note: `${numberFormat.format(kpis.perfis.permissoes)} permissões granulares`,
            icon: <LockIcon className="size-6" />,
            tone: 'info' as const,
        },
        kpis.acessos && {
            key: 'acessos',
            label: 'Acessos recentes',
            value: numberFormat.format(kpis.acessos.logins),
            note: `logins em ${kpis.acessos.janela_dias} dias`,
            icon: <UserCircleIcon className="size-6" />,
            tone: 'warning' as const,
        },
    ].filter((indicator) => indicator !== null);

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

    const relatorios = kpis.relatorios;

    const relatoriosIndicators = relatorios
        ? [
              {
                  key: 'volume',
                  label: 'Solicitações protocoladas',
                  value: numberFormat.format(relatorios.volume),
                  note: `nos últimos ${relatorios.janela_dias} dias`,
                  icon: <FileIcon className="size-6" />,
                  tone: 'brand' as const,
              },
              {
                  key: 'deferimento',
                  label: 'Taxa de deferimento',
                  value: formatarPercentual(relatorios.taxa_deferimento),
                  note: relatorios.taxa_deferimento === null ? 'sem decisões no período' : 'das decisões no período',
                  icon: <CheckCircleIcon className="size-6" />,
                  tone: 'success' as const,
              },
              {
                  key: 'indeferimento',
                  label: 'Taxa de indeferimento',
                  value: formatarPercentual(relatorios.taxa_indeferimento),
                  note: relatorios.taxa_indeferimento === null ? 'sem decisões no período' : 'das decisões no período',
                  icon: <AlertIcon className="size-6" />,
                  tone: 'error' as const,
              },
              {
                  key: 'tempo',
                  label: 'Tempo médio de análise',
                  value: formatarMinutos(relatorios.tempo_analise_minutos),
                  note: relatorios.tempo_analise_minutos === null ? 'sem amostras no período' : 'tempo útil por processo',
                  icon: <InfoIcon className="size-6" />,
                  tone: 'info' as const,
              },
              {
                  key: 'expressa',
                  label: 'Resposta expressa',
                  value: formatarPercentual(relatorios.taxa_expressa),
                  note: relatorios.meta_expressa === null ? 'meta não definida' : `meta ${formatarPercentual(relatorios.meta_expressa)}`,
                  icon: <ListIcon className="size-6" />,
                  tone: 'brand' as const,
              },
          ]
        : [];

    return (
        <>
            <Head title="Painel de gestão" />
            <PageHeader title="Painel de gestão" breadcrumbs={[{ label: 'Gestão' }]} />

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                {indicators.length > 0 && (
                    <div className="col-span-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            {indicators.map((indicator) => (
                                <KpiCard
                                    key={indicator.key}
                                    label={indicator.label}
                                    value={indicator.value}
                                    note={indicator.note}
                                    icon={indicator.icon}
                                    tone={indicator.tone}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {relatorios && (
                    <div className="col-span-12 space-y-4 md:space-y-6">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">
                                Operação dos últimos {relatorios.janela_dias} dias
                            </h3>
                            <Link
                                href="/gestao/relatorios/indicadores"
                                className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                            >
                                Indicadores de viabilidade
                                <ArrowRightIcon className="size-4" />
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-5">
                            {relatoriosIndicators.map((indicator) => (
                                <KpiCard
                                    key={indicator.key}
                                    label={indicator.label}
                                    value={indicator.value}
                                    note={indicator.note}
                                    icon={indicator.icon}
                                    tone={indicator.tone}
                                />
                            ))}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader
                                    title="Volume de solicitações"
                                    description={`Protocoladas por dia nos últimos ${relatorios.janela_dias} dias.`}
                                />
                                <CardContent>
                                    {relatorios.serie_volume.length > 0 ? (
                                        <Chart option={volumeChartOption(relatorios.serie_volume)} className="h-72 w-full" />
                                    ) : (
                                        <GraficoSemDados />
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader
                                    title="Distribuição por risco"
                                    description="Por nível do Decreto nº 32.636/2020 (real) ou categoria derivada."
                                />
                                <CardContent>
                                    {relatorios.por_risco.length > 0 ? (
                                        <Chart option={riscoChartOption(relatorios.por_risco)} className="h-72 w-full" />
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
                                            <span className="text-sm text-gray-500 dark:text-gray-400">
                                                {module.label}
                                            </span>
                                            <h4 className="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">
                                                {module.name}
                                            </h4>
                                        </div>
                                        <ArrowRightIcon className="mb-1.5 size-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                <div className="col-span-12">
                    <Card>
                        <CardHeader
                            title="Sessão atual"
                            description="Conta conectada ao ambiente de gestão da SEDUR."
                        />
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Nome</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">E-mail</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.email ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Papéis</dt>
                                    <dd className="mt-1 flex flex-wrap gap-1">
                                        {auth.roles.length === 0 ? (
                                            <span className="text-theme-sm text-gray-400 dark:text-gray-500">
                                                Sem papel
                                            </span>
                                        ) : (
                                            auth.roles.map((role) => (
                                                <Badge key={role} size="sm">
                                                    {role}
                                                </Badge>
                                            ))
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
