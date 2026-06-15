import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import DecisionExplanation, { type DecisionExplanationData } from '@/components/auditoria/decision-explanation';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon, InfoIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface PerCnaeItem {
    cnae: string;
    cnae_formatado?: string;
    is_primary?: boolean;
    tendencia?: string;
    tendencia_label?: string;
    fluxo?: string;
    resultado?: string;
    fundamentacao?: string[];
}

type RulesVersions = Record<string, string | null | Record<string, string | null>>;

interface SolicitacaoResumo {
    id: number;
    protocol_number: string | null;
    status: string;
    status_label: string;
    empresa: string | null;
    cnpj: string | null;
    endereco: string;
}

/** Detalhe somente leitura da ViabilityDecision (decisão imutável do expresso). */
interface ViabilityDecisionDetalhe {
    id: number;
    flow: string;
    outcome: string;
    outcome_label: string;
    consolidated_result: string | null;
    consolidated_result_label: string | null;
    tvl_product_number: string | null;
    per_cnae: PerCnaeItem[];
    rules_versions: RulesVersions;
    fundamentacao: string[];
    reason: string | null;
    decided_at: string | null;
    decided_by: string | null;
    is_sistema: boolean;
    solicitacao: SolicitacaoResumo | null;
}

interface CanalTransmissao {
    canal: string;
    status: 'pendente' | 'transmitido' | 'nao_aplicavel' | 'aguardando';
    label: string;
    result: string | null;
    registrado_em: string | null;
}

interface ResultadoExpressoShowProps {
    decisao: ViabilityDecisionDetalhe;
    transmissao: {
        regin: CanalTransmissao;
        sefaz: CanalTransmissao;
    };
    /** Explicabilidade passo a passo (HU-099), projeção pura do decision_trace. */
    explicacao?: DecisionExplanationData | null;
}

function outcomeColor(outcome: string): 'success' | 'error' | 'light' {
    if (outcome === 'deferida') {
        return 'success';
    }

    if (outcome === 'indeferida') {
        return 'error';
    }

    return 'light';
}

function transmissaoColor(status: CanalTransmissao['status']): 'success' | 'warning' | 'light' {
    if (status === 'transmitido') {
        return 'success';
    }

    if (status === 'pendente') {
        return 'warning';
    }

    return 'light';
}

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

const GRUPO_REGRAS: Record<string, string> = {
    territorio: 'Território',
    louos: 'LOUOS',
    risco: 'Risco',
};

/** Achata rules_versions (formato aninhado ou plano) em pares rótulo/valor. */
function flattenRegras(rules: RulesVersions): { label: string; value: string }[] {
    const linhas: { label: string; value: string }[] = [];

    for (const [grupo, valor] of Object.entries(rules)) {
        const rotuloGrupo = GRUPO_REGRAS[grupo] ?? grupo;

        if (valor !== null && typeof valor === 'object') {
            for (const [sub, versao] of Object.entries(valor)) {
                linhas.push({ label: `${rotuloGrupo} · ${sub}`, value: versao ?? '—' });
            }
        } else {
            linhas.push({ label: rotuloGrupo, value: valor ?? '—' });
        }
    }

    return linhas;
}

function DescItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                {label}
            </dt>
            <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{children}</dd>
        </div>
    );
}

