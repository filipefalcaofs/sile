import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { GroupIcon, PencilIcon, PowerIcon } from '@/components/icons';
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

interface AnalystOption {
    id: number;
    name: string;
}

interface SectorItem {
    id: number;
    name: string;
    active: boolean;
    analysts_count: number;
    requests_count: number;
    analysts: AnalystOption[];
}

interface SectorsIndexProps {
    sectors: {
        data: SectorItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    analistasDisponiveis: AnalystOption[];
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

function CreateSectorModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const [active, setActive] = useState('1');

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo setor</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O setor é a caixa de análise da distribuição. Depois de criado, vincule os analistas responsáveis.
            </p>

            <Form action="/gestao/setores" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="create-sector-name">Nome</Label>
                            <Input
                                id="create-sector-name"
                                type="text"
                                name="name"
                                required
                                placeholder="Ex.: Análise Centro"
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-sector-active">Situação</Label>
                            <Select
                                id="create-sector-active"
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

function EditSectorModal({ sector, onClose }: { sector: SectorItem; onClose: () => void }) {
    const [active, setActive] = useState(sector.active ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar setor</h4>

            <Form action={`/gestao/setores/${sector.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-sector-name-${sector.id}`}>Nome</Label>
                            <Input
                                id={`edit-sector-name-${sector.id}`}
                                type="text"
                                name="name"
                                defaultValue={sector.name}
                                required
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-sector-active-${sector.id}`}>Situação</Label>
                            <Select
                                id={`edit-sector-active-${sector.id}`}
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

/** Vínculo analista↔setor (RN-005): multiselect dos analistas disponíveis. */
function ManageAnalystsModal({
    sector,
    disponiveis,
    onClose,
}: {
    sector: SectorItem;
    disponiveis: AnalystOption[];
    onClose: () => void;
}) {
    const [selecionados, setSelecionados] = useState<number[]>(() => sector.analysts.map((analyst) => analyst.id));
    const [processing, setProcessing] = useState(false);

    function alternar(id: number, marcado: boolean) {
        setSelecionados((atual) => (marcado ? [...atual, id] : atual.filter((item) => item !== id)));
    }

    function salvar() {
        router.put(
            `/gestao/setores/${sector.id}/analistas`,
            { analyst_ids: selecionados },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
            },
        );
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Analistas do setor {sector.name}</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Marque os analistas responsáveis por esta caixa. Um analista pode cobrir vários setores (RN-005).
            </p>

            <div className="mt-5 space-y-3">
                {disponiveis.length === 0 ? (
                    <EmptyState
                        title="Nenhum analista disponível"
                        description="Cadastre usuários com o papel de analista para vinculá-los a um setor."
                    />
                ) : (
                    disponiveis.map((analyst) => (
                        <Checkbox
                            key={analyst.id}
                            label={analyst.name}
                            checked={selecionados.includes(analyst.id)}
                            onChange={(marcado) => alternar(analyst.id, marcado)}
                        />
                    ))
                )}
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" onClick={salvar} loading={processing} disabled={disponiveis.length === 0}>
                    Salvar vínculos
                </Button>
            </div>
        </Modal>
    );
}

export default function SectorsIndex({ sectors, analistasDisponiveis, filters, perPageOptions }: SectorsIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-setores');

    const table = useServerTable({
        url: '/gestao/setores',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<SectorItem | null>(null);
    const [managing, setManaging] = useState<SectorItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<SectorItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<SectorItem>[] = [
        {
            id: 'name',
            header: 'Setor',
            sortable: true,
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (sector) => sector.name,
        },
        {
            id: 'analysts_count',
            header: 'Analistas',
            align: 'center',
            cell: (sector) => (
                <Badge color="light" size="sm">
                    {sector.analysts_count}
                </Badge>
            ),
        },
        {
            id: 'requests_count',
            header: 'Processos',
            align: 'center',
            cell: (sector) => (
                <Badge color="light" size="sm">
                    {sector.requests_count}
                </Badge>
            ),
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (sector) => <SituationBadge active={sector.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (sector) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="neutral"
                                  icon={<GroupIcon className="size-4.5" />}
                                  label="Gerenciar analistas"
                                  onClick={() => setManaging(sector)}
                              />
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(sector)}
                              />
                              <TableAction
                                  tone={sector.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={sector.active ? 'Inativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(sector)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<SectorItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/setores/${pendingToggle.id}/ativacao`,
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
                  title: 'Inativar setor',
                  description: `Confirma a inativação de "${pendingToggle.name}"? Ele deixa de receber novas distribuições, mas o histórico e os vínculos são preservados (RN-004).`,
                  confirmLabel: 'Inativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar setor',
                  description: `Confirma a reativação de "${pendingToggle.name}"? Ele volta a receber distribuições.`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Setores" />
            <PageHeader title="Setores" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Setores de análise"
                    description="A caixa de distribuição da análise técnica (HU-138). Vincule os analistas responsáveis por cada setor."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Novo setor
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
                                placeholder: 'Buscar por nome...',
                                label: 'Buscar setores',
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
                            rows={sectors.data}
                            rowKey={(sector) => sector.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum setor cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre o primeiro setor para organizar a distribuição da análise.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                                Novo setor
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={sectors.links}
                            meta={{ from: sectors.from, to: sectors.to, total: sectors.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {canMaintain && <CreateSectorModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <EditSectorModal sector={editing} onClose={() => setEditing(null)} />}

            {managing && (
                <ManageAnalystsModal
                    sector={managing}
                    disponiveis={analistasDisponiveis}
                    onClose={() => setManaging(null)}
                />
            )}

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

SectorsIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
