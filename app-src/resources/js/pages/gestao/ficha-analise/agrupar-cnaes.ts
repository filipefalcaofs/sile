interface CnaeDaComparacao {
    codigo: string;
    descricao?: string | null;
}

export interface ComparacaoParaAgrupar {
    sem_viabilidade: boolean;
    mantidas: CnaeDaComparacao[];
    incluidas: CnaeDaComparacao[];
    excluidas: CnaeDaComparacao[];
}

export interface GrupoDeCnaes<T> {
    chave: 'todas' | 'existentes' | 'incluidas';
    titulo: string | null;
    itens: { item: T; indice: number }[];
}

const digitos = (codigo: string): string => codigo.replace(/\D/g, '');

/**
 * Quadros do "Enquadramento por atividade" separados pela comparação com a
 * viabilidade anterior: as que a empresa já tem e as incluídas no pedido. O
 * índice original fica com cada item, porque a ficha grava pela posição.
 * Sem comparação, ou sem viabilidade anterior, fica um grupo só.
 */
export function agruparCnaes<T extends { cnae: string }>(perCnae: T[], comparacao: ComparacaoParaAgrupar | null): GrupoDeCnaes<T>[] {
    const itens = perCnae.map((item, indice) => ({ item, indice }));

    if (comparacao === null || comparacao.sem_viabilidade) {
        return [{ chave: 'todas', titulo: null, itens }];
    }

    const existentes = new Set(comparacao.mantidas.map((cnae) => digitos(cnae.codigo)));

    return [
        { chave: 'existentes', titulo: 'Atividades existentes', itens: itens.filter(({ item }) => existentes.has(digitos(item.cnae))) },
        { chave: 'incluidas', titulo: 'Atividades incluídas', itens: itens.filter(({ item }) => !existentes.has(digitos(item.cnae))) },
    ];
}
