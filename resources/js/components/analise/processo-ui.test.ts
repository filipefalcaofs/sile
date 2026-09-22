import { describe, expect, it } from 'vitest';
import { rotuloFluxoRisco, rotuloSemSla } from './processo-ui';

describe('rotuloFluxoRisco', () => {
    it('trata expresso como elegibilidade por risco, não como desfecho', () => {
        expect(rotuloFluxoRisco('expresso')).toBe('Elegível ao expresso (risco)');
        expect(rotuloFluxoRisco('analise')).toBe('Análise técnica');
        expect(rotuloFluxoRisco(null)).toBe('—');
    });
});

describe('rotuloSemSla', () => {
    it('exibe Fluxo expresso para processo decidido no expresso (nunca entra na fila)', () => {
        expect(rotuloSemSla('expresso')).toBe('Fluxo expresso');
    });

    it('mantém Sem prazo para processo fora da fila sem decisão expressa', () => {
        expect(rotuloSemSla(null)).toBe('Sem prazo');
        expect(rotuloSemSla('analise_tecnica')).toBe('Sem prazo');
        expect(rotuloSemSla('em_analise')).toBe('Sem prazo');
    });
});
