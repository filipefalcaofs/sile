import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { CheckCircleIcon, EyeIcon, InfoIcon, LockIcon, ShieldIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';

interface Consentimentos {
    sem_termo_publicado: boolean;
    versao_vigente: string | number | null;
    publicado_em: string | null;
    total_usuarios: number;
    aceitaram_vigente: number;
    pendentes_reaceite: number;
    percentual_aceite: number;
}

interface Retencao {
    access_logs_dias: number | null;
    ultimo_pruning_em: string | null;
    ultimo_pruning_removidos: number | null;
    decisoes_fora_do_pruning: boolean;
}

interface AcessoPorEvento {
    log_name: string | null;
    event: string | null;
    total: number;
}

interface AcessosDadoPessoal {
    janela_dias: number;
    desde: string | null;
    total: number;
    por_evento: AcessoPorEvento[];
}

interface DireitosTitular {
    status: string;
    descricao: string;
}

interface LgpdIndexProps {
    consentimentos: Consentimentos;
    retencao: Retencao;
    acessosDadoPessoal: AcessosDadoPessoal;
    direitosTitular: DireitosTitular;
}

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** Formata a data ISO (sem hora) para o padrão pt-BR. */
function formatarData(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Número formatado em pt-BR; valor ausente vira travessão. */
function formatarNumero(valor: number | null | undefined, casas = 0): string {
    if (typeof valor !== 'number' || Number.isNaN(valor)) {
        return '—';
    }

    return valor.toLocaleString('pt-BR', { maximumFractionDigits: casas });
}

const colunasAcessos: ColumnDef<AcessoPorEvento>[] = [
    {
        id: 'log_name',
        header: 'Origem',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) =>
            linha.log_name ? (
                <Badge color="light" size="sm">
                    {linha.log_name}
                </Badge>
            ) : (
                <span className="text-gray-400 dark:text-gray-500">—</span>
            ),
    },
    {
        id: 'event',
        header: 'Ação',
        cellClassName: 'text-gray-700 dark:text-gray-300',
        cell: (linha) => linha.event ?? '—',
    },
    {
        id: 'total',
        header: 'Acessos',
        align: 'end',
        cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
        cell: (linha) => formatarNumero(linha.total),
    },
];

/**
 * Painel de monitoramento de conformidade (página gestao/lgpd/index, HU-102):
 * consome as agregações minimizadas do LgpdMonitorController — consentimentos,
 * retenção/pruning e acessos a dado pessoal (apenas métricas, nunca PII crua) —
 * e exibe a pendência DPO de forma honesta. Defensivo para o SSR não quebrar
 * com props vazias/ausentes.
 */
export default function LgpdIndex({
    consentimentos,
    retencao,
    acessosDadoPessoal,
    direitosTitular,
}: LgpdIndexProps) {
    const porEvento = Array.isArray(acessosDadoPessoal?.por_evento) ? acessosDadoPessoal.por_evento : [];
    const semTermo = consentimentos?.sem_termo_publicado ?? true;

    return (
        <>
            <Head title="Monitoramento LGPD" />
            <PageHeader title="Monitoramento LGPD" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                {/* Visão geral minimizada (apenas métricas, nunca PII crua) */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <KpiCard
                        label="Aceite do termo vigente"
                        value={semTermo ? '—' : `${formatarNumero(consentimentos.percentual_aceite, 1)}%`}
                        icon={<CheckCircleIcon className="size-6" />}
                        tone="success"
                        note={
                            semTermo
                                ? 'Nenhum termo publicado'
                                : `${formatarNumero(consentimentos.aceitaram_vigente)} de ${formatarNumero(consentimentos.total_usuarios)} usuários`
                        }
                    />
                    <KpiCard
                        label="Retenção de logs de acesso"
                        value={retencao?.access_logs_dias !== null ? `${formatarNumero(retencao.access_logs_dias)} dias` : '—'}
                        icon={<LockIcon className="size-6" />}
                        tone="info"
                        note="Decisões preservadas (compliance)"
                    />
                    <KpiCard
                        label={`Acessos a dado pessoal (${formatarNumero(acessosDadoPessoal?.janela_dias)} dias)`}
                        value={formatarNumero(acessosDadoPessoal?.total)}
                        icon={<EyeIcon className="size-6" />}
                        tone="warning"
                        note="Métrica agregada, sem PII"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Consentimentos */}
                    <Card>
                        <CardHeader
                            title="Consentimentos"
                            description="Aceite da versão vigente do termo LGPD (LegalTerm × LegalTermAcceptance)."
                        />
                        <CardContent>
                            {semTermo ? (
                                <div className="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-900/20">
                                    <InfoIcon className="size-5 shrink-0 fill-current text-warning-500" />
                                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                        Nenhum termo LGPD publicado. Sem termo vigente, não há consentimento a medir — o
                                        indicador permanece desarmado (honesto).
                                    </p>
                                </div>
                            ) : (
                                <dl className="grid grid-cols-2 gap-5">
                                    <Metrica
                                        label="Aceitaram a versão vigente"
                                        value={formatarNumero(consentimentos.aceitaram_vigente)}
                                    />
                                    <Metrica
                                        label="Pendentes de reaceite"
                                        value={formatarNumero(consentimentos.pendentes_reaceite)}
                                        tone={consentimentos.pendentes_reaceite > 0 ? 'warning' : 'default'}
                                    />
                                    <Metrica
                                        label="Total de usuários"
                                        value={formatarNumero(consentimentos.total_usuarios)}
                                    />
                                    <Metrica
                                        label="Versão vigente"
                                        value={consentimentos.versao_vigente !== null ? String(consentimentos.versao_vigente) : '—'}
                                    />
                                    <div className="col-span-2">
                                        <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                            Publicada em
                                        </dt>
                                        <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">
                                            {formatarData(consentimentos.publicado_em)}
                                        </dd>
                                    </div>
                                </dl>
                            )}
                        </CardContent>
                    </Card>

                    {/* Retenção */}
                    <Card>
                        <CardHeader
                            title="Retenção e pruning"
                            description="Política de retenção dos logs de acesso e o último expurgo executado."
                        />
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-5">
                                <Metrica
                                    label="Retenção (logs de acesso)"
                                    value={retencao?.access_logs_dias !== null ? `${formatarNumero(retencao.access_logs_dias)} dias` : '—'}
                                />
                                <Metrica
                                    label="Removidos no último pruning"
                                    value={retencao?.ultimo_pruning_removidos !== null ? formatarNumero(retencao.ultimo_pruning_removidos) : '—'}
                                />
                                <div className="col-span-2">
                                    <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                        Último pruning
                                    </dt>
                                    <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">
                                        {retencao?.ultimo_pruning_em
                                            ? formatarDataHora(retencao.ultimo_pruning_em)
                                            : 'Nunca executado'}
                                    </dd>
                                </div>
                            </dl>

                            {retencao?.decisoes_fora_do_pruning && (
                                <div className="mt-4 flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                                    <ShieldIcon className="size-5 shrink-0 text-blue-light-500" />
                                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                        As decisões de viabilidade ficam <strong>fora do pruning</strong>: são preservadas
                                        por compliance. Apenas os logs de acesso são expurgados pela retenção.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Acessos a dado pessoal (métrica/série, minimizado) */}
                <Card>
                    <CardHeader
                        title="Acessos a dado pessoal"
                        description={`Contagem de acessos a dados pessoais nos últimos ${formatarNumero(acessosDadoPessoal?.janela_dias)} dias${
                            acessosDadoPessoal?.desde ? ` (desde ${formatarData(acessosDadoPessoal.desde)})` : ''
                        }. Métrica agregada por ação — nunca o dado pessoal em si.`}
                    />
                    <CardContent>
                        <DataTable<AcessoPorEvento>
                            columns={colunasAcessos}
                            rows={porEvento}
                            rowKey={(linha) => `${linha.log_name ?? ''}-${linha.event ?? ''}`}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title="Nenhum acesso a dado pessoal na janela"
                                    description="Não há registros marcados como acesso a dado pessoal no período analisado."
                                />
                            }
                        />
                    </CardContent>
                </Card>

                {/* Direitos do titular — pendência DPO honesta */}
                <Card>
                    <CardHeader title="Direitos do titular (LGPD art. 18)" />
                    <CardContent>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <Badge color="warning" size="md">
                                {direitosTitular?.status === 'pendente-dpo' ? 'Pendente — DPO/SEDUR' : (direitosTitular?.status ?? 'Pendente')}
                            </Badge>
                            <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                {direitosTitular?.descricao ??
                                    'A eliminação e a anonimização de dados do titular dependem de definição de rito pela SEDUR/DPO.'}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/** Métrica rotulada (par dt/dd) com tom opcional de alerta. */
function Metrica({ label, value, tone = 'default' }: { label: string; value: string; tone?: 'default' | 'warning' }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">{label}</dt>
            <dd
                className={`mt-1 text-lg font-semibold ${
                    tone === 'warning' ? 'text-warning-600 dark:text-warning-400' : 'text-gray-800 dark:text-white/90'
                }`}
            >
                {value}
            </dd>
        </div>
    );
}

LgpdIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
