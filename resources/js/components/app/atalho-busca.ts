/**
 * Rótulo do atalho da busca global. macOS usa ⌘K; Windows e Linux usam Ctrl+K.
 */
export function rotuloAtalhoBusca(agente: string): string {
    return /Mac|iPhone|iPad|iPod/.test(agente) ? '⌘K' : 'Ctrl+K';
}
