import FieldError from '@/components/form/field-error';
import { Form, Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Label from '@/components/form/label';
import Input from '@/components/form/input';
import { PowerIcon } from '@/components/icons';
import Select from '@/components/form/select';
import Textarea from '@/components/form/textarea';
import Avatar from '@/components/ui/avatar';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface UserItem {
    id: number;
    name: string;
    email: string;
    roles: string[];
    inactivated_at: string | null;
    cpf_masked: string;
    auth_type: 'ad' | 'local';
    ad_username: string | null;
}

interface SolicitacaoItem {
    id: number;
    name: string;
    email: string;
    ad_username: string | null;
    perfil: string;
    justificativa: string;
    created_at: string;
}

type UsersTab = 'gestao' | 'portal';

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
        tab: UsersTab;
    };
    counts: {
        gestao: number;
        portal: number;
    };
    roles: string[];
    solicitacoes: SolicitacaoItem[];
}

function UsersTabs({
    active,
    counts,
    onChange,
}: {
    active: UsersTab;
    counts: { gestao: number; portal: number };
    onChange: (tab: UsersTab) => void;
}) {
    const tabs: { id: UsersTab; label: string; count: number }[] = [
        { id: 'gestao', label: 'Equipe SEDUR', count: counts.gestao },
        { id: 'portal', label: 'Usuários do portal', count: counts.portal },
    ];

    return (
        <div className="border-b border-gray-200 dark:border-gray-800" role="tablist" aria-label="Tipo de usuário">
            <nav className="-mb-px flex gap-6">
                {tabs.map((tab) => {
                    const isActive = tab.id === active;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={isActive}
                            onClick={() => onChange(tab.id)}
                            className={`inline-flex items-center gap-2 border-b-2 pt-1 pb-3 text-sm font-medium transition-colors ${
                                isActive
                                    ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                    : 'cursor-pointer border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-2 py-0.5 text-theme-xs font-semibold ${
                                    isActive
                                        ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                        : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400'
                                }`}
                            >
                                {tab.count}
                            </span>
                        </button>
                    );
                })}
            </nav>
        </div>
    );
}

