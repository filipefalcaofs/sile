import { usePage } from '@inertiajs/react';
import { rotuloFluxoRisco } from '@/components/analise/processo-ui';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { rotulo } from '@/lib/vocabulario';
import type { SharedProps } from '@/types';

/** Veredito locacional propagado do motor LOUOS (HU-044). */
export type VereditoResultado = 'permitido' | 'permitido_com_condicoes' | 'nao_permitido' | 'pendente';

/** Status de uma dimensão territorial (Fase 4). */
export type DimensaoStatus = 'identificado' | 'nao_encontrado' | 'indisponivel';

/** Status de uma dimensão de risco (Fase 6). */
export type RiscoStatus = 'classificado' | 'nao_classificado';

export interface VereditoLocacional {
    resultado: VereditoResultado;
    label: string;
    motivo: string | null;
}

/** Quanto o resultado da consulta é confiável: só `conclusiva` carrega veredito. */
export type EstadoConfiabilidade = 'conclusiva' | 'incompleta' | 'inconclusiva';

/** Dado que o cidadão ainda precisa informar. `itens` lista os campos de `vagas`. */
export interface PendenciaConsulta {
    campo: string;
    rotulo: string;
    itens?: string[];
}

export interface ConfiabilidadeConsulta {
    estado: EstadoConfiabilidade;
    label: string;
    pendencias: PendenciaConsulta[];
    motivo: string | null;
}

export interface EnquadramentoUso {
    status: DimensaoStatus;
    grupo: string | null;
    subgrupo: string | null;
    codigo_louos?: string | null;
    motivo?: string | null;
    versao_regra?: string | null;
}

export interface ConsolidadoEnquadramento {
    resultado: VereditoResultado;
    fundamentacao?: string[];
    condicionantes?: unknown[];
    motivo?: string | null;
}

export interface Enquadramento {
    enquadramento: EnquadramentoUso;
    quadro10: Record<string, unknown>;
    quadro11a: Record<string, unknown>;
    consolidado: ConsolidadoEnquadramento;
    versoes: Record<string, string | null>;
}

export interface RiscoMunicipalDimensao {
    status: RiscoStatus;
    nivel: string | null;
    nivel_label: string | null;
    condicionantes?: unknown[];
    versao_regras?: string | null;
}

export interface RiscoSanitarioDimensao {
    status: RiscoStatus;
    nivel_original: string | null;
    nivel_final: string | null;
    reclassificado: boolean;
    condicionantes_perguntas?: unknown[];
    versao_regras?: string | null;
}

export interface Encaminhamento {
    fluxo: 'expresso' | 'analise';
    dimensao_decisiva: string;
    motivo: string;
    gatilhos_acionados?: unknown[];
}

export interface Risco {
    municipal: RiscoMunicipalDimensao;
    sanitario: RiscoSanitarioDimensao;
    encaminhamento: Encaminhamento;
    fundamentacao: string[];
    versoes: Record<string, string | null>;
}

export interface DimensaoTerritorial {
    status: DimensaoStatus;
    nome?: string | null;
    propriedades?: Record<string, unknown> | null;
    motivo?: string | null;
    versao_camada?: string | null;
    distancia_m?: number | null;
}

export interface RestricoesTerritoriais {
    status: DimensaoStatus;
    itens?: Array<{ nome?: string | null; propriedades?: Record<string, unknown> | null }>;
    motivo?: string | null;
    versao_camada?: string | null;
}

export interface Territorio {
    bairro: DimensaoTerritorial;
    via: DimensaoTerritorial;
    zona: DimensaoTerritorial;
    lote: DimensaoTerritorial;
    restricoes: RestricoesTerritoriais;
}

export interface Geocode {
    latitude: number;
    longitude: number;
    display_name: string;
    confidence: number | null;
    address: Record<string, unknown>;
}

export interface EntradaConsulta {
    tipo: 'endereco' | 'cnae' | 'inscricao';
    cnae: string;
    cnae_formatado?: string;
    area?: number | null;
    endereco?: string | null;
    inscricao?: string | null;
}

