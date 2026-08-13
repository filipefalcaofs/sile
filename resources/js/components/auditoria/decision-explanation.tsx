import type { ReactNode } from 'react';
import { CheckCircleIcon, InfoIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

interface Desfecho {
    outcome: string | null;
    outcome_label: string | null;
    consolidated_result: string | null;
    consolidated_result_label: string | null;
}

/** Passo da decisão (shape uniforme do trace 12-02 / projeção 12-05). */
interface Passo {
    passo: string | null;
    titulo: string | null;
    registrado: boolean;
    entrada: unknown;
    resultado_parcial: unknown;
    motivo: string | null;
    versao_regra: string | null;
    fundamentacao?: unknown;
}

interface BlocoCnae {
    cnae: string | null;
    cnae_formatado: string | null;
    is_primary: boolean;
    origem: string | null;
    passos: Passo[];
}

type RulesVersions = Record<string, string | null | Record<string, string | null>>;

/** Shape projetado pela DecisionExplanationResource (HU-099), consumido na UI. */
export interface DecisionExplanationData {
    legado: boolean;
    desfecho: Desfecho | null;
    por_cnae: BlocoCnae[];
    fundamentacao: string[];
    rules_versions: RulesVersions;
}

const GRUPO_REGRAS: Record<string, string> = {
    territorio: 'Território',
    louos: 'LOUOS',
    risco: 'Risco',
};

const ORIGEM_LABEL: Record<string, string> = {
    motor: 'Decisão automática (motor)',
    analista: 'Decisão de analista',
};

function outcomeColor(outcome: string | null): BadgeColor {
    if (outcome === 'deferida') {
        return 'success';
    }

    if (outcome === 'indeferida') {
        return 'error';
    }

    return 'light';
}

/** Coage um valor do snapshot a texto legível, sem despejar estruturas cruas. */
function valorLegivel(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'sim' : 'não';
    }

    if (typeof valor === 'string' || typeof valor === 'number') {
        return String(valor);
    }

    if (Array.isArray(valor)) {
        const itens = valor.filter((item) => typeof item === 'string' || typeof item === 'number');

        if (itens.length === valor.length) {
            return itens.join(', ');
        }
    }

    try {
        return JSON.stringify(valor);
    } catch {
        return '—';
    }
}

