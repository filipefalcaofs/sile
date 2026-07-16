import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import {
    CategoriaBadges,
    formatarDataHora,
    type ProcessoItem,
    SemaforoBadge,
} from '@/components/analise/processo-ui';
import { AlertIcon, ArrowRightIcon, FileIcon, InfoIcon, ListIcon } from '@/components/icons';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import type { KpiTone } from '@/components/ui/kpi-card';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface Contadores {
    aguardando_analise: number;
    em_analise: number;
    em_pendencia: number;
    vencendo_hoje: number;
}

interface CargaAnalista {
    analista_id: number;
    analista: string;
    total: number;
}

interface VisaoSetor {
    carga: CargaAnalista[];
    vermelhos: number;
}

interface FilaProps {
    modo: 'meus' | 'setor';
    processos: ProcessoItem[];
    contadores: Contadores;
    visaoSetor: VisaoSetor | null;
}

const numberFormat = new Intl.NumberFormat('pt-BR');

/** Abas da fila — alternam o escopo via GET preservando a rolagem. */
const ABAS: { value: 'meus' | 'setor'; label: string }[] = [
    { value: 'meus', label: 'Meus processos' },
    { value: 'setor', label: 'Caixa do setor' },
];

export default function Fila({ modo, processos, contadores, visaoSetor }: FilaProps) {
    function trocarModo(proximo: 'meus' | 'setor') {
        if (proximo === modo) {
            return;
        }

        router.get('/gestao/processos/fila', { modo: proximo }, { preserveScroll: true, preserveState: false });
    }

    const indicadores: { key: string; label: string; value: number; icon: ReactNode; tone: KpiTone }[] = [
        {
            key: 'aguardando',
            label: 'Aguardando análise',
            value: contadores.aguardando_analise,
            icon: <ListIcon className="size-6" />,
            tone: 'info',
        },
        {
            key: 'em_analise',
            label: 'Em análise',
            value: contadores.em_analise,
            icon: <FileIcon className="size-6" />,
            tone: 'brand',
        },
        {
            key: 'em_pendencia',
            label: 'Em convite',
            value: contadores.em_pendencia,
            icon: <InfoIcon className="size-6" />,
            tone: 'warning',
        },
        {
            key: 'vencendo_hoje',
            label: 'Vencendo hoje',
            value: contadores.vencendo_hoje,
            icon: <AlertIcon className="size-6" />,
            tone: 'error',
        },
    ];

    const columns: ColumnDef<ProcessoItem>[] = [
        {
            id: 'processo',
            header: 'Processo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">{item.protocol_number ?? '—'}</span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.bap ? `BAP ${item.bap}` : 'sem BAP'}
                        {item.tvl_product_number ? ` · TVL ${item.tvl_product_number}` : ''}
                    </span>
                </div>
            ),
        },
        {
            id: 'empresa',
            header: 'Empresa',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.empresa ?? '—'}</span>
                    {item.cnpj && <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.cnpj}</span>}
                </div>
            ),
        },
        {
            id: 'etapa',
            header: 'Etapa',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.analysis_stage_label ?? '—'}</span>
                    <CategoriaBadges categorias={item.categorias} />
                </div>
            ),
        },
        {
            id: 'prazo',
            header: 'Prazo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatarDataHora(item.analysis_due_at),
        },
        {
            id: 'semaforo',
            header: 'SLA',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => <SemaforoBadge sla={item.sla} />,
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end">
                    <TableAction
                        tone="brand"
                        href={`/gestao/processos/${item.id}`}
                        icon={<ArrowRightIcon className="size-4.5" />}
                        label="Abrir processo"
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Fila de trabalho" />
            <PageHeader title="Fila de trabalho" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                    {indicadores.map((indicador) => (
                        <KpiCard
                            key={indicador.key}
                            label={indicador.label}
                            value={numberFormat.format(indicador.value)}
                            icon={indicador.icon}
                            tone={indicador.tone}
                        />
                    ))}
                </div>

                {visaoSetor && (
                    <Card>
                        <CardHeader
                            title="Visão do setor"
                            description="Carga por analista e processos vencidos nas caixas que você coordena (HU-144 CA-03)."
                        />
                        <CardContent>
                            <div className="grid gap-6 lg:grid-cols-3">
                                <div className="lg:col-span-2">
                                    <h4 className="mb-3 text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                        Carga por analista
                                    </h4>
                                    {visaoSetor.carga.length === 0 ? (
                                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                            Nenhum processo atribuído nas caixas do setor.
                                        </p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {visaoSetor.carga.map((carga) => (
                                                <li
                                                    key={carga.analista_id}
                                                    className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-2.5 dark:border-gray-800"
                                                >
                                                    <span className="text-theme-sm text-gray-700 dark:text-gray-300">
                                                        {carga.analista}
                                                    </span>
                                                    <span className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">
                                                        {numberFormat.format(carga.total)}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                                <div className="flex items-center gap-4 rounded-2xl border border-error-200 bg-error-50 p-5 dark:border-error-500/30 dark:bg-error-500/15">
                                    <AlertIcon className="size-8 shrink-0 fill-current text-error-500" />
                                    <div>
                                        <span className="block text-sm text-error-600 dark:text-error-400">
                                            Processos vencidos
                                        </span>
                                        <h4 className="mt-0.5 text-2xl font-bold tracking-tight text-error-700 dark:text-error-300">
                                            {numberFormat.format(visaoSetor.vermelhos)}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader
                        title="Processos a tratar"
                        description="Trabalho priorizado por prazo (SLA). O semáforo sinaliza o tempo restante: verde no prazo, amarelo em alerta, vermelho vencido."
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <div
                                role="tablist"
                                aria-label="Escopo da fila"
                                className="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-white/[0.03]"
                            >
                                {ABAS.map((aba) => {
                                    const ativa = aba.value === modo;

                                    return (
                                        <button
                                            key={aba.value}
                                            type="button"
                                            role="tab"
                                            aria-selected={ativa}
                                            onClick={() => trocarModo(aba.value)}
                                            className={`rounded-md px-4 py-2 text-theme-sm font-medium transition ${
                                                ativa
                                                    ? 'bg-white text-brand-500 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400'
                                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                            }`}
                                        >
                                            {aba.label}
                                        </button>
                                    );
                                })}
                            </div>

                            <DataTable
                                columns={columns}
                                rows={processos}
                                rowKey={(item) => item.id}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={
                                            modo === 'meus'
                                                ? 'Sua fila está limpa'
                                                : 'Nenhum processo na caixa do setor'
                                        }
                                        description={
                                            modo === 'meus'
                                                ? 'Você não tem processos atribuídos em análise ou convite. Assuma processos pela caixa do setor.'
                                                : 'Não há processos em análise ou convite nas caixas dos seus setores.'
                                        }
                                    />
                                }
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Fila.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
