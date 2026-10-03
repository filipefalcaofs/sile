export type EstadoLeitura = 'permitido' | 'nao_permitido' | 'condicionado' | 'pendente' | 'impedimento';

export interface LinhaSintese {
    id: string;
    texto: string;
    estado: EstadoLeitura;
}

export interface AtividadeSintese {
    cnae: string;
    cnae_formatado?: string | null;
    is_primary?: boolean;
    codigo_louos?: string | null;
    fundamentacao?: string[] | null;
    quadros?: Array<{ key: string; label: string; estado: string; detalhe: string }> | null;
}

export interface EntradaSintese {
    statusProcesso: string;
    rotuloProcesso: string;
    motorDisponivel: boolean;
    motivosAnalise: string[];
    atividades: AtividadeSintese[];
    zona: string | null;
    via: string | null;
}

export interface SinteseFicha {
    titulo: string;
    contexto: string;
    linhas: LinhaSintese[];
    base: string[];
}

const DESFECHOS = new Set(['deferida', 'indeferida', 'desativado', 'cassado', 'revogado', 'cancelada']);

/**
 * Síntese do topo da ficha. Só repete o que o processo e o motor já gravaram.
 * Não transforma uma leitura de quadro em sugestão de deferir ou indeferir.
 */
export function sinteseDaFicha(entrada: EntradaSintese): SinteseFicha {
    const desfecho = DESFECHOS.has(entrada.statusProcesso);
    const linhas: LinhaSintese[] = entrada.motivosAnalise
        .filter((motivo) => motivo.trim() !== '')
        .map((motivo, indice) => ({
            id: `motivo-${indice}`,
            texto: motivo.trim(),
            estado: 'impedimento' as const,
        }));

    const atividades = atividadesDaLeitura(entrada.atividades);

    for (const atividade of atividades) {
        const prefixo = atividades.length > 1 ? `${atividade.cnae_formatado ?? atividade.cnae}: ` : '';

        for (const quadro of atividade.quadros ?? []) {
            linhas.push({
                id: `${atividade.cnae}-${quadro.key}`,
                texto: `${prefixo}${quadro.detalhe}`,
                estado: estadoDoQuadro(quadro.estado),
            });
        }
    }

    if (linhas.length === 0 && !entrada.motorDisponivel) {
        linhas.push({
            id: 'sem-motor',
            texto: 'O motor não pré-analisou este processo. A análise é humana.',
            estado: 'pendente',
        });
    }

    return {
        titulo: entrada.rotuloProcesso,
        contexto: contexto(desfecho, entrada.motivosAnalise.length > 0, entrada.motorDisponivel),
        linhas,
        base: base(entrada, atividades),
    };
}

function atividadesDaLeitura(atividades: AtividadeSintese[]): AtividadeSintese[] {
    const principais = atividades.filter((atividade) => atividade.is_primary);

    return principais.length > 0 ? principais : atividades;
}

function estadoDoQuadro(estado: string): EstadoLeitura {
    if (estado === 'permitido' || estado === 'nao_permitido' || estado === 'condicionado' || estado === 'pendente') {
        return estado;
    }

    return 'pendente';
}

function contexto(desfecho: boolean, temMotivo: boolean, motorDisponivel: boolean): string {
    if (desfecho) {
        return 'Desfecho já registrado. Os pontos abaixo são o que ficou gravado, não uma nova sugestão.';
    }

    if (temMotivo) {
        return 'Ainda sem desfecho. O sistema encaminhou à análise pelos motivos abaixo.';
    }

    if (!motorDisponivel) {
        return 'Ainda sem desfecho. O motor não pré-analisou este processo.';
    }

    return 'Ainda sem desfecho. A leitura abaixo é o que o motor gravou. Não é uma sugestão de deferir ou indeferir.';
}

function base(entrada: EntradaSintese, atividades: AtividadeSintese[]): string[] {
    const citacoes = [...new Set(atividades.flatMap((atividade) => atividade.fundamentacao ?? []).filter((item) => item.trim() !== ''))];
    const codigos = [
        ...new Set(
            atividades
                .map((atividade) => atividade.codigo_louos?.trim() ?? '')
                .filter((codigo) => codigo !== ''),
        ),
    ];

    return [
        ...citacoes,
        ...codigos.map((codigo) => `Enquadramento ${codigo}`),
        ...(entrada.zona ? [`Zona ${entrada.zona}`] : []),
        ...(entrada.via ? [`Via ${entrada.via}`] : []),
    ];
}
