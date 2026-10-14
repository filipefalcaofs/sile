import { describe, expect, it } from 'vitest';
import { escolhaDeSetor } from './setor-destino';

const dois = [
    { id: 1, name: 'A' },
    { id: 2, name: 'B' },
];

describe('escolhaDeSetor', () => {
    it('não pede setor a quem distribui só nos próprios setores', () => {
        expect(escolhaDeSetor(false, dois, [9])).toBe('nenhuma');
    });

    it('não pede setor quando o analista tem um setor só', () => {
        expect(escolhaDeSetor(true, [{ id: 1, name: 'A' }], [9])).toBe('nenhuma');
    });

    it('deixa opcional quando o analista já está no setor de todos os processos', () => {
        expect(escolhaDeSetor(true, dois, [1, 2])).toBe('opcional');
    });

    it('exige o setor quando algum processo está fora dos setores do analista', () => {
        expect(escolhaDeSetor(true, dois, [1, 9])).toBe('obrigatoria');
    });

    it('exige o setor quando algum processo está sem setor', () => {
        expect(escolhaDeSetor(true, dois, [null])).toBe('obrigatoria');
    });
});
