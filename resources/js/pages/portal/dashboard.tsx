import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import {
    AlertIcon,
    ArrowRightIcon,
    BellIcon,
    CheckCircleIcon,
    FileIcon,
    GroupIcon,
    ListIcon,
    PencilIcon,
    SearchIcon,
} from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import KpiCard, { type KpiTone } from '@/components/ui/kpi-card';
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

interface StatusInfo {
    value: string;
    label: string;
    public_label: string;
}

interface SolicitacaoResumo {
    id: number;
    protocol_number: string | null;
    status: StatusInfo;
    service_type: string | null;
    company: { legal_name: string; formatted_cnpj: string } | null;
    created_at: string | null;
}

interface PendenciaResumo {
    id: number;
    solicitacao_id: number;
    protocol_number: string | null;
    descricao: string;
    due_at: string | null;
}

interface RascunhoResumo {
    id: number;
    service_type: string | null;
    company_legal_name: string | null;
    created_at: string | null;
}

interface DashboardProps {
    indicadores: {
        em_andamento: number;
        empresas: number;
        consultas: number | null;
    };
    atencao: {
        pendencias: PendenciaResumo[];
        rascunhos: RascunhoResumo[];
    };
    solicitacoesRecentes: SolicitacaoResumo[];
    emRepresentacao: boolean;
}

interface Indicator {
    key: string;
    label: string;
    value: string;
    note: string;
    icon: ReactNode;
    tone: KpiTone;
    /** Quando presente, o card vira um atalho de navegação. */
    href?: string;
}

interface QuickAction {
    name: string;
    label: string;
    href: string;
    icon: ReactNode;
}

const numberFormat = new Intl.NumberFormat('pt-BR');

/** Cor do selo conforme o estado do processo (reforço visual, nunca único canal). */
function statusColor(value: string): BadgeColor {
    switch (value) {
        case 'protocolada':
            return 'info';
        case 'deferida':
            return 'success';
        case 'indeferida':
        case 'cancelada':
            return 'error';
        case 'em_pendencia':
        case 'aguardando_bap':
            return 'warning';
        default:
            return 'light';
    }
}

/** 'YYYY-MM-DD HH:MM:SS' como 'DD/MM/YYYY' sem depender do fuso do navegador. */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;

    return `${day}/${month}/${year}`;
}

const quickActions: QuickAction[] = [
    {
        name: 'Consulta de viabilidade',
        label: 'Verifique se a atividade é permitida',
        href: '/portal/viabilidade',
        icon: <SearchIcon className="size-6 text-gray-800 dark:text-white/90" aria-hidden="true" />,
    },
    {
        name: 'Minhas empresas',
        label: 'Empresas vinculadas a você',
        href: '/portal/empresas',
        icon: <ListIcon className="size-6 text-gray-800 dark:text-white/90" aria-hidden="true" />,
    },
    {
        name: 'Procurações',
        label: 'Vínculos de representação',
        href: '/portal/procuracoes',
        icon: <FileIcon className="size-6 text-gray-800 dark:text-white/90" aria-hidden="true" />,
    },
    {
        name: 'Meus acessos',
        label: 'Histórico da sua conta',
        href: '/portal/acessos',
        icon: <GroupIcon className="size-6 text-gray-800 dark:text-white/90" aria-hidden="true" />,
    },
];

