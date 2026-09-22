import { describe, expect, it } from 'vitest';
import { alocarPorQuantidade } from './alocar-lote';

const ana = { analista_id: 1, analista: 'Ana' };
const bruno = { analista_id: 2, analista: 'Bruno' };
const processos = [{ id: 10 }, { id: 20 }, { id: 30 }];

describe('alocarPorQuantidade', () => {
    it('entrega os processos na ordem recebida, de cima para baixo na lista de analistas', () => {
        const grupos = alocarPorQuantidade(processos, [ana, bruno], { 1: 2, 2: 1 });

        expect(grupos).toEqual([
            { analista_id: 1, analista: 'Ana', request_ids: [10, 20] },
            { analista_id: 2, analista: 'Bruno', request_ids: [30] },
        ]);
    });

    it('ignora analista com quantidade zero e não troca a ordem de quem tem quantidade', () => {
        const grupos = alocarPorQuantidade(processos, [ana, bruno], { 1: 0, 2: 2 });

        expect(grupos).toEqual([{ analista_id: 2, analista: 'Bruno', request_ids: [10, 20] }]);
    });
});
