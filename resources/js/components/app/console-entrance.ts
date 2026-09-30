export const CHAVE_ENTRADA_CONSOLE = 'viabiliza:entrada-console';

/**
 * O login grava esta marca no último frame da cena de alinhamento, logo
 * antes de navegar. É o que diz ao console que esta chegada específica
 * continua uma animação — navegações seguintes entram sem cascata.
 */
export function marcarEntradaConsole(): void {
    try {
        sessionStorage.setItem(CHAVE_ENTRADA_CONSOLE, '1');
    } catch {
        // Armazenamento bloqueado: a entrada só fica sem animação.
    }
}

export function consumirEntradaConsole(): boolean {
    try {
        const marcado = sessionStorage.getItem(CHAVE_ENTRADA_CONSOLE) !== null;
        sessionStorage.removeItem(CHAVE_ENTRADA_CONSOLE);

        return marcado;
    } catch {
        return false;
    }
}
