import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppShell from '@/components/app/app-shell';
import type { SidebarItem } from '@/components/app/app-sidebar';
import { GridIcon, GroupIcon, LockIcon, PlugInIcon, TableIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import { ThemeProvider } from '@/contexts/theme-context';
import type { SharedProps } from '@/types';

interface GestaoLayoutProps {
    children: ReactNode;
}

export default function GestaoLayout({ children }: GestaoLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;

    const items: SidebarItem[] = [
        { name: 'Painel', href: '/gestao', icon: <GridIcon /> },
        {
            name: 'CNAEs',
            href: '/gestao/cnaes',
            icon: <TableIcon />,
            visible: auth.permissions.includes('consultar-cnaes'),
        },
        {
            name: 'Usuários',
            href: '/gestao/usuarios',
            icon: <GroupIcon />,
            visible: auth.permissions.includes('manter-usuarios'),
        },
        {
            name: 'Perfis',
            href: '/gestao/perfis',
            icon: <LockIcon />,
            visible: auth.permissions.includes('manter-perfis'),
        },
        {
            name: 'Parâmetros',
            href: '/gestao/parametros',
            icon: <PlugInIcon />,
            visible: auth.permissions.includes('manter-parametros'),
        },
    ];

    return (
        <ThemeProvider>
            <AppShell items={items} homeHref="/gestao" subtitle="Gestão SEDUR">
                {flash.status && (
                    <div className="mb-6">
                        <Alert variant="success" title="Sucesso" message={flash.status} />
                    </div>
                )}
                {children}
            </AppShell>
        </ThemeProvider>
    );
}
