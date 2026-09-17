import { Head, router, useForm, usePage } from '@inertiajs/react';
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

interface PropertyTypeItem {
    id: number;
    code: string;
    label: string;
    drives_rule: boolean;
    active: boolean;
    aliases: string[];
}

interface PropertyTypesIndexProps {
    propertyTypes: {
        data: PropertyTypeItem[];
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

function DrivesRuleBadge({ drivesRule }: { drivesRule: boolean }) {
    return (
        <Badge size="sm" color={drivesRule ? 'brand' : 'light'}>
            {drivesRule ? 'Dirige regra' : 'Ramo comum'}
        </Badge>
    );
}

function AliasChips({ aliases }: { aliases: string[] }) {
    if (aliases.length === 0) {
        return <span className="text-gray-400 dark:text-gray-600">—</span>;
    }
    return (
        <div className="flex flex-wrap gap-1">
            {aliases.map((alias) => (
                <Badge key={alias} size="sm" color="light">
                    {alias}
                </Badge>
            ))}
        </div>
    );
}

function DrivesRuleField({
    checked,
    onChange,
    disabled = false,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
}) {
    return (
        <label
            className={`flex cursor-pointer select-none items-center gap-3 text-sm font-medium ${
                disabled ? 'cursor-not-allowed text-gray-400' : 'text-gray-700 dark:text-gray-400'
            }`}
        >
            <div className="relative">
                <div
                    className={`block h-6 w-11 rounded-full transition duration-150 ease-linear ${
                        disabled
                            ? 'bg-gray-100 dark:bg-gray-800'
                            : checked
                              ? 'bg-brand-500'
                              : 'bg-gray-200 dark:bg-white/10'
                    }`}
                />
                <div
                    className={`absolute left-0.5 top-0.5 h-5 w-5 rounded-full shadow-theme-sm duration-150 ease-linear ${
                        checked ? 'translate-x-full bg-white' : 'translate-x-0 bg-white'
                    }`}
                />
                <input
                    type="checkbox"
                    className="sr-only"
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => !disabled && onChange(e.target.checked)}
                />
            </div>
            Dirige regra de enquadramento
        </label>
    );
}

function CreatePropertyTypeModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const [aliasesText, setAliasesText] = useState('');
    const [drivesRule, setDrivesRule] = useState(false);
    const [pendingDrivesRuleTrue, setPendingDrivesRuleTrue] = useState(false);

    const form = useForm({
        code: '',
        label: '',
        drives_rule: false,
        active: true,
        aliases: [] as string[],
    });

    function handleDrivesRuleChange(value: boolean) {
        if (value && !drivesRule) {
            setPendingDrivesRuleTrue(true);
        } else {
            setDrivesRule(false);
            form.setData('drives_rule', false);
        }
    }

    function confirmDrivesRule() {
        setDrivesRule(true);
        form.setData('drives_rule', true);
        setPendingDrivesRuleTrue(false);
    }

