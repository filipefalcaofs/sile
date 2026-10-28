/** Situações em que o processo para de tramitar: contar o tempo até agora não diz nada. */
const SITUACOES_FINAIS = ['deferida', 'indeferida', 'cancelada', 'desativado', 'cassado', 'revogado'];

/** Duração humana entre dois instantes. */
function formatarDuracao(inicio: number, fim: number): string {
    const minutos = Math.round((fim - inicio) / 60000);

    if (minutos < 60) {
        return `${minutos} min`;
    }

    const horas = Math.round(minutos / 60);

    if (horas < 48) {
        return `${horas} h`;
    }

    return `${Math.round(horas / 24)} dias`;
}

/**
 * Selo de tempo de uma situação na timeline: quanto durou até a próxima situação;
 * na situação atual, "há X" se ela ainda está em andamento e nada se for final.
 */
export function duracaoNaTramitacao(
    situacao: string,
    inicioIso: string | null,
    proximaIso: string | null,
    agora: number = Date.now(),
): string | null {
    if (!inicioIso) {
        return null;
    }

    if (proximaIso === null && SITUACOES_FINAIS.includes(situacao)) {
        return null;
    }

    const inicio = new Date(inicioIso).getTime();
    const fim = proximaIso ? new Date(proximaIso).getTime() : agora;

    if (Number.isNaN(inicio) || Number.isNaN(fim) || fim < inicio) {
        return null;
    }

    const duracao = formatarDuracao(inicio, fim);

    return proximaIso ? duracao : `há ${duracao}`;
}
