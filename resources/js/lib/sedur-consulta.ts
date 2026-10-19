export function mensagemDaConsultaSedur(data: unknown, fallback: string): string {
    let body: unknown = data;

    if (typeof data === 'string') {
        try {
            body = JSON.parse(data) as unknown;
        } catch {
            return fallback;
        }
    }

    if (body && typeof body === 'object' && 'message' in body && typeof body.message === 'string' && body.message.trim() !== '') {
        return body.message;
    }

    return fallback;
}

export function resumoConsultaCpfSedur(
    nome: string | null,
    cadastrado: boolean,
    contexto: 'atendimento' | 'contingencia' = 'atendimento',
): string {
    const situacao = cadastrado
        ? contexto === 'atendimento'
            ? 'Já cadastrado no Viabiliza — pode iniciar o atendimento.'
            : 'Já cadastrado no Viabiliza.'
        : contexto === 'atendimento'
          ? 'Encontrado na SEDUR e ainda sem cadastro no Viabiliza — ao iniciar o atendimento, o cidadão é registrado no balcão.'
          : 'Encontrado na SEDUR e ainda sem cadastro no Viabiliza — será registrado no balcão ao protocolar.';

    return nome ? `${nome}. ${situacao}` : situacao;
}
