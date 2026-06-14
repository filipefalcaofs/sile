import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

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

export interface Quadro7 {
    status: DimensaoStatus;
    grupo: string | null;
    subgrupo: string | null;
    faixa: { area_min: number; area_max: number | null } | null;
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
    quadro7: Quadro7;
    quadro10: Record<string, unknown>;
    quadro11: Record<string, unknown>;
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
    veredito_locacional: VereditoLocacional;
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

const RISCO_SANITARIO_LABELS: Record<string, string> = {
    baixo: 'Baixo Risco',
    medio: 'Médio Risco',
    alto: 'Alto Risco',
};

/** Humaniza um value de enum (ex.: `baixo_a` → `Baixo a`) como fallback honesto. */
function humanizar(valor: string): string {
    const texto = valor.replace(/_/g, ' ').trim();

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** Cor do badge por nível de risco — diferenciação visual, nunca classificação inventada. */
function corPorNivel(nivel: string | null): 'success' | 'warning' | 'error' | 'light' {
    if (nivel === null) {
        return 'light';
    }
    if (nivel === 'alto') {
        return 'error';
    }
    if (nivel === 'medio') {
        return 'warning';
    }

    return 'success';
}

function faixaLabel(faixa: Quadro7['faixa']): string | null {
    if (faixa === null) {
        return null;
    }
    if (faixa.area_max === null) {
        return `a partir de ${faixa.area_min} m²`;
    }

    return `de ${faixa.area_min} a ${faixa.area_max} m²`;
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
                            {dimensao.motivo ?? 'Base pendente SEDUR.'}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Renderiza o resultado REAL da consulta de viabilidade (HU-057/058/059) sem
 * fachada: o veredito locacional vem propagado do motor LOUOS — quando
 * `pendente`, sempre exibe o motivo (ex.: zona pendente SEDUR) e NUNCA mostra
 * Permitido/Não permitido sem zona. Reutilizável pelo histórico (07-09).
 */
export function ResultadoViabilidade({ result }: { result: ResultadoConsulta }) {
    const veredito = result.veredito_locacional;
    const estilo = VEREDITO_STYLES[veredito.resultado];
    const { municipal, sanitario, encaminhamento } = result.risco;
    const { quadro7 } = result.enquadramento;
    const motivoVeredito =
        veredito.motivo ??
        (veredito.resultado === 'pendente' ? 'Depende de análise técnica da SEDUR.' : null);

    const sanitarioLabel =
        sanitario.nivel_final !== null
            ? (RISCO_SANITARIO_LABELS[sanitario.nivel_final] ?? humanizar(sanitario.nivel_final))
            : null;

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            <section aria-label="Veredito locacional" className={`rounded-2xl border p-5 sm:p-6 ${estilo.container}`}>
                <span
                    className={`inline-flex items-center rounded-full px-3 py-1 text-theme-xs font-semibold uppercase tracking-wide ${estilo.chip}`}
                >
                    Viabilidade locacional
                </span>
                <h2 className="mt-3 text-xl font-semibold text-gray-800 dark:text-white/90">{veredito.label}</h2>
                {motivoVeredito && (
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">{motivoVeredito}</p>
                )}
            </section>

            <div className="grid gap-4 md:gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Classificação de risco" description="Risco municipal e sanitário e o encaminhamento da atividade." />
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
                                    {encaminhamento.fluxo === 'expresso' ? (
                                        <Badge color="success">Fluxo expresso</Badge>
                                    ) : (
                                        <Badge color="warning">Análise técnica</Badge>
                                    )}
                                </div>
                                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{encaminhamento.motivo}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Enquadramento de uso (Quadro 7)" description="Grupo de uso da LOUOS pela faixa de área da atividade." />
                    <CardContent>
                        {quadro7.status === 'identificado' && (
                            <div className="flex flex-col gap-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Grupo de uso</span>
                                    <span className="text-right text-sm text-gray-800 dark:text-white/90">
                                        {quadro7.grupo ?? 'Identificado'}
                                        {quadro7.subgrupo && (
                                            <span className="text-gray-500 dark:text-gray-400"> · {quadro7.subgrupo}</span>
                                        )}
                                    </span>
                                </div>
                                {faixaLabel(quadro7.faixa) && (
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Faixa de área</span>
                                        <span className="text-sm text-gray-800 dark:text-white/90">{faixaLabel(quadro7.faixa)}</span>
                                    </div>
                                )}
                            </div>
                        )}
                        {quadro7.status === 'nao_encontrado' && (
                            <div className="flex flex-col items-start gap-2">
                                <Badge color="light">Sem enquadramento</Badge>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {quadro7.motivo ?? 'CNAE sem enquadramento no Quadro 7 — segue para análise técnica.'}
                                </p>
                            </div>
                        )}
                        {quadro7.status === 'indisponivel' && (
                            <div className="flex flex-col items-start gap-2">
                                <Badge color="warning">Indisponível</Badge>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {quadro7.motivo ?? 'Quadro 7 indisponível — base pendente SEDUR.'}
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
                        description="Bairro e via vêm das camadas oficiais. Zona e lote ficam indisponíveis enquanto a base estiver pendente SEDUR."
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
                                                {result.territorio.restricoes.motivo ?? 'Base pendente SEDUR.'}
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
        </div>
    );
}
