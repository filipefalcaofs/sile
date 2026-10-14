// @vitest-environment happy-dom
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SidebarProvider } from '@/contexts/sidebar-context';
import { ThemeProvider } from '@/contexts/theme-context';
import AppHeader from './app-header';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, className }: { href: string; children: ReactNode; className?: string }) => (
        <a href={href} className={className}>
            {children}
        </a>
    ),
    usePage: () => ({
        url: '/portal/painel',
        props: { auth: { user: { id: 1, name: 'Ana Souza', email: 'ana@exemplo.test' } }, notificacoes: { nao_lidas: 0 } },
    }),
}));

const armazenamento = new Map<string, string>();

vi.stubGlobal('localStorage', {
    getItem: (chave: string) => armazenamento.get(chave) ?? null,
    setItem: (chave: string, valor: string) => armazenamento.set(chave, valor),
    removeItem: (chave: string) => armazenamento.delete(chave),
    clear: () => armazenamento.clear(),
});

describe('AppHeader', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    function montar(ui: ReactNode) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => {
            root.render(
                <ThemeProvider>
                    <SidebarProvider>{ui}</SidebarProvider>
                </ThemeProvider>,
            );
        });
    }

    function hrefDoLogoMobile(): string | null {
        const logo = Array.from(host.querySelectorAll('a')).find((link) => link.textContent === 'VIABILIZA');

        return logo?.getAttribute('href') ?? null;
    }

    it('leva o logo do cabeçalho mobile ao logoHref quando informado', () => {
        montar(<AppHeader homeHref="/portal" logoHref="/portal/painel" logoutHref="/portal/logout" accountHref="/portal/conta/perfil" />);

        expect(hrefDoLogoMobile()).toBe('/portal/painel');
    });

    it('mantém o homeHref no logo quando não há logoHref (console)', () => {
        montar(<AppHeader homeHref="/gestao" logoutHref="/gestao/logout" accountHref="/gestao/conta" />);

        expect(hrefDoLogoMobile()).toBe('/gestao');
    });
});
