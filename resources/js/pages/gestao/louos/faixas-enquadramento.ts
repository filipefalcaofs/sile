import { formatarMetros } from '@/lib/metros';

export interface LinhaFaixa {
    subcategoria: string | null;
    ate_m2: string | number | null;
    enquadramento2: string | null;
    ate_m2_2: string | number | null;
    enquadramento3: string | null;
    acima_m2: string | number | null;
}

function numero(valor: string | number | null | undefined): number | null {
    if (valor === null || valor === undefined || valor === '') {
        return null;
    }

    const convertido = typeof valor === 'number' ? valor : Number(valor);

    return Number.isFinite(convertido) ? convertido : null;
}

export interface FaixaLegivel {
    codigo: string;
    limite: string | null;
}

/** Bandas na ordem da planilha: uso e corte, sem a coluna que misturava código e metro. */
export function faixasDaLinha(linha: LinhaFaixa): FaixaLegivel[] {
    const faixas: FaixaLegivel[] = [];

    if (linha.subcategoria) {
        const ate = formatarMetros(linha.ate_m2);

        faixas.push({ codigo: linha.subcategoria, limite: ate ? `até ${ate} m²` : null });
    }

    if (linha.enquadramento2) {
        if (linha.enquadramento3) {
            const teto = formatarMetros(linha.ate_m2_2);

            faixas.push({ codigo: linha.enquadramento2, limite: teto ? `até ${teto} m²` : null });
        } else {
            const piso = formatarMetros(linha.ate_m2) ?? formatarMetros(linha.ate_m2_2);

            faixas.push({ codigo: linha.enquadramento2, limite: piso ? `acima de ${piso} m²` : null });
        }
    }

    if (linha.enquadramento3) {
        const acima = numero(linha.acima_m2);
        const tetoAnterior = numero(linha.ate_m2_2);
        const piso =
            acima !== null && tetoAnterior !== null && Math.abs(acima - tetoAnterior) <= 0.02
                ? formatarMetros(tetoAnterior)
                : (formatarMetros(linha.acima_m2) ?? formatarMetros(linha.ate_m2_2));

        faixas.push({ codigo: linha.enquadramento3, limite: piso ? `acima de ${piso} m²` : null });
    }

    return faixas;
}

function textoDaFaixa(faixa: FaixaLegivel | undefined): string {
    if (!faixa) {
        return '—';
    }

    return faixa.limite ? `${faixa.codigo} ${faixa.limite}` : faixa.codigo;
}

/** Segunda banda: código e corte de área. Sem terceira banda, vale acima do primeiro teto. */
export function textoFaixa2(linha: LinhaFaixa): string {
    return textoDaFaixa(faixasDaLinha(linha).find((faixa) => faixa.codigo === linha.enquadramento2));
}

/** Terceira banda: acima do teto anterior. O centésimo gravado na planilha não aparece. */
export function textoFaixa3(linha: LinhaFaixa): string {
    return textoDaFaixa(faixasDaLinha(linha).find((faixa) => faixa.codigo === linha.enquadramento3));
}

type CorSelo = 'success' | 'warning' | 'error' | 'info' | 'light';

export function apresentacaoRisco(risco: string): { rotulo: string; cor: CorSelo } {
    const chave = risco.trim().toLocaleUpperCase('pt-BR');

    if (chave.includes('BAIXO')) {
        return { rotulo: 'Baixo', cor: 'success' };
    }

    if (chave.includes('MÉDIO') || chave.includes('MEDIO')) {
        return { rotulo: 'Médio', cor: 'warning' };
    }

    if (chave.includes('ALTO')) {
        return { rotulo: 'Alto', cor: 'error' };
    }

    if (chave.includes('CNLU')) {
        return { rotulo: 'A definir pela CNLU', cor: 'info' };
    }

    return { rotulo: risco, cor: 'light' };
}

export function apresentacaoClassificacao(valor: string | null): { rotulo: string; cor: CorSelo } | null {
    if (!valor) {
        return null;
    }

    const chave = valor.trim().toLocaleUpperCase('pt-BR');

    if (chave === 'EXPRESSO') {
        return { rotulo: 'Expresso', cor: 'success' };
    }

    if (chave === 'SEMI EXPRESSO') {
        return { rotulo: 'Semi expresso', cor: 'warning' };
    }

    if (chave === 'VALIDAÇÃO' || chave === 'VALIDACAO') {
        return { rotulo: 'Validação', cor: 'info' };
    }

    return { rotulo: valor, cor: 'light' };
}
