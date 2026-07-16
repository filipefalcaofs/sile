import { Head, Link, WhenVisible } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import { AlertIcon, CheckCircleIcon, InfoIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

interface StatusInfo {
    value: string;
    label: string;
    public_label: string;
}

interface TimelineStep {
    rotulo: string;
    data: string | null;
    status?: string;
    status_label?: string;
    motivo?: string | null;
}

interface PrazoEstimado {
    dias: number;
    ressalva: string;
}

interface Timeline {
    status_atual: { value: string; public_label: string; label?: string };
    etapas: TimelineStep[];
    pendencias: string[];
    prazo_estimado: PrazoEstimado | null;
}

interface CnaeItem {
    formatted_code: string;
    description: string;
    is_primary: boolean;
}

interface Solicitacao {
    id: number;
    protocol_number: string | null;
    status: StatusInfo;
    protocoled_at: string | null;
    created_at: string | null;
    service_type: string | null;
    company: { legal_name: string; formatted_cnpj: string } | null;
    used_area_m2: string | number | null;
    address: {
        street: string | null;
        number: string | null;
        neighborhood: string | null;
        reference: string | null;
    };
    cnaes: CnaeItem[];
}

interface ExplicacaoIaOutput {
    explicacao?: string | null;
    fonte?: string | null;
    [chave: string]: unknown;
}

interface ExplicacaoIa {
    id: number;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    output: ExplicacaoIaOutput;
    created_at: string | null;
}

interface ProtocoloProps {
    solicitacao: Solicitacao;
    timeline: Timeline;
    publicLink: string | null;
    /** Há decisão registrada? Governa a exibição do card de explicação (HU-119). */
    temDecisao: boolean;
    /** Explicação da decisão em linguagem cidadã (HU-119) — prop deferida. */
    explicacaoIa?: ExplicacaoIa[];
}

/** Cor do selo conforme o estado do processo (diferenciação visual, não estado). */
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

/** Formata ISO 8601 como 'DD/MM/YYYY HH:MM' sem depender de fuso do navegador. */
function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;

    return `${day}/${month}/${year} ${hour}:${minute}`;
}

function InfoRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            <span className="text-sm text-gray-800 dark:text-white/90">{value ?? '—'}</span>
        </div>
    );
}

function TimelineView({ timeline }: { timeline: Timeline }) {
    const { etapas, status_atual } = timeline;

    return (
        <ol className="flex flex-col">
            {etapas.length === 0 && (
                <li className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Esta solicitação ainda não possui etapas registradas. Conclua o preenchimento e protocole para
                    iniciar o acompanhamento.
                </li>
            )}
            {etapas.map((etapa, index) => {
                const isLast = index === etapas.length - 1;

                return (
                    <li key={`${etapa.rotulo}-${index}`} className="flex gap-4">
                        <div className="flex flex-col items-center">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                <CheckCircleIcon className="size-5 fill-current" />
                            </span>
                            {!isLast && <span className="my-1 w-px grow bg-gray-200 dark:bg-gray-700" aria-hidden="true" />}
                        </div>
                        <div className="pb-6">
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">{etapa.rotulo}</p>
                            <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                {formatDateTime(etapa.data)}
                            </p>
                            {etapa.motivo && (
                                <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{etapa.motivo}</p>
                            )}
                        </div>
                    </li>
                );
            })}
            <li className="flex gap-4">
                <div className="flex flex-col items-center">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-500 ring-2 ring-brand-200 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500/30">
                        <span className="size-2.5 rounded-full bg-current" aria-hidden="true" />
                    </span>
                </div>
                <div>
                    <p className="text-theme-xs font-medium text-brand-600 dark:text-brand-400">Situação atual</p>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white/90">{status_atual.public_label}</p>
                    {status_atual.label && status_atual.label !== status_atual.public_label && (
                        <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                            Situação técnica: {status_atual.label}
                        </p>
                    )}
                </div>
            </li>
        </ol>
    );
}

