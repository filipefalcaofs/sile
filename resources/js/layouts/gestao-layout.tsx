import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppShell from '@/components/app/app-shell';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import { FileIcon, GearIcon, GridIcon, GroupIcon, ListIcon, LockIcon, MailIcon, MapPinIcon, PlugInIcon, ShieldIcon, TableIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import { ThemeProvider } from '@/contexts/theme-context';
import type { SharedProps } from '@/types';

interface GestaoLayoutProps {
    children: ReactNode;
}

export default function GestaoLayout({ children }: GestaoLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;

    const groups: SidebarGroup[] = [
        {
            label: 'Visão geral',
            items: [
                { name: 'Painel', href: '/gestao', icon: <GridIcon /> },
                {
                    name: 'Consulta territorial',
                    href: '/gestao/territorio',
                    icon: <MapPinIcon />,
                    visible: auth.permissions.includes('consultar-territorio'),
                },
            ],
        },
        {
            label: 'Cadastros',
            items: [
                {
                    name: 'CNAEs',
                    href: '/gestao/cnaes',
                    icon: <TableIcon />,
                    visible: auth.permissions.includes('consultar-cnaes'),
                },
                {
                    name: 'Classificação de risco',
                    href: '/gestao/risco',
                    icon: <ShieldIcon />,
                    visible: auth.permissions.includes('consultar-risco'),
                },
                {
                    name: 'Condicionantes',
                    href: '/gestao/risco/condicionantes',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('manter-risco'),
                },
                {
                    name: 'Quadros LOUOS',
                    href: '/gestao/louos',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('consultar-louos'),
                },
                {
                    name: 'Simulação de regras',
                    href: '/gestao/louos/simulacao',
                    icon: <GearIcon />,
                    visible: auth.permissions.includes('manter-louos'),
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
            ],
        },
        {
            label: 'Sistema',
            items: [
                {
                    name: 'Parâmetros',
                    href: '/gestao/parametros',
                    icon: <PlugInIcon />,
                    visible: auth.permissions.includes('manter-parametros'),
                },
                {
                    name: 'E-mails',
                    href: '/gestao/emails',
                    icon: <MailIcon />,
                    visible: auth.permissions.includes('monitorar-emails'),
                },
            ],
        },
    ];

    return (
        <ThemeProvider>
            <AppShell groups={groups} homeHref="/gestao" logoutHref="/gestao/logout" subtitle="Gestão SEDUR" variant="console">
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
