import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
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
    };
}

type PendingAction = { type: 'delete' | 'toggle'; cnae: CnaeItem };

const actionButtonStyles =
    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-theme-xs font-medium ring-1 ring-inset transition disabled:cursor-not-allowed disabled:opacity-60';

const brandActionStyles = `${actionButtonStyles} text-brand-500 ring-brand-200 hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10`;

const warningActionStyles = `${actionButtonStyles} text-warning-600 ring-warning-300 hover:bg-warning-50 dark:text-orange-400 dark:ring-warning-500/30 dark:hover:bg-warning-500/10`;

const successActionStyles = `${actionButtonStyles} text-success-600 ring-success-300 hover:bg-success-50 dark:text-success-400 dark:ring-success-500/30 dark:hover:bg-success-500/10`;

const errorActionStyles = `${actionButtonStyles} text-error-600 ring-error-300 hover:bg-error-50 dark:text-error-400 dark:ring-error-500/30 dark:hover:bg-error-500/10`;

const headerCellStyles = 'px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400';

function PageBreadcrumb({ pageTitle }: { pageTitle: string }) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{pageTitle}</h2>
            <nav aria-label="Trilha de navegação">
                <ol className="flex flex-wrap items-center gap-1.5">
                    <li>
                        <Link
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="/gestao"
                        >
                            Painel
                            <svg
                                className="stroke-current"
                                width="17"
                                height="16"
                                viewBox="0 0 17 16"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                    strokeWidth="1.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </Link>
                    </li>
                    <li className="text-sm text-gray-800 dark:text-white/90">{pageTitle}</li>
                </ol>
            </nav>
        </div>
    );
}

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

function CnaeActions({
    cnae,
    onEdit,
    onToggle,
    onDelete,
}: {
    cnae: CnaeItem;
    onEdit: () => void;
    onToggle: () => void;
    onDelete: () => void;
}) {
    return (
        <div className="flex flex-wrap justify-end gap-2">
            <button type="button" onClick={onEdit} className={brandActionStyles}>
                Editar
            </button>
            <button
                type="button"
                onClick={onToggle}
                className={cnae.active ? warningActionStyles : successActionStyles}
            >
                {cnae.active ? 'Desativar' : 'Reativar'}
            </button>
            <button type="button" onClick={onDelete} className={errorActionStyles}>
                Excluir
            </button>
        </div>
    );
}

export default function CnaesIndex({ cnaes, filters }: CnaesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-cnaes');

    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);
    const [showCreate, setShowCreate] = useState(false);
    const [editingCnae, setEditingCnae] = useState<CnaeItem | null>(null);
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timeout = setTimeout(() => {
            router.get('/gestao/cnaes', { search }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timeout);
    }, [search]);

    const searching = search.trim() !== '';

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
        <GestaoLayout>
            <Head title="CNAEs" />
            <PageBreadcrumb pageTitle="CNAEs" />

            <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div className="flex flex-wrap items-start justify-between gap-3 px-6 py-5">
                    <div>
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">CNAEs cadastrados</h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA)
                        </p>
                    </div>
                    {canMaintain && (
                        <Button size="sm" onClick={() => setShowCreate(true)}>
                            Cadastrar CNAE
                        </Button>
                    )}
                </div>
                <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="search" className="sr-only">
                                Buscar CNAEs
                            </label>
                            <div className="w-full sm:max-w-sm">
                                <Input
                                    id="search"
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Buscar por código ou denominação..."
                                />
                            </div>
                        </div>

                        {cnaes.data.length === 0 ? (
                            <EmptyState
                                title={searching ? 'Nenhum resultado para a busca' : 'Nenhum CNAE cadastrado'}
                                description={
                                    searching
                                        ? 'Ajuste o termo de busca e tente novamente.'
                                        : 'Cadastre o primeiro CNAE para montar a base de atividades.'
                                }
                                action={
                                    !searching && canMaintain ? (
                                        <Button size="sm" onClick={() => setShowCreate(true)}>
                                            Cadastrar CNAE
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                                <div className="max-w-full overflow-x-auto">
                                    <Table>
                                        <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                                            <TableRow>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Código
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Denominação
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Classe
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Situação
                                                </TableCell>
                                                {canMaintain && (
                                                    <TableCell
                                                        isHeader
                                                        className={`${headerCellStyles} text-end`}
                                                    >
                                                        Ações
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                            {cnaes.data.map((cnae) => (
                                                <TableRow
                                                    key={cnae.id}
                                                    className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                                >
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                                                        {cnae.formatted_code}
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                        {cnae.description}
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                        {cnae.class_code}
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                        <SituationBadge active={cnae.active} />
                                                    </TableCell>
                                                    {canMaintain && (
                                                        <TableCell className="px-5 py-4 text-end text-theme-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                            <CnaeActions
                                                                cnae={cnae}
                                                                onEdit={() => setEditingCnae(cnae)}
                                                                onToggle={() =>
                                                                    setPendingAction({ type: 'toggle', cnae })
                                                                }
                                                                onDelete={() =>
                                                                    setPendingAction({ type: 'delete', cnae })
                                                                }
                                                            />
                                                        </TableCell>
                                                    )}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        )}

                        <Pagination
                            links={cnaes.links}
                            meta={{ from: cnaes.from, to: cnaes.to, total: cnaes.total }}
                        />
                    </div>
                </div>
            </div>

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
        </GestaoLayout>
    );
}
