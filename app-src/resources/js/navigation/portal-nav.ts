/**
 * Catálogo único do portal do cidadão. Fonte de verdade da sidebar, do
 * "O que você quer fazer?" do painel e dos serviços da landing. O portal fica
 * com três serviços: os processos começam na JUCEB.
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

/** Lista de convites que o cidadão pode responder (badge no menu). */
export const ROTA_CONVITES = '/portal/convites';

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
            { name: 'Renovar viabilidade', href: '/portal/renovacao', icon: 'check-circle' },
            { name: 'Consultar processo', href: '/portal/consulta', icon: 'search' },
            { name: 'Responder convite', href: ROTA_CONVITES, icon: 'file' },
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
        titulo: 'Renovar viabilidade',
        descricao: 'Renove uma viabilidade provisória pelo CNPJ da empresa ou pelo número, do Viabiliza ou do sistema anterior.',
        href: '/portal/renovacao',
        icon: 'check-circle',
        publica: true,
    },
    {
        titulo: 'Consultar processo',
        descricao: 'Veja a situação e a tramitação de um processo pelo número do processo, da viabilidade ou do BAP.',
        href: '/portal/consulta',
        icon: 'search',
        publica: true,
    },
    {
        titulo: 'Responder convite',
        descricao: 'Responda aos pedidos da SEDUR nos processos em que você é o requerente.',
        href: ROTA_CONVITES,
        icon: 'file',
        publica: true,
    },
];


/** Rota da consulta prévia: some do menu e das ações quando o gestor a desliga. */
export const ROTA_CONSULTA_VIABILIDADE = '/portal/viabilidade';

/** Ações do painel; sem a consulta quando features.consulta_viabilidade está desligada. */
export function acoesDoPortal(consultaHabilitada = true): PortalAcao[] {
    return PORTAL_ACOES.filter((acao) => consultaHabilitada || acao.href !== ROTA_CONSULTA_VIABILIDADE);
}

export function acoesPublicas(consultaHabilitada = true): PortalAcao[] {
    return acoesDoPortal(consultaHabilitada).filter((acao) => acao.publica);
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
