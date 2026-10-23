export interface PorcaoZona {
    zona: string;
    area_m2: number;
    percentual: number;
}

export interface ZonaCamada {
    status: string;
    nome: string | null;
    fonte?: 'ponto' | 'enderecamento_maior_porcao' | 'centralidade_linear' | null;
    porcoes?: PorcaoZona[];
    maior_porcao?: { status: string; motivo: string | null; empate?: string[] } | null;
}

const ROTULO_FONTE: Record<string, string> = {
    ponto: 'ponto da inscrição',
    enderecamento_maior_porcao: 'maior porção do lote',
    centralidade_linear: 'centralidade linear (linha sobre o lote)',
};

function percentual(valor: number): string {
    return `${valor.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
}

/**
 * Linha da zona no inventário: zona, regra que a decidiu e, havendo mais de
 * uma porção, o percentual de cada (art. 18, § 2º, da LOUOS).
 */
export function textoZona(zona?: ZonaCamada | null): string {
    if (!zona) {
        return '—';
    }

    if (zona.status !== 'identificado' || !zona.nome) {
        return zona.status === 'indisponivel' ? 'Camada indisponível' : 'Não identificado';
    }

    const fonte = zona.fonte ? ROTULO_FONTE[zona.fonte] : undefined;
    const porcoes = zona.porcoes ?? [];
    const detalhe = porcoes.length > 1 ? ` (${porcoes.map((p) => `${p.zona} ${percentual(p.percentual)}`).join(' · ')})` : '';

    return fonte ? `${zona.nome} — ${fonte}${detalhe}` : `${zona.nome}${detalhe}`;
}
