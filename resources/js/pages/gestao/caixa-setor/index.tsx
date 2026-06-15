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

interface ProcessoItem {
    id: number;
    protocol_number: string | null;
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

interface CaixaSetorIndexProps {
    processos: Paginado<ProcessoItem>;
    filtros: {
        per_page: number;
    };
    perPageOptions: number[];
    podeDistribuir: boolean;
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

export default function CaixaSetorIndex({
    processos,
    filtros,
    perPageOptions,
    podeDistribuir,
    analistas,
}: CaixaSetorIndexProps) {
    const linhas = Array.isArray(processos?.data) ? processos.data : [];
    const opcoesAnalista = (Array.isArray(analistas) ? analistas : []).map((analista) => ({
        value: String(analista.id),
        label: analista.name,
    }));

    const [assumindoId, setAssumindoId] = useState<number | null>(null);
    const [distribuindo, setDistribuindo] = useState<ProcessoItem | null>(null);

    const distribuicao = useForm<{ request_id: number; analista_id: string }>({
        request_id: 0,
        analista_id: '',
    });

    function alterarPagina(perPage: number) {
        router.get(
            '/gestao/caixa-setor',
            { per_page: perPage },
            { preserveScroll: true, preserveState: false },
        );
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

    function abrirDistribuicao(item: ProcessoItem) {
        distribuicao.clearErrors();
        distribuicao.setData({ request_id: item.id, analista_id: '' });
        setDistribuindo(item);
    }

    function confirmarDistribuicao() {
        distribuicao.post('/gestao/caixa-setor/distribuir', {
            preserveScroll: true,
            onSuccess: () => {
                setDistribuindo(null);
                distribuicao.reset();
            },
        });
    }

    const columns: ColumnDef<ProcessoItem>[] = [
        {
            id: 'processo',
            header: 'Processo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">
                        {item.protocol_number ?? '—'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.sector ?? 'sem setor'}
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
            cell: (item) => item.analysis_stage_label ?? '—',
        },
        {
            id: 'responsavel',
            header: 'Responsável',
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
                    <TableAction
                        tone="brand"
                        onClick={() => assumir(item)}
                        disabled={assumindoId === item.id}
                        icon={<UserCircleIcon className="size-4.5" />}
                        label="Assumir processo"
                    />
                    {podeDistribuir && (
                        <TableAction
                            tone="neutral"
                            onClick={() => abrirDistribuicao(item)}
                            icon={<GroupIcon className="size-4.5" />}
                            label="Distribuir a um analista"
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
                    description="Processos em análise dos seus setores, priorizados por prazo. O analista assume um processo; o gestor distribui a um analista do setor. Assumir ou distribuir fixa o responsável — não retira o processo do setor."
                />
                <CardContent>
                    <div className="space-y-5">
                        <div className="flex justify-end">
                            <PerPageSelect
                                value={filtros.per_page}
                                options={perPageOptions}
                                onChange={alterarPagina}
                            />
                        </div>

                        <DataTable
                            columns={columns}
                            rows={linhas}
                            rowKey={(item) => item.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title="Nenhum processo na caixa do setor"
                                    description="Não há processos em análise aguardando distribuição nos seus setores."
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
                isOpen={distribuindo !== null}
                onClose={() => setDistribuindo(null)}
                className="m-4 max-w-lg p-6 sm:p-8"
            >
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Distribuir processo</h3>
                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                    Processo {distribuindo?.protocol_number ?? '—'} — selecione o analista do setor responsável pela análise.
                </p>

                <div className="mt-6">
                    <label htmlFor="distribuir-analista" className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                        Analista
                    </label>
                    <Select
                        id="distribuir-analista"
                        value={distribuicao.data.analista_id}
                        onChange={(value) => distribuicao.setData('analista_id', value)}
                        placeholder={opcoesAnalista.length > 0 ? 'Selecione o analista' : 'Nenhum analista vinculado ao setor'}
                        options={opcoesAnalista}
                        disabled={opcoesAnalista.length === 0}
                    />
                    {distribuicao.errors.analista_id && (
                        <p className="mt-1.5 text-theme-xs text-error-500">{distribuicao.errors.analista_id}</p>
                    )}
                </div>

                <div className="mt-8 flex justify-end gap-3">
                    <Button variant="outline" onClick={() => setDistribuindo(null)} disabled={distribuicao.processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={confirmarDistribuicao}
                        loading={distribuicao.processing}
                        disabled={distribuicao.data.analista_id === ''}
                    >
                        Distribuir
                    </Button>
                </div>
            </Modal>
        </>
    );
}

CaixaSetorIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
