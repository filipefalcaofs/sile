import { useState } from 'react';
import type { ReactNode } from 'react';
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
    logoutHref?: string;
    accountHref?: string;
    subtitle?: string;
    variant?: SidebarVariant;
    collapsibleGroups?: boolean;
    headerToolbar?: ReactNode;
    children: ReactNode;
}

function ShellContent({
    groups,
    homeHref,
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
                <AppHeader
                    homeHref={homeHref}
                    logoutHref={logoutHref ?? '/portal/logout'}
                    accountHref={accountHref ?? '/portal/conta/perfil'}
                    toolbar={headerToolbar}
                />
                <div id="conteudo" className="w-full min-w-0 p-4 md:p-6">
                    <PageTransition>{children}</PageTransition>
                </div>
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

