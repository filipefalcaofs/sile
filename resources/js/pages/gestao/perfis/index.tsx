import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
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

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-600 dark:text-red-400">{message}</p>;
}

function PermissionChips({ permissions }: { permissions: string[] }) {
    if (permissions.length === 0) {
        return <span className="text-xs text-neutral-400 dark:text-neutral-500">Sem permissões</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {permissions.map((permission) => (
                <span
                    key={permission}
                    className="inline-block rounded-lg bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-100"
                >
                    {permission}
                </span>
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
        <div className="grid gap-4 sm:grid-cols-2">
            {groups.map((group) => (
                <fieldset key={group.label}>
                    <legend className="text-xs font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
                        {group.label}
                    </legend>
                    <div className="mt-2 flex flex-col gap-1.5">
                        {group.items.map((permission) => {
                            const locked = permission === lockedPermission;

                            return (
                                <div key={permission}>
                                    <label
                                        htmlFor={`${idPrefix}-${permission}`}
                                        className={`flex items-center gap-2 text-sm ${
                                            locked
                                                ? 'text-neutral-400 dark:text-neutral-500'
                                                : 'text-neutral-700 dark:text-neutral-300'
                                        }`}
                                    >
                                        <input
                                            id={`${idPrefix}-${permission}`}
                                            type="checkbox"
                                            name="permissions[]"
                                            value={permission}
                                            defaultChecked={locked || defaultChecked.includes(permission)}
                                            disabled={locked}
                                            className="rounded border-neutral-300 text-blue-700 focus:ring-2 focus:ring-blue-600/20 disabled:opacity-60 dark:border-neutral-700"
                                        />
                                        <span>{permission}</span>
                                    </label>
                                    {locked && (
                                        <>
                                            {/* Checkbox desabilitada não envia valor — o hidden garante o envio. A regra real está no backend. */}
                                            <input type="hidden" name="permissions[]" value={permission} />
                                            <p className="ml-6 text-xs text-neutral-400 dark:text-neutral-500">
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
        <Form action={`/gestao/perfis/${role.id}`} method="put" className="mt-4 border-t border-neutral-100 pt-4 dark:border-neutral-800">
            {({ errors, processing }) => (
                <div className="flex flex-col gap-4">
                    <div>
                        <label
                            htmlFor={`name-${role.id}`}
                            className="block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                        >
                            Nome do perfil
                        </label>
                        <input
                            id={`name-${role.id}`}
                            type="text"
                            name="name"
                            defaultValue={role.name}
                            readOnly={role.structural}
                            className={`mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none sm:max-w-sm dark:border-neutral-700 dark:text-neutral-100 ${
                                role.structural
                                    ? 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'
                                    : 'bg-white dark:bg-neutral-950'
                            }`}
                        />
                        {role.structural && (
                            <p className="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                                Papéis estruturais não podem ser renomeados.
                            </p>
                        )}
                        <FieldError message={errors.name} />
                    </div>

                    <PermissionsGrid
                        permissions={permissions}
                        idPrefix={`edit-${role.id}`}
                        defaultChecked={role.permissions}
                        lockedPermission={role.name === 'administrador' ? 'acessar-gestao' : undefined}
                    />
                    <FieldError message={errors.permissions} />

                    <div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            {processing ? 'Salvando...' : 'Salvar alterações'}
                        </button>
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
                        className="rounded-lg border border-red-300 px-3 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950"
                    >
                        Excluir
                    </button>
                    {blocked && (
                        <p className="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
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
        <article className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                            {role.name}
                        </h3>
                        {role.structural && (
                            <span className="inline-block rounded-lg bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                Estrutural
                            </span>
                        )}
                    </div>
                    <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {role.users_count === 1 ? '1 usuário' : `${role.users_count} usuários`}
                    </p>
                </div>
                <div className="flex items-start gap-3">
                    <button
                        type="button"
                        onClick={() => setEditing((current) => !current)}
                        className="rounded-lg border border-blue-300 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-950"
                    >
                        {editing ? 'Fechar' : 'Editar'}
                    </button>
                    {!role.structural && <DeleteRoleForm role={role} />}
                </div>
            </div>

            <div className="mt-3">
                <PermissionChips permissions={role.permissions} />
            </div>

            {editing && <EditRoleForm role={role} permissions={permissions} />}
        </article>
    );
}

export default function RolesIndex({ roles, permissions }: RolesIndexProps) {
    return (
        <GestaoLayout>
            <Head title="Perfis e permissões" />
            <div className="flex flex-col gap-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Perfis e permissões
                    </h2>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Segregação de funções: perfis com permissões granulares por funcionalidade
                    </p>
                </div>

                <div className="flex flex-col gap-4">
                    {roles.map((role) => (
                        <RoleCard key={role.id} role={role} permissions={permissions} />
                    ))}
                </div>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Criar perfil
                    </h3>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        O novo perfil passa a valer imediatamente para os usuários vinculados a ele.
                    </p>

                    <Form action="/gestao/perfis" method="post" resetOnSuccess className="mt-4">
                        {({ errors, processing }) => (
                            <div className="flex flex-col gap-4">
                                <div>
                                    <label
                                        htmlFor="create-name"
                                        className="block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                                    >
                                        Nome do perfil
                                    </label>
                                    <input
                                        id="create-name"
                                        type="text"
                                        name="name"
                                        required
                                        className="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none sm:max-w-sm dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                                    />
                                    <FieldError message={errors.name} />
                                </div>

                                <PermissionsGrid permissions={permissions} idPrefix="create" />
                                <FieldError message={errors.permissions} />

                                <div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                                    >
                                        {processing ? 'Criando...' : 'Criar perfil'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </Form>
                </section>
            </div>
        </GestaoLayout>
    );
}
