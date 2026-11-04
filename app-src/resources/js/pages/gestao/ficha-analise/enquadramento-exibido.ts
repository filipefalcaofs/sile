export interface EnquadramentoMotor {
    codigo_louos: string | null;
    codigo_tll: string | null;
    grupo: string | null;
    subgrupo: string | null;
}

interface ItemComEnquadramento {
    codigo_louos?: string | null;
    codigo_tll?: string | null;
    grupo_uso?: string | null;
    subgrupo_uso?: string | null;
    enquadramento_motor?: EnquadramentoMotor | null;
}

interface OpcaoComFaixa {
    subgrupo?: string | null;
    subgrupo_area?: string | null;
    grupo_area?: string | null;
}

export interface EnquadramentoExibido {
    subgrupo: string | null;
    grupo: string | null;
    /** O analista escolheu uma opção diferente da que o motor sugeriu. */
    alterado: boolean;
    subgrupoMotor: string | null;
}

function chave(louos?: string | null, tll?: string | null): string {
    return `${louos ?? ''}|${tll ?? ''}`;
}

/**
 * Subgrupo e grupo que a ficha mostra para a atividade. Enquanto a opção
 * LOUOS × TLL é a do motor, vale o que ele gravou; trocada a opção, vale o
 * subgrupo que a área do processo alcança na nova linha da planilha.
 */
export function enquadramentoExibido(item: ItemComEnquadramento, opcao: OpcaoComFaixa | undefined): EnquadramentoExibido {
    const motor = item.enquadramento_motor ?? null;
    const alterado = motor !== null && chave(item.codigo_louos, item.codigo_tll) !== chave(motor.codigo_louos, motor.codigo_tll);

    if (alterado) {
        return {
            subgrupo: opcao?.subgrupo_area ?? null,
            grupo: opcao?.grupo_area ?? null,
            alterado: true,
            subgrupoMotor: motor.subgrupo,
        };
    }

    return {
        subgrupo: item.subgrupo_uso ?? opcao?.subgrupo_area ?? opcao?.subgrupo ?? null,
        grupo: item.grupo_uso ?? opcao?.grupo_area ?? null,
        alterado: false,
        subgrupoMotor: motor?.subgrupo ?? null,
    };
}

/**
 * Quadros 10 e 11-A que a ficha mostra: trocada a opção, os recalculados para
 * ela no servidor; sem eles (território não gravado), os do motor.
 */
export function quadrosExibidos<Quadro>(
    alterado: boolean,
    quadrosMotor: Quadro[],
    opcao: { quadros?: Quadro[] | null } | undefined,
): { quadros: Quadro[]; recalculados: boolean } {
    if (alterado && opcao?.quadros != null) {
        return { quadros: opcao.quadros, recalculados: true };
    }

    return { quadros: quadrosMotor, recalculados: false };
}
