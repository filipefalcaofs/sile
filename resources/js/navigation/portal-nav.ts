/**
 * Catálogo único do portal do cidadão. Fonte de verdade da sidebar, do
 * "O que você quer fazer?" do painel e dos serviços da landing.
 *
 * Antes de incluir ou mover item: ler `.cursor/rules/menu-navegacao.mdc`.
 */

export type PortalNavIcon = 'grid' | 'bell' | 'search' | 'file' | 'check-circle' | 'list' | 'group' | 'user' | 'shield';

export interface PortalNavItem {
    name: string;
    href: string;
    icon: PortalNavIcon;
}

export interface PortalNavGroup {
    id: 'inicio' | 'servicos' | 'conta';
    label: string;
    items: PortalNavItem[];
}

/** Ação de serviço exibida como card (painel e, quando `publica`, landing). */
export interface PortalAcao {
    titulo: string;
    descricao: string;
    href: string;
    icon: PortalNavIcon;
    publica: boolean;
}

export const PORTAL_NAV_GROUPS: PortalNavGroup[] = [
    {
        id: 'inicio',
        label: 'Início',
        items: [
            { name: 'Meu painel', href: '/portal/painel', icon: 'grid' },
            { name: 'Notificações', href: '/portal/notificacoes', icon: 'bell' },
        ],
    },
    {
        id: 'servicos',
        label: 'Serviços',
        items: [
            { name: 'Consultar viabilidade', href: '/portal/viabilidade', icon: 'search' },
            { name: 'Minhas solicitações', href: '/portal/solicitacoes', icon: 'file' },
            { name: 'Renovar viabilidade', href: '/portal/renovacao', icon: 'check-circle' },
            { name: 'Minhas empresas', href: '/portal/empresas', icon: 'list' },
            { name: 'Procurações', href: '/portal/procuracoes', icon: 'group' },
        ],
    },
    {
        id: 'conta',
        label: 'Minha conta',
        items: [
            { name: 'Meus dados', href: '/portal/conta/perfil', icon: 'user' },
            { name: 'Histórico de acessos', href: '/portal/acessos', icon: 'shield' },
        ],
    },
];

export const PORTAL_ACOES: PortalAcao[] = [
    {
        titulo: 'Consultar viabilidade',
        descricao: 'Veja se a atividade pode funcionar no endereço, antes de fazer o pedido.',
        href: '/portal/viabilidade',
        icon: 'search',
        publica: true,
    },
    {
        titulo: 'Solicitar viabilidade',
        descricao: 'Protocole o pedido e acompanhe a análise. Atividades de baixo risco têm resposta automática.',
        href: '/portal/solicitacoes/nova',
        icon: 'file',
        publica: true,
    },
    {
        titulo: 'Renovar viabilidade',
        descricao: 'Prorrogue uma viabilidade provisória emitida pelo portal.',
        href: '/portal/renovacao',
        icon: 'check-circle',
        publica: true,
    },
    {
        titulo: 'Gerenciar empresas',
        descricao: 'Cadastre suas empresas e mantenha as atividades (CNAEs) atualizadas.',
        href: '/portal/empresas',
        icon: 'list',
        publica: false,
    },
    {
        titulo: 'Procurações',
        descricao: 'Autorize alguém a agir em seu nome ou atue por quem autorizou você.',
        href: '/portal/procuracoes',
        icon: 'group',
        publica: true,
    },
];

/** Destinos dos indicadores do painel: a lista de solicitações já filtrada. */
export const FILTROS_SOLICITACOES = {
    emAnalise: '/portal/solicitacoes?status=em_analise',
    convites: '/portal/solicitacoes?status=em_pendencia',
    todas: '/portal/solicitacoes',
} as const;

export function acoesPublicas(): PortalAcao[] {
    return PORTAL_ACOES.filter((acao) => acao.publica);
}

/** Invariantes do catálogo. Usado pelos testes para impedir regressão. */
export function assertPortalNavHealth(
    groups: readonly PortalNavGroup[] = PORTAL_NAV_GROUPS,
    acoes: readonly PortalAcao[] = PORTAL_ACOES,
): string[] {
    const erros: string[] = [];

    const hrefs = groups.flatMap((group) => group.items.map((item) => item.href));
    const duplicados = hrefs.filter((href, index) => hrefs.indexOf(href) !== index);

    if (duplicados.length > 0) {
        erros.push(`Hrefs duplicados no menu: ${[...new Set(duplicados)].join(', ')}.`);
    }

    const icones = groups.flatMap((group) => group.items.map((item) => item.icon));
    const iconesRepetidos = icones.filter((icon, index) => icones.indexOf(icon) !== index);

    if (iconesRepetidos.length > 0) {
        erros.push(`Ícones repetidos no menu: ${[...new Set(iconesRepetidos)].join(', ')}.`);
    }

    for (const group of groups) {
        if (group.items.length === 1) {
            erros.push(`Grupo "${group.label}" tem um único item — absorva em um grupo existente.`);
        }
    }

    const servicos = groups.find((group) => group.id === 'servicos')?.items ?? [];

    for (const item of servicos) {
        const coberto = acoes.some((acao) => acao.href === item.href || acao.href.startsWith(`${item.href}/`));

        if (!coberto) {
            erros.push(`Serviço "${item.name}" não tem ação no painel.`);
        }
    }

    for (const acao of acoes) {
        if (!acao.href.startsWith('/portal/')) {
            erros.push(`Ação "${acao.titulo}" aponta para fora do portal.`);
        }
    }

    return erros;
}
