import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { RevealLink } from '@/components/motion';
import {
    AlertIcon,
    ArrowRightIcon,
    BellIcon,
    CheckCircleIcon,
    FileIcon,
    PencilIcon,
    SearchIcon,
} from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import KpiCard, { type KpiTone } from '@/components/ui/kpi-card';
import PortalLayout from '@/layouts/portal-layout';
import { FILTROS_SOLICITACOES, PORTAL_ACOES } from '@/navigation/portal-nav';
import { PORTAL_NAV_ICONS } from '@/navigation/portal-nav-icons';
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
        em_analise: number;
        processos: number;
        em_convite: number;
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

export default function Dashboard({ indicadores, atencao, solicitacoesRecentes }: DashboardProps) {
    const { auth, portalServicos } = usePage<SharedProps>().props;
    const servicosHabilitados = portalServicos.enabled;
    const primeiroNome = auth.user?.name.split(' ')[0] ?? 'cidadão';

    const temAtencao = atencao.pendencias.length > 0 || atencao.rascunhos.length > 0;

    const indicators: Indicator[] = [
        {
            key: 'convite',
            label: 'Convites para responder',
            value: numberFormat.format(indicadores.em_convite),
            note: 'aguardando a sua resposta',
            icon: <BellIcon className="size-6" aria-hidden="true" />,
            tone: 'warning',
            href: servicosHabilitados ? FILTROS_SOLICITACOES.convites : undefined,
        },
        {
            key: 'analise',
            label: 'Em análise',
            value: numberFormat.format(indicadores.em_analise),
            note: 'sendo analisadas pela SEDUR',
            icon: <SearchIcon className="size-6" aria-hidden="true" />,
            tone: 'brand',
            href: servicosHabilitados ? FILTROS_SOLICITACOES.emAnalise : undefined,
        },
        {
            key: 'processos',
            label: 'Total de solicitações',
            value: numberFormat.format(indicadores.processos),
            note: 'todas as suas solicitações',
            icon: <FileIcon className="size-6" aria-hidden="true" />,
            tone: 'info',
            href: servicosHabilitados ? FILTROS_SOLICITACOES.todas : undefined,
        },
    ];

    return (
        <>
            <Head title="Meu painel" />
            <PageHeader title="Meu painel" />
            <p className="-mt-2 mb-6 text-base text-gray-600 dark:text-gray-300">
                Olá, {primeiroNome}. O que você precisa resolver hoje?
            </p>

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
                                                        Convite para responder — {pendencia.protocol_number ?? 'solicitação'}
                                                    </p>
                                                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                        A SEDUR pediu uma informação ou documento para seguir com a análise.
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {pendencia.descricao}
                                                        {pendencia.due_at ? ` · responda até ${formatDate(pendencia.due_at)}` : ''}
                                                    </p>
                                                </div>
                                                {servicosHabilitados && (
                                                    <Link
                                                        href={`/portal/solicitacoes/${pendencia.solicitacao_id}/pendencias`}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-warning-500 px-3 py-2 text-theme-sm font-medium text-white transition hover:bg-warning-600"
                                                    >
                                                        Responder
                                                        <ArrowRightIcon className="size-4" aria-hidden="true" />
                                                    </Link>
                                                )}
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
                                                {servicosHabilitados && (
                                                    <Link
                                                        href={`/portal/solicitacoes/${rascunho.id}/editar`}
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                                    >
                                                        <PencilIcon className="size-4" aria-hidden="true" />
                                                        Retomar e protocolar
                                                    </Link>
                                                )}
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

                {servicosHabilitados && (
                <section className="col-span-12" aria-labelledby="painel-acoes">
                    <h2 id="painel-acoes" className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">
                        O que você quer fazer?
                    </h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
                        {PORTAL_ACOES.map((acao) => {
                            const Icon = PORTAL_NAV_ICONS[acao.icon];

                            return (
                                <RevealLink
                                    key={acao.href}
                                    href={acao.href}
                                    className="group flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                                >
                                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                                        <Icon className="size-6" aria-hidden="true" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="text-base font-semibold text-gray-800 dark:text-white/90">
                                                {acao.titulo}
                                            </span>
                                            <ArrowRightIcon
                                                className="size-5 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span className="mt-1 block text-theme-sm text-gray-500 dark:text-gray-400">
                                            {acao.descricao}
                                        </span>
                                    </span>
                                </RevealLink>
                            );
                        })}
                    </div>
                </section>
                )}

                <div className="col-span-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
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
                                servicosHabilitados ? (
                                    <div className="flex flex-wrap items-center gap-4">
                                        <Link
                                            href="/portal/viabilidade/historico"
                                            className="text-theme-sm font-medium text-gray-600 transition hover:text-brand-500 dark:text-gray-300 dark:hover:text-brand-400"
                                        >
                                            Minhas consultas de viabilidade
                                        </Link>
                                        <Link
                                            href={FILTROS_SOLICITACOES.todas}
                                            className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                        >
                                            Ver todas
                                            <ArrowRightIcon className="size-4" aria-hidden="true" />
                                        </Link>
                                    </div>
                                ) : undefined
                            }
                        />
                        <CardContent flush>
                            {solicitacoesRecentes.length === 0 ? (
                                <EmptyState
                                    icon={<FileIcon className="size-6" aria-hidden="true" />}
                                    title="Você ainda não tem solicitações"
                                    description="Comece verificando se a atividade é permitida no endereço desejado."
                                    action={
                                        servicosHabilitados ? (
                                            <Link
                                                href="/portal/viabilidade"
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white transition hover:bg-brand-600"
                                            >
                                                Fazer consulta de viabilidade
                                                <ArrowRightIcon className="size-4" aria-hidden="true" />
                                            </Link>
                                        ) : undefined
                                    }
                                />
                            ) : (
                                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {solicitacoesRecentes.map((solicitacao) => {
                                        const corpo = (
                                            <>
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
                                            </>
                                        );

                                        return (
                                            <li key={solicitacao.id}>
                                                {servicosHabilitados ? (
                                                    <Link
                                                        href={`/portal/solicitacoes/${solicitacao.id}`}
                                                        className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] sm:px-6"
                                                    >
                                                        {corpo}
                                                    </Link>
                                                ) : (
                                                    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                                                        {corpo}
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
