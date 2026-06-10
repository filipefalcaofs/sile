import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import Badge from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
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

const actionButtonStyles =
    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-theme-xs font-medium ring-1 ring-inset transition disabled:cursor-not-allowed disabled:opacity-60';

const brandActionStyles = `${actionButtonStyles} text-brand-500 ring-brand-200 hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10`;

const warningActionStyles = `${actionButtonStyles} text-warning-600 ring-warning-300 hover:bg-warning-50 dark:text-orange-400 dark:ring-warning-500/30 dark:hover:bg-warning-500/10`;

const successActionStyles = `${actionButtonStyles} text-success-600 ring-success-300 hover:bg-success-50 dark:text-success-400 dark:ring-success-500/30 dark:hover:bg-success-500/10`;

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

function SituationBadge({ inactivatedAt }: { inactivatedAt: string | null }) {
    const active = inactivatedAt === null;

    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativa' : 'Inativa'}
        </Badge>
    );
}

function RoleBadges({ roles }: { roles: string[] }) {
    if (roles.length === 0) {
        return <span className="text-theme-xs text-gray-400 dark:text-gray-500">Sem papel</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
                <Badge key={role} size="sm">
                    {role}
                </Badge>
            ))}
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-theme-xs text-error-500">{message}</p>;
}

function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        className={`inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm font-medium transition ${
                            link.active
                                ? 'bg-brand-500 text-white'
                                : 'text-gray-700 hover:bg-brand-50 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-brand-500/[0.12] dark:hover:text-brand-400'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm text-gray-400 dark:text-gray-600"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}

function RoleForm({ user, roles }: { user: UserItem; roles: string[] }) {
    const [role, setRole] = useState(user.roles[0] ?? '');

    return (
        <Form action={`/gestao/usuarios/${user.id}/papel`} method="put" className="inline">
            {({ errors, processing }) => (
                <div className="flex flex-wrap items-center gap-2">
                    <label htmlFor={`role-${user.id}`} className="sr-only">
                        Papel de {user.name}
                    </label>
                    <div className="w-44">
                        <Select
                            id={`role-${user.id}`}
                            name="role"
                            value={role}
                            onChange={setRole}
                            placeholder="Selecionar..."
                            options={roles.map((roleOption) => ({ value: roleOption, label: roleOption }))}
                        />
                    </div>
                    <button type="submit" disabled={processing} className={brandActionStyles}>
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
                        className={active ? warningActionStyles : successActionStyles}
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
            <PageBreadcrumb pageTitle="Usuários" />

            <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div className="px-6 py-5">
                    <h3 className="text-base font-medium text-gray-800 dark:text-white/90">Contas cadastradas</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Contas do sistema: situação, papel e histórico de acessos
                    </p>
                </div>
                <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="search" className="sr-only">
                                Buscar usuários
                            </label>
                            <div className="w-full sm:max-w-sm">
                                <Input
                                    id="search"
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Buscar por nome ou e-mail..."
                                />
                            </div>
                        </div>

                        {users.data.length === 0 ? (
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                Nenhum usuário encontrado.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                                <div className="max-w-full overflow-x-auto">
                                    <Table>
                                        <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                                            <TableRow>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Nome
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    E-mail
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    CPF
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Papel
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Situação
                                                </TableCell>
                                                <TableCell isHeader className={headerCellStyles}>
                                                    Ações
                                                </TableCell>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                            {users.data.map((user) => (
                                                <TableRow
                                                    key={user.id}
                                                    className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                                >
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                        {user.name}
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                        {user.email}
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                        {user.cpf_masked}
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                        <RoleBadges roles={user.roles} />
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                        <SituationBadge inactivatedAt={user.inactivated_at} />
                                                    </TableCell>
                                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                        <div className="flex flex-wrap items-center gap-3">
                                                            <Link
                                                                href={`/gestao/acessos/${user.id}`}
                                                                className="text-theme-xs font-medium text-gray-500 underline-offset-2 transition hover:text-brand-500 hover:underline dark:text-gray-400 dark:hover:text-brand-400"
                                                            >
                                                                Acessos
                                                            </Link>
                                                            <RoleForm user={user} roles={roles} />
                                                            <ActivationForm user={user} />
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        )}

                        <Pagination links={users.links} />
                    </div>
                </div>
            </div>
        </GestaoLayout>
    );
}
