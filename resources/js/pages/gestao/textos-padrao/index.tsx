import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { InfoIcon, PencilIcon, PowerIcon } from '@/components/icons';
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

interface StandardTextItem {
    id: number;
    category: string;
    content: string;
    active: boolean;
    version: number;
    updated_at: string | null;
}

interface StandardTextsIndexProps {
    standardTexts: {
        data: StandardTextItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        category: string;
        sort: string;
        direction: SortDirection;
        per_page: number;
        active: string;
    };
    perPageOptions: number[];
}

const CONTENT_STYLES =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

function CreateStandardTextModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const [active, setActive] = useState('1');

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo texto-padrão</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Trecho pré-aprovado que o analista insere no parecer. Nasce na versão 1.
            </p>

            <Form action="/gestao/textos-padrao" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="create-text-category">Categoria</Label>
                            <Input
                                id="create-text-category"
                                type="text"
                                name="category"
                                required
                                placeholder="Ex.: deferimento, condicionante, indeferimento"
                                error={!!errors.category}
                                hint={errors.category}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-text-content">Conteúdo</Label>
                            <textarea
                                id="create-text-content"
                                name="content"
                                rows={6}
                                required
                                placeholder="Texto do trecho pré-aprovado…"
                                className={CONTENT_STYLES}
                            />
                            {errors.content && <p className="mt-1.5 text-theme-xs text-error-500">{errors.content}</p>}
                        </div>
                        <div>
                            <Label htmlFor="create-text-active">Situação</Label>
                            <Select
                                id="create-text-active"
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

function EditStandardTextModal({ text, onClose }: { text: StandardTextItem; onClose: () => void }) {
    const [active, setActive] = useState(text.active ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar texto-padrão</h4>

            <div className="mt-4 flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                <InfoIcon className="size-5 shrink-0 fill-current text-blue-light-500" />
                <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                    Alterar o <strong>conteúdo</strong> publica uma nova versão (atual: v{text.version}) e preserva o
                    histórico. Editar só a categoria ou a situação mantém a versão vigente (RN-005).
                </p>
            </div>

            <Form action={`/gestao/textos-padrao/${text.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-text-category-${text.id}`}>Categoria</Label>
                            <Input
                                id={`edit-text-category-${text.id}`}
                                type="text"
                                name="category"
                                defaultValue={text.category}
                                required
                                error={!!errors.category}
                                hint={errors.category}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-text-content-${text.id}`}>Conteúdo</Label>
                            <textarea
                                id={`edit-text-content-${text.id}`}
                                name="content"
                                rows={6}
                                defaultValue={text.content}
                                required
                                className={CONTENT_STYLES}
                            />
                            {errors.content && <p className="mt-1.5 text-theme-xs text-error-500">{errors.content}</p>}
                        </div>
                        <div>
                            <Label htmlFor={`edit-text-active-${text.id}`}>Situação</Label>
                            <Select
                                id={`edit-text-active-${text.id}`}
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

export default function StandardTextsIndex({ standardTexts, filters, perPageOptions }: StandardTextsIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-parametros');

    const table = useServerTable({
        url: '/gestao/textos-padrao',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { category: filters.category, active: filters.active },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<StandardTextItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<StandardTextItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.category !== '' || table.filters.active !== '';

    const categorias = useMemo(
        () => Array.from(new Set(standardTexts.data.map((item) => item.category))).sort(),
        [standardTexts.data],
    );

    const columns: ColumnDef<StandardTextItem>[] = [
        {
            id: 'category',
            header: 'Categoria',
            sortable: true,
            cellClassName: 'whitespace-nowrap',
            cell: (text) => (
                <Badge color="light" size="sm">
                    {text.category}
                </Badge>
            ),
        },
        {
            id: 'content',
            header: 'Conteúdo',
            cell: (text) => (
                <span className="line-clamp-2 max-w-md text-gray-700 dark:text-gray-300" title={text.content}>
                    {text.content}
                </span>
            ),
        },
        {
            id: 'version',
            header: 'Versão',
            sortable: true,
            align: 'center',
            cell: (text) => (
                <Badge color="info" size="sm">
                    v{text.version}
                </Badge>
            ),
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (text) => <SituationBadge active={text.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (text) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(text)}
                              />
                              <TableAction
                                  tone={text.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={text.active ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(text)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<StandardTextItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/textos-padrao/${pendingToggle.id}/ativacao`,
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
                  title: 'Desativar texto-padrão',
                  description:
                      'Confirma a desativação? O trecho sai da biblioteca disponível ao parecer, mas o histórico e a versão são preservados.',
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar texto-padrão',
                  description: 'Confirma a reativação? O trecho volta a ficar disponível para inserção no parecer.',
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Textos-padrão" />
            <PageHeader title="Textos-padrão" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Biblioteca de textos-padrão"
                    description="Trechos pré-aprovados e versionados para o parecer da análise (HU-085)."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Novo texto-padrão
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
                                placeholder: 'Buscar no conteúdo...',
                                label: 'Buscar textos-padrão',
                            }}
                            filters={
                                <div className="flex flex-wrap gap-3">
                                    {categorias.length > 0 && (
                                        <div className="w-48">
                                            <label htmlFor="filter-category" className="sr-only">
                                                Filtrar por categoria
                                            </label>
                                            <Select
                                                id="filter-category"
                                                value={table.filters.category}
                                                onChange={(value) => table.setFilter('category', value)}
                                                placeholder="Categoria"
                                                options={categorias.map((cat) => ({ value: cat, label: cat }))}
                                            />
                                        </div>
                                    )}
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

                        {filtering && (
                            <div className="flex flex-wrap items-center gap-2">
                                {table.filters.category !== '' && (
                                    <Badge size="sm" color="light">
                                        Categoria: {table.filters.category}
                                    </Badge>
                                )}
                                {table.filters.active !== '' && (
                                    <Badge size="sm" color="light">
                                        Situação: {table.filters.active === '1' ? 'Ativos' : 'Inativos'}
                                    </Badge>
                                )}
                                <button
                                    type="button"
                                    onClick={() => {
                                        table.setFilter('category', '');
                                        table.setFilter('active', '');
                                    }}
                                    className="text-theme-xs font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                >
                                    Limpar filtros
                                </button>
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={standardTexts.data}
                            rowKey={(text) => text.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum texto-padrão cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre o primeiro trecho para acelerar a redação dos pareceres.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                                Novo texto-padrão
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={standardTexts.links}
                            meta={{ from: standardTexts.from, to: standardTexts.to, total: standardTexts.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {canMaintain && <CreateStandardTextModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <EditStandardTextModal text={editing} onClose={() => setEditing(null)} />}

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

StandardTextsIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
