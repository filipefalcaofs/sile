/**
 * Desfecho mostrado no topo da ficha (SEDUR 20/10/2026): só "Deferido" ou
 * "Indeferido", e só quando a análise está concluída. Nos demais estados o
 * topo não mostra veredito.
 */
export type DesfechoAnalise = { titulo: 'Deferido' | 'Indeferido'; tom: 'deferida' | 'indeferida' };

export function desfechoDaAnalise(statusProcesso: string): DesfechoAnalise | null {
    if (statusProcesso === 'deferida') {
        return { titulo: 'Deferido', tom: 'deferida' };
    }

    if (statusProcesso === 'indeferida') {
        return { titulo: 'Indeferido', tom: 'indeferida' };
    }

    return null;
}
