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
    status_escolhido?: string | null;
    tendencia?: string | null;
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
    tom: 'deferida' | 'indeferida' | 'neutro';
    contexto: string;
    linhas: LinhaSintese[];
    base: string[];
}

const DESFECHOS = new Set(['deferida', 'indeferida', 'desativado', 'cassado', 'revogado', 'cancelada']);

/**
 * Síntese do topo da ficha. A decisão de cada CNAE é a que o motor já marcou
 * pela lei (Deferida / Indeferida), mesmo com o processo ainda em análise.
 * "Em análise" é a fila humana, não a ausência de veredito.
 */
export function sinteseDaFicha(entrada: EntradaSintese): SinteseFicha {
    const atividades = entrada.atividades;
    const decisoes = atividades.map((atividade) => ({
        atividade,
        decisao: decisaoDaAtividade(atividade),
    }));
    const titulo = tituloDaDecisao(decisoes.map((item) => item.decisao), entrada);
    const linhas: LinhaSintese[] = [];

    if (decisoes.length > 1) {
        for (const { atividade, decisao } of decisoes) {
            linhas.push({
                id: `${atividade.cnae}-decisao`,
                texto: `${atividade.cnae_formatado ?? atividade.cnae}: ${rotuloDecisao(decisao)}`,
                estado: estadoDaDecisao(decisao),
            });
        }
    }

    for (const { atividade } of decisoes) {
        const prefixo = decisoes.length > 1 ? `${atividade.cnae_formatado ?? atividade.cnae}: ` : '';

        for (const quadro of atividade.quadros ?? []) {
            linhas.push({
                id: `${atividade.cnae}-${quadro.key}`,
                texto: `${prefixo}${quadro.detalhe}`,
                estado: estadoDoQuadro(quadro.estado),
            });
        }
    }

    for (const [indice, motivo] of entrada.motivosAnalise.entries()) {
        if (motivo.trim() === '') {
            continue;
        }

        linhas.push({
            id: `motivo-${indice}`,
            texto: motivo.trim(),
            estado: 'impedimento',
        });
    }

    if (linhas.length === 0 && !entrada.motorDisponivel) {
        linhas.push({
            id: 'sem-motor',
            texto: 'O motor não pré-analisou este processo. A análise é humana.',
            estado: 'pendente',
        });
    }

    return {
        titulo: titulo.texto,
        tom: titulo.tom,
        contexto: contexto(titulo.tom, entrada.motivosAnalise.some((motivo) => motivo.trim() !== ''), entrada.motorDisponivel),
        linhas,
        base: base(entrada, atividades),
    };
}

function decisaoDaAtividade(atividade: AtividadeSintese): 'deferida' | 'indeferida' | null {
    if (atividade.status_escolhido === 'deferida' || atividade.status_escolhido === 'indeferida') {
        return atividade.status_escolhido;
    }

    if (atividade.tendencia === 'permitido' || atividade.tendencia === 'permitido_com_condicoes') {
        return 'deferida';
    }

    if (atividade.tendencia === 'nao_permitido') {
        return 'indeferida';
    }

    return null;
}

function tituloDaDecisao(
    decisoes: Array<'deferida' | 'indeferida' | null>,
    entrada: EntradaSintese,
): { texto: string; tom: SinteseFicha['tom'] } {
    if (decisoes.some((decisao) => decisao === 'indeferida')) {
        return { texto: 'Indeferida', tom: 'indeferida' };
    }

    if (decisoes.some((decisao) => decisao === 'deferida')) {
        return { texto: 'Deferida', tom: 'deferida' };
    }

    if (DESFECHOS.has(entrada.statusProcesso) && (entrada.statusProcesso === 'deferida' || entrada.statusProcesso === 'indeferida')) {
        return {
            texto: entrada.rotuloProcesso,
            tom: entrada.statusProcesso,
        };
    }

    return { texto: entrada.rotuloProcesso, tom: 'neutro' };
}

function rotuloDecisao(decisao: 'deferida' | 'indeferida' | null): string {
    if (decisao === 'deferida') {
        return 'Deferida';
    }

    if (decisao === 'indeferida') {
        return 'Indeferida';
    }

    return 'Pendente';
}

function estadoDaDecisao(decisao: 'deferida' | 'indeferida' | null): EstadoLeitura {
    if (decisao === 'deferida') {
        return 'permitido';
    }

    if (decisao === 'indeferida') {
        return 'nao_permitido';
    }

    return 'pendente';
}

function estadoDoQuadro(estado: string): EstadoLeitura {
    if (estado === 'permitido' || estado === 'nao_permitido' || estado === 'condicionado' || estado === 'pendente') {
        return estado;
    }

    return 'pendente';
}

function contexto(tom: SinteseFicha['tom'], temMotivo: boolean, motorDisponivel: boolean): string {
    if (tom === 'deferida' || tom === 'indeferida') {
        const fila = temMotivo ? ' O processo segue na fila humana pelos motivos ao final.' : '';

        return `Decisão do motor pela lei, já marcada em cada atividade. O analista confirma ou altera.${fila}`;
    }

    if (!motorDisponivel) {
        return 'O motor não pré-analisou este processo. A análise é humana.';
    }

    return 'O motor não marcou deferida ou indeferida nesta atividade.';
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
