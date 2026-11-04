// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import DataTable from './data-table';
import type { ColumnDef } from './types';

interface Linha {
    id: number;
    nome: string;
}

const colunas: ColumnDef<Linha>[] = [
    { id: 'nome', header: 'Nome', cell: (linha) => linha.nome },
    { id: 'actions', header: 'Ações', sticky: 'end', cell: () => <a href="/x">Abrir</a> },
];

describe('DataTable', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    function renderizar(props: Partial<Parameters<typeof DataTable<Linha>>[0]> = {}) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);

        act(() => {
            root.render(<DataTable columns={colunas} rows={[{ id: 1, nome: 'A' }]} rowKey={(l) => l.id} {...props} />);
        });
    }

    it('fixa à direita a coluna marcada como sticky, no cabeçalho e nas linhas', () => {
        renderizar();

        const th = host.querySelectorAll('th')[1];
        const td = host.querySelectorAll('tbody td')[1];

        expect(th.className).toContain('sticky');
        expect(th.className).toContain('right-0');
        expect(td.className).toContain('sticky');
        expect(td.className).toContain('right-0');
        expect(host.querySelectorAll('tbody td')[0].className).not.toContain('sticky');
    });

    it('limita a altura com cabeçalho fixo para a rolagem horizontal ficar à vista', () => {
        renderizar({ maxHeightClassName: 'max-h-[70vh]' });

        const rolagem = host.querySelector('table')?.parentElement;

        expect(rolagem?.className).toContain('max-h-[70vh]');
        expect(rolagem?.className).toContain('overflow-auto');
        expect(host.querySelector('thead')?.className).toContain('sticky');
    });

    it('sem limite de altura mantém só a rolagem horizontal', () => {
        renderizar();

        const rolagem = host.querySelector('table')?.parentElement;

        expect(rolagem?.className).toContain('overflow-x-auto');
        expect(host.querySelector('thead')?.className).not.toContain('sticky');
    });

    it('densidade justa usa espaçamento lateral menor', () => {
        renderizar({ density: 'tight' });

        expect(host.querySelector('tbody td')?.className).toContain('px-3');
    });
});
