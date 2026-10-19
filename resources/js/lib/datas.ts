const FUSO_EXIBICAO = 'America/Bahia';

const formatador = new Intl.DateTimeFormat('pt-BR', {
    timeZone: FUSO_EXIBICAO,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

/**
 * Formata ISO 8601 como 'DD/MM/YYYY HH:MM' no horário de Brasília, sem depender
 * do fuso do navegador. A aplicação grava em UTC; texto sem offset é tratado como UTC.
 */
export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const semOffset = /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/.test(value);
    const date = new Date(semOffset ? `${value.replace(' ', 'T')}Z` : value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const partes = Object.fromEntries(formatador.formatToParts(date).map((parte) => [parte.type, parte.value]));

    return `${partes.day}/${partes.month}/${partes.year} ${partes.hour}:${partes.minute}`;
}
