import { describe, expect, it } from 'vitest';
import { filtrarGruposMenu } from './filtrar-menu';

const grupos = [
    {
        label: 'Operação',
        items: [{ name: 'Caixa de entrada' }, { name: 'Vistorias' }],
    },
    {
        label: 'Regras',
        items: [{ name: 'CNAEs' }, { name: 'Simulação REGIN' }],
    },
];

describe('filtrarGruposMenu', () => {
    it('devolve o menu inteiro quando a busca está vazia', () => {
        expect(filtrarGruposMenu(grupos, '   ')).toEqual(grupos);
    });

    it('encontra item ignorando acento e maiúsculas', () => {
        expect(filtrarGruposMenu(grupos, 'CAIXA').map((grupo) => grupo.items.map((item) => item.name))).toEqual([
            ['Caixa de entrada'],
        ]);
    });

    it('mostra o grupo inteiro quando a busca começa o nome da categoria', () => {
        expect(filtrarGruposMenu(grupos, 'op').flatMap((grupo) => grupo.items.map((item) => item.name))).toEqual([
            'Caixa de entrada',
            'Vistorias',
        ]);
    });

    it('omite grupos sem correspondência', () => {
        expect(filtrarGruposMenu(grupos, 'regin')).toEqual([
            { label: 'Regras', items: [{ name: 'Simulação REGIN' }] },
        ]);
    });

    it('não abre a categoria inteira com uma letra só', () => {
        expect(filtrarGruposMenu(grupos, 'r').flatMap((grupo) => grupo.items.map((item) => item.name))).toEqual([
            'Caixa de entrada',
            'Vistorias',
            'Simulação REGIN',
        ]);
    });
});