/** Contrato JSON de ConsultaViabilidadeResult::toArray() (07-04/07-06). */
export interface ResultadoConsulta {
    entrada: EntradaConsulta;
    geocode: Geocode | null;
    territorio: Territorio | null;
    enquadramento: Enquadramento;
    risco: Risco;
    restricoes: RestricoesTerritoriais | null;
    veredito_locacional: VereditoLocacional | null;
    /** Ausente em snapshots antigos do histórico. */
    confiabilidade?: ConfiabilidadeConsulta;
    /** Aviso fixo de simulação; ausente em snapshots antigos do histórico. */
    aviso_simulacao?: string;
    fundamentacao: string[];
    avisos: string[];
    versoes: Record<string, Record<string, string | null>>;
}

/**
 * Estilo do veredito por resultado. `pendente` NUNCA herda a aparência de
 * permitido/não permitido — é a degradação honesta (sem zona oficial, segue
 * para análise técnica).
 */
const VEREDITO_STYLES: Record<VereditoResultado, { container: string; chip: string }> = {
    permitido: {
        container: 'border-success-500 bg-success-50 dark:border-success-500/30 dark:bg-success-500/15',
        chip: 'bg-success-500 text-white',
    },
    permitido_com_condicoes: {
        container: 'border-warning-500 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/15',
        chip: 'bg-warning-500 text-white',
    },
    nao_permitido: {
        container: 'border-error-500 bg-error-50 dark:border-error-500/30 dark:bg-error-500/15',
        chip: 'bg-error-500 text-white',
    },
    pendente: {
        container: 'border-blue-light-500 bg-blue-light-50 dark:border-blue-light-500/30 dark:bg-blue-light-500/15',
        chip: 'bg-blue-light-500 text-white',
    },
};

/** Humaniza um value de enum quando o catálogo compartilhado não cobre. */
function humanizar(valor: string): string {
    return valor.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}

/** Cor do badge por nível de risco — diferenciação visual, nunca classificação inventada. */
function corPorNivel(nivel: string | null): 'success' | 'warning' | 'error' | 'light' {
    if (nivel === null) {
        return 'light';
    }
    if (nivel === 'alto') {
        return 'error';
    }
    if (nivel === 'medio' || nivel === 'baixo_b') {
        return 'warning';
    }

    return 'success';
}

/** Linha de uma dimensão territorial: real quando identificada, honesta quando indisponível. */
function DimensaoItem({ rotulo, dimensao }: { rotulo: string; dimensao: DimensaoTerritorial }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{rotulo}</span>
            <div className="text-right">
                {dimensao.status === 'identificado' && (
                    <span className="text-sm text-gray-800 dark:text-white/90">
                        {dimensao.nome ?? 'Identificado'}
                        {typeof dimensao.distancia_m === 'number' && (
                            <span className="ml-1 text-gray-500 dark:text-gray-400">
                                (a {Math.round(dimensao.distancia_m)} m)
                            </span>
                        )}
                    </span>
                )}
                {dimensao.status === 'nao_encontrado' && (
                    <span className="text-sm text-gray-500 dark:text-gray-400">Não encontrado neste ponto</span>
                )}
                {dimensao.status === 'indisponivel' && (
                    <div className="flex flex-col items-end gap-1">
                        <Badge color="warning">Indisponível</Badge>
                        <span className="max-w-xs text-xs text-gray-500 dark:text-gray-400">
                            {dimensao.motivo ?? 'Base pendente de disponibilização pela SEDUR.'}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

const CONFIABILIDADE_STYLES: Record<EstadoConfiabilidade, { container: string; chip: string }> = {
    conclusiva: VEREDITO_STYLES.permitido,
    incompleta: VEREDITO_STYLES.permitido_com_condicoes,
    inconclusiva: VEREDITO_STYLES.pendente,
};

/**
 * Estado de confiabilidade da consulta: dado faltando (incompleta) ou motivo da
 * inconclusão, sem orientar próximo passo — a consulta é só informativa.
 */
function BlocoConfiabilidade({ confiabilidade }: { confiabilidade: ConfiabilidadeConsulta }) {
    const estilo = CONFIABILIDADE_STYLES[confiabilidade.estado];

    if (confiabilidade.estado === 'conclusiva') {
        return (
            <p aria-label="Confiabilidade da consulta" className="text-sm font-medium text-gray-600 dark:text-gray-300">
                {confiabilidade.label}
            </p>
        );
    }

    return (
        <section aria-label="Confiabilidade da consulta" className={`rounded-2xl border p-5 sm:p-6 ${estilo.container}`}>
            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{confiabilidade.label}</h2>
            {confiabilidade.estado === 'incompleta' && (
                <ul className="mt-3 flex list-disc flex-col gap-1 pl-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {confiabilidade.pendencias.map((pendencia) => (
                        <li key={`${pendencia.campo}-${pendencia.rotulo}`}>{pendencia.rotulo}</li>
                    ))}
                </ul>
            )}
            {confiabilidade.estado === 'inconclusiva' && confiabilidade.motivo && (
                <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">{confiabilidade.motivo}</p>
            )}
        </section>
    );
}

