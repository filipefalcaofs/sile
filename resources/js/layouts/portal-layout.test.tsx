// @vitest-environment happy-dom
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PortalLayout from './portal-layout';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

type Flash = { status?: string; error?: string | null; warning?: string | null };

const MENSAGEM_SERVICOS = 'Os serviços do portal estão temporariamente indisponíveis.';

let flash: Flash = {};
let servicosLigados = true;

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({
        props: {
            actingFor: null,
            flash,
            portalServicos: { enabled: servicosLigados, mensagem: MENSAGEM_SERVICOS },
            consultaViabilidade: { enabled: true },
        },
    }),
}));

vi.mock('@/components/app/app-shell', () => ({ default: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock('@/contexts/theme-context', () => ({ ThemeProvider: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('@/navigation/portal-nav-icons', () => ({ portalSidebarGroups: () => [] }));

let root: Root | null = null;
let container: HTMLElement | null = null;

function renderizar(): HTMLElement {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
    act(() => {
        root!.render(
            <PortalLayout>
                <p>conteúdo da página</p>
            </PortalLayout>,
        );
    });

    return container;
}

function avisos(el: HTMLElement): string[] {
    return Array.from(el.querySelectorAll('[role="alert"], [role="status"]')).map((aviso) => aviso.textContent ?? '');
}

afterEach(() => {
    act(() => root?.unmount());
    container?.remove();
    root = null;
    container = null;
    flash = {};
    servicosLigados = true;
});

describe('PortalLayout: avisos do servidor', () => {
    it('mostra como erro a mensagem de recusa enviada pelo servidor', () => {
        flash = { error: 'Esta viabilidade já foi renovada.' };

        const el = renderizar();
        const erros = Array.from(el.querySelectorAll('[role="alert"]'));

        expect(erros).toHaveLength(1);
        expect(erros[0].textContent).toContain('Não foi possível continuar');
        expect(erros[0].textContent).toContain('Esta viabilidade já foi renovada.');
        expect(el.textContent).toContain('conteúdo da página');
    });

    it('não mostra aviso de erro quando o servidor não enviou mensagem', () => {
        flash = { error: null };

        expect(avisos(renderizar())).toEqual([]);
    });

    it('mostra erro e atenção ao mesmo tempo, cada um no seu aviso', () => {
        flash = { error: 'Não foi possível conferir o CNPJ agora.', warning: 'Confira os dados da empresa.' };

        const textos = avisos(renderizar());

        expect(textos).toHaveLength(2);
        expect(textos.some((texto) => texto.includes('Não foi possível conferir o CNPJ agora.'))).toBe(true);
        expect(textos.some((texto) => texto.includes('Confira os dados da empresa.'))).toBe(true);
    });

    it('nenhuma página do portal lê o flash por conta própria, porque o layout já mostra os avisos', () => {
        const paginas = import.meta.glob(['../pages/portal/**/*.tsx', '!../pages/portal/**/*.test.tsx'], {
            query: '?raw',
            import: 'default',
            eager: true,
        }) as Record<string, string>;

        const repetem = Object.entries(paginas)
            .filter(([, fonte]) => /\bflash\b/.test(fonte))
            .map(([caminho]) => caminho);

        expect(Object.keys(paginas).length).toBeGreaterThan(5);
        expect(repetem).toEqual([]);
    });

    it('não esconde o erro atrás do aviso de serviços indisponíveis', () => {
        servicosLigados = false;
        flash = { warning: MENSAGEM_SERVICOS, error: 'Esta viabilidade não pôde ser carregada para esta empresa.' };

        const textos = avisos(renderizar());

        expect(textos.some((texto) => texto.includes('Serviços indisponíveis'))).toBe(true);
        expect(textos.some((texto) => texto.includes('Esta viabilidade não pôde ser carregada para esta empresa.'))).toBe(true);
        expect(textos.filter((texto) => texto.includes(MENSAGEM_SERVICOS))).toHaveLength(1);
    });
});