/** Humaniza a chave (snake_case → texto) para os rótulos de entrada/resultado. */
function rotular(chave: string): string {
    const texto = chave.replace(/_/g, ' ');

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** Normaliza um valor a um registro chave→valor, ou null se não for objeto. */
function comoRegistro(valor: unknown): Record<string, unknown> | null {
    if (valor !== null && typeof valor === 'object' && !Array.isArray(valor)) {
        return valor as Record<string, unknown>;
    }

    return null;
}

/** Achata rules_versions (aninhado ou plano) em pares rótulo/valor. */
function flattenRegras(rules: RulesVersions): { label: string; value: string }[] {
    const linhas: { label: string; value: string }[] = [];

    for (const [grupo, valor] of Object.entries(rules ?? {})) {
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

/**
 * Explicabilidade passo a passo de uma decisão de viabilidade (HU-099).
 * Renderiza a projeção PURA do decision_trace por CNAE na ordem canônica
 * (entrada → risco → LOUOS Quadro 7/10/11/11A → consolidação → desfecho),
 * marcando honestamente os passos do legado não registrados — nunca inventa.
 * É somente apresentação: itera o shape uniforme genericamente e é defensivo
 * para não quebrar o SSR com props vazias/nulas/legadas.
 */
export default function DecisionExplanation({ explicacao }: { explicacao: DecisionExplanationData }) {
    const porCnae = Array.isArray(explicacao?.por_cnae) ? explicacao.por_cnae : [];
    const fundamentacao = Array.isArray(explicacao?.fundamentacao) ? explicacao.fundamentacao : [];
    const regras = flattenRegras(explicacao?.rules_versions ?? {});
    const desfecho = explicacao?.desfecho ?? null;
    const legado = explicacao?.legado ?? false;

    return (
        <div className="space-y-5">
            {legado && (
                <div className="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-900/20">
                    <InfoIcon className="size-5 shrink-0 fill-current text-warning-500" />
                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                        Decisão anterior ao registro estruturado do passo a passo. Os passos do motor que não foram
                        snapshotados aparecem marcados como <strong>"não registrado nesta decisão"</strong> — o sistema
                        não recomputa nem inventa valores.
                    </p>
                </div>
            )}

            {desfecho && (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Desfecho
                    </span>
                    <Badge color={outcomeColor(desfecho.outcome)} size="sm">
                        {desfecho.outcome_label ?? desfecho.outcome ?? '—'}
                    </Badge>
                    {(desfecho.consolidated_result_label ?? desfecho.consolidated_result) && (
                        <Badge color="light" size="sm">
                            {desfecho.consolidated_result_label ?? desfecho.consolidated_result}
                        </Badge>
                    )}
                </div>
            )}

            {porCnae.length === 0 ? (
                <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                    Sem passo a passo registrado nesta decisão.
                </p>
            ) : (
                <div className="space-y-5">
                    {porCnae.map((bloco, index) => (
                        <BlocoCnaeView key={`${bloco.cnae ?? 'cnae'}-${index}`} bloco={bloco} />
                    ))}
                </div>
            )}

            {(regras.length > 0 || fundamentacao.length > 0) && (
                <div className="grid gap-5 border-t border-gray-100 pt-5 dark:border-gray-800 sm:grid-cols-2">
                    {regras.length > 0 && (
                        <div>
                            <h4 className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                Versões de regra aplicadas
                            </h4>
                            <dl className="mt-2 space-y-2">
                                {regras.map((linha, idx) => (
                                    <div key={idx} className="flex items-center justify-between gap-3">
                                        <dt className="text-theme-sm text-gray-500 dark:text-gray-400">{linha.label}</dt>
                                        <dd className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                            {linha.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                    {fundamentacao.length > 0 && (
                        <div>
                            <h4 className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                Fundamentação legal
                            </h4>
                            <ul className="mt-2 list-inside list-disc space-y-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                {fundamentacao.map((ref, idx) => (
                                    <li key={idx}>{String(ref)}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/** Bloco de um CNAE: cabeçalho + linha do tempo dos passos na ordem registrada. */
function BlocoCnaeView({ bloco }: { bloco: BlocoCnae }) {
    const passos = Array.isArray(bloco?.passos) ? bloco.passos : [];

    return (
        <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium text-gray-800 dark:text-white/90">
                    {bloco.cnae_formatado ?? bloco.cnae ?? 'CNAE'}
                </span>
                {bloco.is_primary && (
                    <Badge color="info" size="sm">
                        Principal
                    </Badge>
                )}
                {bloco.origem && (
                    <Badge color="light" size="sm">
                        {ORIGEM_LABEL[bloco.origem] ?? bloco.origem}
                    </Badge>
                )}
            </div>

            {passos.length === 0 ? (
                <p className="mt-3 text-theme-sm text-gray-500 dark:text-gray-400">
                    Sem passos registrados para este CNAE.
                </p>
            ) : (
                <ol className="mt-4 space-y-4 border-l border-gray-200 pl-5 dark:border-gray-800">
                    {passos.map((passo, index) => (
                        <PassoView key={`${passo.passo ?? 'passo'}-${index}`} passo={passo} />
                    ))}
                </ol>
            )}
        </div>
    );
}

/** Um passo da decisão: título, marca de registro/legado e o que foi gravado. */
function PassoView({ passo }: { passo: Passo }) {
    const registrado = passo?.registrado ?? false;
    const entrada = comoRegistro(passo?.entrada);
    const resultado = comoRegistro(passo?.resultado_parcial);
    const fundamentacao = Array.isArray(passo?.fundamentacao) ? passo.fundamentacao : [];

    return (
        <li className="relative">
            <span
                className={`absolute top-1 -left-[1.65rem] flex size-3.5 items-center justify-center rounded-full border-2 border-white dark:border-gray-900 ${
                    registrado ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600'
                }`}
            >
                {registrado && <CheckCircleIcon className="size-2.5 text-white" />}
            </span>

            <div className="flex flex-wrap items-center gap-2">
                <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                    {passo.titulo ?? passo.passo ?? 'Passo'}
                </span>
                {!registrado && (
                    <Badge color="warning" size="sm">
                        não registrado nesta decisão
                    </Badge>
                )}
                {passo.versao_regra && (
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        regra: {passo.versao_regra}
                    </span>
                )}
            </div>

            {!registrado && passo.motivo && (
                <p className="mt-1 text-theme-xs text-gray-400 italic dark:text-gray-500">{passo.motivo}</p>
            )}

            {registrado && (
                <div className="mt-2 space-y-2">
                    {entrada && <ParesChaveValor titulo="Entrada" dados={entrada} />}
                    {resultado && <ParesChaveValor titulo="Resultado" dados={resultado} />}
                    {fundamentacao.length > 0 && (
                        <ul className="list-inside list-disc text-theme-xs text-gray-500 dark:text-gray-400">
                            {fundamentacao.map((ref, idx) => (
                                <li key={idx}>{String(ref)}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </li>
    );
}

/** Pares chave→valor de um trecho do snapshot (entrada/resultado), legíveis. */
function ParesChaveValor({ titulo, dados }: { titulo: string; dados: Record<string, unknown> }): ReactNode {
    const entradas = Object.entries(dados).filter(([, valor]) => valor !== null && valor !== undefined && valor !== '');

    if (entradas.length === 0) {
        return null;
    }

    return (
        <div>
            <span className="text-theme-xs font-medium text-gray-400 dark:text-gray-500">{titulo}</span>
            <dl className="mt-1 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                {entradas.map(([chave, valor]) => (
                    <div key={chave} className="flex gap-1.5 text-theme-xs">
                        <dt className="font-medium text-gray-500 dark:text-gray-400">{rotular(chave)}:</dt>
                        <dd className="text-gray-700 dark:text-gray-300">{valorLegivel(valor)}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
