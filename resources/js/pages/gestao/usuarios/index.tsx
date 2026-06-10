import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import GestaoLayout from '@/layouts/gestao-layout';

interface UserItem {
    id: number;
    name: string;
    email: string;
    roles: string[];
    inactivated_at: string | null;
    cpf_masked: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface UsersIndexProps {
    users: {
        data: UserItem[];
        links: PaginationLink[];
    };
    filters: {
        search: string;
    };
    roles: string[];
}

function SituationBadge({ inactivatedAt }: { inactivatedAt: string | null }) {
    const active = inactivatedAt === null;
    const styles = active
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100'
        : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100';

    return (
        <span className={`inline-block rounded-lg px-2 py-0.5 text-xs font-medium ${styles}`}>
            {active ? 'Ativa' : 'Inativa'}
        </span>
    );
}

function RoleBadges({ roles }: { roles: string[] }) {
    if (roles.length === 0) {
        return <span className="text-xs text-neutral-400 dark:text-neutral-500">Sem papel</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
                <span
                    key={role}
                    className="inline-block rounded-lg bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-100"
                >
                    {role}
                </span>
            ))}
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-600 dark:text-red-400">{message}</p>;
}

function RoleForm({ user, roles }: { user: UserItem; roles: string[] }) {
    return (
        <Form action={`/gestao/usuarios/${user.id}/papel`} method="put" className="inline">
            {({ errors, processing }) => (
                <div className="flex flex-wrap items-center gap-2">
                    <label htmlFor={`role-${user.id}`} className="sr-only">
                        Papel de {user.name}
                    </label>
                    <select
                        id={`role-${user.id}`}
                        name="role"
                        defaultValue={user.roles[0] ?? ''}
                        className="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                    >
                        {user.roles.length === 0 && (
                            <option value="" disabled>
                                Selecionar...
                            </option>
                        )}
                        {roles.map((role) => (
                            <option key={role} value={role}>
                                {role}
                            </option>
                        ))}
                    </select>
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg border border-blue-300 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-950"
                    >
                        Alterar papel
                    </button>
                    <FieldError message={errors.role} />
                </div>
            )}
        </Form>
    );
}

function ActivationForm({ user }: { user: UserItem }) {
    const active = user.inactivated_at === null;
    const confirmMessage = active
        ? 'Inativar esta conta? O usuário perderá o acesso imediatamente.'
        : 'Reativar esta conta?';

    return (
        <Form action={`/gestao/usuarios/${user.id}/inativacao`} method="put" className="inline">
            {({ errors, processing }) => (
                <div>
                    <button
                        type="submit"
                        disabled={processing}
                        onClick={(event) => {
                            if (!window.confirm(confirmMessage)) {
                                event.preventDefault();
                            }
                        }}
                        className={`rounded-lg border px-3 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ${
                            active
                                ? 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-950'
                                : 'border-green-300 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-950'
                        }`}
                    >
                        {active ? 'Inativar' : 'Reativar'}
                    </button>
                    <FieldError message={errors.user} />
                </div>
            )}
        </Form>
    );
}

export default function UsersIndex({ users, filters, roles }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timeout = setTimeout(() => {
            router.get('/gestao/usuarios', { search }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <GestaoLayout>
            <Head title="Usuários" />
            <div className="flex flex-col gap-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Usuários
                    </h2>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Contas do sistema: situação, papel e histórico de acessos
                    </p>
                </div>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <label htmlFor="search" className="sr-only">
                        Buscar usuários
                    </label>
                    <input
                        id="search"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar por nome ou e-mail..."
                        className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none sm:max-w-sm dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                    />

                    {users.data.length === 0 ? (
                        <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                            Nenhum usuário encontrado.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                        <th className="py-2 pr-4 font-medium">Nome</th>
                                        <th className="py-2 pr-4 font-medium">E-mail</th>
                                        <th className="py-2 pr-4 font-medium">CPF</th>
                                        <th className="py-2 pr-4 font-medium">Papel</th>
                                        <th className="py-2 pr-4 font-medium">Situação</th>
                                        <th className="py-2 font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-b border-neutral-100 text-neutral-900 last:border-0 dark:border-neutral-800 dark:text-neutral-100"
                                        >
                                            <td className="py-2.5 pr-4 font-medium">{user.name}</td>
                                            <td className="py-2.5 pr-4">{user.email}</td>
                                            <td className="py-2.5 pr-4 whitespace-nowrap">
                                                {user.cpf_masked}
                                            </td>
                                            <td className="py-2.5 pr-4">
                                                <RoleBadges roles={user.roles} />
                                            </td>
                                            <td className="py-2.5 pr-4">
                                                <SituationBadge inactivatedAt={user.inactivated_at} />
                                            </td>
                                            <td className="py-2.5">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <Link
                                                        href={`/gestao/acessos/${user.id}`}
                                                        className="text-xs font-medium text-neutral-500 underline-offset-2 transition hover:text-blue-700 hover:underline dark:text-neutral-400 dark:hover:text-blue-400"
                                                    >
                                                        Acessos
                                                    </Link>
                                                    <RoleForm user={user} roles={roles} />
                                                    <ActivationForm user={user} />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {users.links.length > 3 && (
                        <nav className="mt-4 flex flex-wrap gap-1">
                            {users.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`rounded-lg px-3 py-1.5 text-sm transition ${
                                            link.active
                                                ? 'bg-blue-700 font-medium text-white dark:bg-blue-600'
                                                : 'text-neutral-700 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={index}
                                        className="rounded-lg px-3 py-1.5 text-sm text-neutral-400 dark:text-neutral-600"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </section>
            </div>
        </GestaoLayout>
    );
}
