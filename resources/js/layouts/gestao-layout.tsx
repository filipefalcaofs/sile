import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CommandSearch from '@/components/app/command-search';
import AppShell from '@/components/app/app-shell';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import Alert from '@/components/ui/alert';
import { ThemeProvider } from '@/contexts/theme-context';
import { gestaoNavIcon } from '@/navigation/gestao-nav-icons';
import { filterGestaoNav } from '@/navigation/gestao-nav';
import type { SharedProps } from '@/types';

interface GestaoLayoutProps {
    children: ReactNode;
}

export default function GestaoLayout({ children }: GestaoLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;

    const groups: SidebarGroup[] = filterGestaoNav(auth.permissions).map((group) => ({
        label: group.label,
        items: group.items.map((item) => ({
            name: item.name,
            href: item.href,
            icon: gestaoNavIcon(item.icon),
        })),
    }));

    return (
        <ThemeProvider>
            <AppShell
                groups={groups}
                homeHref="/gestao"
                logoutHref="/gestao/logout"
                accountHref="/gestao/conta/perfil"
                subtitle="Gestão SEDUR"
                variant="console"
                collapsibleGroups
                beforeNav={<CommandSearch />}
            >
                {flash.status && (
                    <div className="mb-6">
                        <Alert variant="success" title="Sucesso" message={flash.status} />
                    </div>
                )}
                {flash.error && (
                    <div className="mb-6">
                        <Alert variant="error" title="Ação bloqueada" message={flash.error} />
                    </div>
                )}
                {children}
            </AppShell>
        </ThemeProvider>
    );
}
