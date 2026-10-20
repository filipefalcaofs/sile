import { Head, router, usePage } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import CnaesDoProcesso from '@/components/analise/cnaes-do-processo';
import DistribuirModal from '@/components/analise/distribuir-modal';
import ProcessoFiltros, { FILTROS_VAZIOS, type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon, GroupIcon, UserCircleIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';
import type { SetorDoAnalista } from './setor-destino';

type Visao = 'para_distribuir' | 'distribuidos' | 'para_conclusao';

interface ProcessoItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    imovel: string;
    servico: string | null;
    servicos: string[];
    cnaes: { codigo: string; descricao: string; principal: boolean }[];
    empresa: string | null;
    cnpj: string | null;
    status: string;
    status_label: string;
    tipo_imovel: string | null;
    status_tramitacao: string | null;
    data_entrada: string | null;
    analysis_status: string | null;
    analysis_stage: string | null;
    analysis_stage_label: string | null;
    sector_id: number | null;
    sector: string | null;
    assigned_user_id: number | null;
    assigned_to: string | null;
    encaminhado_para: string | null;
    analysis_due_at: string | null;
    pode_redistribuir: boolean;
}

interface Analista {
    id: number;
    name: string;
    setores: SetorDoAnalista[];
}

interface Paginado<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface ModalTramitacao {
    tipo: 'distribuir' | 'redistribuir';
    ids: number[];
    descricao: string;
}

interface CaixaSetorIndexProps {
    processos: Paginado<ProcessoItem>;
    visao: Visao;
    contadores: Record<Visao, number>;
    filtros: ProcessoFiltrosValores & { per_page: number };
    servicoOptions: { value: string; label: string }[];
    analysisStatusOptions: { value: string; label: string }[];
    categoriaOptions: { value: string; label: string }[];
    analistaOptions: { value: string; label: string }[];
    perPageOptions: number[];
    podeDistribuir: boolean;
    podeAssumir: boolean;
    distribuiEmQualquerSetor: boolean;
    analistas: Analista[];
    vistoriadores: Analista[];
}

/** Linha a vistoriar — a distribuição é só a vistoriadores. Depois de vistoriada, volta a analista. */
function emVistoria(item: ProcessoItem): boolean {
    return item.analysis_status === 'vistoriar';
}