function SolicitacaoRow({ item, roles, onReject }: { item: SolicitacaoItem; roles: string[]; onReject: () => void }) {
    const [role, setRole] = useState(item.perfil);
    const [processing, setProcessing] = useState(false);

    function approve() {
        router.put(
            `/gestao/usuarios/solicitacoes/${item.id}/aprovar`,
            { role },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <li className="flex flex-col gap-4 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="min-w-0">
                <p className="font-medium text-gray-800 dark:text-white/90">{item.name}</p>
                <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                    {item.email}
                    {item.ad_username ? ` · AD: ${item.ad_username}` : ''}
                </p>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">{item.justificativa}</p>
            </div>
            <div className="flex w-full flex-col gap-2 lg:w-80">
                <Label htmlFor={`solicitacao-role-${item.id}`}>Perfil a conceder (pedido: {item.perfil})</Label>
                <Select
                    id={`solicitacao-role-${item.id}`}
                    name="role"
                    value={role}
                    onChange={setRole}
                    placeholder="Selecionar..."
                    options={roles.map((roleOption) => ({ value: roleOption, label: roleOption }))}
                />
                <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" onClick={onReject} disabled={processing}>
                        Recusar
                    </Button>
                    <Button size="sm" onClick={approve} disabled={processing || role === ''}>
                        Aprovar
                    </Button>
                </div>
            </div>
        </li>
    );
}

function RejectModal({ item, onClose }: { item: SolicitacaoItem; onClose: () => void }) {
    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-[507px] p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Recusar solicitação</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Informe o motivo da recusa para {item.name}. Ele aparece para o servidor no painel.
            </p>

            <Form action={`/gestao/usuarios/solicitacoes/${item.id}/recusar`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`recusa-motivo-${item.id}`}>Motivo</Label>
                            <Textarea id={`recusa-motivo-${item.id}`} name="motivo" rows={3} error={Boolean(errors.motivo)} />
                            {errors.motivo && <FieldError>{errors.motivo}</FieldError>}
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Recusando...' : 'Recusar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
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
                            {errors.role && <FieldError>{errors.role}</FieldError>}
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

function AuthTypeModal({ user, onClose }: { user: UserItem; onClose: () => void }) {
    const [authType, setAuthType] = useState<'ad' | 'local'>(user.auth_type);
    const [adUsername, setAdUsername] = useState(user.ad_username ?? '');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-[507px] p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Tipo de autenticação</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Defina como {user.name} ({user.email}) entra no console.
            </p>

            <Form action={`/gestao/usuarios/${user.id}/autenticacao`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`auth-type-${user.id}`}>Autenticação</Label>
                            <Select
                                id={`auth-type-${user.id}`}
                                name="auth_type"
                                value={authType}
                                onChange={(value) => setAuthType(value as 'ad' | 'local')}
                                options={[
                                    { value: 'local', label: 'Local (senha do sistema)' },
                                    { value: 'ad', label: 'Active Directory (rede corporativa)' },
                                ]}
                            />
                            {errors.auth_type && <FieldError>{errors.auth_type}</FieldError>}
                        </div>

                        {authType === 'ad' && (
                            <div>
                                <Label htmlFor={`ad-username-${user.id}`}>Conta AD</Label>
                                <Input
                                    id={`ad-username-${user.id}`}
                                    name="ad_username"
                                    value={adUsername}
                                    onChange={(event) => setAdUsername(event.target.value)}
                                    placeholder="ex.: seuusuarioad"
                                    autoComplete="off"
                                />
                                {errors.ad_username && (
                                    <FieldError>{errors.ad_username}</FieldError>
                                )}
                            </div>
                        )}

                        {authType !== user.auth_type && (
                            <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                {authType === 'ad'
                                    ? 'A senha local deixará de valer; o acesso passa a ser validado na rede corporativa.'
                                    : 'O usuário receberá um e-mail para definir a nova senha local.'}
                            </p>
                        )}

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

export default function UsersIndex({ users, filters, counts, roles, solicitacoes }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);
    const [roleUser, setRoleUser] = useState<UserItem | null>(null);
    const [authTypeUser, setAuthTypeUser] = useState<UserItem | null>(null);
    const [activationUser, setActivationUser] = useState<UserItem | null>(null);
    const [activationProcessing, setActivationProcessing] = useState(false);
    const [rejectItem, setRejectItem] = useState<SolicitacaoItem | null>(null);

    const tab = filters.tab ?? 'gestao';

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timeout = setTimeout(() => {
            router.get('/gestao/usuarios', { tab, search }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timeout);
        // tab fora das deps de propósito: a troca de aba navega na hora pelo
        // onChange — o efeito cobre apenas o debounce da busca.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function changeTab(nextTab: UsersTab) {
        if (nextTab === tab) {
            return;
        }

        router.get('/gestao/usuarios', { tab: nextTab, search }, { preserveState: true, replace: true });
    }

    const searching = search.trim() !== '';

    const columns: ColumnDef<UserItem>[] = [
        {
            id: 'name',
            header: 'Nome',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (user) => (
                <div className="flex items-center gap-3">
                    <Avatar name={user.name} size="sm" />
                    <span>{user.name}</span>
                </div>
            ),
        },
        {
            id: 'email',
            header: 'E-mail',
            cell: (user) => user.email,
        },
        {
            id: 'cpf',
            header: 'CPF',
            cellClassName: 'whitespace-nowrap',
            cell: (user) => user.cpf_masked,
        },
        {
            id: 'roles',
            header: 'Papel',
            cell: (user) => <RoleBadges roles={user.roles} />,
        },
        {
            id: 'situation',
            header: 'Situação',
            cell: (user) => <SituationBadge inactivatedAt={user.inactivated_at} />,
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (user) => {
                const active = user.inactivated_at === null;

                return (
                    <div className="flex justify-end gap-2">
                        <TableAction tone="neutral" href={`/gestao/acessos/${user.id}`}>
                            Acessos
                        </TableAction>
                        <TableAction tone="neutral" onClick={() => setAuthTypeUser(user)}>
                            Autenticação
                        </TableAction>
                        <TableAction tone="brand" onClick={() => setRoleUser(user)}>
                            Alterar papel
                        </TableAction>
                        <TableAction
                            tone={active ? 'warning' : 'success'}
                            icon={<PowerIcon className="size-4.5" />}
                            label={active ? 'Inativar' : 'Reativar'}
                            onClick={() => setActivationUser(user)}
                        />
                    </div>
                );
            },
        },
    ];

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
        <>
            <Head title="Usuários" />
            <PageHeader title="Usuários" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            {solicitacoes.length > 0 && (
                <Card className="mb-6">
                    <CardHeader
                        title={`Solicitações de acesso (${solicitacoes.length})`}
                        description="Servidores que entraram pelo AD e pediram um perfil. Aprove com o perfil pedido, troque o perfil ou recuse."
                    />
                    <CardContent>
                        <ul className="divide-y divide-gray-200 dark:divide-gray-800">
                            {solicitacoes.map((item) => (
                                <SolicitacaoRow key={item.id} item={item} roles={roles} onReject={() => setRejectItem(item)} />
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader
                    title="Contas cadastradas"
                    description={
                        tab === 'gestao'
                            ? 'Servidores e perfis internos com acesso ao console SEDUR'
                            : 'Cidadãos, contadores e procuradores que usam o portal'
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <UsersTabs active={tab} counts={counts} onChange={changeTab} />

                        <TableToolbar
                            search={{
                                value: search,
                                onChange: setSearch,
                                placeholder: 'Buscar por nome ou e-mail...',
                                label: 'Buscar usuários',
                            }}
                        />

                        <DataTable
                            columns={columns}
                            rows={users.data}
                            rowKey={(user) => user.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={
                                        searching
                                            ? 'Nenhum resultado para a busca'
                                            : tab === 'gestao'
                                              ? 'Nenhum usuário na equipe SEDUR'
                                              : 'Nenhum usuário do portal'
                                    }
                                    description={
                                        searching
                                            ? 'Ajuste o termo de busca e tente novamente.'
                                            : tab === 'gestao'
                                              ? 'Contas com acesso ao console aparecem aqui.'
                                              : 'Contas criadas pelo portal do cidadão aparecem aqui.'
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={users.links}
                            meta={{ from: users.from, to: users.to, total: users.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {roleUser && <RoleModal user={roleUser} roles={roles} onClose={() => setRoleUser(null)} />}
            {rejectItem && <RejectModal item={rejectItem} onClose={() => setRejectItem(null)} />}

            {authTypeUser && <AuthTypeModal user={authTypeUser} onClose={() => setAuthTypeUser(null)} />}

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
        </>
    );
}

UsersIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
