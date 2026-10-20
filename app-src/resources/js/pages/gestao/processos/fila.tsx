import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CnaesDoProcesso from '@/components/analise/cnaes-do-processo';
import ProcessoFiltros, { FILTROS_VAZIOS, type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
import JanelaDiasToggle from '@/components/app/janela-dias-toggle';
import PageHeader from '@/components/app/page-header';
import {
    CategoriaBadges,
    type ProcessoItem,
} from '@/components/analise/processo-ui';
import { AlertIcon, ArrowRightIcon, CheckCircleIcon, FileIcon, InfoIcon, ListIcon } from '@/components/icons';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import type { KpiTone } from '@/components/ui/kpi-card';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface Contadores {
    aguardando_analise: number;
    em_analise: number;
    em_pendencia: number;
    vencendo_hoje: number;
    analisados: number;
}

type Visao = 'analise' | 'analisados';

const ABAS: { id: Visao; rotulo: string }[] = [
    { id: 'analise', rotulo: 'Análise' },
    { id: 'analisados', rotulo: 'Analisados' },
];

interface CargaAnalista {
    analista_id: number;
    analista: string;
    total: number;
}

interface VisaoSetor {
    carga: CargaAnalista[];
    vermelhos: number;
}

interface Paginado<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface FilaProps {
    visao: Visao;
    janela: number;
    janelas: number[];
    processos: Paginado<ProcessoItem>;
    contadores: Contadores;
    visaoSetor: VisaoSetor | null;
    filtros: ProcessoFiltrosValores & { per_page: number };
    perPageOptions: number[];
    servicoOptions: { value: string; label: string }[];
    analysisStatusOptions: { value: string; label: string }[];
    categoriaOptions: { value: string; label: string }[];
}

const numberFormat = new Intl.NumberFormat('pt-BR');

function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Fila({
    visao,
    janela,
    janelas,
    processos,
    contadores,
    filtros,
    perPageOptions,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
}: FilaProps) {
    const { per_page: _perPage, ...filtrosDeCampo } = filtros;

    function navegar(params: { visao?: Visao; janela?: number; per_page?: number; filtros?: ProcessoFiltrosValores }) {
        const filtrosAtuais = params.filtros ?? filtrosDeCampo;
        router.get(
            '/gestao/processos/fila',
            {
                visao: params.visao ?? visao,
                janela: params.janela ?? janela,
                per_page: params.per_page ?? filtros.per_page,
                ...Object.fromEntries(Object.entries(filtrosAtuais).filter(([, v]) => v !== '')),
            },
            { preserveScroll: true, preserveState: false },
        );
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
        {
            key: 'analisados',
            label: `Analisados (${janela} dias)`,
            value: contadores.analisados,
            icon: <CheckCircleIcon className="size-6" />,
            tone: 'success',
        },
    ];

    const columns: ColumnDef<ProcessoItem>[] = [
        {
            id: 'processo_sedur',
            header: 'Processo SEDUR',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span
                        className={`text-gray-800 dark:text-white/90 ${
                            item.analysis_status === 'convite_respondido' ? 'font-bold' : 'font-medium'
                        }`}
                    >
                        {item.bap ?? '—'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.protocol_number ?? 'sem protocolo'}
                    </span>
                </div>
            ),
        },
        {
            id: 'data_entrada',
            header: 'Data de entrada',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatarDataHora(item.protocoled_at),
        },
        {
            id: 'endereco',
            header: 'Endereço',
            cell: (item) => (
                <span className="text-gray-700 dark:text-gray-300">{item.imovel !== '' ? item.imovel : '—'}</span>
            ),
        },
        {
            id: 'tipo_imovel',
            header: 'Tipo do imóvel',
            cellClassName: 'min-w-32',
            cell: (item) => item.tipo_imovel ?? <span className="text-gray-400 dark:text-gray-500">—</span>,
        },
        {
            id: 'servico',
            header: 'Serviço',
            cellClassName: 'min-w-56 max-w-xs',
            cell: (item) =>
                item.servicos.length === 0 ? (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ) : (
                    <ul className="flex flex-col gap-1">
                        {item.servicos.map((servico) => (
                            <li key={servico} className="leading-snug text-gray-700 dark:text-gray-300">
                                {servico}
                            </li>
                        ))}
                    </ul>
                ),
        },
        {
            id: 'cnae',
            header: 'CNAE',
            cellClassName: 'min-w-64 max-w-sm',
            cell: (item) => <CnaesDoProcesso cnaes={item.cnaes_detalhe ?? []} />,
        },
        {
            id: 'analise',
            header: 'Análise',
            cellClassName: 'max-w-64 align-top whitespace-normal',
            cell: (item) => (
                <div className="flex w-64 max-w-full flex-col items-start gap-1">
                    <CategoriaBadges categorias={item.categorias} />
                    {item.motivo_da_analise ? (
                        <span className="min-w-0 whitespace-normal text-theme-xs leading-snug text-gray-500 dark:text-gray-400">
                            {item.motivo_da_analise}
                        </span>
                    ) : (
                        <span className="text-gray-400 dark:text-gray-500">—</span>
                    )}
                </div>
            ),
        },
        {
            id: 'status_tramitacao',
            header: 'Status',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <span className={item.analysis_status === 'convite_respondido' ? 'font-bold text-gray-900 dark:text-white' : undefined}>
                    {item.analysis_status_label ?? item.status_label ?? <span className="text-gray-400 dark:text-gray-500">—</span>}
                </span>
            ),
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
            <Head title="Caixa de entrada" />
            <PageHeader title="Caixa de entrada" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                <div className="flex justify-end">
                    <JanelaDiasToggle janelas={janelas} ativa={janela} onSelecionar={(dias) => navegar({ janela: dias })} />
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-5">
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

                <Card>
                    <CardHeader
                        title="Processos a tratar"
                        description={
                            visao === 'analise'
                                ? 'Fila de trabalho do analista.'
                                : 'Todos os processos que você analisou.'
                        }
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <ProcessoFiltros
                                valores={filtrosDeCampo}
                                servicoOptions={servicoOptions}
                                analysisStatusOptions={analysisStatusOptions}
                                categoriaOptions={categoriaOptions}
                                mostrarAnalista={false}
                                onAplicar={(valores) => navegar({ filtros: valores })}
                                onLimpar={() => navegar({ filtros: FILTROS_VAZIOS })}
                            />

                            <div role="tablist" aria-label="Visões da caixa de entrada" className="flex border-b border-gray-200 dark:border-gray-800">
                                {ABAS.map((aba) => {
                                    const ativa = aba.id === visao;

                                    return (
                                        <button
                                            key={aba.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={ativa}
                                            onClick={() => navegar({ visao: aba.id })}
                                            className={`border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                                ativa
                                                    ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-200'
                                            }`}
                                        >
                                            {aba.rotulo}
                                        </button>
                                    );
                                })}
                            </div>

                            <DataTable
                                columns={columns}
                                rows={processos.data}
                                rowKey={(item) => item.id}
                                density="compact"
                                emptyState={
                                    visao === 'analise' ? (
                                        <EmptyState
                                            title="Sua fila está limpa"
                                            description="Você não tem processos atribuídos em análise ou convite. Assuma processos pela caixa do setor."
                                        />
                                    ) : (
                                        <EmptyState
                                            title="Nenhum processo analisado"
                                            description="Os processos aparecem aqui depois que você finaliza a ficha de análise."
                                        />
                                    )
                                }
                            />

                            <div className="flex items-center justify-end">
                                <PerPageSelect
                                    value={filtros.per_page}
                                    options={perPageOptions}
                                    onChange={(pp) => navegar({ per_page: pp })}
                                />
                            </div>
                            <Pagination
                                links={processos.links}
                                meta={{ from: processos.from, to: processos.to, total: processos.total }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Fila.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
