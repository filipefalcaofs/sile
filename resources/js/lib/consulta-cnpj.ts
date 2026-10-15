const MENSAGEM_CONSULTA_INDISPONIVEL = 'Não foi possível consultar o CNPJ. Preencha os dados manualmente.';

/**
 * Mensagem da busca de CNPJ. Documento inválido usa o erro do campo e não
 * orienta o preenchimento manual. Indisponibilidade do serviço, sim.
 */
export function mensagemBuscaCnpj(payload: unknown): string {
    if (!payload || typeof payload !== 'object') {
        return MENSAGEM_CONSULTA_INDISPONIVEL;
    }

    const corpo = payload as { cnpj?: string | string[]; message?: string; errors?: { cnpj?: string[] } };
    const campo = corpo.errors?.cnpj?.[0] ?? (Array.isArray(corpo.cnpj) ? corpo.cnpj[0] : corpo.cnpj);

    if (typeof campo === 'string' && campo.trim() !== '') {
        return campo;
    }

    if (typeof corpo.message === 'string' && /cnpj/i.test(corpo.message)) {
        return corpo.message;
    }

    return MENSAGEM_CONSULTA_INDISPONIVEL;
}
