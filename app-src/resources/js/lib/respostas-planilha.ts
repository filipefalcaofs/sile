export type RespostaPlanilha = boolean | number | number[];

export interface OpcaoPergunta {
    rotulo: string;
    texto: string;
}

export interface PerguntaPlanilha {
    numero: number;
    texto: string;
    tipo: 'sim_nao' | 'opcao_unica' | 'multipla';
    opcoes: OpcaoPergunta[];
    depende?: { pergunta: number; sim: boolean } | null;
}

/** "1" vale 1, "12" vale 12 e a letra "A" vale 1, "B" vale 2. */
export function numeroDaOpcao(rotulo: string): number {
    if (/^\d+$/.test(rotulo)) {
        return Number(rotulo);
    }

    return rotulo.toUpperCase().charCodeAt(0) - 'A'.charCodeAt(0) + 1;
}

/** Marca ou desmarca uma opção e devolve a lista ordenada. */
export function alternarOpcao(atual: number[] | undefined, opcao: number): number[] {
    const lista = atual ?? [];

    return (lista.includes(opcao) ? lista.filter((item) => item !== opcao) : [...lista, opcao]).sort((a, b) => a - b);
}

/**
 * Enunciado sem as linhas de opção, que a tela mostra como radios ou caixas.
 * Em pergunta Sim/Não (sem opções para listar) o texto segue como veio.
 */
export function enunciadoSemOpcoes(texto: string, opcoes: OpcaoPergunta[]): string {
    if (opcoes.length === 0) {
        return texto;
    }

    return texto
        .split(/\r?\n/)
        .filter((linha) => !/^\s*(\d+|[A-Z])\s*[.)]\s*\S/.test(linha))
        .join('\n')
        .trim();
}

export function respondida(pergunta: PerguntaPlanilha, valor: RespostaPlanilha | undefined): boolean {
    if (valor === undefined) {
        return false;
    }

    return pergunta.tipo === 'multipla' ? Array.isArray(valor) && valor.length > 0 : true;
}

function comoSimNao(valor: RespostaPlanilha): boolean {
    if (typeof valor === 'boolean') {
        return valor;
    }

    if (typeof valor === 'number') {
        return valor === 1;
    }

    return valor.length > 0;
}

/**
 * Perguntas que a tela mostra: as que dependem de outra resposta ("exibir
 * apenas se respondido Sim na pergunta N") somem quando a resposta é a
 * contrária, e a cadeia de dependências é seguida.
 */
export function perguntasVisiveis(
    perguntas: PerguntaPlanilha[],
    respostas: Record<number, RespostaPlanilha>,
): PerguntaPlanilha[] {
    const escondidas = new Set<number>();

    for (const pergunta of [...perguntas].sort((a, b) => a.numero - b.numero)) {
        const dependencia = pergunta.depende;

        if (!dependencia) {
            continue;
        }

        const resposta = respostas[dependencia.pergunta];

        if (
            escondidas.has(dependencia.pergunta) ||
            (resposta !== undefined && comoSimNao(resposta) !== dependencia.sim)
        ) {
            escondidas.add(pergunta.numero);
        }
    }

    return perguntas.filter((pergunta) => !escondidas.has(pergunta.numero));
}
