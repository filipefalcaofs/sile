import type { ReactNode } from 'react';
import AppHeader from '@/components/app/app-header';
import AppSidebar from '@/components/app/app-sidebar';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import Backdrop from '@/components/app/backdrop';
import { SidebarProvider, useSidebar } from '@/contexts/sidebar-context';

interface AppShellProps {
    groups: SidebarGroup[];
    homeHref: string;
    subtitle?: string;
    children: ReactNode;
}

function ShellContent({ groups, homeHref, subtitle, children }: AppShellProps) {
    const { isExpanded, isHovered, isMobileOpen } = useSidebar();

    return (
        <div className="min-h-screen xl:flex">
            <div>
                <AppSidebar groups={groups} homeHref={homeHref} subtitle={subtitle} />
                <Backdrop />
            </div>
            <div
                className={`flex-1 transition-all duration-300 ease-in-out ${
                    isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]'
                } ${isMobileOpen ? 'ml-0' : ''}`}
            >
                <AppHeader homeHref={homeHref} />
                <div className="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">{children}</div>
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
        <SidebarProvider>
            <ShellContent {...props} />
        </SidebarProvider>
    );
}
