import { describe, expect, it } from 'vitest';
import {
    acoesPublicas,
    assertPortalNavHealth,
    FILTROS_SOLICITACOES,
    PORTAL_ACOES,
    PORTAL_NAV_GROUPS,
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

    it('lista todos os serviços do cidadão no menu', () => {
        expect(hrefsDoGrupo('servicos')).toEqual([
            '/portal/viabilidade',
            '/portal/solicitacoes',
            '/portal/renovacao',
            '/portal/empresas',
        ]);
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

        expect(publicas.map((acao) => acao.href)).toEqual([
            '/portal/viabilidade',
            '/portal/solicitacoes/nova',
            '/portal/renovacao',
            '/portal/procuracoes',
        ]);
        expect(publicas.every((acao) => acao.href.startsWith('/portal/'))).toBe(true);
    });

    it('aponta os indicadores do painel para a lista já filtrada', () => {
        expect(FILTROS_SOLICITACOES.emAnalise).toBe('/portal/solicitacoes?status=em_analise');
        expect(FILTROS_SOLICITACOES.convites).toBe('/portal/solicitacoes?status=em_pendencia');
        expect(FILTROS_SOLICITACOES.todas).toBe('/portal/solicitacoes');
    });

    it('acusa serviço do menu sem ação no painel', () => {
        const semEmpresas: PortalAcao[] = PORTAL_ACOES.filter((acao) => acao.href !== '/portal/empresas');

        expect(assertPortalNavHealth(PORTAL_NAV_GROUPS, semEmpresas)).toContain(
            'Serviço "Minhas empresas" não tem ação no painel.',
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
