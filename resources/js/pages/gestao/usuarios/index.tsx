import { Form, Head, Link, router } from '@inertiajs/react';
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

interface UserItem {
    id: number;
    name: string;
    email: string;
    roles: string[];
    inactivated_at: string | null;
    cpf_masked: string;
}

interface UsersIndexProps {
    users: {
        data: UserItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
    };
    roles: string[];
}

const actionButtonStyles =
    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-theme-xs font-medium ring-1 ring-inset transition disabled:cursor-not-allowed disabled:opacity-60';

const brandActionStyles = `${actionButtonStyles} text-brand-500 ring-brand-200 hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10`;

const neutralActionStyles = `${actionButtonStyles} text-gray-600 ring-gray-300 hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]`;

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

function RoleModal({ user, roles, onClose }: { user: UserItem; roles: string[]; onClose: () => void }) {
    const [role, setRole] = useState(user.roles[0] ?? '');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-[507px] p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Alterar papel</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Selecione o novo papel da conta de {user.name} ({user.email}).
            </p>

            <Form action={`/gestao/usuarios/${user.id}/papel`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`role-${user.id}`}>Papel</Label>
                            <Select
                                id={`role-${user.id}`}
                                name="role"
                                value={role}
                                onChange={setRole}
                                placeholder="Selecionar..."
                                options={roles.map((roleOption) => ({ value: roleOption, label: roleOption }))}
                            />
                            {errors.role && <p className="mt-1.5 text-theme-xs text-error-500">{errors.role}</p>}
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

export default function UsersIndex({ users, filters, roles }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);
    const [roleUser, setRoleUser] = useState<UserItem | null>(null);
    const [activationUser, setActivationUser] = useState<UserItem | null>(null);
    const [activationProcessing, setActivationProcessing] = useState(false);

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

    const searching = search.trim() !== '';

    function toggleActivation() {
        if (!activationUser) {
            return;
        }

        router.put(`/gestao/usuarios/${activationUser.id}/inativacao`, undefined, {
            preserveScroll: true,
            onStart: () => setActivationProcessing(true),
            onFinish: () => setActivationProcessing(false),
            onSuccess: () => setActivationUser(null),
        });
    }

    const activationContent = activationUser
        ? activationUser.inactivated_at === null
            ? {
                  variant: 'warning' as const,
                  title: 'Inativar conta',
                  description: `Inativar a conta de ${activationUser.name}? O usuário perderá o acesso imediatamente.`,
                  confirmLabel: 'Inativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar conta',
                  description: `Reativar a conta de ${activationUser.name}? O usuário volta a acessar o sistema com o papel atual.`,
                  confirmLabel: 'Reativar',
              }
        : null;

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
                            <EmptyState
                                title={searching ? 'Nenhum resultado para a busca' : 'Nenhum usuário cadastrado'}
                                description={
                                    searching
                                        ? 'Ajuste o termo de busca e tente novamente.'
                                        : 'As contas criadas no sistema aparecem aqui.'
                                }
                            />
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
                                                <TableCell isHeader className={`${headerCellStyles} text-end`}>
                                                    Ações
                                                </TableCell>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                            {users.data.map((user) => {
                                                const active = user.inactivated_at === null;

                                                return (
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
                                                        <TableCell className="px-5 py-4 text-end text-theme-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                            <div className="flex flex-wrap justify-end gap-2">
                                                                <Link
                                                                    href={`/gestao/acessos/${user.id}`}
                                                                    className={neutralActionStyles}
                                                                >
                                                                    Acessos
                                                                </Link>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setRoleUser(user)}
                                                                    className={brandActionStyles}
                                                                >
                                                                    Alterar papel
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setActivationUser(user)}
                                                                    className={
                                                                        active
                                                                            ? warningActionStyles
                                                                            : successActionStyles
                                                                    }
                                                                >
                                                                    {active ? 'Inativar' : 'Reativar'}
                                                                </button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        )}

                        <Pagination
                            links={users.links}
                            meta={{ from: users.from, to: users.to, total: users.total }}
                        />
                    </div>
                </div>
            </div>

            {roleUser && <RoleModal user={roleUser} roles={roles} onClose={() => setRoleUser(null)} />}

            {activationContent && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setActivationUser(null)}
                    onConfirm={toggleActivation}
                    title={activationContent.title}
                    description={activationContent.description}
                    confirmLabel={activationContent.confirmLabel}
                    variant={activationContent.variant}
                    processing={activationProcessing}
                />
            )}
        </GestaoLayout>
    );
}
