// @vitest-environment happy-dom
import { act, type ComponentProps } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
    router: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
    usePage: () => ({ url: '/gestao/processos/1', props: { auth: { permissions: ['analisar-processos'] } } }),
}));

vi.mock('@/components/geo/mapa-section', () => ({ MapaSection: () => null }));
vi.mock('@/components/geo/mapa-decisao-section', () => ({ MapaDecisaoSection: () => null }));
vi.mock('@/components/analise/ficha-simplifica', () => ({
    default: ({ numero }: { numero: string }) => <div data-testid="ficha-simplifica">{numero}</div>,
}));

import Show from './show';

type Props = ComponentProps<typeof Show>;

function props(legado: Props['processo']['legado']): Props {
    return {
        processo: {
            id: 1,
            protocol_number: 'VIA-2026-000001',
            bap: null,
            renovacao: false,
            legado,
            tvl_product_number: null,
            status: 'em_analise',
            status_label: 'Em análise',
            categorias: [],
            cnaes_detalhe: [],
        } as unknown as Props['processo'],
        parecer: null,
        timeline: [],
        geo: { poligono: null, ponto: null, origem: null, camadas: null },
        analysisStatusProximas: [],
        conclusaoOpcoes: [],
    };
}

describe('Detalhe do processo: consulta ao legado', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    beforeEach(() => {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    function botaoLegado(): HTMLButtonElement | undefined {
        return [...host.querySelectorAll('button')].find((botao) => botao.textContent === 'Consultar legado');
    }

    it('renovação legada mostra Consultar legado e abre a ficha do Simplifica', () => {
        act(() => root.render(<Show {...props({ processo: '2023000123-00004567/2023', tvl: 1234567 })} />));

        const botao = botaoLegado();
        expect(botao).toBeDefined();
        expect(host.querySelector('[data-testid="ficha-simplifica"]')).toBeNull();

        act(() => botao!.click());

        expect(host.querySelector('[data-testid="ficha-simplifica"]')?.textContent).toBe('2023000123-00004567/2023');
    });

    it('processo sem origem no Simplifica não mostra o botão', () => {
        act(() => root.render(<Show {...props(null)} />));

        expect(botaoLegado()).toBeUndefined();
    });
});
