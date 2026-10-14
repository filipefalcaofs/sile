export interface SetorDoAnalista {
    id: number;
    name: string;
}

export type EscolhaDeSetor = 'nenhuma' | 'opcional' | 'obrigatoria';

/**
 * Quem distribui em qualquer setor escolhe o setor que recebe o processo quando
 * o analista está em mais de um. Se o analista já está no setor de todos os
 * processos, a escolha é opcional: sem ela, cada processo fica onde está.
 */
export function escolhaDeSetor(
    distribuiEmQualquerSetor: boolean,
    setoresDoAnalista: SetorDoAnalista[],
    setoresDosProcessos: (number | null)[],
): EscolhaDeSetor {
    if (!distribuiEmQualquerSetor || setoresDoAnalista.length <= 1) {
        return 'nenhuma';
    }

    const ids = new Set(setoresDoAnalista.map((setor) => setor.id));

    return setoresDosProcessos.every((setor) => setor !== null && ids.has(setor)) ? 'opcional' : 'obrigatoria';
}
