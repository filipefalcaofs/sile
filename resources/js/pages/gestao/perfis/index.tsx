import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import GestaoLayout from '@/layouts/gestao-layout';

interface RoleItem {
    id: number;
    name: string;
    permissions: string[];
    users_count: number;
    structural: boolean;
}

interface RolesIndexProps {
    roles: RoleItem[];
    permissions: string[];
}

const GROUP_LABELS: Record<string, string> = {
    manter: 'Manter',
    consultar: 'Consultar',
    acessar: 'Acessar',
    gerenciar: 'Gerenciar',
};

const actionButtonStyles =
    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-theme-xs font-medium ring-1 ring-inset transition disabled:cursor-not-allowed disabled:opacity-60';

const brandActionStyles = `${actionButtonStyles} text-brand-500 ring-brand-200 hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10`;

const errorActionStyles = `${actionButtonStyles} text-error-600 ring-error-300 hover:bg-error-50 dark:text-error-400 dark:ring-error-500/30 dark:hover:bg-error-500/10`;

function groupPermissions(permissions: string[]): { label: string; items: string[] }[] {
    const groups = new Map<string, string[]>();

    for (const permission of permissions) {
        const prefix = permission.split('-')[0];
        const items = groups.get(prefix) ?? [];
        items.push(permission);
        groups.set(prefix, items);
    }

    return Array.from(groups.entries()).map(([prefix, items]) => ({
        label: GROUP_LABELS[prefix] ?? prefix.charAt(0).toUpperCase() + prefix.slice(1),
        items,
    }));
}

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

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-theme-xs text-error-500">{message}</p>;
}

function PermissionChips({ permissions }: { permissions: string[] }) {
    if (permissions.length === 0) {
        return <span className="text-theme-xs text-gray-400 dark:text-gray-500">Sem permissões</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {permissions.map((permission) => (
                <Badge key={permission} size="sm">
                    {permission}
                </Badge>
            ))}
        </div>
    );
}

function PermissionsGrid({
    permissions,
    idPrefix,
    defaultChecked = [],
    lockedPermission,
}: {
    permissions: string[];
    idPrefix: string;
    defaultChecked?: string[];
    lockedPermission?: string;
}) {
    const groups = groupPermissions(permissions);

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            {groups.map((group) => (
                <fieldset key={group.label}>
                    <legend className="text-theme-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {group.label}
                    </legend>
                    <div className="mt-3 flex flex-col gap-2">
                        {group.items.map((permission) => {
                            const locked = permission === lockedPermission;

                            return (
                                <div key={permission}>
                                    <label
                                        htmlFor={`${idPrefix}-${permission}`}
                                        className={`flex items-center gap-3 text-theme-sm ${
                                            locked
                                                ? 'cursor-not-allowed text-gray-400 dark:text-gray-500'
                                                : 'cursor-pointer text-gray-700 dark:text-gray-300'
                                        }`}
                                    >
                                        <span className="relative flex h-5 w-5 shrink-0 items-center justify-center">
                                            <input
                                                id={`${idPrefix}-${permission}`}
                                                type="checkbox"
                                                name="permissions[]"
                                                value={permission}
                                                defaultChecked={locked || defaultChecked.includes(permission)}
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
                                        <span>{permission}</span>
                                    </label>
                                    {locked && (
                                        <>
                                            {/* Checkbox desabilitada não envia valor — o hidden garante o envio. A regra real está no backend. */}
                                            <input type="hidden" name="permissions[]" value={permission} />
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

function EditRoleForm({ role, permissions }: { role: RoleItem; permissions: string[] }) {
    return (
        <Form action={`/gestao/perfis/${role.id}`} method="put">
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
                        permissions={permissions}
                        idPrefix={`edit-${role.id}`}
                        defaultChecked={role.permissions}
                        lockedPermission={role.name === 'administrador' ? 'acessar-gestao' : undefined}
                    />
                    <FieldError message={errors.permissions} />

                    <div>
                        <Button size="sm" type="submit" disabled={processing}>
                            {processing ? 'Salvando...' : 'Salvar alterações'}
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}

function DeleteRoleForm({ role }: { role: RoleItem }) {
    const blocked = role.users_count > 0;

    return (
        <Form action={`/gestao/perfis/${role.id}`} method="delete" className="inline">
            {({ errors, processing }) => (
                <div className="text-right">
                    <button
                        type="submit"
                        disabled={processing || blocked}
                        onClick={(event) => {
                            if (!window.confirm('Excluir este perfil?')) {
                                event.preventDefault();
                            }
                        }}
                        className={errorActionStyles}
                    >
                        Excluir
                    </button>
                    {blocked && (
                        <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                            Há usuários vinculados a este perfil.
                        </p>
                    )}
                    <FieldError message={errors.role} />
                </div>
            )}
        </Form>
    );
}

function RoleCard({ role, permissions }: { role: RoleItem; permissions: string[] }) {
    const [editing, setEditing] = useState(false);

    return (
        <article className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex flex-wrap items-start justify-between gap-3 px-6 py-5">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">{role.name}</h3>
                        {role.structural && (
                            <Badge size="sm" color="light">
                                Estrutural
                            </Badge>
                        )}
                    </div>
                    <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                        {role.users_count === 1 ? '1 usuário' : `${role.users_count} usuários`}
                    </p>
                </div>
                <div className="flex items-start gap-2">
                    <button
                        type="button"
                        onClick={() => setEditing((current) => !current)}
                        className={brandActionStyles}
                    >
                        {editing ? 'Fechar' : 'Editar'}
                    </button>
                    {!role.structural && <DeleteRoleForm role={role} />}
                </div>
            </div>

            <div className="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                <PermissionChips permissions={role.permissions} />
            </div>

            {editing && (
                <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                    <EditRoleForm role={role} permissions={permissions} />
                </div>
            )}
        </article>
    );
}

export default function RolesIndex({ roles, permissions }: RolesIndexProps) {
    return (
        <GestaoLayout>
            <Head title="Perfis e permissões" />
            <PageBreadcrumb pageTitle="Perfis e permissões" />

            <div className="flex flex-col gap-4 md:gap-6">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Segregação de funções: perfis com permissões granulares por funcionalidade
                </p>

                <div className="flex flex-col gap-4 md:gap-6">
                    {roles.map((role) => (
                        <RoleCard key={role.id} role={role} permissions={permissions} />
                    ))}
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div className="px-6 py-5">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">Criar perfil</h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            O novo perfil passa a valer imediatamente para os usuários vinculados a ele.
                        </p>
                    </div>
                    <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                        <Form action="/gestao/perfis" method="post" resetOnSuccess>
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

                                    <PermissionsGrid permissions={permissions} idPrefix="create" />
                                    <FieldError message={errors.permissions} />

                                    <div>
                                        <Button size="sm" type="submit" disabled={processing}>
                                            {processing ? 'Criando...' : 'Criar perfil'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </GestaoLayout>
    );
}
