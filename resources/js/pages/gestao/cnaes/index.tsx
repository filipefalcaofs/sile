import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import { PencilIcon, PowerIcon, TrashIcon } from '@/components/icons';
import Label from '@/components/form/label';
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
import { Modal } from '@/components/ui/modal';
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

function CreateCnaeModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Cadastrar CNAE</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Informe o código da subclasse, a denominação e a hierarquia oficial (IBGE/CONCLA).
            </p>

            <Form action="/gestao/cnaes" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="create-code">Código (DDDD-D/SS)</Label>
                                <Input
                                    id="create-code"
                                    type="text"
                                    name="code"
                                    required
                                    placeholder="0000-0/00"
                                    error={!!errors.code}
                                    hint={errors.code}
                                />
                            </div>
                            <div>
                                <Label htmlFor="create-description">Denominação</Label>
                                <Input
                                    id="create-description"
                                    type="text"
                                    name="description"
                                    required
                                    error={!!errors.description}
                                    hint={errors.description}
                                />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="create-section-code">Seção (código e descrição)</Label>
                                <div className="flex gap-2">
                                    <div className="w-16 shrink-0">
                                        <Input
                                            id="create-section-code"
                                            type="text"
                                            name="section_code"
                                            required
                                            maxLength={1}
                                            placeholder="A"
                                            error={!!errors.section_code}
                                            hint={errors.section_code}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <Input
                                            type="text"
                                            name="section_description"
                                            required
                                            aria-label="Descrição da seção"
                                            error={!!errors.section_description}
                                            hint={errors.section_description}
                                        />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="create-division-code">Divisão (código e descrição)</Label>
                                <div className="flex gap-2">
                                    <div className="w-16 shrink-0">
                                        <Input
                                            id="create-division-code"
                                            type="text"
                                            name="division_code"
                                            required
                                            maxLength={2}
                                            placeholder="01"
                                            error={!!errors.division_code}
                                            hint={errors.division_code}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <Input
                                            type="text"
                                            name="division_description"
                                            required
                                            aria-label="Descrição da divisão"
                                            error={!!errors.division_description}
                                            hint={errors.division_description}
                                        />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="create-group-code">Grupo (código e descrição)</Label>
                                <div className="flex gap-2">
                                    <div className="w-20 shrink-0">
                                        <Input
                                            id="create-group-code"
                                            type="text"
                                            name="group_code"
                                            required
                                            maxLength={5}
                                            placeholder="01.1"
                                            error={!!errors.group_code}
                                            hint={errors.group_code}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <Input
                                            type="text"
                                            name="group_description"
                                            required
                                            aria-label="Descrição do grupo"
                                            error={!!errors.group_description}
                                            hint={errors.group_description}
                                        />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="create-class-code">Classe (código e descrição)</Label>
                                <div className="flex gap-2">
                                    <div className="w-24 shrink-0">
                                        <Input
                                            id="create-class-code"
                                            type="text"
                                            name="class_code"
                                            required
                                            maxLength={7}
                                            placeholder="01.11-3"
                                            error={!!errors.class_code}
                                            hint={errors.class_code}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <Input
                                            type="text"
                                            name="class_description"
                                            required
                                            aria-label="Descrição da classe"
                                            error={!!errors.class_description}
                                            hint={errors.class_description}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

function EditCnaeModal({ cnae, onClose }: { cnae: CnaeItem; onClose: () => void }) {
    const [active, setActive] = useState(cnae.active ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar CNAE</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {cnae.formatted_code} — o código não pode ser alterado.
            </p>

            <Form action={`/gestao/cnaes/${cnae.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-description-${cnae.id}`}>Denominação</Label>
                            <Input
                                id={`edit-description-${cnae.id}`}
                                type="text"
                                name="description"
                                defaultValue={cnae.description}
                                required
                                error={!!errors.description}
                                hint={errors.description}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-active-${cnae.id}`}>Situação</Label>
                            <Select
                                id={`edit-active-${cnae.id}`}
                                name="active"
                                value={active}
                                onChange={setActive}
                                options={[
                                    { value: '1', label: 'Ativo' },
                                    { value: '0', label: 'Inativo' },
                                ]}
                            />
                            {errors.active && <p className="mt-1.5 text-theme-xs text-error-500">{errors.active}</p>}
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar alterações'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
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

    const [showCreate, setShowCreate] = useState(false);
    const [editingCnae, setEditingCnae] = useState<CnaeItem | null>(null);
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
                                  onClick={() => setEditingCnae(cnae)}
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
                            <Button size="sm" onClick={() => setShowCreate(true)}>
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
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
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

            {canMaintain && <CreateCnaeModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editingCnae && <EditCnaeModal cnae={editingCnae} onClose={() => setEditingCnae(null)} />}

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
