// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ProcessoFiltros from './processo-filtros';

describe('ProcessoFiltros — número da porta', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    it('envia o número informado junto com os demais filtros', () => {
        const aoAplicar = vi.fn();
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => {
            root.render(
                <ProcessoFiltros
                    valores={{ numero: '7' }}
                    servicoOptions={[]}
                    analysisStatusOptions={[]}
                    categoriaOptions={[]}
                    onAplicar={aoAplicar}
                    onLimpar={() => undefined}
                />,
            );
        });

        const campo = host.querySelector<HTMLInputElement>('#filtro-numero');
        expect(campo?.value).toBe('7');

        act(() => {
            host.querySelector('form')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        });

        expect(aoAplicar).toHaveBeenCalledWith(expect.objectContaining({ numero: '7' }));
    });
});
