/**
 * Catálogo único da sidebar de Gestão. Fonte de verdade do menu e do Cmd+K.
 *
 * Antes de incluir, mover ou criar grupo: ler `.cursor/rules/menu-navegacao.mdc`.
 */

export type GestaoNavIcon =
    | 'grid'
    | 'list'
    | 'file'
    | 'user'
    | 'map'
    | 'table'
    | 'tag'
    | 'gear'
    | 'plugin'
    | 'alert'
    | 'shield'
    | 'group'
    | 'lock'
    | 'mail';

export interface GestaoNavItem {
    name: string;
    href: string;
    icon: GestaoNavIcon;
    /**
     * `null` = visível para qualquer usuário autenticado na gestão.
     * Array = visível para quem tem QUALQUER uma das permissões (anyOf) —
     * ex.: a caixa do setor é de quem analisa (analisar-processos) e de
     * quem tramita (distribuir-processos — gestor/apoio).
     */
    permission: string | string[] | null;
}

export interface GestaoNavGroup {
    id: string;
    label: string;
    items: GestaoNavItem[];
}

export interface DestinoComando {
    label: string;
    grupo: string;
    href: string;
    permissao: string | string[] | null;
}

export const GESTAO_NAV_GROUPS: GestaoNavGroup[] = [
    {
        id: 'operacao',
        label: 'Operação',
        items: [
            { name: 'Painel', href: '/gestao', icon: 'grid', permission: null },
            { name: 'Caixa de entrada', href: '/gestao/processos/fila', icon: 'list', permission: 'analisar-processos' },
            {
                name: 'Caixa do setor',
                href: '/gestao/caixa-setor',
                icon: 'group',
                permission: ['analisar-processos', 'distribuir-processos'],
            },
            { name: 'Vistorias', href: '/gestao/vistorias', icon: 'map', permission: 'preencher-ficha-vistoria' },
            { name: 'Consulta de processos', href: '/gestao/processos', icon: 'file', permission: 'consultar-solicitacoes' },
            { name: 'Atendimento presencial', href: '/gestao/atendimento', icon: 'user', permission: 'atendimento-presencial' },
            {
                name: 'Nova solicitação (contingência)',
                href: '/gestao/contingencia',
                icon: 'file',
                permission: 'registrar-contingencia',
            },
            {
                name: 'Resultados do fluxo expresso',
                href: '/gestao/resultados-expresso',
                icon: 'list',
                permission: 'consultar-solicitacoes',
            },
        ],
    },
    {
        id: 'territorio',
        label: 'Território',
        items: [
            { name: 'Consulta territorial', href: '/gestao/territorio', icon: 'map', permission: 'consultar-territorio' },
            {
                name: 'Camadas geográficas',
                href: '/gestao/territorio/camadas',
                icon: 'table',
                permission: 'manter-territorio',
            },
            {
                name: 'Camadas do GeoServer',
                href: '/gestao/territorio/geoserver',
                icon: 'grid',
                permission: 'manter-territorio',
            },
        ],
    },
    {
        id: 'regras',
        label: 'Regras',
        items: [
            { name: 'CNAEs', href: '/gestao/cnaes', icon: 'table', permission: 'consultar-cnaes' },
            { name: 'Tipos de serviço', href: '/gestao/tipos-servico', icon: 'tag', permission: 'manter-tipos-servico' },
            { name: 'Tipos de imóvel', href: '/gestao/tipos-imovel', icon: 'tag', permission: 'manter-tipos-imovel' },
            {
                name: 'Gatilhos de risco',
                href: '/gestao/gatilhos-risco',
                icon: 'alert',
                permission: 'manter-gatilhos-risco',
            },
            { name: 'Quadros LOUOS', href: '/gestao/louos', icon: 'file', permission: 'consultar-louos' },
            { name: 'Zonas', href: '/gestao/louos/zonas', icon: 'map', permission: 'manter-louos' },
            { name: 'Vias', href: '/gestao/louos/vias', icon: 'map', permission: 'manter-louos' },
            {
                name: 'Requisitos documentais',
                href: '/gestao/requisitos-documentais',
                icon: 'file',
                permission: 'manter-requisitos-documentais',
            },
            {
                name: 'Anexos escritório virtual',
                href: '/gestao/escritorio-virtual/anexos',
                icon: 'file',
                permission: 'manter-cnaes',
            },
            {
                name: 'Planilha de regras',
                href: '/gestao/regras-tratamento',
                icon: 'table',
                permission: 'consultar-cnaes',
            },
            {
                name: 'Simulação REGIN',
                href: '/gestao/risco/simulacao-regin',
                icon: 'plugin',
                permission: 'consultar-cnaes',
            },
            { name: 'Simulação de regras', href: '/gestao/louos/simulacao', icon: 'gear', permission: 'manter-louos' },
        ],
    },
    {
        id: 'indicadores',
        label: 'Indicadores',
        items: [
            {
                name: 'Indicadores de viabilidade',
                href: '/gestao/relatorios/indicadores',
                icon: 'grid',
                permission: 'consultar-relatorios',
            },
            { name: 'SLA e vencimentos', href: '/gestao/relatorios/sla', icon: 'alert', permission: 'consultar-relatorios' },
            { name: 'Tempo de análise', href: '/gestao/relatorios/tempo', icon: 'list', permission: 'consultar-relatorios' },
            {
                name: 'Produtividade',
                href: '/gestao/relatorios/produtividade',
                icon: 'group',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Tempo de emissão de TVL',
                href: '/gestao/relatorios/tempo-emissao-tvl',
                icon: 'list',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Quedas do expresso',
                href: '/gestao/relatorios/quedas',
                icon: 'alert',
                permission: 'consultar-relatorios',
            },
        ],
    },
    {
        id: 'relatorios',
        label: 'Relatórios',
        items: [
            {
                name: 'Pendências e exigências',
                href: '/gestao/relatorios/pendencias',
                icon: 'list',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Trilha por processo',
                href: '/gestao/relatorios/trilha',
                icon: 'shield',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Painel por bairro',
                href: '/gestao/relatorios/geo-bairro',
                icon: 'map',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Saturação locacional',
                href: '/gestao/relatorios/saturacao',
                icon: 'alert',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Escritório virtual (sede × abrigados)',
                href: '/gestao/relatorios/escritorio-virtual',
                icon: 'file',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Atendimento em contingência',
                href: '/gestao/relatorios/contingencia',
                icon: 'user',
                permission: 'consultar-relatorios',
            },
            {
                name: 'Falhas de comunicação',
                href: '/gestao/relatorios/comunicacoes-falhas',
                icon: 'mail',
                permission: 'consultar-relatorios',
            },
        ],
    },
    {
        id: 'auditoria',
        label: 'Auditoria',
        items: [
            { name: 'Trilha de auditoria', href: '/gestao/auditoria', icon: 'list', permission: 'consultar-auditoria' },
            { name: 'Alertas de abuso', href: '/gestao/abuso', icon: 'alert', permission: 'gerenciar-alertas-abuso' },
            {
                name: 'Auditoria preditiva',
                href: '/gestao/auditoria-preditiva',
                icon: 'shield',
                permission: 'gerenciar-alertas-abuso',
            },
        ],
    },
    {
        id: 'administracao',
        label: 'Administração',
        items: [
            { name: 'Usuários', href: '/gestao/usuarios', icon: 'group', permission: 'manter-usuarios' },
            { name: 'Perfis', href: '/gestao/perfis', icon: 'lock', permission: 'manter-perfis' },
            { name: 'Setores', href: '/gestao/setores', icon: 'group', permission: 'manter-setores' },
        ],
    },
    {
        id: 'configuracao',
        label: 'Configuração',
        items: [
            { name: 'Parâmetros', href: '/gestao/parametros', icon: 'plugin', permission: 'manter-parametros' },
            { name: 'Feriados', href: '/gestao/feriados', icon: 'tag', permission: 'manter-parametros' },
            { name: 'Valores TLL', href: '/gestao/tll', icon: 'tag', permission: 'manter-parametros' },
            { name: 'Termos legais', href: '/gestao/termos-legais', icon: 'file', permission: 'manter-parametros' },
            { name: 'Textos decisórios', href: '/gestao/textos-decisao', icon: 'file', permission: 'manter-parametros' },
            { name: 'Minutas e textos-padrão', href: '/gestao/textos-padrao', icon: 'table', permission: 'manter-parametros' },
            { name: 'API REGIN', href: '/gestao/config-regin', icon: 'plugin', permission: 'manter-parametros' },
            {
                name: 'API de inscrição imobiliária',
                href: '/gestao/config-inscricao-imobiliaria',
                icon: 'plugin',
                permission: 'manter-parametros',
            },
            { name: 'Servidores de e-mail', href: '/gestao/config-email', icon: 'mail', permission: 'manter-config-email' },
            { name: 'Monitoramento de e-mails', href: '/gestao/emails', icon: 'list', permission: 'monitorar-emails' },
            { name: 'Configuração de IA', href: '/gestao/config-ia', icon: 'gear', permission: 'manter-config-ia' },
        ],
    },
];