function PublicLinkCard({ link }: { link: string }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(link);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2500);
        } catch {
            setCopied(false);
        }
    }

    return (
        <Card>
            <CardHeader
                title="Link público de acompanhamento"
                description="Compartilhe o andamento sem expor login ou dados sensíveis. O link tem validade e mostra apenas situação, etapas e prazo estimado."
            />
            <CardContent>
                <div className="flex flex-col gap-3 sm:flex-row">
                    <input
                        type="text"
                        readOnly
                        value={link}
                        aria-label="Link público de acompanhamento"
                        onFocus={(event) => event.target.select()}
                        className="h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm text-gray-700 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    <Button size="sm" variant="outline" onClick={copy} className="shrink-0">
                        {copied ? 'Link copiado' : 'Copiar link'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

/** Placeholder acessível enquanto a explicação da decisão por IA carrega. */
function ExplicacaoSkeleton() {
    return (
        <Card>
            <CardHeader
                title="Entenda a decisão (explicação por IA — sugestão)"
                description="Carregando a explicação em linguagem simples…"
            />
            <CardContent>
                <div className="space-y-3" aria-hidden="true">
                    <div className="h-4 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    <div className="h-20 w-full animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                </div>
                <p className="sr-only">Carregando a explicação da decisão gerada por inteligência artificial.</p>
            </CardContent>
        </Card>
    );
}

/**
 * Card "Entenda a decisão" (HU-119): apresenta a explicação da decisão em
 * LINGUAGEM CIDADÃ gerada pela IA, sempre marcada como "sugestão — revise". É a
 * versão leiga da explicabilidade (HU-099), FIEL à decisão registrada — não
 * decide, não reabre o mérito e não promete nada além do que a decisão garante.
 * Indisponível (toggle off/sem provedor/ainda processando) ⇒ não renderiza nada
 * (degradação honesta, sem fachada).
 *
 * Acessibilidade (eMAG/WCAG 2.1 AA): região com aria-live="polite" para anunciar
 * a explicação quando ela chega; texto e rótulos em pt-BR; situação comunicada
 * por TEXTO (não só cor); ícone com texto associado; contraste pelas cores do
 * design system; ressalva clara de que o documento oficial prevalece.
 */
function ExplicacaoCidadaCard({ explicacoes }: { explicacoes: ExplicacaoIa[] }) {
    if (explicacoes.length === 0) {
        return null;
    }

    return (
        <section aria-live="polite" aria-label="Explicação da decisão em linguagem simples">
            <Card>
                <CardHeader
                    title="Entenda a decisão (explicação por IA — sugestão)"
                    description="Explicação da decisão em linguagem simples, gerada pela IA para ajudar no seu entendimento. Em caso de dúvida, vale o documento oficial da decisão."
                />
                <CardContent>
                    <ul className="space-y-4" aria-label="Explicação da decisão sugerida pela IA">
                        {explicacoes.map((sugestao) => {
                            const explicacao =
                                typeof sugestao.output.explicacao === 'string' ? sugestao.output.explicacao : '';
                            const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;
                            const escalada = sugestao.status === 'escalada_humano';

                            return (
                                <li
                                    key={sugestao.id}
                                    className="rounded-xl border border-blue-light-100 bg-blue-light-50 p-4 dark:border-blue-light-500/20 dark:bg-blue-light-500/10"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Badge color="info" size="sm">
                                            Sugestão — revise
                                        </Badge>
                                        <Badge color={escalada ? 'warning' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </div>

                                    {explicacao === '' ? (
                                        <p className="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                            A IA não retornou texto de explicação. Consulte o documento oficial da
                                            decisão para os detalhes.
                                        </p>
                                    ) : (
                                        <p className="mt-3 text-sm whitespace-pre-line text-gray-800 dark:text-white/90">
                                            {explicacao}
                                        </p>
                                    )}

                                    {fonte && (
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Fonte: {fonte}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>

                    <div className="mt-4 flex items-start gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                        <span className="mt-0.5 shrink-0 text-gray-400" aria-hidden="true">
                            <InfoIcon className="size-4 fill-current" />
                        </span>
                        <p>
                            Esta explicação é um apoio ao entendimento gerado por inteligência artificial e pode
                            conter imprecisões. O documento oficial da decisão é o que vale.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </section>
    );
}

export default function Protocolo({ solicitacao, timeline, publicLink, temDecisao, explicacaoIa }: ProtocoloProps) {
    const titulo = solicitacao.protocol_number ?? 'Solicitação de viabilidade';

    return (
        <>
            <Head title={`Protocolo ${titulo}`} />
            <PageHeader
                title={titulo}
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Minhas solicitações', href: '/portal/solicitacoes' },
                ]}
                actions={
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge size="sm" color={statusColor(solicitacao.status.value)}>
                            {solicitacao.status.public_label}
                        </Badge>
                        <Link
                            href={`/portal/solicitacoes/${solicitacao.id}/comunicacoes`}
                            className="text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                        >
                            Histórico de comunicações
                        </Link>
                    </div>
                }
            />

            <div className="flex flex-col gap-4 md:gap-6">
                <Card>
                    <CardHeader
                        title="Andamento do processo"
                        description="Acompanhe as etapas concluídas, a situação atual e o que falta — em linguagem simples."
                    />
                    <CardContent>
                        <div className="flex flex-col gap-6">
                            {timeline.pendencias.length > 0 && (
                                <Alert
                                    variant="warning"
                                    title="Convites com você"
                                    message={timeline.pendencias.join(' ')}
                                />
                            )}

                            <TimelineView timeline={timeline} />

                            {timeline.prazo_estimado && (
                                <div className="flex items-start gap-3 rounded-xl border border-blue-light-100 bg-blue-light-50 p-4 dark:border-blue-light-500/20 dark:bg-blue-light-500/10">
                                    <span className="mt-0.5 text-blue-light-500">
                                        <InfoIcon className="size-5 fill-current" />
                                    </span>
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                            Prazo estimado: {timeline.prazo_estimado.dias} dias
                                        </p>
                                        <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                            {timeline.prazo_estimado.ressalva}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Explicação da decisão em linguagem cidadã (HU-119): só aparece
                    quando há decisão registrada; carregada sob demanda (deferida)
                    quando a seção entra em tela. Sempre "sugestão — revise" e fiel à
                    decisão; indisponível (toggle off/sem provedor) ⇒ não renderiza
                    (degradação honesta, sem fachada). */}
                {temDecisao && (
                    <WhenVisible data="explicacaoIa" buffer={200} fallback={<ExplicacaoSkeleton />}>
                        <ExplicacaoCidadaCard explicacoes={explicacaoIa ?? []} />
                    </WhenVisible>
                )}

                <Card>
                    <CardHeader title="Dados do processo" />
                    <CardContent>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <InfoRow label="Protocolo" value={solicitacao.protocol_number ?? 'Ainda não protocolado'} />
                            <InfoRow label="Tipo de serviço" value={solicitacao.service_type} />
                            <InfoRow label="Empresa" value={solicitacao.company?.legal_name} />
                            <InfoRow label="CNPJ" value={solicitacao.company?.formatted_cnpj} />
                            <InfoRow label="Protocolado em" value={formatDateTime(solicitacao.protocoled_at)} />
                            <InfoRow label="Criado em" value={formatDateTime(solicitacao.created_at)} />
                            <InfoRow
                                label="Área utilizada"
                                value={solicitacao.used_area_m2 ? `${solicitacao.used_area_m2} m²` : null}
                            />
                            <InfoRow
                                label="Endereço"
                                value={[
                                    solicitacao.address.street,
                                    solicitacao.address.number,
                                    solicitacao.address.neighborhood,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || null}
                            />
                        </div>

                        {solicitacao.cnaes.length > 0 && (
                            <div className="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                                <h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">
                                    Atividades (CNAEs)
                                </h4>
                                <div className="flex flex-col gap-2">
                                    {solicitacao.cnaes.map((cnae) => (
                                        <div
                                            key={cnae.formatted_code}
                                            className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800"
                                        >
                                            <div className="flex flex-col">
                                                <span className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {cnae.formatted_code}
                                                </span>
                                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                    {cnae.description}
                                                </span>
                                            </div>
                                            {cnae.is_primary && (
                                                <Badge size="sm" color="primary">
                                                    Principal
                                                </Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {publicLink ? (
                    <PublicLinkCard link={publicLink} />
                ) : (
                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3">
                                <span className="mt-0.5 text-gray-400">
                                    <AlertIcon className="size-5 fill-current" />
                                </span>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    O link público de acompanhamento fica disponível após o protocolo da solicitação.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Protocolo.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
