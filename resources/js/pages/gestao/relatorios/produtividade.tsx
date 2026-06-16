import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import { CheckCircleIcon, InfoIcon, ListIcon } from '@/components/icons';
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

/**
 * Linha de produtividade por analista (HU-130). No modo ANÔNIMO (default
 * conservador) chega só `analista_rotulo` (ordinal estável, sem PII); no modo
 * NOMINAL (liberado por permissão no backend) chega `analista_id` + `nome`.
 */
interface ProdutividadeRow {
    analista_rotulo?: string;
    analista_id?: number;
    nome?: string;
    decididas: number;
    deferidas: number;
    indeferidas: number;
    atribuidas: number;
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

interface ProdutividadeProps {
    produtividade: ProdutividadeRow[];
    /** O backend liberou a visão nominal (RN-007)? A tela NUNCA força o nome. */
    nominal: boolean;
    filtros: FiltrosAplicados;
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
}

const URL_PRODUTIVIDADE = '/gestao/relatorios/produtividade';

const numberFormat = new Intl.NumberFormat('pt-BR');

/** Rótulo da linha: nome só quando o backend o mandou (nominal); senão ordinal. */
function rotuloAnalista(row: ProdutividadeRow): string {
    return row.nome ?? row.analista_rotulo ?? 'Analista';
}

/** Chave estável da linha (id nominal ou rótulo ordinal anônimo). */
function chaveAnalista(row: ProdutividadeRow): string | number {
    return row.analista_id ?? row.analista_rotulo ?? rotuloAnalista(row);
}

/** Volume de decisões por analista (barras horizontais) — maior no topo. */
function volumeChartOption(linhas: ProdutividadeRow[]): EChartsOption {
    const ordenado = [...linhas].reverse();

    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 16, right: 24, top: 16, bottom: 16, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', data: ordenado.map((linha) => rotuloAnalista(linha)) },
        series: [{ type: 'bar', name: 'Decisões', data: ordenado.map((linha) => linha.decididas) }],
    };
}

/** Estado vazio honesto de um gráfico. */
function GraficoSemDados() {
    return (
        <div className="flex h-72 w-full items-center justify-center rounded-2xl bg-gray-50 text-theme-sm text-gray-400 dark:bg-white/[0.02] dark:text-gray-500">
            Sem decisões no período.
        </div>
    );
}

export default function Produtividade({ produtividade, nominal, filtros }: ProdutividadeProps) {
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

        router.get(URL_PRODUTIVIDADE, params, { preserveState: true, preserveScroll: true, replace: true });
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

    const colunas: ColumnDef<ProdutividadeRow>[] = [
        {
            id: 'analista',
            header: nominal ? 'Analista' : 'Analista (anônimo)',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (linha) => rotuloAnalista(linha),
        },
        {
            id: 'decididas',
            header: 'Decisões',
            align: 'end',
            cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
            cell: (linha) => numberFormat.format(linha.decididas),
        },
        {
            id: 'deferidas',
            header: 'Deferidas',
            align: 'end',
            cellClassName: 'whitespace-nowrap text-gray-500 dark:text-gray-400',
            cell: (linha) => numberFormat.format(linha.deferidas),
        },
        {
            id: 'indeferidas',
            header: 'Indeferidas',
            align: 'end',
            cellClassName: 'whitespace-nowrap text-gray-500 dark:text-gray-400',
            cell: (linha) => numberFormat.format(linha.indeferidas),
        },
        {
            id: 'atribuidas',
            header: 'Atribuídas',
            align: 'end',
            cellClassName: 'whitespace-nowrap text-gray-500 dark:text-gray-400',
            cell: (linha) => numberFormat.format(linha.atribuidas),
        },
    ];

    const totalDecididas = produtividade.reduce((soma, linha) => soma + linha.decididas, 0);

    return (
        <>
            <Head title="Produtividade por analista" />
            <PageHeader
                title="Produtividade por analista"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Relatórios' },
                    { label: 'Produtividade por analista' },
                ]}
            />

            <div className="space-y-4 md:space-y-6">
                {nominal ? <AvisoNominal /> : <AvisoAnonimo />}

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período da decisão. Os números são reais sobre as decisões de análise técnica — sem decisões no recorte, a tabela fica vazia (nunca um número inventado)."
                        actions={<ExportMenu url={URL_PRODUTIVIDADE} params={currentParams} label="Exportar produtividade" />}
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
                        label="Analistas com decisão no período"
                        value={numberFormat.format(produtividade.length)}
                        note={nominal ? 'visão nominal' : 'visão anônima'}
                        icon={<ListIcon className="size-6" />}
                        tone="brand"
                    />
                    <KpiCard
                        label="Decisões de análise técnica"
                        value={numberFormat.format(totalDecididas)}
                        note="no recorte filtrado"
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="success"
                    />
                </div>

                <Card>
                    <CardHeader title="Volume por analista" description="Decisões de análise técnica concluídas por analista no recorte." />
                    <CardContent>
                        {produtividade.length > 0 ? (
                            <Chart option={volumeChartOption(produtividade)} className="h-80 w-full" />
                        ) : (
                            <GraficoSemDados />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Detalhamento" description="Decisões (deferidas/indeferidas) e processos atribuídos por analista no recorte." />
                    <CardContent>
                        <DataTable<ProdutividadeRow>
                            columns={colunas}
                            rows={produtividade}
                            rowKey={(linha) => chaveAnalista(linha)}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtrando ? 'Nenhuma decisão para os filtros' : 'Nenhuma decisão no período'}
                                    description={
                                        filtrando
                                            ? 'Ajuste o período e tente novamente.'
                                            : 'Quando houver decisões de análise técnica, a produtividade aparece aqui.'
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
 * Aviso da visão ANÔNIMA (default conservador, RN-007/LGPD): os analistas
 * aparecem por rótulo ordinal estável, sem nome. A tela não força nome.
 */
function AvisoAnonimo() {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <InfoIcon className="mt-0.5 size-5 shrink-0 text-warning-600 dark:text-orange-400" />
            <p className="text-theme-sm text-warning-700 dark:text-orange-300">
                Visão <strong>anônima</strong>: os analistas são identificados por um rótulo ordinal (Analista #1, #2…), sem nome. A
                identificação nominal é restrita à permissão própria (RN-007/LGPD) — quando ausente, esta tela mostra apenas o seu próprio
                recorte, sempre anonimizado.
            </p>
        </div>
    );
}

/** Aviso discreto de que a visão nominal está habilitada (permissão liberada). */
function AvisoNominal() {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
            <InfoIcon className="mt-0.5 size-5 shrink-0 text-brand-500 dark:text-brand-400" />
            <p className="text-theme-sm text-brand-700 dark:text-brand-300">
                Visão <strong>nominal</strong> habilitada pela sua permissão (RN-007). Trate os nomes dos analistas conforme a política de uso
                de dados pessoais — a consulta é auditada.
            </p>
        </div>
    );
}

Produtividade.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
