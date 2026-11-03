import { describe, expect, it } from 'vitest';
import { buscaSemMascaraDeCodigo } from './use-server-table';

describe('buscaSemMascaraDeCodigo', () => {
    it('envia só os dígitos de um código com máscara', () => {
        expect(buscaSemMascaraDeCodigo('4711-3/02')).toBe('4711302');
        expect(buscaSemMascaraDeCodigo('47.11-3-02')).toBe('4711302');
        expect(buscaSemMascaraDeCodigo('4711')).toBe('4711');
    });

    it('mantém o texto com letras como foi digitado', () => {
        expect(buscaSemMascaraDeCodigo('comércio varejista')).toBe('comércio varejista');
        expect(buscaSemMascaraDeCodigo('loja 24h')).toBe('loja 24h');
    });

    it('não transforma separadores sem dígitos', () => {
        expect(buscaSemMascaraDeCodigo('-/')).toBe('-/');
    });
});
