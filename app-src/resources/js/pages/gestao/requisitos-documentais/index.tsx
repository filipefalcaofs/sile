import { Form, Head, router, useHttp, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { PencilIcon, PowerIcon, SearchIcon, TagIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
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
import { Skeleton } from '@/components/ui/skeleton';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface CnaeLink {
    id: number;
    formatted_code: string;
    description: string;
}

interface RequirementItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    required: boolean;
    active: boolean;
    validation_instructions: string | null;
    cnaes: CnaeLink[];
}

interface RequisitosIndexProps {
    requirements: {
        data: RequirementItem[];
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

const REQUIRED_OPTIONS = [
    { value: '1', label: 'Obrigatório' },
    { value: '0', label: 'Opcional' },
];

const textareaClassName =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

function RequiredBadge({ required }: { required: boolean }) {
    return (
        <Badge size="sm" color={required ? 'primary' : 'light'}>
            {required ? 'Obrigatório' : 'Opcional'}
        </Badge>
    );
}

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

const SEARCH_DEBOUNCE_MS = 350;
const MIN_SEARCH_LENGTH = 2;

/**
 * Busca incremental de CNAEs ativos para vincular ao requisito, reusando o
 * CnaeSearchController via GET /gestao/requisitos-documentais/cnaes-disponiveis
 * (só ativos, máx. 20). Local a esta tela do console.
 */
function CnaePicker({ onSelect, excludeIds }: { onSelect: (cnae: CnaeLink) => void; excludeIds: number[] }) {
    const inputId = useId();
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<CnaeLink[]>([]);
    const [searched, setSearched] = useState(false);
    const [failed, setFailed] = useState(false);

    const http = useHttp<Record<string, never>, CnaeLink[]>({});
    const httpRef = useRef(http);
    httpRef.current = http;

    const requestSeq = useRef(0);

    useEffect(() => {
        const trimmed = term.trim();

        if (trimmed.length < MIN_SEARCH_LENGTH) {
            requestSeq.current += 1;
            setResults([]);
            setSearched(false);
            setFailed(false);

            return;
        }

        const timeout = setTimeout(() => {
            const seq = ++requestSeq.current;

            void httpRef.current.get(`/gestao/requisitos-documentais/cnaes-disponiveis?search=${encodeURIComponent(trimmed)}`, {
                onSuccess: (response) => {
                    if (seq !== requestSeq.current) {
                        return;
                    }

                    setResults(Array.isArray(response) ? response : []);
                    setSearched(true);
                    setFailed(false);
                },
                onError: () => {
                    if (seq !== requestSeq.current) {
                        return;
                    }

                    setResults([]);
                    setSearched(false);
                    setFailed(true);
                },
            });
        }, SEARCH_DEBOUNCE_MS);

        return () => clearTimeout(timeout);
    }, [term]);

    function select(cnae: CnaeLink) {
        onSelect(cnae);
        requestSeq.current += 1;
        setTerm('');
        setResults([]);
        setSearched(false);
        setFailed(false);
    }

    const visibleResults = results.filter((cnae) => !excludeIds.includes(cnae.id));
    const searching = http.processing;

    return (
        <div className="flex flex-col gap-3">
            <div className="relative">
                <label htmlFor={inputId} className="sr-only">
                    Buscar CNAE
                </label>
                <span className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                    <SearchIcon className="size-5" />
                </span>
                <input
                    id={inputId}
                    type="search"
                    value={term}
                    onChange={(event) => setTerm(event.target.value)}
                    placeholder="Buscar CNAE por código ou descrição..."
                    autoComplete="off"
                    className="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
            </div>

            {failed && (
                <Alert
                    variant="error"
                    title="Busca de CNAEs indisponível"
                    message="Não foi possível consultar a tabela oficial de CNAEs. Tente novamente."
                />
            )}

            {searching && (
                <div className="flex flex-col gap-2" aria-label="Buscando CNAEs">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-2/3" />
                </div>
            )}

            {!searching && searched && visibleResults.length === 0 && (
                <p className="text-sm text-gray-500 dark:text-gray-400">Nenhum CNAE ativo encontrado.</p>
            )}

            {!searching && visibleResults.length > 0 && (
                <ul className="max-h-56 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 dark:divide-white/[0.05] dark:border-gray-800">
                    {visibleResults.map((cnae) => (
                        <li key={cnae.id}>
                            <button
                                type="button"
                                onClick={() => select(cnae)}
                                className="flex w-full flex-col items-start gap-0.5 px-4 py-3 text-start transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-hidden dark:hover:bg-white/[0.03] dark:focus:bg-white/[0.03]"
                            >
                                <span className="text-sm font-medium text-gray-800 dark:text-white/90">{cnae.formatted_code}</span>
                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">{cnae.description}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function CreateRequirementModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const [required, setRequired] = useState('1');

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo requisito documental</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O código identifica o requisito e não pode ser alterado depois. A obrigatoriedade por CNAE é definida pelo vínculo.
            </p>

            <Form action="/gestao/requisitos-documentais" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="create-code">Código</Label>
                                <Input
                                    id="create-code"
                                    type="text"
                                    name="code"
                                    required
                                    placeholder="CONTRATO_SOCIAL"
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
                                    placeholder="Contrato social"
                                    error={!!errors.name}
                                    hint={errors.name}
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="create-required">Obrigatoriedade base</Label>
                            <Select
                                id="create-required"
                                name="required"
                                value={required}
                                onChange={setRequired}
                                options={REQUIRED_OPTIONS}
                            />
                        </div>

                        <div>
                            <Label htmlFor="create-description">Descrição</Label>
                            <textarea
                                id="create-description"
                                name="description"
                                rows={2}
                                className={textareaClassName}
                                placeholder="Quando e por que este documento é exigido."
                            />
                            {errors.description && <p className="mt-1.5 text-theme-xs text-error-500">{errors.description}</p>}
                        </div>

                        <div>
                            <Label htmlFor="create-validation">Instruções de validação (IA — EP14)</Label>
                            <textarea
                                id="create-validation"
                                name="validation_instructions"
                                rows={3}
                                className={textareaClassName}
                                placeholder="O que conferir no documento (gancho da validação por IA, inerte por ora)."
                            />
                            {errors.validation_instructions && (
                                <p className="mt-1.5 text-theme-xs text-error-500">{errors.validation_instructions}</p>
                            )}
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

function EditRequirementModal({ requirement, onClose }: { requirement: RequirementItem; onClose: () => void }) {
    const [required, setRequired] = useState(requirement.required ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar requisito documental</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {requirement.code} — o código não pode ser alterado.
            </p>

            <Form action={`/gestao/requisitos-documentais/${requirement.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-name-${requirement.id}`}>Nome</Label>
                            <Input
                                id={`edit-name-${requirement.id}`}
                                type="text"
                                name="name"
                                defaultValue={requirement.name}
                                required
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>

                        <div>
                            <Label htmlFor={`edit-required-${requirement.id}`}>Obrigatoriedade base</Label>
                            <Select
                                id={`edit-required-${requirement.id}`}
                                name="required"
                                value={required}
                                onChange={setRequired}
                                options={REQUIRED_OPTIONS}
                            />
                        </div>

                        <div>
                            <Label htmlFor={`edit-description-${requirement.id}`}>Descrição</Label>
                            <textarea
                                id={`edit-description-${requirement.id}`}
                                name="description"
                                rows={2}
                                defaultValue={requirement.description ?? ''}
                                className={textareaClassName}
                            />
                            {errors.description && <p className="mt-1.5 text-theme-xs text-error-500">{errors.description}</p>}
                        </div>

                        <div>
                            <Label htmlFor={`edit-validation-${requirement.id}`}>Instruções de validação (IA — EP14)</Label>
                            <textarea
                                id={`edit-validation-${requirement.id}`}
                                name="validation_instructions"
                                rows={3}
                                defaultValue={requirement.validation_instructions ?? ''}
                                className={textareaClassName}
                            />
                            {errors.validation_instructions && (
                                <p className="mt-1.5 text-theme-xs text-error-500">{errors.validation_instructions}</p>
                            )}
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

function ManageCnaesModal({ requirement, onClose }: { requirement: RequirementItem; onClose: () => void }) {
    const [selected, setSelected] = useState<CnaeLink[]>(requirement.cnaes);
    const [processing, setProcessing] = useState(false);

    function add(cnae: CnaeLink) {
        setSelected((current) => (current.some((item) => item.id === cnae.id) ? current : [...current, cnae]));
    }

    function remove(id: number) {
        setSelected((current) => current.filter((item) => item.id !== id));
    }

    function save() {
        router.put(
            `/gestao/requisitos-documentais/${requirement.id}/cnaes`,
            { cnae_ids: selected.map((cnae) => cnae.id) },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
            },
        );
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">CNAEs que exigem este requisito</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {requirement.code} — {requirement.name}. A lista oficial por CNAE ainda será carregada pela SEDUR.
            </p>

            <div className="mt-6 flex flex-col gap-5">
                <CnaePicker onSelect={add} excludeIds={selected.map((cnae) => cnae.id)} />

                <div>
                    <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        Vinculados ({selected.length})
                    </span>
                    {selected.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Nenhum CNAE vinculado. Sem vínculo, o requisito não é exigido por atividade.
                        </p>
                    ) : (
                        <ul className="mt-2 flex flex-wrap gap-2">
                            {selected.map((cnae) => (
                                <li
                                    key={cnae.id}
                                    className="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 py-1 pr-1 pl-3 text-theme-xs text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                                >
                                    <span className="font-medium">{cnae.formatted_code}</span>
                                    <button
                                        type="button"
                                        onClick={() => remove(cnae.id)}
                                        aria-label={`Remover CNAE ${cnae.formatted_code}`}
                                        className="flex size-5 items-center justify-center rounded-full text-gray-400 transition hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/15"
                                    >
                                        &times;
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" onClick={save} disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar vínculos'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

export default function RequisitosDocumentaisIndex({ requirements, filters, perPageOptions }: RequisitosIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-requisitos-documentais');

    const table = useServerTable({
        url: '/gestao/requisitos-documentais',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<RequirementItem | null>(null);
    const [managing, setManaging] = useState<RequirementItem | null>(null);
    const [toggling, setToggling] = useState<RequirementItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<RequirementItem>[] = [
        {
            id: 'code',
            header: 'Código',
            sortable: true,
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (item) => item.code,
        },
        {
            id: 'name',
            header: 'Nome',
            sortable: true,
            cell: (item) => item.name,
        },
        {
            id: 'required',
            header: 'Obrigatoriedade',
            cell: (item) => <RequiredBadge required={item.required} />,
        },
        {
            id: 'cnaes',
            header: 'CNAEs',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => `${item.cnaes.length}`,
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (item) => <SituationBadge active={item.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (item) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(item)}
                              />
                              <TableAction
                                  tone="neutral"
                                  icon={<TagIcon className="size-4.5" />}
                                  label="Gerenciar CNAEs"
                                  onClick={() => setManaging(item)}
                              />
                              <TableAction
                                  tone={item.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={item.active ? 'Desativar' : 'Reativar'}
                                  onClick={() => setToggling(item)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<RequirementItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!toggling) {
            return;
        }

        router.put(
            `/gestao/requisitos-documentais/${toggling.id}/toggle`,
            {},
            {
                preserveScroll: true,
                onStart: () => setActionProcessing(true),
                onFinish: () => setActionProcessing(false),
                onSuccess: () => setToggling(null),
            },
        );
    }

    return (
        <>
            <Head title="Requisitos documentais" />
            <PageHeader title="Requisitos documentais" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Requisitos documentais"
                    description="Documentos exigidos por atividade (modelo Requisito) — a obrigatoriedade por CNAE é administrável"
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Novo requisito
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
                                label: 'Buscar requisitos',
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
                                <PerPageSelect value={table.perPage} options={perPageOptions} onChange={table.setPerPage} />
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
                            rows={requirements.data}
                            rowKey={(item) => item.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum requisito cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre os requisitos documentais e vincule-os aos CNAEs para a validação da solicitação.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                                Novo requisito
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={requirements.links}
                            meta={{ from: requirements.from, to: requirements.to, total: requirements.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {canMaintain && <CreateRequirementModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <EditRequirementModal requirement={editing} onClose={() => setEditing(null)} />}

            {managing && <ManageCnaesModal requirement={managing} onClose={() => setManaging(null)} />}

            {toggling && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setToggling(null)}
                    onConfirm={executeToggle}
                    title={toggling.active ? 'Desativar requisito' : 'Reativar requisito'}
                    description={
                        toggling.active
                            ? `Confirma a desativação do requisito ${toggling.code} — ${toggling.name}? O registro e o histórico são preservados.`
                            : `Confirma a reativação do requisito ${toggling.code} — ${toggling.name}?`
                    }
                    confirmLabel={toggling.active ? 'Desativar' : 'Reativar'}
                    variant={toggling.active ? 'warning' : 'info'}
                    processing={actionProcessing}
                />
            )}
        </>
    );
}

RequisitosDocumentaisIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