const LIMITE_GRUPOS = 8;
const LIMITE_ITENS_GRUPO_MISTO = 8;
const LIMITE_ITENS_GRUPO_HOMOGENEO = 12;
const GRUPOS_HOMOGENEOS = new Set([
    'territorio',
    'regras',
    'indicadores',
    'relatorios',
    'auditoria',
    'administracao',
    'configuracao',
]);

export function groupOfHref(href: string): GestaoNavGroup | undefined {
    return GESTAO_NAV_GROUPS.find((group) => group.items.some((item) => item.href === href));
}

/** Item visível quando o usuário tem a permissão — ou QUALQUER uma da lista (anyOf). */
function permiteItem(item: GestaoNavItem, permissions: readonly string[]): boolean {
    if (item.permission === null) {
        return true;
    }

    const exigidas = Array.isArray(item.permission) ? item.permission : [item.permission];

    return exigidas.some((permission) => permissions.includes(permission));
}

export function filterGestaoNav(permissions: readonly string[]): GestaoNavGroup[] {
    return GESTAO_NAV_GROUPS.map((group) => ({
        ...group,
        items: group.items.filter((item) => permiteItem(item, permissions)),
    })).filter((group) => group.items.length > 0);
}

export function destinosComando(permissions: readonly string[]): DestinoComando[] {
    return filterGestaoNav(permissions).flatMap((group) =>
        group.items.map((item) => ({
            label: item.name,
            grupo: group.label,
            href: item.href,
            permissao: item.permission,
        })),
    );
}

