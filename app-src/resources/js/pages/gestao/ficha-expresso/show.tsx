import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EtapasMotorCard, type EtapaMotor } from '@/components/analise/etapas-motor';
import { QuadrosChecklist, type QuadroChecklist } from '@/components/analise/quadros-checklist';
import { rotuloFluxoRisco } from '@/components/analise/processo-ui';
import DecisionExplanation, { type DecisionExplanationData } from '@/components/auditoria/decision-explanation';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon, CheckCircleIcon, InfoIcon } from '@/components/icons';
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
    fundamentacao?: string[];
    quadros?: QuadroChecklist[] | null;
}

interface Decisao {
    outcome: string;
    outcome_label: string;
    consolidated_result_label: string | null;
    tvl_product_number: string | null;
    per_cnae: PerCnaeItem[];
    reason: string | null;
    decided_at: string | null;
    decided_by: string | null;
    is_sistema: boolean;
}

interface Localizacao {
    logradouro: string | null;
    numero_metrico: string | null;
    bairro: string | null;
    cep: string | null;
    zona: string | null;
    via: string | null;
}

interface FichaExpressoProps {
    processo: {
        id: number;
        protocol_number: string | null;
        status: string;
        status_label: string;
        empresa: string | null;
        cnpj: string | null;
    };
    decisao: Decisao;
    explicacao: DecisionExplanationData;
    etapasMotor: EtapaMotor[];
    localizacao: Localizacao;
    podeReprocessar: boolean;
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

function resultadoColor(tendencia: string | undefined): 'success' | 'error' | 'warning' | 'light' {
    if (tendencia === 'permitido' || tendencia === 'permitido_com_condicoes') {
        return 'success';
    }

    if (tendencia === 'nao_permitido') {
        return 'error';
    }

    if (tendencia === 'pendente') {
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

function fundamentosDe(item: PerCnaeItem): string[] {
    if (!Array.isArray(item.fundamentacao)) {
        return [];
    }

    return item.fundamentacao.filter((texto) => typeof texto === 'string' && texto.trim() !== '');
}

function DescItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">{label}</dt>
            <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{children}</dd>
        </div>
    );
}

export default function FichaExpressoShow({
    processo,
    decisao,
    explicacao,
    etapasMotor,
    localizacao,
    podeReprocessar,
}: FichaExpressoProps) {
    const perCnae = Array.isArray(decisao.per_cnae) ? decisao.per_cnae : [];
    const titulo = `Ficha ${processo.protocol_number ?? `#${processo.id}`}`;

    return (
        <>
            <Head title={titulo} />
            <PageHeader
                title={titulo}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                    { label: processo.protocol_number ?? 'Processo', href: `/gestao/processos/${processo.id}` },
                ]}
                actions={
                    <Badge color={outcomeColor(decisao.outcome)} size="sm">
                        {decisao.outcome_label}
                    </Badge>
                }
            />

            <div className="space-y-6">
                <Link
                    href={`/gestao/processos/${processo.id}`}
                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                >
                    <ArrowRightIcon className="size-4 rotate-180" />
                    Voltar ao processo
                </Link>

                <div className="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    {decisao.outcome === 'deferida' ? (
                        <CheckCircleIcon className="size-5 shrink-0 fill-current text-success-500" />
                    ) : (
                        <InfoIcon className="size-5 shrink-0 fill-current text-gray-500" />
                    )}
                    <div className="space-y-1">
                        <p className="text-theme-sm text-gray-700 dark:text-gray-200">
                            Decisão do fluxo expresso, somente leitura. O sistema concluiu em{' '}
                            {formatarDataHora(decisao.decided_at)}
                            {decisao.is_sistema ? ' automaticamente' : decisao.decided_by ? ` por ${decisao.decided_by}` : ''}.
                        </p>
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            {processo.empresa ?? 'Empresa não informada'}
                            {processo.cnpj ? ` · ${processo.cnpj}` : ''}
                            {decisao.tvl_product_number ? ` · Viabilidade ${decisao.tvl_product_number}` : ''}
                            {decisao.consolidated_result_label ? ` · ${decisao.consolidated_result_label}` : ''}
                        </p>
                    </div>
                </div>

                {decisao.reason && (
                    <Card>
                        <CardHeader title="Motivo do resultado" description="Texto gravado na decisão do fluxo expresso." />
                        <CardContent>
                            <p className="text-theme-sm text-gray-700 dark:text-gray-200">{decisao.reason}</p>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader
                        title="Resultado por atividade (CNAE)"
                        description="Veredito de cada atividade econômica desta solicitação."
                    />
                    <CardContent>
                        {perCnae.length === 0 ? (
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                Esta decisão não registrou o resultado por CNAE.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {perCnae.map((item, indice) => {
                                    const fundamentos = fundamentosDe(item);

                                    return (
                                        <li
                                            key={`${item.cnae}-${indice}`}
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
                                                <Badge color={resultadoColor(item.tendencia)} size="sm">
                                                    {item.tendencia_label ?? item.tendencia ?? 'Sem veredito'}
                                                </Badge>
                                                {item.fluxo && (
                                                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                                                        Encaminhamento por risco: {rotuloFluxoRisco(item.fluxo)}
                                                    </span>
                                                )}
                                            </div>
                                            {(item.quadros?.length ?? 0) > 0 && <QuadrosChecklist quadros={item.quadros ?? []} />}
                                            {fundamentos.length > 0 && (
                                                <ul className="mt-3 list-inside list-disc text-theme-sm text-gray-600 dark:text-gray-300">
                                                    {fundamentos.map((texto, textoIndice) => (
                                                        <li key={textoIndice}>{texto}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <EtapasMotorCard processoId={processo.id} etapas={etapasMotor ?? []} podeReprocessar={podeReprocessar} />

                <Card>
                    <CardHeader title="Localização" description="Endereço e enquadramento territorial usados na decisão." />
                    <CardContent>
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <DescItem label="Logradouro">{localizacao?.logradouro ?? '—'}</DescItem>
                            <DescItem label="Número">{localizacao?.numero_metrico ?? '—'}</DescItem>
                            <DescItem label="Bairro">{localizacao?.bairro ?? '—'}</DescItem>
                            <DescItem label="CEP">{localizacao?.cep ?? '—'}</DescItem>
                            <DescItem label="Zona">{localizacao?.zona ?? '—'}</DescItem>
                            <DescItem label="Via">{localizacao?.via ?? '—'}</DescItem>
                        </dl>
                    </CardContent>
                </Card>

                {explicacao && (
                    <Card>
                        <CardHeader
                            title="O que o sistema fez"
                            description="Passo a passo gravado na decisão, por CNAE. O motor não é reexecutado nesta consulta."
                        />
                        <CardContent>
                            <DecisionExplanation explicacao={explicacao} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

FichaExpressoShow.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
