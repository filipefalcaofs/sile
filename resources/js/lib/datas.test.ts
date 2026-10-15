import { describe, expect, it } from 'vitest';
import { formatDateTime } from './datas';

describe('formatDateTime', () => {
    it('converte ISO em UTC para o horário de Brasília', () => {
        expect(formatDateTime('2026-10-15T17:52:00+00:00')).toBe('15/10/2026 14:52');
        expect(formatDateTime('2026-10-15T17:52:00.000000Z')).toBe('15/10/2026 14:52');
    });

    it('respeita outro offset explícito', () => {
        expect(formatDateTime('2026-10-15T14:52:00-03:00')).toBe('15/10/2026 14:52');
    });

    it('vira o dia quando a conversão cruza a meia-noite', () => {
        expect(formatDateTime('2026-10-16T01:30:00Z')).toBe('15/10/2026 22:30');
    });

    it('trata texto sem offset como UTC (fuso da aplicação)', () => {
        expect(formatDateTime('2026-10-15 17:52:00')).toBe('15/10/2026 14:52');
    });

    it('devolve traço para vazio e o próprio texto para valor inválido', () => {
        expect(formatDateTime(null)).toBe('—');
        expect(formatDateTime('')).toBe('—');
        expect(formatDateTime('não é data')).toBe('não é data');
    });
});
