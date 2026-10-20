/**
 * Só processos sem vistoriador e sem ficha entram na distribuição: na aba
 * Todas, as linhas distribuídas, em campo ou concluídas não podem ser marcadas.
 */
export function podeSelecionar(item: { situacao: string }): boolean {
    return item.situacao === 'designar';
}

export function distribuiveis<T extends { situacao: string }>(linhas: T[]): T[] {
    return linhas.filter(podeSelecionar);
}
