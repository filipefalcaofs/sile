// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ExportMenu } from './export-menu';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { export_formatos: ['csv', 'xlsx', 'pdf'] } }),
}));

describe('ExportMenu', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    it('abre o PDF em outra guia e mantém CSV e Excel como download', () => {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);

        act(() => {
            root.render(<ExportMenu url="/gestao/relatorios/sla" params={{ per_page: 25 }} />);
        });
        act(() => {
            host.querySelector('button')?.click();
        });

        const item = (trecho: string) =>
            Array.from(document.querySelectorAll<HTMLAnchorElement>('a[role="menuitem"]')).find((a) =>
                a.textContent?.toLowerCase().includes(trecho),
            );

        expect(item('pdf')?.getAttribute('target')).toBe('_blank');
        expect(item('pdf')?.getAttribute('rel')).toContain('noopener');
        expect(item('csv')?.getAttribute('target')).toBeNull();
        expect(item('excel')?.getAttribute('target')).toBeNull();
    });
});
