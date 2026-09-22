import { Head, router, useForm } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Select from '@/components/form/select';
import { ArrowRightIcon, GroupIcon, UserCircleIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

type Visao = 'para_distribuir' | 'distribuidos';

interface ProcessoItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    imovel: string;
    empresa: string | null;
    cnpj: string | null;
    status: string;
    status_label: string;
    analysis_stage: string | null;
    analysis_stage_label: string | null;
    sector: string | null;
    assigned_user_id: number | null;
    assigned_to: string | null;
    analysis_due_at: string | null;
    pode_redistribuir: boolean;
}

interface Analista {
    id: number;
    name: string;
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
    filtros: {
        per_page: number;
    };
    perPageOptions: number[];
    podeDistribuir: boolean;
    podeAssumir: boolean;
    analistas: Analista[];
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
];

export default function CaixaSetorIndex({
    processos,
    visao,
    contadores,
    filtros,
    perPageOptions,
    podeDistribuir,
    podeAssumir,
    analistas,
}: CaixaSetorIndexProps) {
    const linhas = Array.isArray(processos?.data) ? processos.data : [];
    const opcoesAnalista = (Array.isArray(analistas) ? analistas : []).map((analista) => ({
        value: String(analista.id),
        label: analista.name,
    }));

    const [assumindoId, setAssumindoId] = useState<number | null>(null);
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [modal, setModal] = useState<ModalTramitacao | null>(null);

    const tramitacao = useForm<{ request_ids: number[]; analista_id: string }>({
        request_ids: [],
        analista_id: '',
    });

    const selecaoAtiva = visao === 'para_distribuir' && podeDistribuir;
    const todosSelecionados = linhas.length > 0 && linhas.every((item) => selecionados.includes(item.id));

    function navegar(params: { visao?: Visao; per_page?: number }) {
        setSelecionados([]);
        router.get(
            '/gestao/caixa-setor',
            { visao: params.visao ?? visao, per_page: params.per_page ?? filtros.per_page },
            { preserveScroll: true, preserveState: false },
        );
    }

    function alternarSelecao(id: number) {
        setSelecionados((atual) => (atual.includes(id) ? atual.filter((item) => item !== id) : [...atual, id]));
    }

    function alternarTodos() {
        setSelecionados(todosSelecionados ? [] : linhas.map((item) => item.id));
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
        tramitacao.clearErrors();
        tramitacao.setData({ request_ids: novo.ids, analista_id: '' });
        setModal(novo);
    }

    function confirmarTramitacao() {
        const rota = modal?.tipo === 'redistribuir' ? '/gestao/caixa-setor/redistribuir' : '/gestao/caixa-setor/distribuir';

        tramitacao.post(rota, {
            preserveScroll: true,
            onSuccess: () => {
                setModal(null);
                setSelecionados([]);
                tramitacao.reset();
            },
        });
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
                              className="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
                          />
                      ),
                      cellClassName: 'w-10 whitespace-nowrap',
                      cell: (item: ProcessoItem) => (
                          <input
                              type="checkbox"
                              aria-label={`Selecionar processo ${item.bap ?? item.protocol_number ?? item.id}`}
                              checked={selecionados.includes(item.id)}
                              onChange={() => alternarSelecao(item.id)}
                              className="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
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
            id: 'endereco',
            header: 'Endereço',
            cell: (item) => (
                <span className="text-gray-700 dark:text-gray-300">{item.imovel !== '' ? item.imovel : '—'}</span>
            ),
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
                    {podeAssumir && item.assigned_user_id === null && (
                        <TableAction
                            tone="brand"
                            onClick={() => assumir(item)}
                            disabled={assumindoId === item.id}
                            icon={<UserCircleIcon className="size-4.5" />}
                            label="Assumir processo"
                        />
                    )}
                    {podeDistribuir && item.assigned_user_id === null && (
                        <TableAction
                            tone="neutral"
                            onClick={() =>
                                abrirModal({
                                    tipo: 'distribuir',
                                    ids: [item.id],
                                    descricao: `Processo ${item.bap ?? item.protocol_number ?? item.id}`,
                                })
                            }
                            icon={<GroupIcon className="size-4.5" />}
                            label="Distribuir a um analista"
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
                    description="Processos em análise dos seus setores, priorizados por prazo. O apoio ou o gestor seleciona os processos e tramita para um analista do setor; o analista assume um processo. Assumir ou distribuir fixa o responsável — não retira o processo do setor."
                />
                <CardContent>
                    <div className="space-y-5">
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

                        <div className="flex items-center justify-between gap-3">
                            <div>
                                {selecaoAtiva && selecionados.length > 0 && (
                                    <Button
                                        variant="primary"
                                        onClick={() =>
                                            abrirModal({
                                                tipo: 'distribuir',
                                                ids: selecionados,
                                                descricao: `${selecionados.length} processo(s) selecionado(s)`,
                                            })
                                        }
                                    >
                                        Tramitar selecionados ({selecionados.length})
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
                                            : 'Nenhum processo distribuído'
                                    }
                                    description={
                                        visao === 'para_distribuir'
                                            ? 'Não há processos aguardando distribuição nos seus setores.'
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

            <Modal
                isOpen={modal !== null}
                onClose={() => setModal(null)}
                className="m-4 max-w-lg p-6 sm:p-8"
            >
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                    {modal?.tipo === 'redistribuir' ? 'Redistribuir processo' : 'Tramitar processo(s)'}
                </h3>
                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                    {modal?.descricao} — selecione a analista do setor responsável pela análise.
                </p>

                <div className="mt-6">
                    <label htmlFor="tramitar-analista" className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                        Analista
                    </label>
                    <Select
                        id="tramitar-analista"
                        value={tramitacao.data.analista_id}
                        onChange={(value) => tramitacao.setData('analista_id', value)}
                        placeholder={opcoesAnalista.length > 0 ? 'Selecione a analista' : 'Nenhuma analista vinculada ao setor'}
                        options={opcoesAnalista}
                        disabled={opcoesAnalista.length === 0}
                    />
                    {tramitacao.errors.analista_id && (
                        <p className="mt-1.5 text-theme-xs text-error-500">{tramitacao.errors.analista_id}</p>
                    )}
                </div>

                <div className="mt-8 flex justify-end gap-3">
                    <Button variant="outline" onClick={() => setModal(null)} disabled={tramitacao.processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={confirmarTramitacao}
                        loading={tramitacao.processing}
                        disabled={tramitacao.data.analista_id === ''}
                    >
                        Confirmar envio
                    </Button>
                </div>
            </Modal>
        </>
    );
}

CaixaSetorIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
