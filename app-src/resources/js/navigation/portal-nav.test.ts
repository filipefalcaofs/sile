import { describe, expect, it } from 'vitest';
import { portalSidebarGroups } from './portal-nav-icons';
import {
    acoesDoPortal,
    acoesPublicas,
    assertPortalNavHealth,
    PORTAL_ACOES,
    PORTAL_NAV_GROUPS,
    ROTA_CONVITES,
    type PortalAcao,
    type PortalNavGroup,
} from './portal-nav';

const hrefsDoGrupo = (id: string) =>
    PORTAL_NAV_GROUPS.find((group) => group.id === id)?.items.map((item) => item.href) ?? [];

describe('Catálogo de navegação do portal do cidadão', () => {
    it('mantém a ordem fixa dos grupos', () => {
        expect(PORTAL_NAV_GROUPS.map((group) => group.id)).toEqual(['inicio', 'servicos', 'conta']);
    });

    it('passa nas invariantes de organização', () => {
        expect(assertPortalNavHealth()).toEqual([]);
    });

    it('lista só os três serviços do cidadão no menu', () => {
        expect(hrefsDoGrupo('servicos')).toEqual(['/portal/renovacao', '/portal/consulta', '/portal/convites']);
    });

    it('põe painel e notificações em Início, e dados e acessos em Minha conta', () => {
        expect(hrefsDoGrupo('inicio')).toEqual(['/portal/painel', '/portal/notificacoes']);
        expect(hrefsDoGrupo('conta')).toEqual(['/portal/conta/perfil', '/portal/acessos']);
    });

    it('oferece no painel uma ação para cada serviço do menu', () => {
        for (const href of hrefsDoGrupo('servicos')) {
            const coberto = PORTAL_ACOES.some((acao) => acao.href === href || acao.href.startsWith(`${href}/`));

            expect(coberto, `sem ação para ${href}`).toBe(true);
        }
    });

    it('mostra na landing só as ações públicas, todas com link', () => {
        const publicas = acoesPublicas();

        expect(publicas.map((acao) => acao.href)).toEqual(['/portal/renovacao', '/portal/consulta', '/portal/convites']);
        expect(publicas.every((acao) => acao.href.startsWith('/portal/'))).toBe(true);
    });

    it('aponta o indicador de convites do painel para a lista de convites', () => {
        expect(ROTA_CONVITES).toBe('/portal/convites');
    });

    it('acusa serviço do menu sem ação no painel', () => {
        const semConsulta: PortalAcao[] = PORTAL_ACOES.filter((acao) => acao.href !== '/portal/consulta');

        expect(assertPortalNavHealth(PORTAL_NAV_GROUPS, semConsulta)).toContain(
            'Serviço "Consultar processo" não tem ação no painel.',
        );
    });

    it('acusa grupo de item único, href duplicado e ícone repetido', () => {
        const quebrado: PortalNavGroup[] = [
            { id: 'inicio', label: 'Início', items: [{ name: 'Meu painel', href: '/portal/painel', icon: 'grid' }] },
            {
                id: 'servicos',
                label: 'Serviços',
                items: [
                    { name: 'A', href: '/portal/a', icon: 'file' },
                    { name: 'B', href: '/portal/a', icon: 'file' },
                ],
            },
            { id: 'conta', label: 'Minha conta', items: [] },
        ];

        const erros = assertPortalNavHealth(quebrado, []);

        expect(erros).toContain('Grupo "Início" tem um único item — absorva em um grupo existente.');
        expect(erros).toContain('Hrefs duplicados no menu: /portal/a.');
        expect(erros).toContain('Ícones repetidos no menu: file.');
    });
});

describe('Serviços retirados e convites na navegação', () => {
    const retirados = ['/portal/viabilidade', '/portal/solicitacoes', '/portal/empresas', '/portal/procuracoes'];

    it('não oferece consulta prévia, solicitação, empresas nem procurações', () => {
        const hrefs = [
            ...acoesDoPortal().map((acao) => acao.href),
            ...portalSidebarGroups().flatMap((group) => group.items.map((item) => item.href)),
        ];

        for (const retirado of retirados) {
            expect(hrefs.some((href) => href.startsWith(retirado)), retirado).toBe(false);
        }
    });

    it('mostra a contagem de convites só no item Responder convite', () => {
        const itens = portalSidebarGroups(true, true, 2).flatMap((group) => group.items);

        expect(itens.find((item) => item.href === '/portal/convites')?.badge).toBe(2);
        expect(itens.filter((item) => item.badge).length).toBe(1);
    });

    it('com os serviços do portal desligados o grupo de serviços continua fora do menu', () => {
        const rotulos = portalSidebarGroups(false, true).map((group) => group.label);

        expect(rotulos).not.toContain('Serviços');
    });
});