    function cancelDrivesRule() {
        setPendingDrivesRuleTrue(false);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const parsedAliases = aliasesText
            .split('\n')
            .map((s) => s.trim())
            .filter((s) => s.length > 0);
        form.transform((data) => ({ ...data, aliases: parsedAliases }));
        form.post('/gestao/tipos-imovel', {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                form.reset();
                setAliasesText('');
                setDrivesRule(false);
            },
        });
    }

    return (
        <>
            <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
                <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo tipo de imóvel</h4>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    O código identifica o tipo e não poderá ser alterado depois. O tipo é usado no enquadramento da LOUOS e na classificação de
                    risco.
                </p>

                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="create-code">Código</Label>
                            <Input
                                id="create-code"
                                type="text"
                                name="code"
                                required
                                placeholder="residencial-unifamiliar"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                error={!!form.errors.code}
                                hint={form.errors.code}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-label">Rótulo</Label>
                            <Input
                                id="create-label"
                                type="text"
                                name="label"
                                required
                                placeholder="Residencial unifamiliar"
                                value={form.data.label}
                                onChange={(e) => form.setData('label', e.target.value)}
                                error={!!form.errors.label}
                                hint={form.errors.label}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-aliases">Grafias alternativas (uma por linha)</Label>
                            <textarea
                                id="create-aliases"
                                rows={3}
                                value={aliasesText}
                                onChange={(e) => setAliasesText(e.target.value)}
                                placeholder={'Residencial\nUnifamiliar'}
                                className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                            {form.errors.aliases && (
                                <p className="mt-1.5 text-xs text-error-500">{form.errors.aliases}</p>
                            )}
                        </div>
                        <div>
                            <DrivesRuleField checked={drivesRule} onChange={handleDrivesRuleChange} />
                            {form.errors.drives_rule && (
                                <p className="mt-1.5 text-xs text-error-500">{form.errors.drives_rule}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="create-active">Situação</Label>
                            <Select
                                id="create-active"
                                name="active"
                                value={form.data.active ? '1' : '0'}
                                onChange={(value) => form.setData('active', value === '1')}
                                options={[
                                    { value: '1', label: 'Ativo' },
                                    { value: '0', label: 'Inativo' },
                                ]}
                            />
                        </div>

                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={form.processing}>
                                {form.processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={pendingDrivesRuleTrue}
                onClose={cancelDrivesRule}
                onConfirm={confirmDrivesRule}
                title="Ativar gatilho de análise técnica"
                description="Processos com este tipo de imóvel passam a ir para análise técnica (gatilho dados_do_processo). Confirmar?"
                confirmLabel="Confirmar"
                variant="warning"
            />
        </>
    );
}

function EditPropertyTypeModal({ propertyType, onClose }: { propertyType: PropertyTypeItem; onClose: () => void }) {
    const [aliasesText, setAliasesText] = useState(propertyType.aliases.join('\n'));
    const [drivesRule, setDrivesRule] = useState(propertyType.drives_rule);
    const [pendingDrivesRuleTrue, setPendingDrivesRuleTrue] = useState(false);

    const form = useForm({
        label: propertyType.label,
        drives_rule: propertyType.drives_rule,
        active: propertyType.active,
        aliases: propertyType.aliases,
    });

    function handleDrivesRuleChange(value: boolean) {
        if (value && !drivesRule) {
            setPendingDrivesRuleTrue(true);
        } else {
            setDrivesRule(false);
            form.setData('drives_rule', false);
        }
    }

    function confirmDrivesRule() {
        setDrivesRule(true);
        form.setData('drives_rule', true);
        setPendingDrivesRuleTrue(false);
    }

    function cancelDrivesRule() {
        setPendingDrivesRuleTrue(false);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const parsedAliases = aliasesText
            .split('\n')
            .map((s) => s.trim())
            .filter((s) => s.length > 0);
        form.transform((data) => ({ ...data, aliases: parsedAliases }));
        form.put(`/gestao/tipos-imovel/${propertyType.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <>
            <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
                <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar tipo de imóvel</h4>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{propertyType.code} — o código não pode ser alterado.</p>

                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-code-${propertyType.id}`}>Código</Label>
                            <Input
                                id={`edit-code-${propertyType.id}`}
                                type="text"
                                value={propertyType.code}
                                disabled
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-label-${propertyType.id}`}>Rótulo</Label>
                            <Input
                                id={`edit-label-${propertyType.id}`}
                                type="text"
                                name="label"
                                required
                                value={form.data.label}
                                onChange={(e) => form.setData('label', e.target.value)}
                                error={!!form.errors.label}
                                hint={form.errors.label}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-aliases-${propertyType.id}`}>Grafias alternativas (uma por linha)</Label>
                            <textarea
                                id={`edit-aliases-${propertyType.id}`}
                                rows={3}
                                value={aliasesText}
                                onChange={(e) => setAliasesText(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                            {form.errors.aliases && (
                                <p className="mt-1.5 text-xs text-error-500">{form.errors.aliases}</p>
                            )}
                        </div>
                        <div>
                            <DrivesRuleField checked={drivesRule} onChange={handleDrivesRuleChange} />
                            {form.errors.drives_rule && (
                                <p className="mt-1.5 text-xs text-error-500">{form.errors.drives_rule}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor={`edit-active-${propertyType.id}`}>Situação</Label>
                            <Select
                                id={`edit-active-${propertyType.id}`}
                                name="active"
                                value={form.data.active ? '1' : '0'}
                                onChange={(value) => form.setData('active', value === '1')}
                                options={[
                                    { value: '1', label: 'Ativo' },
                                    { value: '0', label: 'Inativo' },
                                ]}
                            />
                            {form.errors.active && <p className="mt-1.5 text-theme-xs text-error-500">{form.errors.active}</p>}
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={form.processing}>
                                {form.processing ? 'Salvando...' : 'Salvar alterações'}
                            </Button>
                        </div>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={pendingDrivesRuleTrue}
                onClose={cancelDrivesRule}
                onConfirm={confirmDrivesRule}
                title="Ativar gatilho de análise técnica"
                description="Processos com este tipo de imóvel passam a ir para análise técnica (gatilho dados_do_processo). Confirmar?"
                confirmLabel="Confirmar"
                variant="warning"
            />
        </>
    );
}

export default function PropertyTypesIndex({ propertyTypes, filters, perPageOptions }: PropertyTypesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-tipos-imovel');

    const table = useServerTable({
        url: '/gestao/tipos-imovel',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<PropertyTypeItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<PropertyTypeItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<PropertyTypeItem>[] = [
        {
            id: 'code',
            header: 'Código',
            sortable: true,
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (propertyType) => propertyType.code,
        },
        {
            id: 'label',
            header: 'Rótulo',
            sortable: true,
            cell: (propertyType) => propertyType.label,
        },
        {
            id: 'drives_rule',
            header: 'Dirige regra',
            cell: (propertyType) => <DrivesRuleBadge drivesRule={propertyType.drives_rule} />,
        },
        {
            id: 'aliases',
            header: 'Grafias alternativas',
            cell: (propertyType) => <AliasChips aliases={propertyType.aliases} />,
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (propertyType) => <SituationBadge active={propertyType.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (propertyType) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(propertyType)}
                              />
                              <TableAction
                                  tone={propertyType.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={propertyType.active ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(propertyType)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<PropertyTypeItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/tipos-imovel/${pendingToggle.id}/ativacao`,
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
                  title: 'Desativar tipo de imóvel',
                  description: `Confirma a desativação de "${pendingToggle.label}"? Ele deixa de ser reconhecido no enquadramento, mas o histórico dos processos que já o usaram é preservado.`,
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar tipo de imóvel',
                  description: `Confirma a reativação de "${pendingToggle.label}"? Ele volta a ser reconhecido no enquadramento da LOUOS.`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Tipos de imóvel" />
            <PageHeader title="Tipos de imóvel" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Tipos de imóvel"
                    description="Classificam o imóvel no enquadramento da LOUOS e determinam o roteamento dos processos de viabilidade."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Novo tipo de imóvel
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
                                placeholder: 'Buscar por código ou rótulo...',
                                label: 'Buscar tipos de imóvel',
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
                            rows={propertyTypes.data}
                            rowKey={(propertyType) => propertyType.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum tipo de imóvel cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre o primeiro tipo de imóvel para habilitar o enquadramento da LOUOS.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                                Novo tipo de imóvel
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={propertyTypes.links}
                            meta={{ from: propertyTypes.from, to: propertyTypes.to, total: propertyTypes.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {canMaintain && showCreate && <CreatePropertyTypeModal isOpen onClose={() => setShowCreate(false)} />}

            {editing && <EditPropertyTypeModal propertyType={editing} onClose={() => setEditing(null)} />}

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

PropertyTypesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
