import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import { PencilIcon, PowerIcon, TrashIcon } from '@/components/icons';
import Select from '@/components/form/select';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef, SortDirection } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface CnaeItem {
    id: number;
    code: string;
    formatted_code: string;
    description: string;
    active: boolean;
    class_code: string;
}

interface CnaesIndexProps {
    cnaes: {
        data: CnaeItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        sort: string;
        direction: SortDirection;
        per_page: number;
        active: string;
    };
    perPageOptions: number[];
}

type PendingAction = { type: 'delete' | 'toggle'; cnae: CnaeItem };

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

export default function CnaesIndex({ cnaes, filters, perPageOptions }: CnaesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-cnaes');

    const table = useServerTable({
        url: '/gestao/cnaes',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<CnaeItem>[] = [
        {
            id: 'code',
            header: 'Código',
            sortable: true,
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (cnae) => cnae.formatted_code,
        },
        {
            id: 'description',
            header: 'Denominação',
            sortable: true,
            cell: (cnae) => cnae.description,
        },
        {
            id: 'class_code',
            header: 'Classe',
            cellClassName: 'whitespace-nowrap',
            cell: (cnae) => cnae.class_code,
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (cnae) => <SituationBadge active={cnae.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (cnae) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  href={`/gestao/cnaes/${cnae.id}/editar`}
                              />
                              <TableAction
                                  tone={cnae.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={cnae.active ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingAction({ type: 'toggle', cnae })}
                              />
                              <TableAction
                                  tone="error"
                                  icon={<TrashIcon className="size-4.5" />}
                                  label="Excluir"
                                  onClick={() => setPendingAction({ type: 'delete', cnae })}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<CnaeItem>,
              ]
            : []),
    ];

    function executePendingAction() {
        if (!pendingAction) {
            return;
        }

        const { type, cnae } = pendingAction;
        const options = {
            preserveScroll: true,
            onStart: () => setActionProcessing(true),
            onFinish: () => setActionProcessing(false),
            onSuccess: () => setPendingAction(null),
        };

        if (type === 'delete') {
            router.delete(`/gestao/cnaes/${cnae.id}`, options);
        } else {
            router.put(
                `/gestao/cnaes/${cnae.id}`,
                { description: cnae.description, active: cnae.active ? '0' : '1' },
                options,
            );
        }
    }

    const confirmContent = pendingAction
        ? pendingAction.type === 'delete'
            ? {
                  variant: 'danger' as const,
                  title: 'Excluir CNAE',
                  description: `Confirma a exclusão do CNAE ${pendingAction.cnae.formatted_code} — ${pendingAction.cnae.description}? Esta ação não pode ser desfeita.`,
                  confirmLabel: 'Excluir',
              }
            : pendingAction.cnae.active
              ? {
                    variant: 'warning' as const,
                    title: 'Desativar CNAE',
                    description: `Confirma a desativação do CNAE ${pendingAction.cnae.formatted_code} — ${pendingAction.cnae.description}?`,
                    confirmLabel: 'Desativar',
                }
              : {
                    variant: 'info' as const,
                    title: 'Reativar CNAE',
                    description: `Confirma a reativação do CNAE ${pendingAction.cnae.formatted_code} — ${pendingAction.cnae.description}?`,
                    confirmLabel: 'Reativar',
                }
        : null;

    return (
        <>
            <Head title="CNAEs" />
            <PageHeader title="CNAEs" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="CNAEs cadastrados"
                    description="Estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA)"
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => router.visit('/gestao/cnaes/criar')}>
                                Cadastrar CNAE
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar por código ou denominação...',
                                label: 'Buscar CNAEs',
                            }}
                            filters={
                                <div className="w-40">
                                    <label htmlFor="filter-active" className="sr-only">
                                        Filtrar por situação
                                    </label>
                                    <Select
                                        id="filter-active"
                                        value={table.filters.active}
                                        onChange={(value) => table.setFilter('active', value)}
                                        placeholder="Situação"
                                        options={[
                                            { value: '1', label: 'Ativos' },
                                            { value: '0', label: 'Inativos' },
                                        ]}
                                    />
                                </div>
                            }
                            actions={
                                <PerPageSelect
                                    value={table.perPage}
                                    options={perPageOptions}
                                    onChange={table.setPerPage}
                                />
                            }
                        />

                        {table.filters.active !== '' && (
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge size="sm" color="light">
                                    Situação: {table.filters.active === '1' ? 'Ativos' : 'Inativos'}
                                </Badge>
                                <button
                                    type="button"
                                    onClick={() => table.setFilter('active', '')}
                                    className="text-theme-xs font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                >
                                    Limpar filtro
                                </button>
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={cnaes.data}
                            rowKey={(cnae) => cnae.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum CNAE cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre o primeiro CNAE para montar a base de atividades.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => router.visit('/gestao/cnaes/criar')}>
                                                Cadastrar CNAE
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={cnaes.links}
                            meta={{ from: cnaes.from, to: cnaes.to, total: cnaes.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {confirmContent && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingAction(null)}
                    onConfirm={executePendingAction}
                    title={confirmContent.title}
                    description={confirmContent.description}
                    confirmLabel={confirmContent.confirmLabel}
                    variant={confirmContent.variant}
                    processing={actionProcessing}
                />
            )}
        </>
    );
}

CnaesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
