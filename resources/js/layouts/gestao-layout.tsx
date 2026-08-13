import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CommandSearch from '@/components/app/command-search';
import AppShell from '@/components/app/app-shell';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import { AlertIcon, FileIcon, GearIcon, GridIcon, GroupIcon, ListIcon, LockIcon, MailIcon, MapPinIcon, PlugInIcon, ShieldIcon, TableIcon, TagIcon, UserCircleIcon } from '@/components/icons';
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
            label: 'Início',
            items: [{ name: 'Painel', href: '/gestao', icon: <GridIcon /> }],
        },
        {
            label: 'Atendimento e operação',
            items: [
                {
                    name: 'Consulta territorial',
                    href: '/gestao/territorio',
                    icon: <MapPinIcon />,
                    visible: auth.permissions.includes('consultar-territorio'),
                },
                {
                    name: 'Atendimento presencial',
                    href: '/gestao/atendimento',
                    icon: <UserCircleIcon />,
                    visible: auth.permissions.includes('atendimento-presencial'),
                },
                {
                    name: 'Nova solicitação (contingência)',
                    href: '/gestao/contingencia',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('registrar-contingencia'),
                },
                {
                    name: 'Resultados do fluxo expresso',
                    href: '/gestao/resultados-expresso',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('consultar-solicitacoes'),
                },
            ],
        },
        {
            label: 'Análise técnica',
            items: [
                {
                    name: 'Fila de trabalho',
                    href: '/gestao/processos/fila',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('analisar-processos'),
                },
                {
                    name: 'Processos',
                    href: '/gestao/processos',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('consultar-solicitacoes'),
                },
                {
                    name: 'Setores',
                    href: '/gestao/setores',
                    icon: <GroupIcon />,
                    visible: auth.permissions.includes('manter-setores'),
                },
                {
                    name: 'Textos-padrão',
                    href: '/gestao/textos-padrao',
                    icon: <TableIcon />,
                    visible: auth.permissions.includes('manter-parametros'),
                },
            ],
        },
        {
            label: 'Regras do licenciamento',
            items: [
                {
                    name: 'CNAEs',
                    href: '/gestao/cnaes',
                    icon: <TableIcon />,
                    visible: auth.permissions.includes('consultar-cnaes'),
                },
                {
                    name: 'Tipos de serviço',
                    href: '/gestao/tipos-servico',
                    icon: <TagIcon />,
                    visible: auth.permissions.includes('manter-tipos-servico'),
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
                    name: 'Requisitos documentais',
                    href: '/gestao/requisitos-documentais',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('manter-requisitos-documentais'),
                },
            ],
        },
        {
            label: 'Auditoria e compliance',
            items: [
                {
                    name: 'Trilha de auditoria',
                    href: '/gestao/auditoria',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('consultar-auditoria'),
                },
                {
                    name: 'Conformidade LGPD',
                    href: '/gestao/lgpd',
                    icon: <LockIcon />,
                    visible: auth.permissions.includes('monitorar-lgpd'),
                },
                {
                    name: 'Alertas de abuso',
                    href: '/gestao/abuso',
                    icon: <AlertIcon />,
                    visible: auth.permissions.includes('gerenciar-alertas-abuso'),
                },
                {
                    name: 'Auditoria preditiva',
                    href: '/gestao/auditoria-preditiva',
                    icon: <ShieldIcon />,
                    visible: auth.permissions.includes('gerenciar-alertas-abuso'),
                },
            ],
        },
        {
            label: 'Relatórios',
            items: [
                {
                    name: 'Indicadores de viabilidade',
                    href: '/gestao/relatorios/indicadores',
                    icon: <GridIcon />,
                    visible: auth.permissions.includes('consultar-relatorios'),
                },
                {
                    name: 'Tempo de análise',
                    href: '/gestao/relatorios/tempo',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('consultar-relatorios'),
                },
                {
                    name: 'Produtividade',
                    href: '/gestao/relatorios/produtividade',
                    icon: <GroupIcon />,
                    visible: auth.permissions.includes('consultar-relatorios'),
                },
                {
                    name: 'Quedas do expresso',
                    href: '/gestao/relatorios/quedas',
                    icon: <AlertIcon />,
                    visible: auth.permissions.includes('consultar-relatorios'),
                },
                {
                    name: 'Painel por bairro',
                    href: '/gestao/relatorios/geo-bairro',
                    icon: <MapPinIcon />,
                    visible: auth.permissions.includes('consultar-relatorios'),
                },
                {
                    name: 'Saturação locacional',
                    href: '/gestao/relatorios/saturacao',
                    icon: <AlertIcon />,
                    visible: auth.permissions.includes('consultar-relatorios'),
                },
                {
                    name: 'Feriados',
                    href: '/gestao/feriados',
                    icon: <TagIcon />,
                    visible: auth.permissions.includes('manter-parametros'),
                },
            ],
        },
        {
            label: 'Administração',
            items: [
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
                {
                    name: 'Servidores de e-mail',
                    href: '/gestao/config-email',
                    icon: <MailIcon />,
                    visible: auth.permissions.includes('manter-config-email'),
                },
                {
                    name: 'Monitoramento de e-mails',
                    href: '/gestao/emails',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('monitorar-emails'),
                },
                {
                    name: 'Configuração de IA',
                    href: '/gestao/config-ia',
                    icon: <GearIcon />,
                    visible: auth.permissions.includes('manter-config-ia'),
                },
            ],
        },
    ];

    return (
        <ThemeProvider>
            <AppShell groups={groups} homeHref="/gestao" logoutHref="/gestao/logout" subtitle="Gestão SEDUR" variant="console" collapsibleGroups>
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
            <CommandSearch />
        </ThemeProvider>
    );
}