/**
 * Renderiza o resultado REAL da consulta de viabilidade (HU-057/058/059) sem
 * fachada: o veredito locacional vem propagado do motor LOUOS — quando
 * `pendente`, sempre exibe o motivo (ex.: zona pendente SEDUR) e NUNCA mostra
 * Permitido/Não permitido sem zona. Reutilizável pelo histórico (07-09).
 */
export function ResultadoViabilidade({ result }: { result: ResultadoConsulta }) {
    const { vocabulario } = usePage<SharedProps>().props;
    const veredito = result.veredito_locacional;
    const confiabilidade = result.confiabilidade;
    const vereditoVisivel = veredito !== null && (confiabilidade === undefined || confiabilidade.estado === 'conclusiva');
    const { municipal, sanitario, encaminhamento } = result.risco;
    const { enquadramento } = result.enquadramento;
    const motivoVeredito = veredito
        ? (veredito.motivo ?? (veredito.resultado === 'pendente' ? 'Depende de análise técnica da SEDUR.' : null))
        : null;

    const sanitarioLabel =
        sanitario.nivel_final !== null
            ? rotulo(vocabulario.risco_sanitario, sanitario.nivel_final, humanizar(sanitario.nivel_final))
            : null;

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            {confiabilidade && <BlocoConfiabilidade confiabilidade={confiabilidade} />}

            {vereditoVisivel && veredito && (
                <section aria-label="Veredito locacional" className={`rounded-2xl border p-5 sm:p-6 ${VEREDITO_STYLES[veredito.resultado].container}`}>
                    <span
                        className={`inline-flex items-center rounded-full px-3 py-1 text-theme-xs font-semibold uppercase tracking-wide ${VEREDITO_STYLES[veredito.resultado].chip}`}
                    >
                        Viabilidade locacional
                    </span>
                    <h2 className="mt-3 text-xl font-semibold text-gray-800 dark:text-white/90">{veredito.label}</h2>
                    {motivoVeredito && (
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">{motivoVeredito}</p>
                    )}
                </section>
            )}

            <div className="grid gap-4 md:gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Classificação de risco" description="Riscos municipal e sanitário, além do encaminhamento da atividade." />
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Risco municipal</span>
                                {municipal.status === 'classificado' && municipal.nivel_label ? (
                                    <Badge color={corPorNivel(municipal.nivel)}>{municipal.nivel_label}</Badge>
                                ) : (
                                    <Badge color="light">Não classificado</Badge>
                                )}
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Risco sanitário</span>
                                {sanitario.status === 'classificado' && sanitarioLabel ? (
                                    <Badge color={corPorNivel(sanitario.nivel_final)}>{sanitarioLabel}</Badge>
                                ) : (
                                    <Badge color="light">Não classificado</Badge>
                                )}
                            </div>

                            <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Encaminhamento</span>
                                    <Badge color={encaminhamento.fluxo === 'expresso' ? 'success' : 'warning'}>
                                        {rotuloFluxoRisco(encaminhamento.fluxo)}
                                    </Badge>
                                </div>
                                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{encaminhamento.motivo}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Enquadramento de uso" description="Grupo de uso da LOUOS pelo ramo vigente da planilha de regras." />
                    <CardContent>
                        {enquadramento.status === 'identificado' && (
                            <div className="flex flex-col gap-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Grupo de uso</span>
                                    <span className="text-right text-sm text-gray-800 dark:text-white/90">
                                        {enquadramento.grupo ?? 'Identificado'}
                                        {enquadramento.subgrupo && (
                                            <span className="text-gray-500 dark:text-gray-400"> · {enquadramento.subgrupo}</span>
                                        )}
                                    </span>
                                </div>
                                {enquadramento.codigo_louos && (
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Código LOUOS</span>
                                        <span className="text-sm text-gray-800 dark:text-white/90">{enquadramento.codigo_louos}</span>
                                    </div>
                                )}
                                {enquadramento.versao_regra && (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Versão {enquadramento.versao_regra}</p>
                                )}
                            </div>
                        )}
                        {enquadramento.status === 'nao_encontrado' && (
                            <div className="flex flex-col items-start gap-2">
                                <Badge color="light">Sem enquadramento</Badge>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {enquadramento.motivo ?? 'CNAE sem ramo na planilha vigente — segue para análise técnica.'}
                                </p>
                            </div>
                        )}
                        {enquadramento.status === 'indisponivel' && (
                            <div className="flex flex-col items-start gap-2">
                                <Badge color="warning">Indisponível</Badge>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {enquadramento.motivo ?? 'Planilha de tratamento indisponível.'}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {result.territorio && (
                <Card>
                    <CardHeader
                        title="Território"
                        description="Bairro e via vêm das camadas oficiais. Zona e lote ficam indisponíveis enquanto a base estiver pendente de disponibilização pela SEDUR."
                    />
                    <CardContent>
                        <div className="flex flex-col">
                            <DimensaoItem rotulo="Bairro" dimensao={result.territorio.bairro} />
                            <DimensaoItem rotulo="Via mais próxima" dimensao={result.territorio.via} />
                            <DimensaoItem rotulo="Zona urbanística" dimensao={result.territorio.zona} />
                            <DimensaoItem rotulo="Lote" dimensao={result.territorio.lote} />
                            <div className="flex flex-wrap items-start justify-between gap-2 py-3">
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Restrições</span>
                                <div className="text-right">
                                    {result.territorio.restricoes.status === 'identificado' &&
                                    result.territorio.restricoes.itens &&
                                    result.territorio.restricoes.itens.length > 0 ? (
                                        <ul className="flex flex-col items-end gap-1">
                                            {result.territorio.restricoes.itens.map((item, indice) => (
                                                <li key={item.nome ?? indice} className="text-sm text-gray-800 dark:text-white/90">
                                                    {item.nome ?? 'Restrição'}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : result.territorio.restricoes.status === 'indisponivel' ? (
                                        <div className="flex flex-col items-end gap-1">
                                            <Badge color="warning">Indisponível</Badge>
                                            <span className="max-w-xs text-xs text-gray-500 dark:text-gray-400">
                                                {result.territorio.restricoes.motivo ?? 'Base pendente de disponibilização pela SEDUR.'}
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="text-sm text-gray-500 dark:text-gray-400">Nenhuma restrição neste ponto</span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {result.fundamentacao.length > 0 && (
                <Card>
                    <CardHeader title="Fundamentação legal" description="Referências aplicadas pelos motores nesta consulta." />
                    <CardContent>
                        <ul className="flex list-disc flex-col gap-2 pl-5">
                            {result.fundamentacao.map((referencia) => (
                                <li key={referencia} className="text-sm text-gray-600 dark:text-gray-300">
                                    {referencia}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            {result.avisos.length > 0 && (
                <div className="flex flex-col gap-3">
                    {result.avisos.map((aviso) => (
                        <Alert key={aviso} variant="warning" title="Aviso" message={aviso} />
                    ))}
                </div>
            )}

            {result.aviso_simulacao && (
                <p className="text-sm text-gray-500 dark:text-gray-400">{result.aviso_simulacao}</p>
            )}
        </div>
    );
}
