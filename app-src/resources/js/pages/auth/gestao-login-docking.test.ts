import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { sequenciaDeEntrada } from './gestao-login-docking';

describe('Sequência de entrada no console', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('percorre authorized, preparing e docking antes de navegar', () => {
        const fases: string[] = [];
        const navegar = vi.fn();

        sequenciaDeEntrada({
            destino: '/gestao',
            reduzido: false,
            aoMudarFase: (fase) => fases.push(fase),
            navegar,
        });

        expect(fases).toEqual(['authorized']);

        vi.advanceTimersByTime(900);
        expect(fases).toEqual(['authorized', 'preparing']);

        vi.advanceTimersByTime(1100);
        expect(fases).toEqual(['authorized', 'preparing', 'docking']);
        expect(navegar).not.toHaveBeenCalled();

        vi.advanceTimersByTime(900);
        expect(navegar).toHaveBeenCalledWith('/gestao');
    });

    it('com movimento reduzido navega na hora, sem passar pelas fases', () => {
        const fases: string[] = [];
        const navegar = vi.fn();

        sequenciaDeEntrada({
            destino: '/gestao/parametros',
            reduzido: true,
            aoMudarFase: (fase) => fases.push(fase),
            navegar,
        });

        expect(navegar).toHaveBeenCalledWith('/gestao/parametros');
        expect(fases).toEqual([]);
    });

    it('cancelar interrompe a sequência e não navega', () => {
        const navegar = vi.fn();

        const cancelar = sequenciaDeEntrada({
            destino: '/gestao',
            reduzido: false,
            aoMudarFase: () => {},
            navegar,
        });

        vi.advanceTimersByTime(900);
        cancelar();
        vi.advanceTimersByTime(5000);

        expect(navegar).not.toHaveBeenCalled();
    });
});
