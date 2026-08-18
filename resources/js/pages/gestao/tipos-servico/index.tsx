import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import { PencilIcon, PowerIcon } from '@/components/icons';
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

interface ServiceTypeItem {
    id: number;
    code: string;
    name: string;
    flow_hint: string | null;
    active: boolean;
}

interface ServiceTypesIndexProps {
    serviceTypes: {
        data: ServiceTypeItem[];
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

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

function CreateServiceTypeModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const [active, setActive] = useState('1');

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo tipo de serviço</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O código identifica o tipo e não poderá ser alterado depois. O tipo determina fluxo, documentos e relatórios.
            </p>

            <Form action="/gestao/tipos-servico" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="create-code">Código</Label>
                            <Input
                                id="create-code"
                                type="text"
                                name="code"
                                required
                                placeholder="primeiro-estabelecimento"
                                error={!!errors.code}
                                hint={errors.code}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-name">Nome</Label>
                            <Input
                                id="create-name"
                                type="text"
                                name="name"
                                required
                                placeholder="Viabilidade de primeiro estabelecimento"
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-flow-hint">Pista de fluxo (opcional)</Label>
                            <Input
                                id="create-flow-hint"
                                type="text"
                                name="flow_hint"
                                placeholder="Abertura, alteração, renovação..."
                                error={!!errors.flow_hint}
                                hint={errors.flow_hint}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-active">Situação</Label>
                            <Select
                                id="create-active"
                                name="active"
                                value={active}
                                onChange={setActive}
                                options={[
                                    { value: '1', label: 'Ativo' },
                                    { value: '0', label: 'Inativo' },
                                ]}
                            />
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

function EditServiceTypeModal({ serviceType, onClose }: { serviceType: ServiceTypeItem; onClose: () => void }) {
    const [active, setActive] = useState(serviceType.active ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar tipo de serviço</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {serviceType.code} — o código não pode ser alterado.
            </p>

            <Form action={`/gestao/tipos-servico/${serviceType.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-name-${serviceType.id}`}>Nome</Label>
                            <Input
                                id={`edit-name-${serviceType.id}`}
                                type="text"
                                name="name"
                                defaultValue={serviceType.name}
                                required
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-flow-hint-${serviceType.id}`}>Pista de fluxo (opcional)</Label>
                            <Input
                                id={`edit-flow-hint-${serviceType.id}`}
                                type="text"
                                name="flow_hint"
                                defaultValue={serviceType.flow_hint ?? ''}
                                error={!!errors.flow_hint}
                                hint={errors.flow_hint}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-active-${serviceType.id}`}>Situação</Label>
                            <Select
                                id={`edit-active-${serviceType.id}`}
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

export default function ServiceTypesIndex({ serviceTypes, filters, perPageOptions }: ServiceTypesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-tipos-servico');

    const table = useServerTable({
        url: '/gestao/tipos-servico',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<ServiceTypeItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<ServiceTypeItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<ServiceTypeItem>[] = [
        {
            id: 'code',
            header: 'Código',
            sortable: true,
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (serviceType) => serviceType.code,
        },
        {
            id: 'name',
            header: 'Nome',
            sortable: true,
            cell: (serviceType) => serviceType.name,
        },
        {
            id: 'flow_hint',
            header: 'Pista de fluxo',
            cell: (serviceType) => serviceType.flow_hint ?? '—',
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (serviceType) => <SituationBadge active={serviceType.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (serviceType) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(serviceType)}
                              />
                              <TableAction
                                  tone={serviceType.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={serviceType.active ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(serviceType)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<ServiceTypeItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/tipos-servico/${pendingToggle.id}/ativacao`,
            {},
            {
                preserveScroll: true,
                onStart: () => setActionProcessing(true),
                onFinish: () => setActionProcessing(false),
                onSuccess: () => setPendingToggle(null),
            },
        );
    }

    const confirmContent = pendingToggle
        ? pendingToggle.active
            ? {
                  variant: 'warning' as const,
                  title: 'Desativar tipo de serviço',
                  description: `Confirma a desativação de "${pendingToggle.name}"? Ele deixa de aparecer na seleção do requerente, mas o histórico das solicitações que já o usaram é preservado.`,
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar tipo de serviço',
                  description: `Confirma a reativação de "${pendingToggle.name}"? Ele volta a aparecer na seleção do requerente.`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Tipos de serviço" />
            <PageHeader title="Tipos de serviço" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Tipos de serviço"
                    description="Definem o fluxo, os documentos e os relatórios de cada solicitação de viabilidade (HU-061)."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Novo tipo de serviço
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
                                placeholder: 'Buscar por código ou nome...',
                                label: 'Buscar tipos de serviço',
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
                            rows={serviceTypes.data}
                            rowKey={(serviceType) => serviceType.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum tipo de serviço cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre o primeiro tipo de serviço para liberar a seleção na solicitação.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                                Novo tipo de serviço
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={serviceTypes.links}
                            meta={{ from: serviceTypes.from, to: serviceTypes.to, total: serviceTypes.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {canMaintain && <CreateServiceTypeModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <EditServiceTypeModal serviceType={editing} onClose={() => setEditing(null)} />}

            {confirmContent && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingToggle(null)}
                    onConfirm={executeToggle}
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

ServiceTypesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
