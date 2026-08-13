import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import { AlertIcon, CheckCircleIcon, ListIcon, MapPinIcon } from '@/components/icons';
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

/** Par bairro×CNAE avaliado (Observatório de Saturação — Módulo 2). */
interface SaturacaoItem {
    bairro: string;
    cnae: string;
    ativos: number;
    capacidade: number | null;
    percentual: number | null;
    situacao: 'saturado' | 'saturando' | 'ok' | 'sem_capacidade';
}

interface Resumo {
    pares_avaliados: number;
    saturados: number;
    saturando: number;
    sem_capacidade: number;
}

interface Limiares {
    alerta: number;
    bloqueio: number;
}

interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
    bairro?: string;
    cnae?: string;
    categoria?: string;
}

interface SaturacaoProps {
    resumo: Resumo;
    porBairroCnae: SaturacaoItem[];
    limiares: Limiares;
    filtros: FiltrosAplicados;
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
    bairro: string;
    cnae: string;
    categoria: string;
}

const URL_SATURACAO = '/gestao/relatorios/saturacao';

const FILTER_KEYS: (keyof FiltrosForm)[] = ['data_de', 'data_ate', 'bairro', 'cnae', 'categoria'];

const CATEGORIA_OPTIONS = [
    { value: 'expresso', label: 'Expresso' },
    { value: 'semi_expresso', label: 'Semi-expresso' },
    { value: 'malha_fina', label: 'Malha Fina' },
    { value: 'sede_escritorio', label: 'Sede de Escritório' },
];

const TOP_PARES = 15;

const SITUACAO: Record<SaturacaoItem['situacao'], { label: string; color: 'error' | 'warning' | 'success' | 'light' }> = {
    saturado: { label: 'Saturado', color: 'error' },
    saturando: { label: 'Saturando', color: 'warning' },
    ok: { label: 'Ok', color: 'success' },
    sem_capacidade: { label: 'Sem capacidade', color: 'light' },
};

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

/** Formata o código CNAE de 7 dígitos no padrão 9999-9/99; mantém o bruto se não casar. */
function formatarCnae(code: string): string {
    return /^\d{7}$/.test(code) ? `${code.slice(0, 4)}-${code.slice(4, 5)}/${code.slice(5, 7)}` : code;
}

/** Top pares por percentual de saturação (apenas com capacidade definida). */
function saturacaoChartOption(itens: SaturacaoItem[]): EChartsOption {
    const comCapacidade = itens.filter((item) => item.percentual !== null).slice(0, TOP_PARES);
    const ordenado = [...comCapacidade].reverse();

    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (valor) => `${valor}%` },
        grid: { left: 16, right: 24, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'value', name: '%' },
        yAxis: { type: 'category', data: ordenado.map((item) => `${item.bairro} · ${formatarCnae(item.cnae)}`) },
        series: [{ type: 'bar', name: 'Saturação', data: ordenado.map((item) => item.percentual) }],
    };
}

function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem dados no período.
        </div>
    );
}

const colunas: ColumnDef<SaturacaoItem>[] = [
    { id: 'bairro', header: 'Bairro', cellClassName: 'text-gray-800 dark:text-white/90', cell: (l) => l.bairro },
    { id: 'cnae', header: 'CNAE', cellClassName: 'whitespace-nowrap', cell: (l) => formatarCnae(l.cnae) },
    { id: 'ativos', header: 'Deferidos', align: 'end', cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90', cell: (l) => numberFormat.format(l.ativos) },
    { id: 'capacidade', header: 'Capacidade', align: 'end', cellClassName: 'whitespace-nowrap', cell: (l) => (l.capacidade === null ? '—' : numberFormat.format(l.capacidade)) },
    { id: 'percentual', header: 'Saturação', align: 'end', cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90', cell: (l) => formatarPercentual(l.percentual) },
    {
        id: 'situacao',
        header: 'Situação',
        cell: (l) => (
            <Badge color={SITUACAO[l.situacao].color} size="sm">
                {SITUACAO[l.situacao].label}
            </Badge>
        ),
    },
];

export default function SaturacaoPainel({ resumo, porBairroCnae, limiares, filtros }: SaturacaoProps) {
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

        router.get(URL_SATURACAO, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    }, []);

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
            <Head title="Observatório de saturação locacional" />
            <PageHeader
                title="Observatório de saturação locacional"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Saturação locacional' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader
                        title="Filtros"
                        description={`Concentração de estabelecimentos deferidos por bairro × CNAE comparada à capacidade recomendada. Limiares vigentes: "saturando" a partir de ${percentFormat.format(limiares.alerta)}%, "saturado" a partir de ${percentFormat.format(limiares.bloqueio)}%. Configure as capacidades por CNAE em Parâmetros.`}
                        actions={<ExportMenu url={URL_SATURACAO} params={currentParams} label="Exportar solicitações" />}
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

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                    <KpiCard label="Pares avaliados" value={numberFormat.format(resumo.pares_avaliados)} note="bairro × CNAE no recorte" icon={<ListIcon className="size-6" />} tone="brand" />
                    <KpiCard label="Saturados" value={numberFormat.format(resumo.saturados)} note={`≥ ${percentFormat.format(limiares.bloqueio)}% da capacidade`} icon={<AlertIcon className="size-6" />} tone={resumo.saturados > 0 ? 'error' : 'brand'} />
                    <KpiCard label="Saturando" value={numberFormat.format(resumo.saturando)} note={`≥ ${percentFormat.format(limiares.alerta)}% da capacidade`} icon={<AlertIcon className="size-6" />} tone={resumo.saturando > 0 ? 'warning' : 'brand'} />
                    <KpiCard label="Sem capacidade" value={numberFormat.format(resumo.sem_capacidade)} note="CNAE sem limite definido" icon={<CheckCircleIcon className="size-6" />} tone="brand" />
                </div>

                <Card>
                    <CardHeader
                        title="Maiores saturações"
                        description={`Top ${TOP_PARES} pares bairro × CNAE por percentual da capacidade (apenas com capacidade definida).`}
                        actions={
                            <span className="inline-flex items-center gap-1.5">
                                <MapPinIcon className="size-4 text-warning-500" />
                                <Badge color="warning" size="sm">
                                    Zona oficial (GIS) pendente — recorte por bairro
                                </Badge>
                            </span>
                        }
                    />
                    <CardContent>
                        {porBairroCnae.some((item) => item.percentual !== null) ? (
                            <Chart option={saturacaoChartOption(porBairroCnae)} className="h-96 w-full" />
                        ) : (
                            <GraficoSemDados />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Detalhamento por bairro × CNAE" description="Estabelecimentos deferidos, capacidade recomendada e situação de saturação." />
                    <CardContent>
                        <DataTable<SaturacaoItem>
                            columns={colunas}
                            rows={porBairroCnae}
                            rowKey={(linha) => `${linha.bairro}|${linha.cnae}`}
                            loading={processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtrando ? 'Nenhum par para os filtros' : 'Nenhum estabelecimento deferido no período'}
                                    description={
                                        filtrando
                                            ? 'Ajuste o período ou os filtros e tente novamente.'
                                            : 'A saturação é calculada sobre estabelecimentos deferidos; configure as capacidades por CNAE em Parâmetros para ver os percentuais.'
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

function FiltroCampo({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            {children}
        </label>
    );
}

SaturacaoPainel.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
