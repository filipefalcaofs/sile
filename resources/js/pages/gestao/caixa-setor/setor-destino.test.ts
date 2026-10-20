import { describe, expect, it } from 'vitest';
import { destinatariosDoSetor, escolhaDeSetor } from './setor-destino';

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

describe('destinatariosDoSetor', () => {
    const pessoas = [
        { id: 1, name: 'Do 156', setores: [{ id: 156, name: 'Vistoria' }] },
        { id: 2, name: 'Do 249', setores: [{ id: 249, name: 'Outro' }] },
        { id: 3, name: 'Dos dois', setores: [{ id: 156, name: 'Vistoria' }, { id: 249, name: 'Outro' }] },
    ];

    it('deixa só quem está no setor de todos os processos selecionados', () => {
        expect(destinatariosDoSetor(false, pessoas, [156]).map((p) => p.id)).toEqual([1, 3]);
        expect(destinatariosDoSetor(false, pessoas, [156, 249]).map((p) => p.id)).toEqual([3]);
    });

    it('não restringe quem distribui em qualquer setor', () => {
        expect(destinatariosDoSetor(true, pessoas, [156])).toHaveLength(3);
    });

    it('não restringe sem processos selecionados', () => {
        expect(destinatariosDoSetor(false, pessoas, [])).toHaveLength(3);
    });
});
