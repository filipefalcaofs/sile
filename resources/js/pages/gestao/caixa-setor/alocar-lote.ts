export interface AlocacaoLote {
    analista_id: number;
    analista: string;
    request_ids: number[];
}

/**
 * Reparte os processos, já ordenados por prazo, na ordem da lista de analistas.
 * Quantidade zero é pulada. Quem aparece antes recebe os processos mais urgentes.
 */
export function alocarPorQuantidade(
    processos: { id: number }[],
    analistas: { analista_id: number; analista: string }[],
    quantidades: Record<number, number>,
): AlocacaoLote[] {
    const ids = processos.map((processo) => processo.id);
    let cursor = 0;
    const grupos: AlocacaoLote[] = [];

    for (const analista of analistas) {
        const quantidade = quantidades[analista.analista_id] || 0;
        if (quantidade <= 0) {
            continue;
        }

        const fatia = ids.slice(cursor, cursor + quantidade);
        cursor += fatia.length;

        if (fatia.length === 0) {
            continue;
        }

        grupos.push({
            analista_id: analista.analista_id,
            analista: analista.analista,
            request_ids: fatia,
        });
    }

    return grupos;
}
