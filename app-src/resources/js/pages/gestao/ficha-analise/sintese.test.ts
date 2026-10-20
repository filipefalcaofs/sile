import { describe, expect, it } from 'vitest';

import { desfechoDaAnalise } from './sintese';

describe('desfecho no topo da ficha', () => {
    it('processo deferido mostra Deferido', () => {
        expect(desfechoDaAnalise('deferida')).toEqual({ titulo: 'Deferido', tom: 'deferida' });
    });

    it('processo indeferido mostra Indeferido', () => {
        expect(desfechoDaAnalise('indeferida')).toEqual({ titulo: 'Indeferido', tom: 'indeferida' });
    });

    it.each(['em_analise', 'em_pendencia', 'cassado', 'desativado', 'revogado', 'cancelada'])('%s não mostra desfecho', (status) => {
        expect(desfechoDaAnalise(status)).toBeNull();
    });
});
