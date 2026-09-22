import { describe, expect, it } from 'vitest';
import {
    gestaoLoginBrandStageClasses,
    gestaoLoginInputClasses,
    gestaoLoginLabelClasses,
    gestaoLoginPanelClasses,
    gestaoLoginShellClasses,
} from './gestao-login-theme';

function hasLightAndDark(classes: string, lightToken: string): void {
    expect(classes).toContain(lightToken);
    expect(classes).toContain('dark:');
}

describe('Tema do login da gestão', () => {
    it('superfície do console tem fundo claro e variante dark', () => {
        hasLightAndDark(gestaoLoginShellClasses, 'bg-gray-50');
        expect(gestaoLoginShellClasses).toContain('text-gray-900');
        expect(gestaoLoginShellClasses).toContain('dark:bg-[');
    });

    it('coluna do formulário tem superfície clara e variante dark', () => {
        hasLightAndDark(gestaoLoginPanelClasses, 'bg-white');
        expect(gestaoLoginPanelClasses).toContain('dark:bg-[');
    });

    it('campos do formulário não ficam presos ao modo escuro', () => {
        hasLightAndDark(gestaoLoginInputClasses, 'bg-white');
        expect(gestaoLoginInputClasses).toContain('text-gray-900');
        expect(gestaoLoginInputClasses).toContain('dark:bg-[');
    });

    it('rótulos acompanham o tema', () => {
        hasLightAndDark(gestaoLoginLabelClasses, 'text-gray-500');
    });

    it('palco institucional mantém texto claro no fundo escuro, independente do tema', () => {
        expect(gestaoLoginBrandStageClasses).toContain('text-[oklch(96%_0.008_250)]');
        expect(gestaoLoginBrandStageClasses).not.toContain('text-gray-900');
    });
});
