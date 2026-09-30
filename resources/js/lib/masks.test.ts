import { describe, expect, it } from 'vitest';
import {
    applyAlphanumericCnpjMask,
    applyDigitsOnly,
    applyMask,
    brDateToIso,
    isoToBrDate,
    MASK_PATTERNS,
} from './masks';

describe('applyMask', () => {
    it('formata CPF parcial e completo', () => {
        expect(applyMask('06681065570', MASK_PATTERNS.cpf)).toBe('066.810.655-70');
        expect(applyMask('066810', MASK_PATTERNS.cpf)).toBe('066.810');
    });

    it('formata CNPJ', () => {
        expect(applyMask('11222333000181', MASK_PATTERNS.cnpj)).toBe('11.222.333/0001-81');
    });

    it('formata CEP e telefone', () => {
        expect(applyMask('40000000', MASK_PATTERNS.cep)).toBe('40000-000');
        expect(applyMask('71999998888', MASK_PATTERNS.phone)).toBe('(71) 99999-8888');
    });

    it('formata data brasileira', () => {
        expect(applyMask('30092026', MASK_PATTERNS.date)).toBe('30/09/2026');
        expect(applyMask('3009', MASK_PATTERNS.date)).toBe('30/09');
    });
});

describe('applyDigitsOnly', () => {
    it('mantém só dígitos com limite', () => {
        expect(applyDigitsOnly('001-001.0010', 20)).toBe('0010010010');
        expect(applyDigitsOnly('12345678901234567890', 20)).toBe('12345678901234567890');
        expect(applyDigitsOnly('123456789012345678901', 20)).toBe('12345678901234567890');
    });
});

describe('applyAlphanumericCnpjMask', () => {
    it('formata CNPJ alfanumérico em maiúsculas', () => {
        expect(applyAlphanumericCnpjMask('12abc3450001-81')).toBe('12.ABC.345/0001-81');
    });
});

describe('isoToBrDate / brDateToIso', () => {
    it('converte ISO para BR e vice-versa', () => {
        expect(isoToBrDate('2026-09-30')).toBe('30/09/2026');
        expect(brDateToIso('30/09/2026')).toBe('2026-09-30');
    });

    it('rejeita datas inválidas', () => {
        expect(brDateToIso('31/02/2026')).toBeNull();
        expect(brDateToIso('30/09')).toBeNull();
        expect(isoToBrDate('')).toBe('');
        expect(isoToBrDate('invalid')).toBe('');
    });
});
