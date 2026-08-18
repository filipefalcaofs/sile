import { Form, Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { PencilIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface RoleItem {
    id: number;
    name: string;
    permissions: string[];
    users_count: number;
    structural: boolean;
}

interface PermissionOption {
    name: string;
    label: string;
    description: string;
    group: string;
}

interface RolesIndexProps {
    roles: RoleItem[];
    permissions: string[];
    permissionCatalog: PermissionOption[];
}

function groupPermissionCatalog(catalog: PermissionOption[]): { label: string; items: PermissionOption[] }[] {
    const groups = new Map<string, PermissionOption[]>();

    for (const permission of catalog) {
        const items = groups.get(permission.group) ?? [];
        items.push(permission);
        groups.set(permission.group, items);
    }

    return Array.from(groups.entries()).map(([label, items]) => ({ label, items }));
}

function permissionLabel(catalog: PermissionOption[], name: string): string {
    return catalog.find((item) => item.name === name)?.label ?? name;
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-theme-xs text-error-500">{message}</p>;
}

function PermissionChips({ permissions, catalog }: { permissions: string[]; catalog: PermissionOption[] }) {
    if (permissions.length === 0) {
        return <span className="text-theme-xs text-gray-400 dark:text-gray-500">Sem permissões</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {permissions.map((permission) => (
                <Badge key={permission} size="sm">
                    {permissionLabel(catalog, permission)}
                </Badge>
            ))}
        </div>
    );
}

function PermissionsGrid({
    catalog,
    idPrefix,
    defaultChecked = [],
    lockedPermission,
}: {
    catalog: PermissionOption[];
    idPrefix: string;
    defaultChecked?: string[];
    lockedPermission?: string;
}) {
    const groups = groupPermissionCatalog(catalog);

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            {groups.map((group) => (
                <fieldset key={group.label}>
                    <legend className="text-theme-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {group.label}
                    </legend>
                    <div className="mt-3 flex flex-col gap-3">
                        {group.items.map((permission) => {
                            const locked = permission.name === lockedPermission;

                            return (
                                <div key={permission.name}>
                                    <label
                                        htmlFor={`${idPrefix}-${permission.name}`}
                                        className={`flex items-start gap-3 text-theme-sm ${
                                            locked
                                                ? 'cursor-not-allowed text-gray-400 dark:text-gray-500'
                                                : 'cursor-pointer text-gray-700 dark:text-gray-300'
                                        }`}
                                    >
                                        <span className="relative mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
                                            <input
                                                id={`${idPrefix}-${permission.name}`}
                                                type="checkbox"
                                                name="permissions[]"
                                                value={permission.name}
                                                defaultChecked={locked || defaultChecked.includes(permission.name)}
                                                disabled={locked}
                                                className="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-gray-300 checked:border-transparent checked:bg-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700"
                                            />
                                            <svg
                                                className="pointer-events-none absolute hidden text-white peer-checked:block peer-disabled:text-gray-200"
                                                width="14"
                                                height="14"
                                                viewBox="0 0 14 14"
                                                fill="none"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    d="M11.6666 3.5L5.24992 9.91667L2.33325 7"
                                                    stroke="currentColor"
                                                    strokeWidth="1.94437"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                />
                                            </svg>
                                        </span>
                                        <span>
                                            <span className="block font-medium">{permission.label}</span>
                                            <span className="mt-0.5 block text-theme-xs font-normal text-gray-500 dark:text-gray-400">
                                                {permission.description}
                                            </span>
                                        </span>
                                    </label>
                                    {locked && (
                                        <>
                                            <input type="hidden" name="permissions[]" value={permission.name} />
                                            <p className="ml-8 mt-1 text-theme-xs text-gray-400 dark:text-gray-500">
                                                Permissão obrigatória do administrador.
                                            </p>
                                        </>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </fieldset>
            ))}
        </div>
    );
}

function CreateRoleModal({
    isOpen,
    onClose,
    catalog,
}: {
    isOpen: boolean;
    onClose: () => void;
    catalog: PermissionOption[];
}) {
    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo perfil</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O novo perfil passa a valer imediatamente para os usuários vinculados a ele.
            </p>

            <Form action="/gestao/perfis" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="create-name">Nome do perfil</Label>
                            <div className="w-full sm:max-w-sm">
                                <Input
                                    id="create-name"
                                    type="text"
                                    name="name"
                                    required
                                    error={!!errors.name}
                                    hint={errors.name}
                                />
                            </div>
                        </div>

                        <PermissionsGrid catalog={catalog} idPrefix="create" />
                        <FieldError message={errors.permissions} />

                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Criando...' : 'Criar perfil'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

function EditRoleModal({
    role,
    catalog,
    onClose,
}: {
    role: RoleItem;
    catalog: PermissionOption[];
    onClose: () => void;
}) {
    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar perfil</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                As alterações valem imediatamente para os usuários vinculados a {role.name}.
            </p>

            <Form action={`/gestao/perfis/${role.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`name-${role.id}`}>Nome do perfil</Label>
                            <div className="w-full sm:max-w-sm">
                                <Input
                                    id={`name-${role.id}`}
                                    type="text"
                                    name="name"
                                    defaultValue={role.name}
                                    readOnly={role.structural}
                                    className={role.structural ? 'opacity-60' : ''}
                                    error={!!errors.name}
                                    hint={errors.name}
                                />
                            </div>
                            {role.structural && (
                                <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                                    Papéis estruturais não podem ser renomeados.
                                </p>
                            )}
                        </div>

                        <PermissionsGrid
                            catalog={catalog}
                            idPrefix={`edit-${role.id}`}
                            defaultChecked={role.permissions}
                            lockedPermission={role.name === 'administrador' ? 'acessar-gestao' : undefined}
                        />
                        <FieldError message={errors.permissions} />

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

export default function RolesIndex({ roles, permissionCatalog }: RolesIndexProps) {
    const [showCreate, setShowCreate] = useState(false);
    const [editingRole, setEditingRole] = useState<RoleItem | null>(null);
    const [deletingRole, setDeletingRole] = useState<RoleItem | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    function deleteRole() {
        if (!deletingRole) {
            return;
        }

        router.delete(`/gestao/perfis/${deletingRole.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => setDeleteProcessing(false),
            onSuccess: () => setDeletingRole(null),
            onError: (errors) => setDeleteError(errors.role ?? 'Não foi possível excluir o perfil.'),
        });
    }

    function closeDeleteDialog() {
        setDeletingRole(null);
        setDeleteError(null);
    }

    const columns: ColumnDef<RoleItem>[] = [
        {
            id: 'name',
            header: 'Perfil',
            cellClassName: 'whitespace-nowrap',
            cell: (role) => (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium text-gray-800 dark:text-white/90">{role.name}</span>
                    {role.structural && (
                        <Badge size="sm" color="light">
                            Estrutural
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            id: 'permissions',
            header: 'Permissões',
            cell: (role) => <PermissionChips permissions={role.permissions} catalog={permissionCatalog} />,
        },
        {
            id: 'users_count',
            header: 'Usuários',
            cellClassName: 'whitespace-nowrap',
            cell: (role) => (role.users_count === 1 ? '1 usuário' : `${role.users_count} usuários`),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (role) => (
                <div className="flex justify-end gap-2">
                    <TableAction
                        tone="brand"
                        icon={<PencilIcon className="size-4.5" />}
                        label="Editar"
                        onClick={() => setEditingRole(role)}
                    />
                    {!role.structural && (
                        <TableAction
                            tone="error"
                            icon={<TrashIcon className="size-4.5" />}
                            label="Excluir"
                            onClick={() => setDeletingRole(role)}
                            disabled={role.users_count > 0}
                            title={role.users_count > 0 ? 'Há usuários vinculados a este perfil.' : undefined}
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Perfis e permissões" />
            <PageHeader title="Perfis e permissões" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Perfis cadastrados"
                    description="Segregação de funções: perfis com permissões granulares por funcionalidade"
                    actions={
                        <Button size="sm" onClick={() => setShowCreate(true)}>
                            Novo perfil
                        </Button>
                    }
                />
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={roles}
                        rowKey={(role) => role.id}
                        density="compact"
                        emptyState={
                            <EmptyState
                                title="Nenhum perfil cadastrado"
                                description="Crie o primeiro perfil para organizar as permissões por função."
                                action={
                                    <Button size="sm" onClick={() => setShowCreate(true)}>
                                        Novo perfil
                                    </Button>
                                }
                            />
                        }
                    />
                </CardContent>
            </Card>

            <CreateRoleModal isOpen={showCreate} onClose={() => setShowCreate(false)} catalog={permissionCatalog} />

            {editingRole && (
                <EditRoleModal role={editingRole} catalog={permissionCatalog} onClose={() => setEditingRole(null)} />
            )}

            {deletingRole && (
                <ConfirmDialog
                    isOpen
                    onClose={closeDeleteDialog}
                    onConfirm={deleteRole}
                    title="Excluir perfil"
                    description={
                        <>
                            <p>
                                Confirma a exclusão do perfil {deletingRole.name}? Esta ação não pode ser desfeita.
                            </p>
                            {deleteError && <p className="mt-2 text-error-500">{deleteError}</p>}
                        </>
                    }
                    confirmLabel="Excluir"
                    variant="danger"
                    processing={deleteProcessing}
                />
            )}
        </>
    );
}

RolesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
