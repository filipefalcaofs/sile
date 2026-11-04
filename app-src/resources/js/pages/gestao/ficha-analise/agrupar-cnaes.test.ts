import { describe, expect, it } from 'vitest';
import { agruparCnaes } from './agrupar-cnaes';

const ficha = [{ cnae: '4711302' }, { cnae: '5611201' }, { cnae: '4520-0/06' }];

describe('agruparCnaes', () => {
    it('sem comparação mostra todas as atividades num só grupo', () => {
        const grupos = agruparCnaes(ficha, null);

        expect(grupos).toEqual([{ chave: 'todas', titulo: null, itens: ficha.map((item, indice) => ({ item, indice })) }]);
    });

    it('sem viabilidade anterior também não separa', () => {
        const grupos = agruparCnaes(ficha, { sem_viabilidade: true, mantidas: [], incluidas: [], excluidas: [] });

        expect(grupos.map((grupo) => grupo.chave)).toEqual(['todas']);
    });

    it('separa existentes e incluídas guardando a posição original de cada atividade', () => {
        const grupos = agruparCnaes(ficha, {
            sem_viabilidade: false,
            mantidas: [
                { codigo: '4711-3/02', descricao: null },
                { codigo: '4520-0/06', descricao: null },
            ],
            incluidas: [{ codigo: '5611-2/01', descricao: null }],
            excluidas: [{ codigo: '4789-0/99', descricao: 'Outros' }],
        });

        expect(grupos.map((grupo) => [grupo.chave, grupo.itens.map(({ indice }) => indice)])).toEqual([
            ['existentes', [0, 2]],
            ['incluidas', [1]],
        ]);
        expect(grupos.map((grupo) => grupo.titulo)).toEqual(['Atividades existentes', 'Atividades incluídas']);
    });
});
