import { describe, expect, it } from 'vitest';
import {
    backdropFade,
    dropdownPop,
    fadeIn,
    fadeInUp,
    modalPop,
    motionDuration,
    motionEase,
    pageEnter,
    revealDelay,
    revealMaxIndex,
    revealStepSeconds,
    surfaceEnter,
    staggerItem,
    staggerList,
    stepSlide,
    tableRowFade,
} from './motion';

/**
 * Contrato dos presets de animação: garante que toda transição do
 * sistema permanece sutil e rápida (teto de 350ms) e que os estados
 * esperados existem, para que nenhuma tela invente animação própria
 * fora do padrão.
 */
describe('presets de motion', () => {
    it('mantém todas as durações abaixo de 350ms', () => {
        for (const duracao of Object.values(motionDuration)) {
            expect(duracao).toBeLessThanOrEqual(0.35);
            expect(duracao).toBeGreaterThan(0);
        }
    });

    it('define curvas de easing reutilizáveis', () => {
        expect(motionEase.out).toHaveLength(4);
        expect(motionEase.inOut).toHaveLength(4);
    });

    it('entradas começam invisíveis e terminam visíveis', () => {
        for (const variants of [fadeIn, fadeInUp, modalPop, dropdownPop, stepSlide, tableRowFade]) {
            expect(variants.initial).toMatchObject({ opacity: 0 });
            expect(variants.animate).toMatchObject({ opacity: 1 });
        }
    });

    it('saídas terminam invisíveis nos componentes com exit', () => {
        for (const variants of [modalPop, dropdownPop, backdropFade]) {
            expect(variants.exit).toMatchObject({ opacity: 0 });
        }
    });

    it('deslocamentos de entrada são pequenos (no máximo 16px)', () => {
        expect(Math.abs(Number(fadeInUp.initial?.y))).toBeLessThanOrEqual(16);
        expect(Math.abs(Number(stepSlide.initial?.x))).toBeLessThanOrEqual(16);
        expect(Math.abs(Number(pageEnter.initial?.y))).toBeLessThanOrEqual(16);
    });

    it('stagger de listas é curto e com atraso máximo limitado', () => {
        const transition = staggerList.animate?.transition;

        expect(transition?.staggerChildren).toBeLessThanOrEqual(0.05);
        expect(staggerItem.initial).toMatchObject({ opacity: 0 });
        expect(staggerItem.animate).toMatchObject({ opacity: 1 });
    });

    it('deixa a entrada legível e trava a fila para a lista não parecer lenta', () => {
        expect(revealDelay(0)).toBe(0);
        expect(revealDelay(-3)).toBe(0);
        expect(revealDelay(1)).toBe(revealStepSeconds);
        expect(revealDelay(revealMaxIndex)).toBe(revealMaxIndex * revealStepSeconds);
        expect(revealDelay(40)).toBe(revealDelay(revealMaxIndex));
        expect(surfaceEnter.y).toBeGreaterThanOrEqual(12);
        expect(surfaceEnter.y).toBeLessThanOrEqual(16);
        expect(surfaceEnter.duration).toBeGreaterThanOrEqual(0.28);
        expect(surfaceEnter.duration).toBeLessThanOrEqual(0.35);
        expect(revealDelay(revealMaxIndex)).toBeGreaterThanOrEqual(0.3);
        expect(revealDelay(revealMaxIndex) + surfaceEnter.duration).toBeLessThanOrEqual(0.8);
    });
});
