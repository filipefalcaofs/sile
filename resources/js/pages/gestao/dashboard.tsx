import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon, GroupIcon, LockIcon, PlugInIcon, TableIcon, UserCircleIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface DashboardKpis {
    cnaes: { ativos: number; total: number } | null;
    usuarios: { ativos: number; total: number } | null;
    perfis: { total: number; permissoes: number } | null;
    acessos: { logins: number; janela_dias: number } | null;
}

interface DashboardProps {
    kpis: DashboardKpis;
}

interface ModuleCard {
    name: string;
    label: string;
    href: string;
    icon: ReactNode;
    visible: boolean;
}

const numberFormat = new Intl.NumberFormat('pt-BR');

export default function Dashboard({ kpis }: DashboardProps) {
    const { auth } = usePage<SharedProps>().props;

    const indicators = [
        kpis.cnaes && {
            key: 'cnaes',
            label: 'CNAEs ativos',
            value: numberFormat.format(kpis.cnaes.ativos),
            note: `de ${numberFormat.format(kpis.cnaes.total)} cadastrados`,
            icon: <TableIcon className="size-6" />,
            tone: 'brand' as const,
        },
        kpis.usuarios && {
            key: 'usuarios',
            label: 'Usuários ativos',
            value: numberFormat.format(kpis.usuarios.ativos),
            note: `de ${numberFormat.format(kpis.usuarios.total)} contas`,
            icon: <GroupIcon className="size-6" />,
            tone: 'success' as const,
        },
        kpis.perfis && {
            key: 'perfis',
            label: 'Perfis de acesso',
            value: numberFormat.format(kpis.perfis.total),
            note: `${numberFormat.format(kpis.perfis.permissoes)} permissões granulares`,
            icon: <LockIcon className="size-6" />,
            tone: 'info' as const,
        },
        kpis.acessos && {
            key: 'acessos',
            label: 'Acessos recentes',
            value: numberFormat.format(kpis.acessos.logins),
            note: `logins em ${kpis.acessos.janela_dias} dias`,
            icon: <UserCircleIcon className="size-6" />,
            tone: 'warning' as const,
        },
    ].filter((indicator) => indicator !== null);

    const modules: ModuleCard[] = [
        {
            name: 'CNAEs',
            label: 'Cadastro de atividades',
            href: '/gestao/cnaes',
            icon: <TableIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('consultar-cnaes'),
        },
        {
            name: 'Usuários',
            label: 'Contas do sistema',
            href: '/gestao/usuarios',
            icon: <GroupIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('manter-usuarios'),
        },
        {
            name: 'Perfis',
            label: 'Perfis e permissões',
            href: '/gestao/perfis',
            icon: <LockIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('manter-perfis'),
        },
        {
            name: 'Parâmetros',
            label: 'Configurações do sistema',
            href: '/gestao/parametros',
            icon: <PlugInIcon className="size-6 text-gray-800 dark:text-white/90" />,
            visible: auth.permissions.includes('manter-parametros'),
        },
    ].filter((module) => module.visible);

    return (
        <GestaoLayout>
            <Head title="Painel de gestão" />
            <PageHeader title="Painel de gestão" breadcrumbs={[{ label: 'Gestão' }]} />

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                {indicators.length > 0 && (
                    <div className="col-span-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            {indicators.map((indicator) => (
                                <KpiCard
                                    key={indicator.key}
                                    label={indicator.label}
                                    value={indicator.value}
                                    note={indicator.note}
                                    icon={indicator.icon}
                                    tone={indicator.tone}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {modules.length > 0 && (
                    <div className="col-span-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            {modules.map((module) => (
                                <Link
                                    key={module.href}
                                    href={module.href}
                                    className="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                                >
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                                        {module.icon}
                                    </div>
                                    <div className="mt-5 flex items-end justify-between">
                                        <div>
                                            <span className="text-sm text-gray-500 dark:text-gray-400">
                                                {module.label}
                                            </span>
                                            <h4 className="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">
                                                {module.name}
                                            </h4>
                                        </div>
                                        <ArrowRightIcon className="mb-1.5 size-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                <div className="col-span-12">
                    <Card>
                        <CardHeader
                            title="Sessão atual"
                            description="Conta conectada ao ambiente de gestão da SEDUR."
                        />
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Nome</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">E-mail</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.email ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Papéis</dt>
                                    <dd className="mt-1 flex flex-wrap gap-1">
                                        {auth.roles.length === 0 ? (
                                            <span className="text-theme-sm text-gray-400 dark:text-gray-500">
                                                Sem papel
                                            </span>
                                        ) : (
                                            auth.roles.map((role) => (
                                                <Badge key={role} size="sm">
                                                    {role}
                                                </Badge>
                                            ))
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </GestaoLayout>
    );
}
