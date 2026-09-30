// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
import { CHAVE_ENTRADA_CONSOLE, consumirEntradaConsole, marcarEntradaConsole } from './console-entrance';

describe('Marca de entrada no console', () => {
    beforeEach(() => sessionStorage.clear());

    it('sem marca, a entrada não é animada', () => {
        expect(consumirEntradaConsole()).toBe(false);
    });

    it('a marca gravada pelo login é consumida uma vez só', () => {
        marcarEntradaConsole();

        expect(sessionStorage.getItem(CHAVE_ENTRADA_CONSOLE)).not.toBeNull();
        expect(consumirEntradaConsole()).toBe(true);
        expect(consumirEntradaConsole()).toBe(false);
        expect(sessionStorage.getItem(CHAVE_ENTRADA_CONSOLE)).toBeNull();
    });
});