export default function ResultadoExpressoShow({ decisao, transmissao, explicacao }: ResultadoExpressoShowProps) {
    const solicitacao = decisao.solicitacao;
    const regras = flattenRegras(decisao.rules_versions);
    // Defensivo: a fundamentação é sempre uma lista vinda do backend, mas um
    // shape inesperado não pode derrubar o SSR da página inteira.
    const fundamentacao = Array.isArray(decisao.fundamentacao) ? decisao.fundamentacao : [];
    const perCnae = Array.isArray(decisao.per_cnae) ? decisao.per_cnae : [];

    return (
        <>
            <Head title={`Resultado ${solicitacao?.protocol_number ?? ''}`.trim()} />
            <PageHeader
                title={solicitacao?.protocol_number ?? 'Resultado do fluxo expresso'}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Resultados do fluxo expresso', href: '/gestao/resultados-expresso' },
                ]}
            />

            <div className="space-y-6">
                <Link
                    href="/gestao/resultados-expresso"
                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                >
                    <ArrowRightIcon className="size-4 rotate-180" />
                    Voltar à lista
                </Link>

                {/* Cabeçalho da decisão */}
                <Card>
                    <CardContent className="border-t-0">
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge color={outcomeColor(decisao.outcome)} size="md">
                                        {decisao.outcome_label}
                                    </Badge>
                                    {solicitacao && (
                                        <Badge color="light" size="sm">
                                            {solicitacao.status_label}
                                        </Badge>
                                    )}
                                </div>
                                {solicitacao && (
                                    <div>
                                        <p className="text-lg font-semibold text-gray-800 dark:text-white/90">
                                            {solicitacao.empresa ?? 'Empresa não informada'}
                                        </p>
                                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                            {solicitacao.cnpj && <span>{solicitacao.cnpj} · </span>}
                                            Protocolo {solicitacao.protocol_number ?? '—'}
                                        </p>
                                        {solicitacao.endereco !== '' && (
                                            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                                {solicitacao.endereco}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>

                            <dl className="grid shrink-0 grid-cols-1 gap-4 sm:text-right">
                                {decisao.tvl_product_number && (
                                    <DescItem label="Número TVL">
                                        <span className="font-medium">{decisao.tvl_product_number}</span>
                                    </DescItem>
                                )}
                                <DescItem label="Decidido em">{formatarDataHora(decisao.decided_at)}</DescItem>
                                <DescItem label="Decidido por">
                                    {decisao.is_sistema ? 'Sistema (fluxo automático)' : (decisao.decided_by ?? '—')}
                                </DescItem>
                            </dl>
                        </div>
                    </CardContent>
                </Card>

                {/* Resultado consolidado */}
                <Card>
                    <CardHeader
                        title="Resultado consolidado"
                        description="Veredito do parecer de viabilidade locacional (consolidação por pior caso — LOUOS RN-009)."
                    />
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge color={outcomeColor(decisao.outcome)} size="sm">
                                {decisao.consolidated_result_label ?? decisao.consolidated_result ?? '—'}
                            </Badge>
                        </div>
                        {decisao.reason && (
                            <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                <span className="font-medium">Motivo:</span> {decisao.reason}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Decisão por CNAE */}
                <Card>
                    <CardHeader
                        title="Decisão por CNAE"
                        description="Veredito e encaminhamento de cada atividade econômica da solicitação."
                    />
                    <CardContent>
                        {perCnae.length === 0 ? (
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                Sem detalhamento por CNAE registrado nesta decisão.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {perCnae.map((item, index) => (
                                    <li
                                        key={`${item.cnae}-${index}`}
                                        className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium text-gray-800 dark:text-white/90">
                                                {item.cnae_formatado ?? item.cnae}
                                            </span>
                                            {item.is_primary && (
                                                <Badge color="info" size="sm">
                                                    Principal
                                                </Badge>
                                            )}
                                            {(item.tendencia_label ?? item.tendencia ?? item.resultado) && (
                                                <Badge color="light" size="sm">
                                                    {item.tendencia_label ?? item.tendencia ?? item.resultado}
                                                </Badge>
                                            )}
                                            {item.fluxo && (
                                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                                                    Fluxo: {item.fluxo}
                                                </span>
                                            )}
                                        </div>
                                        {item.fundamentacao && item.fundamentacao.length > 0 && (
                                            <ul className="mt-2 list-inside list-disc text-theme-sm text-gray-500 dark:text-gray-400">
                                                {item.fundamentacao.map((ref, refIndex) => (
                                                    <li key={refIndex}>{ref}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Versões de regra */}
                    <Card>
                        <CardHeader
                            title="Versões de regra aplicadas"
                            description="As versões vigentes na data da decisão (RN-005) — fundam a explicabilidade."
                        />
                        <CardContent>
                            {regras.length === 0 ? (
                                <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                    Sem versões de regra registradas.
                                </p>
                            ) : (
                                <dl className="space-y-3">
                                    {regras.map((linha, index) => (
                                        <div key={index} className="flex items-center justify-between gap-3">
                                            <dt className="text-theme-sm text-gray-500 dark:text-gray-400">
                                                {linha.label}
                                            </dt>
                                            <dd className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                {linha.value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </CardContent>
                    </Card>

                    {/* Fundamentação legal */}
                    <Card>
                        <CardHeader
                            title="Fundamentação legal"
                            description="Referências normativas que embasam a decisão (produzidas pelos motores LOUOS/risco)."
                        />
                        <CardContent>
                            {fundamentacao.length === 0 ? (
                                <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                    Sem fundamentação registrada.
                                </p>
                            ) : (
                                <ul className="list-inside list-disc space-y-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                    {fundamentacao.map((ref, index) => (
                                        <li key={index}>{ref}</li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Explicabilidade passo a passo (HU-099) — só quando há projeção */}
                {explicacao && (
                    <Card>
                        <CardHeader
                            title="Explicabilidade da decisão"
                            description="Passo a passo de como a viabilidade foi decidida (RN-005) — projeção do que foi registrado, sem reexecutar o motor."
                        />
                        <CardContent>
                            <DecisionExplanation explicacao={explicacao} />
                        </CardContent>
                    </Card>
                )}

                {/* Transmissão Regin/SEFAZ */}
                <Card>
                    <CardHeader
                        title="Transmissão aos integradores"
                        description="Status real dos canais oficiais (Regin/Junta e SEFAZ) lido da trilha de integrações."
                    />
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <CanalCard canal={transmissao.regin} />
                            <CanalCard canal={transmissao.sefaz} />
                        </div>

                        <div className="mt-4 flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                            <InfoIcon className="size-5 shrink-0 fill-current text-blue-light-500" />
                            <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                Canal oficial ao cidadão: <strong>Regin/SEFAZ</strong>. A emissão do documento (TVL/PDF)
                                é função da retaguarda na próxima fase (HU-132); aqui a decisão é apenas consultada.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/** Cartão de status de um canal de transmissão (somente leitura). */
function CanalCard({ canal }: { canal: CanalTransmissao }) {
    return (
        <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <div className="flex items-center justify-between gap-3">
                <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{canal.canal}</span>
                <Badge color={transmissaoColor(canal.status)} size="sm">
                    {canal.label}
                </Badge>
            </div>
            {canal.registrado_em && (
                <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                    Registrado em {formatarDataHora(canal.registrado_em)}
                </p>
            )}
        </div>
    );
}

ResultadoExpressoShow.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
