import { describe, expect, it } from 'vitest';
import { formatarMetros, metrosComUnidade } from './metros';

describe('formatarMetros', () => {
    it('não deixa o ponto decimal do banco na tela', () => {
        expect(formatarMetros('1250.00')).toBe('1.250');
        expect(formatarMetros('1250.01')).toBe('1.250,01');
        expect(formatarMetros(156636)).toBe('156.636');
    });

    it('trata vazio como ausência', () => {
        expect(formatarMetros(null)).toBeNull();
        expect(formatarMetros('')).toBeNull();
        expect(formatarMetros('Q')).toBeNull();
    });

    it('anexa a unidade só quando há número', () => {
        expect(metrosComUnidade('1250.00')).toBe('1.250 m²');
        expect(metrosComUnidade(null)).toBeNull();
    });
});
