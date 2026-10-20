import { describe, expect, it } from 'vitest';
import { distribuiveis, podeSelecionar } from './selecao';

describe('Seleção da distribuição de vistorias', () => {
    it('só permite marcar processos para distribuir', () => {
        expect(podeSelecionar({ situacao: 'designar' })).toBe(true);
        expect(podeSelecionar({ situacao: 'distribuido' })).toBe(false);
        expect(podeSelecionar({ situacao: 'em_campo' })).toBe(false);
        expect(podeSelecionar({ situacao: 'concluida' })).toBe(false);
    });

    it('selecionar todos ignora as linhas que já têm vistoriador', () => {
        const linhas = [
            { id: 1, situacao: 'designar' },
            { id: 2, situacao: 'em_campo' },
            { id: 3, situacao: 'distribuido' },
            { id: 4, situacao: 'designar' },
        ];

        expect(distribuiveis(linhas).map((linha) => linha.id)).toEqual([1, 4]);
    });
});
