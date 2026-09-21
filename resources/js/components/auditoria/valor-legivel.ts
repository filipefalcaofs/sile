/** Rótulos de chaves do snapshot que a humanização genérica não cobre bem. */
const CHAVE_LABEL: Record<string, string> = {
    status_escolhido: 'Conclusão',
    cnae: 'CNAE',
    cnae_formatado: 'CNAE',
    area_m2: 'Área',
    ponto: 'Ponto',
    dimensao_decisiva: 'Dimensão decisiva',
    municipal: 'Municipal',
    sanitario: 'Sanitário',
    encaminhamento: 'Encaminhamento',
    nivel: 'Nível',
    versao_regra: 'Versão da regra',
};

const VALOR_LABEL: Record<string, string> = {
    risco_tratamento: 'Risco (tratamento)',
    municipal: 'Municipal',
    sanitario: 'Sanitário',
    expresso: 'Expresso',
    analise: 'Análise técnica',
    baixo: 'Baixo',
    baixo_a: 'Baixo',
    baixo_b: 'Médio Risco',
    medio: 'Médio Risco',
    alto: 'Alto',
    classificado: 'classificado',
    permitido: 'Permitido',
    nao_permitido: 'Não permitido',
    permitido_condicionado: 'Permitido condicionado',
    permitido_com_condicoes: 'Permitido com condições',
};

const CHAVES_REDUNDANTES = new Set(['cnae']);

export function rotular(chave: string): string {
    if (CHAVE_LABEL[chave]) {
        return CHAVE_LABEL[chave];
    }

    const texto = chave.replace(/_/g, ' ');

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function rotuloValor(valor: string): string {
    return VALOR_LABEL[valor] ?? valor;
}

function vazio(valor: unknown): boolean {
    return valor === null || valor === undefined || valor === '' || (Array.isArray(valor) && valor.length === 0);
}

function ePonto(valor: unknown): valor is { lat: number; lng: number } {
    if (valor === null || typeof valor !== 'object' || Array.isArray(valor)) {
        return false;
    }

    const ponto = valor as { lat?: unknown; lng?: unknown };

    return typeof ponto.lat === 'number' && typeof ponto.lng === 'number';
}

function eRegistro(valor: unknown): valor is Record<string, unknown> {
    return valor !== null && typeof valor === 'object' && !Array.isArray(valor);
}

function resumoClassificacao(valor: Record<string, unknown>): string | null {
    const label = typeof valor.label === 'string' ? valor.label : null;
    const nivel = typeof valor.nivel === 'string' ? valor.nivel : typeof valor.nivel_final === 'string' ? valor.nivel_final : null;

    if (label === null && nivel === null) {
        return null;
    }

    return rotuloValor(label ?? nivel ?? '');
}

function resumoEncaminhamento(valor: Record<string, unknown>): string | null {
    const fluxo = typeof valor.fluxo === 'string' ? rotuloValor(valor.fluxo) : null;
    const motivo = typeof valor.motivo === 'string' ? valor.motivo : null;

    if (fluxo === null && motivo === null) {
        return null;
    }

    if (fluxo !== null && motivo !== null) {
        return `${fluxo} — ${motivo}`;
    }

    return fluxo ?? motivo;
}

/** Coage um valor do snapshot a texto legível, sem despejar estruturas cruas. */
export function valorLegivel(valor: unknown): string {
    if (vazio(valor)) {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'sim' : 'não';
    }

    if (typeof valor === 'number') {
        return String(valor);
    }

    if (typeof valor === 'string') {
        return rotuloValor(valor);
    }

    if (ePonto(valor)) {
        return `${valor.lat}, ${valor.lng}`;
    }

    if (Array.isArray(valor)) {
        const itens = valor.filter((item) => typeof item === 'string' || typeof item === 'number');

        if (itens.length === valor.length) {
            return itens.map((item) => (typeof item === 'string' ? rotuloValor(item) : String(item))).join(', ');
        }

        const resumidos = valor
            .map((item) => valorLegivel(item))
            .filter((item) => item !== '—');

        return resumidos.length > 0 ? resumidos.join('; ') : '—';
    }

    if (eRegistro(valor)) {
        const encaminhamento = resumoEncaminhamento(valor);

        if (encaminhamento !== null && 'fluxo' in valor) {
            return encaminhamento;
        }

        const classificacao = resumoClassificacao(valor);

        if (classificacao !== null && ('nivel' in valor || 'nivel_final' in valor)) {
            return classificacao;
        }

        const pares = paresLegiveis(valor);

        if (pares.length === 0) {
            return '—';
        }

        return pares.map((par) => `${par.label}: ${par.value}`).join('; ');
    }

    return '—';
}

/** Pares chave→valor do snapshot, achatando o que for objeto sem JSON. */
export function paresLegiveis(dados: Record<string, unknown>): { label: string; value: string }[] {
    const temCnaeFormatado = typeof dados.cnae_formatado === 'string' && dados.cnae_formatado !== '';
    const pares: { label: string; value: string }[] = [];

    for (const [chave, valor] of Object.entries(dados)) {
        if (vazio(valor)) {
            continue;
        }

        if (temCnaeFormatado && CHAVES_REDUNDANTES.has(chave)) {
            continue;
        }

        if (chave === 'area_m2' && typeof valor === 'number') {
            pares.push({ label: rotular(chave), value: `${valor} m²` });
            continue;
        }

        pares.push({ label: rotular(chave), value: valorLegivel(valor) });
    }

    return pares;
}
