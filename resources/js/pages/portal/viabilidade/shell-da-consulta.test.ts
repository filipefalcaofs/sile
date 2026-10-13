import { describe, expect, it } from 'vitest';
import { shellDaConsulta } from './shell-da-consulta';

describe('shellDaConsulta', () => {
    it('usa o shell do portal para quem está logado', () => {
        expect(shellDaConsulta({ id: 7 })).toBe('portal');
    });

    it('usa o shell público para o visitante anônimo', () => {
        expect(shellDaConsulta(null)).toBe('publico');
        expect(shellDaConsulta(undefined)).toBe('publico');
    });
});