/**
 * Invariantes da IA. Usado pelos testes para impedir regressão e item no grupo errado.
 */
export function assertGestaoNavHealth(groups: readonly GestaoNavGroup[] = GESTAO_NAV_GROUPS): string[] {
    const erros: string[] = [];

    if (groups.length > LIMITE_GRUPOS) {
        erros.push(`Há ${groups.length} grupos; o máximo é ${LIMITE_GRUPOS}.`);
    }

    const hrefs = groups.flatMap((group) => group.items.map((item) => item.href));
    const duplicados = hrefs.filter((href, index) => hrefs.indexOf(href) !== index);

    if (duplicados.length > 0) {
        erros.push(`Hrefs duplicados no menu: ${[...new Set(duplicados)].join(', ')}.`);
    }

    for (const group of groups) {
        const limite = GRUPOS_HOMOGENEOS.has(group.id) ? LIMITE_ITENS_GRUPO_HOMOGENEO : LIMITE_ITENS_GRUPO_MISTO;

        if (group.items.length > limite) {
            erros.push(`Grupo "${group.label}" tem ${group.items.length} itens; o máximo é ${limite}.`);
        }

        if (group.items.length === 1) {
            erros.push(`Grupo "${group.label}" tem um único item — absorva em um grupo existente.`);
        }
    }

    const feriados = groupOfHref('/gestao/feriados');

    if (feriados?.id !== 'configuracao') {
        erros.push('Feriados deve ficar em Configuração, não em Relatórios.');
    }

    const setores = groupOfHref('/gestao/setores');

    if (setores?.id !== 'administracao') {
        erros.push('Setores deve ficar em Administração (pessoas e estrutura).');
    }

    const fila = groupOfHref('/gestao/processos/fila');

    if (fila?.id !== 'operacao') {
        erros.push('Fila de trabalho deve ficar em Operação.');
    }

    const caixaSetor = groupOfHref('/gestao/caixa-setor');

    if (caixaSetor?.id !== 'operacao') {
        erros.push('Caixa do setor deve ficar em Operação.');
    }

    for (const group of groups) {
        for (const item of group.items) {
            if (item.href.startsWith('/gestao/relatorios/') && group.id !== 'relatorios' && group.id !== 'indicadores') {
                erros.push(`"${item.name}" é relatório e está em "${group.label}".`);
            }

            if (
                (item.href === '/gestao/territorio' || item.href.startsWith('/gestao/territorio/')) &&
                group.id !== 'territorio'
            ) {
                erros.push(`"${item.name}" é território e está em "${group.label}".`);
            }
        }
    }

    const regras = groups.find((group) => group.id === 'regras');
    const simulacoes = regras?.items.filter((item) => item.name.startsWith('Simulação')) ?? [];
    const indicePrimeiraSimulacao = regras?.items.findIndex((item) => item.name.startsWith('Simulação')) ?? -1;
    const haCatalogoDepoisDeSimulacao =
        indicePrimeiraSimulacao >= 0 &&
        (regras?.items.slice(indicePrimeiraSimulacao).some((item) => !item.name.startsWith('Simulação')) ?? false);

    if (simulacoes.length > 0 && haCatalogoDepoisDeSimulacao) {
        erros.push('Simulações devem ficar no fim do grupo Regras.');
    }

    return erros;
}