export default function Dashboard({ indicadores, atencao, solicitacoesRecentes, emRepresentacao }: DashboardProps) {
    const { auth, notificacoes } = usePage<SharedProps>().props;

    const temAtencao = atencao.pendencias.length > 0 || atencao.rascunhos.length > 0;

    const possibleIndicators: (Indicator | null)[] = [
        {
            key: 'andamento',
            label: 'Em andamento',
            value: numberFormat.format(indicadores.em_andamento),
            note: 'solicitações em processamento',
            icon: <FileIcon className="size-6" aria-hidden="true" />,
            tone: 'brand',
        },
        {
            key: 'empresas',
            label: 'Empresas vinculadas',
            value: numberFormat.format(indicadores.empresas),
            note: 'com vínculo ativo',
            icon: <ListIcon className="size-6" aria-hidden="true" />,
            tone: 'info',
        },
        indicadores.consultas !== null
            ? {
                  key: 'consultas',
                  label: 'Consultas feitas',
                  value: numberFormat.format(indicadores.consultas),
                  note: 'consultas de viabilidade',
                  icon: <SearchIcon className="size-6" aria-hidden="true" />,
                  tone: 'success',
              }
            : null,
        !emRepresentacao
            ? {
                  key: 'notificacoes',
                  label: 'Notificações não lidas',
                  value: numberFormat.format(notificacoes.nao_lidas),
                  note: 'avisos do seu processo',
                  icon: <BellIcon className="size-6" aria-hidden="true" />,
                  tone: 'warning',
                  href: '/portal/notificacoes',
              }
            : null,
    ];

    const indicators = possibleIndicators.filter((indicator): indicator is Indicator => indicator !== null);

    return (
        <>
            <Head title="Meu painel" />
            <PageHeader title="Meu painel" />

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                <div className="col-span-12">
                    {temAtencao ? (
                        <div className="rounded-2xl border border-warning-300 bg-warning-50 p-5 dark:border-warning-500/30 dark:bg-warning-500/10 md:p-6">
                            <div className="flex items-start gap-3">
                                <span className="text-warning-500">
                                    <AlertIcon className="size-6 fill-current" aria-hidden="true" />
                                </span>
                                <div className="w-full">
                                    <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">
                                        Precisa da sua atenção
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        Estes itens dependem de você para o processo seguir.
                                    </p>

                                    <ul className="mt-4 flex flex-col gap-3">
                                        {atencao.pendencias.map((pendencia) => (
                                            <li
                                                key={`pendencia-${pendencia.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-white px-4 py-3 dark:border-warning-500/20 dark:bg-white/[0.03]"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                        Convite — {pendencia.protocol_number ?? 'solicitação'}
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {pendencia.descricao}
                                                        {pendencia.due_at ? ` · responda até ${formatDate(pendencia.due_at)}` : ''}
                                                    </p>
                                                </div>
                                                <Link
                                                    href={`/portal/solicitacoes/${pendencia.solicitacao_id}/pendencias`}
                                                    className="inline-flex items-center gap-1.5 rounded-lg bg-warning-500 px-3 py-2 text-theme-sm font-medium text-white transition hover:bg-warning-600"
                                                >
                                                    Responder
                                                    <ArrowRightIcon className="size-4" aria-hidden="true" />
                                                </Link>
                                            </li>
                                        ))}

                                        {atencao.rascunhos.map((rascunho) => (
                                            <li
                                                key={`rascunho-${rascunho.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                        Rascunho não protocolado
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {rascunho.company_legal_name ?? rascunho.service_type ?? 'Solicitação em preenchimento'}
                                                    </p>
                                                </div>
                                                <Link
                                                    href={`/portal/solicitacoes/${rascunho.id}/editar`}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                                >
                                                    <PencilIcon className="size-4" aria-hidden="true" />
                                                    Retomar e protocolar
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center gap-3 rounded-2xl border border-success-200 bg-success-50 p-5 dark:border-success-500/30 dark:bg-success-500/10 md:p-6">
                            <span className="text-success-600 dark:text-success-500">
                                <CheckCircleIcon className="size-6" aria-hidden="true" />
                            </span>
                            <div>
                                <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Tudo em dia</h3>
                                <p className="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                                    Nada precisa de você agora. Acompanhe abaixo o andamento dos seus pedidos.
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="col-span-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                        {indicators.map((indicator) => {
                            const card = (
                                <KpiCard
                                    label={indicator.label}
                                    value={indicator.value}
                                    note={indicator.note}
                                    icon={indicator.icon}
                                    tone={indicator.tone}
                                />
                            );

                            return indicator.href ? (
                                <Link
                                    key={indicator.key}
                                    href={indicator.href}
                                    className="block rounded-2xl transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
                                >
                                    {card}
                                </Link>
                            ) : (
                                <div key={indicator.key}>{card}</div>
                            );
                        })}
                    </div>
                </div>

                <div className="col-span-12">
                    <Card>
                        <CardHeader
                            title="Minhas solicitações recentes"
                            description="Acompanhe o andamento dos seus pedidos de viabilidade."
                            actions={
                                <Link
                                    href="/portal/solicitacoes"
                                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                >
                                    Ver todas
                                    <ArrowRightIcon className="size-4" aria-hidden="true" />
                                </Link>
                            }
                        />
                        <CardContent flush>
                            {solicitacoesRecentes.length === 0 ? (
                                <EmptyState
                                    icon={<FileIcon className="size-6" aria-hidden="true" />}
                                    title="Você ainda não tem solicitações"
                                    description="Comece verificando se a atividade é permitida no endereço desejado."
                                    action={
                                        <Link
                                            href="/portal/viabilidade"
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white transition hover:bg-brand-600"
                                        >
                                            Fazer consulta de viabilidade
                                            <ArrowRightIcon className="size-4" aria-hidden="true" />
                                        </Link>
                                    }
                                />
                            ) : (
                                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {solicitacoesRecentes.map((solicitacao) => (
                                        <li key={solicitacao.id}>
                                            <Link
                                                href={`/portal/solicitacoes/${solicitacao.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] sm:px-6"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium text-gray-800 dark:text-white/90">
                                                        {solicitacao.protocol_number ?? 'Em preenchimento'}
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {solicitacao.company?.legal_name ?? solicitacao.service_type ?? '—'}
                                                        {solicitacao.created_at ? ` · ${formatDate(solicitacao.created_at)}` : ''}
                                                    </p>
                                                </div>
                                                <Badge size="sm" color={statusColor(solicitacao.status.value)}>
                                                    {solicitacao.status.public_label}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="col-span-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                        {quickActions.map((action) => (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                            >
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                                    {action.icon}
                                </div>
                                <div className="mt-5 flex items-end justify-between">
                                    <div>
                                        <span className="text-sm text-gray-500 dark:text-gray-400">{action.label}</span>
                                        <h4 className="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            {action.name}
                                        </h4>
                                    </div>
                                    <ArrowRightIcon className="mb-1.5 size-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400" aria-hidden="true" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="col-span-12">
                    <Card>
                        <CardHeader title="Minha conta" description="Bem-vindo(a) ao Simplifica." />
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
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
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
