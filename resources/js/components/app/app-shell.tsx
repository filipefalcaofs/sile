import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import AccessibilityBar from '@/components/app/accessibility-bar';
import AppHeader from '@/components/app/app-header';
import AppSidebar from '@/components/app/app-sidebar';
import { consumirEntradaConsole } from '@/components/app/console-entrance';
import type { SidebarGroup, SidebarVariant } from '@/components/app/app-sidebar';
import Backdrop from '@/components/app/backdrop';
import { MotionProvider, PageTransition } from '@/components/motion';
import { SidebarProvider, useSidebar } from '@/contexts/sidebar-context';

interface AppShellProps {
    groups: SidebarGroup[];
    homeHref: string;
    /** Destino do logo da sidebar; padrão `homeHref`. */
    logoHref?: string;
    logoutHref?: string;
    accountHref?: string;
    subtitle?: string;
    variant?: SidebarVariant;
    collapsibleGroups?: boolean;
    headerToolbar?: ReactNode;
    children: ReactNode;
}

/**
 * Nota de sessão registrada na chegada vinda do login (protótipo v4:
 * "06 / 06 · Central de viabilidade aberta · sessão registrada"). Entra
 * na cascata do console e esmaece 3,05 s depois (opacity 0,8 s), mantendo
 * o espaço para o conteúdo não saltar.
 */
function NotaEntradaConsole() {
    const [visivel, setVisivel] = useState(true);

    useEffect(() => {
        const temporizador = setTimeout(() => setVisivel(false), 3050);

        return () => clearTimeout(temporizador);
    }, []);

    return (
        <p
            aria-hidden={!visivel}
            className="mb-1.5 font-[ui-monospace,'SF_Mono',Menlo,monospace] text-[10.5px] font-medium tracking-[0.1em] text-brand-500 uppercase motion-safe:animate-[sile-console-surge_0.45s_cubic-bezier(0.2,0.7,0.2,1)_0.2s_both] dark:text-brand-400"
            style={{ opacity: visivel ? undefined : 0, transition: 'opacity 0.8s ease' }}
        >
            06 / 06 · Central de viabilidade aberta · sessão registrada
        </p>
    );
}

function ShellContent({
    groups,
    homeHref,
    logoHref,
    logoutHref,
    accountHref,
    subtitle,
    variant,
    collapsibleGroups,
    headerToolbar,
    children,
}: AppShellProps) {
    const { isExpanded, isHovered, isMobileOpen } = useSidebar();

    // Consumido no primeiro render: a cascata roda só na chegada vinda do login.
    const [entradaDoLogin] = useState(consumirEntradaConsole);

    return (
        <div className="min-h-screen" data-entrada-console={entradaDoLogin ? 'true' : undefined}>
            <AppSidebar
                groups={groups}
                homeHref={homeHref}
                logoHref={logoHref}
                subtitle={subtitle}
                variant={variant}
                collapsibleGroups={collapsibleGroups}
            />
            <Backdrop />
            <div
                className={`min-w-0 transition-all duration-300 ease-in-out ${
                    isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]'
                } ${isMobileOpen ? 'ml-0' : ''}`}
            >
                <div className="sticky top-0 z-99999">
                    <AccessibilityBar />
                    <AppHeader
                        homeHref={homeHref}
                        logoutHref={logoutHref ?? '/portal/logout'}
                        accountHref={accountHref ?? '/portal/conta/perfil'}
                        toolbar={headerToolbar}
                    />
                </div>
                <main id="conteudo" tabIndex={-1} className="w-full min-w-0 p-4 outline-none md:p-6">
                    {entradaDoLogin && <NotaEntradaConsole />}
                    <PageTransition>{children}</PageTransition>
                </main>
                <footer id="rodape" className="border-t border-gray-200 px-4 py-4 text-xs text-gray-500 md:px-6 dark:border-gray-800 dark:text-gray-400">
                    Viabiliza · Secretaria de Desenvolvimento Urbano (SEDUR)
                </footer>
            </div>
        </div>
    );
}

/**
 * Estrutura de página do TailAdmin (sidebar + backdrop + header +
 * container de conteúdo) compartilhada pelos layouts de gestão e do
 * portal.
 */
export default function AppShell(props: AppShellProps) {
    return (
        <MotionProvider>
            <SidebarProvider>
                <ShellContent {...props} />
            </SidebarProvider>
        </MotionProvider>
    );
}