/** Formata a data-hora ISO do prazo para o padrão pt-BR (dd/mm/aaaa hh:mm). */
function formatarPrazo(iso: string | null): string {
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

const ABAS: { id: Visao; rotulo: string }[] = [
    { id: 'para_distribuir', rotulo: 'Para distribuir' },
    { id: 'distribuidos', rotulo: 'Distribuídos' },
    { id: 'para_conclusao', rotulo: 'Para conclusão' },
];

export default function CaixaSetorIndex({
    processos,
    visao,
    contadores,
    filtros,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
    analistaOptions,
    perPageOptions,
    podeDistribuir,
    podeAssumir,
    distribuiEmQualquerSetor,
    analistas,
    vistoriadores,
}: CaixaSetorIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const podeVistoria = auth.permissions.includes('preencher-ficha-vistoria');

    const linhas = Array.isArray(processos?.data) ? processos.data : [];
    const [assumindoId, setAssumindoId] = useState<number | null>(null);
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [modal, setModal] = useState<ModalTramitacao | null>(null);

    const selecaoAtiva = (visao === 'para_distribuir' || visao === 'para_conclusao') && podeDistribuir;
    const todosSelecionados = linhas.length > 0 && linhas.every((item) => selecionados.includes(item.id));

    // Modal em contexto de vistoria quando QUALQUER linha envolvida está no
    // eixo de vistoria — o seletor passa a listar só vistoriadores (a regra é
    // enforced pelo DistribuicaoService; aqui é a conveniência da tela).
    const modalEmVistoria =
        modal !== null && linhas.some((linha) => modal.ids.includes(linha.id) && emVistoria(linha));

    const { per_page: _perPage, ...filtrosDeCampo } = filtros;

    function navegar(params: { visao?: Visao; per_page?: number; filtros?: ProcessoFiltrosValores }) {
        setSelecionados([]);
        const filtrosAtuais = params.filtros ?? filtrosDeCampo;
        router.get(
            '/gestao/caixa-setor',
            {
                visao: params.visao ?? visao,
                per_page: params.per_page ?? filtros.per_page,
                ...Object.fromEntries(Object.entries(filtrosAtuais).filter(([, v]) => v !== '')),
            },
            { preserveScroll: true, preserveState: false },
        );
    }

    function alternarSelecao(id: number) {
        setSelecionados((atual) => (atual.includes(id) ? atual.filter((item) => item !== id) : [...atual, id]));
    }

    function alternarTodos() {
        setSelecionados(todosSelecionados ? [] : linhas.map((item) => item.id));
    }

    // Em "Para conclusão" o analista pega o processo mesmo com o responsável anterior.
    function podeAssumirItem(item: ProcessoItem): boolean {
        if (item.assigned_user_id === null) {
            return true;
        }

        return visao === 'para_conclusao' && item.assigned_user_id !== auth.user?.id;
    }

    function assumir(item: ProcessoItem) {
        router.post(
            `/gestao/caixa-setor/${item.id}/assumir`,
            {},
            {
                preserveScroll: true,
                onStart: () => setAssumindoId(item.id),
                onFinish: () => setAssumindoId(null),
            },
        );
    }

    function abrirModal(novo: ModalTramitacao) {
        setModal(novo);
    }

    const retorno = new URLSearchParams({
        visao,
        per_page: String(filtros.per_page),
        ...Object.fromEntries(Object.entries(filtrosDeCampo).filter(([, v]) => v !== '')),
    }).toString();

    function irParaCentral(ids: number[]) {
        if (ids.length === 0) {
            return;
        }
        router.get('/gestao/caixa-setor/central', { ids: ids.join(','), retorno }, { preserveScroll: true });
    }

    function distribuicaoInteligente(ids: number[] | null) {
        const params: Record<string, string | number> = { retorno };
        if (ids === null) {
            params.todos = 1;
            for (const [chave, valor] of Object.entries(filtrosDeCampo)) {
                if (valor !== '') {
                    params[chave] = valor;
                }
            }
        } else if (ids.length > 0) {
            params.ids = ids.join(',');
        } else {
            return;
        }
        router.get('/gestao/caixa-setor/distribuicao-inteligente', params, { preserveScroll: true });
    }

    const columns: ColumnDef<ProcessoItem>[] = [
        ...(selecaoAtiva
            ? [
                  {
                      id: 'selecao',
                      header: (
                          <input
                              type="checkbox"
                              aria-label="Selecionar todos os processos da página"
                              checked={todosSelecionados}
                              onChange={alternarTodos}
                              className="size-4 rounded border-gray-300 bg-white text-brand-600 scheme-light focus:ring-brand-500 dark:border-gray-400 dark:bg-gray-900 dark:scheme-dark"
                          />
                      ),
                      cellClassName: 'w-10 whitespace-nowrap',
                      cell: (item: ProcessoItem) => (
                          <input
                              type="checkbox"
                              aria-label={`Selecionar processo ${item.bap ?? item.protocol_number ?? item.id}`}
                              checked={selecionados.includes(item.id)}
                              onChange={() => alternarSelecao(item.id)}
                              className="size-4 rounded border-gray-300 bg-white text-brand-600 scheme-light focus:ring-brand-500 dark:border-gray-400 dark:bg-gray-900 dark:scheme-dark"
                          />
                      ),
                  } satisfies ColumnDef<ProcessoItem>,
              ]
            : []),
        {
            id: 'processo_sedur',
            header: 'Processo SEDUR',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">{item.bap ?? '—'}</span>
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
            cell: (item) => formatarPrazo(item.data_entrada),
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
            cell: (item) => <CnaesDoProcesso cnaes={item.cnaes} />,
        },
        {
            id: 'etapa',
            header: 'Etapa',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => item.analysis_stage_label ?? '—',
        },
        {
            id: 'responsavel',
            header: 'Analista',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.assigned_to ?? <span className="text-gray-400 dark:text-gray-500">Não atribuído</span>,
        },
        ...(visao === 'para_conclusao'
            ? []
            : [
                  {
                      id: 'status_tramitacao',
                      header: 'Status',
                      cellClassName: 'whitespace-nowrap',
                      cell: (item: ProcessoItem) =>
                          item.status_tramitacao ?? <span className="text-gray-400 dark:text-gray-500">—</span>,
                  },
              ]),
        ...(visao === 'para_conclusao'
            ? [
                  {
                      id: 'encaminhado_para',
                      header: 'Encaminhado para',
                      cellClassName: 'whitespace-nowrap',
                      cell: (item: ProcessoItem) => item.encaminhado_para ?? '',
                  },
              ]
            : []),
        {
            id: 'prazo',
            header: 'Prazo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatarPrazo(item.analysis_due_at),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end gap-2">
                    {podeAssumir && podeAssumirItem(item) && (!emVistoria(item) || podeVistoria) && (
                        <TableAction
                            tone="brand"
                            onClick={() => assumir(item)}
                            disabled={assumindoId === item.id}
                            icon={<UserCircleIcon className="size-4.5" />}
                            label="Assumir processo"
                        />
                    )}
                    {podeDistribuir && item.assigned_user_id !== null && item.pode_redistribuir && (
                        <TableAction
                            tone="neutral"
                            onClick={() =>
                                abrirModal({
                                    tipo: 'redistribuir',
                                    ids: [item.id],
                                    descricao: `Processo ${item.bap ?? item.protocol_number ?? item.id} — hoje com ${item.assigned_to ?? 'analista'}`,
                                })
                            }
                            icon={<GroupIcon className="size-4.5" />}
                            label="Redistribuir para outra analista"
                        />
                    )}
                    <TableAction
                        tone="neutral"
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
            <Head title="Caixa do setor" />
            <PageHeader title="Caixa do setor" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Processos para distribuição"
                    description={
                        distribuiEmQualquerSetor
                            ? 'Processos em análise de todos os setores, priorizados por prazo. Você tramita para qualquer analista, e o processo passa para o setor dele.'
                            : 'Processos em análise dos seus setores, priorizados por prazo. O gestor seleciona os processos e tramita para um analista do setor; o analista assume um processo. Assumir ou distribuir fixa o responsável — não retira o processo do setor.'
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <ProcessoFiltros
                            valores={filtrosDeCampo}
                            servicoOptions={servicoOptions}
                            analysisStatusOptions={analysisStatusOptions}
                            categoriaOptions={categoriaOptions}
                            analistaOptions={analistaOptions}
                            onAplicar={(valores) => navegar({ filtros: valores })}
                            onLimpar={() => navegar({ filtros: FILTROS_VAZIOS })}
                        />

                        <div role="tablist" aria-label="Visões da caixa do setor" className="flex border-b border-gray-200 dark:border-gray-800">
                            {ABAS.map((aba) => {
                                const ativa = aba.id === visao;

                                return (
                                    <button
                                        key={aba.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={ativa}
                                        onClick={() => navegar({ visao: aba.id })}
                                        className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                            ativa
                                                ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-200'
                                        }`}
                                    >
                                        {aba.rotulo}
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-theme-xs font-medium ${
                                                ativa
                                                    ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'
                                            }`}
                                        >
                                            {contadores?.[aba.id] ?? 0}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-3">
                                {selecaoAtiva && visao === 'para_distribuir' && (
                                    <Button variant="outline" onClick={() => distribuicaoInteligente(null)}>
                                        Distribuição inteligente de todos os elegíveis
                                    </Button>
                                )}
                                {selecaoAtiva && visao === 'para_distribuir' && selecionados.length > 0 && (
                                    <Button variant="outline" onClick={() => distribuicaoInteligente(selecionados)}>
                                        Distribuição inteligente ({selecionados.length})
                                    </Button>
                                )}
                                {selecaoAtiva && selecionados.length > 0 && (
                                    <Button
                                        variant="primary"
                                        onClick={() => {
                                            const algumEmVistoria = linhas.some(
                                                (linha) => selecionados.includes(linha.id) && emVistoria(linha),
                                            );

                                            if (algumEmVistoria || visao === 'para_conclusao') {
                                                abrirModal({
                                                    tipo: 'distribuir',
                                                    ids: selecionados,
                                                    descricao: `${selecionados.length} processo(s) selecionado(s)`,
                                                });

                                                return;
                                            }

                                            irParaCentral(selecionados);
                                        }}
                                    >
                                        Distribuir selecionados ({selecionados.length})
                                    </Button>
                                )}
                            </div>
                            <PerPageSelect
                                value={filtros.per_page}
                                options={perPageOptions}
                                onChange={(perPage) => navegar({ per_page: perPage })}
                            />
                        </div>

                        <DataTable
                            columns={columns}
                            rows={linhas}
                            rowKey={(item) => item.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={
                                        visao === 'para_distribuir'
                                            ? 'Nenhum processo para distribuir'
                                            : visao === 'para_conclusao'
                                              ? 'Nenhum processo para conclusão'
                                              : 'Nenhum processo distribuído'
                                    }
                                    description={
                                        visao === 'para_distribuir'
                                            ? 'Não há processos aguardando distribuição nos seus setores.'
                                            : visao === 'para_conclusao'
                                              ? 'Não há processos com a análise finalizada aguardando conclusão nos seus setores.'
                                              : 'Ainda não há processos atribuídos a analistas nos seus setores.'
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={processos?.links ?? []}
                            meta={{ from: processos?.from ?? null, to: processos?.to ?? null, total: processos?.total ?? 0 }}
                        />
                    </div>
                </CardContent>
            </Card>

            {modal !== null && (
                <DistribuirModal
                    tipo={modal.tipo}
                    ids={modal.ids}
                    descricao={modal.descricao}
                    destinatarios={modalEmVistoria ? vistoriadores : analistas}
                    papel={modalEmVistoria ? 'Vistoriador' : 'Analista'}
                    distribuiEmQualquerSetor={distribuiEmQualquerSetor}
                    setoresDosProcessos={linhas.filter((linha) => modal.ids.includes(linha.id)).map((linha) => linha.sector_id)}
                    onClose={() => setModal(null)}
                    onSuccess={() => {
                        setModal(null);
                        setSelecionados([]);
                    }}
                />
            )}
        </>
    );
}

CaixaSetorIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
