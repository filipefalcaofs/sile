import { describe, expect, it } from 'vitest';
import { rotuloFluxoRisco } from './processo-ui';

describe('rotuloFluxoRisco', () => {
    it('trata expresso como elegibilidade por risco, não como desfecho', () => {
        expect(rotuloFluxoRisco('expresso')).toBe('Elegível ao expresso (risco)');
        expect(rotuloFluxoRisco('analise')).toBe('Análise técnica');
        expect(rotuloFluxoRisco(null)).toBe('—');
    });
});
