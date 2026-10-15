import { describe, expect, it } from 'vitest';
import {
    alternarOpcao,
    enunciadoSemOpcoes,
    numeroDaOpcao,
    perguntasVisiveis,
    respondida,
    type PerguntaPlanilha,
} from './respostas-planilha';

describe('numeroDaOpcao', () => {
    it('lê o número ou a letra do rótulo', () => {
        expect(numeroDaOpcao('1')).toBe(1);
        expect(numeroDaOpcao('12')).toBe(12);
        expect(numeroDaOpcao('A')).toBe(1);
        expect(numeroDaOpcao('B')).toBe(2);
    });
});

describe('alternarOpcao', () => {
    it('marca e desmarca mantendo a lista ordenada', () => {
        expect(alternarOpcao([], 3)).toEqual([3]);
        expect(alternarOpcao([3], 1)).toEqual([1, 3]);
        expect(alternarOpcao([1, 3], 3)).toEqual([1]);
        expect(alternarOpcao(undefined, 2)).toEqual([2]);
    });
});

describe('enunciadoSemOpcoes', () => {
    it('tira as linhas de opção e mantém o enunciado', () => {
        const texto = 'No local funcionará:\n1. Lavanderia  Industrial\n2. Lavanderia não industrial';

        expect(
            enunciadoSemOpcoes(texto, [
                { rotulo: '1', texto: 'Lavanderia  Industrial' },
                { rotulo: '2', texto: 'Lavanderia não industrial' },
            ]),
        ).toBe('No local funcionará:');
    });

    it('não mexe no enunciado de pergunta Sim/Não quando as opções não vêm listadas', () => {
        const texto = 'A atividade será desenvolvida no local?\n1. Sim, no local.\n2. Não, escritório.';

        expect(enunciadoSemOpcoes(texto, [])).toBe(texto);
    });

    it('tira Sim e Não do enunciado quando as opções vêm listadas', () => {
        const texto = 'A atividade será desenvolvida no local?\n1. Sim\n2. Não';

        expect(
            enunciadoSemOpcoes(texto, [
                { rotulo: '1', texto: 'Sim' },
                { rotulo: '2', texto: 'Não' },
            ]),
        ).toBe('A atividade será desenvolvida no local?');
    });
});

describe('respondida', () => {
    const multipla: PerguntaPlanilha = {
        numero: 5,
        texto: 'x',
        tipo: 'multipla',
        opcoes: [{ rotulo: '1', texto: 'a' }],
    };
    const simNao: PerguntaPlanilha = {
        numero: 2,
        texto: 'x',
        tipo: 'sim_nao',
        opcoes: [],
    };

    it('conjunto vazio não conta como resposta', () => {
        expect(respondida(multipla, [])).toBe(false);
        expect(respondida(multipla, [1])).toBe(true);
    });

    it('Sim/Não aceita falso como resposta', () => {
        expect(respondida(simNao, false)).toBe(true);
        expect(respondida(simNao, undefined)).toBe(false);
    });
});

describe('perguntasVisiveis', () => {
    const base: Omit<PerguntaPlanilha, 'numero' | 'depende'> = { texto: 'x', tipo: 'sim_nao', opcoes: [] };
    const perguntas: PerguntaPlanilha[] = [
        { ...base, numero: 25, depende: null },
        { ...base, numero: 26, depende: { pergunta: 25, sim: true } },
        { ...base, numero: 27, depende: { pergunta: 26, sim: true } },
        { ...base, numero: 29, depende: { pergunta: 26, sim: false } },
    ];

    it('mostra todas enquanto nada foi respondido', () => {
        expect(perguntasVisiveis(perguntas, {}).map((p) => p.numero)).toEqual([25, 26, 27, 29]);
    });

    it('esconde as dependentes quando a resposta é a contrária', () => {
        expect(perguntasVisiveis(perguntas, { 25: false }).map((p) => p.numero)).toEqual([25]);
    });

    it('segue a cadeia de dependências', () => {
        expect(perguntasVisiveis(perguntas, { 25: true, 26: true }).map((p) => p.numero)).toEqual([25, 26, 27]);
        expect(perguntasVisiveis(perguntas, { 25: true, 26: false }).map((p) => p.numero)).toEqual([25, 26, 29]);
    });
});
