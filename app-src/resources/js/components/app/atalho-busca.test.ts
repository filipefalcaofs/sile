import { describe, expect, it } from 'vitest';
import { rotuloAtalhoBusca } from './atalho-busca';

describe('rotuloAtalhoBusca', () => {
    it('mostra Ctrl+K no Windows e no Linux', () => {
        expect(rotuloAtalhoBusca('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')).toBe('Ctrl+K');
        expect(rotuloAtalhoBusca('Mozilla/5.0 (X11; Linux x86_64)')).toBe('Ctrl+K');
    });

    it('mostra ⌘K no macOS', () => {
        expect(rotuloAtalhoBusca('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')).toBe('⌘K');
    });
});
