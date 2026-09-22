import { describe, expect, it } from 'vitest';
import {
    assertGestaoNavHealth,
    destinosComando,
    filterGestaoNav,
    GESTAO_NAV_GROUPS,
    groupOfHref,
} from './gestao-nav';

describe('IA da sidebar de Gestão', () => {
    it('mantém a ordem canônica dos grupos', () => {
        expect(GESTAO_NAV_GROUPS.map((group) => group.id)).toEqual([
            'operacao',
            'territorio',
            'regras',
            'indicadores',
            'relatorios',
            'auditoria',
            'administracao',
            'configuracao',
        ]);
    });

    it('passa nas invariantes de organização (sem item no grupo errado)', () => {
        expect(assertGestaoNavHealth()).toEqual([]);
    });

    it('coloca Feriados em Configuração e Setores em Administração', () => {
        expect(groupOfHref('/gestao/feriados')?.id).toBe('configuracao');
        expect(groupOfHref('/gestao/setores')?.id).toBe('administracao');
    });

    it('coloca fila, processos e atendimento em Operação', () => {
        expect(groupOfHref('/gestao/processos/fila')?.id).toBe('operacao');
        expect(groupOfHref('/gestao/processos')?.id).toBe('operacao');
        expect(groupOfHref('/gestao/atendimento')?.id).toBe('operacao');
        expect(groupOfHref('/gestao/caixa-setor')?.id).toBe('operacao');
    });

    it('mostra a caixa do setor para quem analisa ou tramita (apoio)', () => {
        const doAnalista = filterGestaoNav(['analisar-processos']);
        const doApoio = filterGestaoNav(['distribuir-processos']);

        const hrefsDe = (groups: ReturnType<typeof filterGestaoNav>) =>
            groups.flatMap((group) => group.items.map((item) => item.href));

        expect(hrefsDe(doAnalista)).toContain('/gestao/caixa-setor');
        expect(hrefsDe(doApoio)).toContain('/gestao/caixa-setor');
        expect(hrefsDe(filterGestaoNav(['consultar-solicitacoes']))).not.toContain('/gestao/caixa-setor');
    });

    it('coloca a planilha de regras no grupo Regras, antes das simulações', () => {
        expect(groupOfHref('/gestao/regras-tratamento')?.id).toBe('regras');

        const regras = GESTAO_NAV_GROUPS.find((group) => group.id === 'regras');
        const nomes = regras?.items.map((item) => item.name) ?? [];
        const planilha = nomes.indexOf('Planilha de regras');
        const primeiraSimulacao = nomes.findIndex((nome) => nome.startsWith('Simulação'));

        expect(planilha).toBeGreaterThan(-1);
        expect(planilha).toBeLessThan(primeiraSimulacao);
    });

    it('isola território e camadas GIS no grupo Território', () => {
        expect(groupOfHref('/gestao/territorio')?.id).toBe('territorio');
        expect(groupOfHref('/gestao/territorio/camadas')?.id).toBe('territorio');
        expect(groupOfHref('/gestao/territorio/geoserver')?.id).toBe('territorio');
    });

    it('esconde grupos e itens sem permissão, mas mantém o Painel', () => {
        const visivel = filterGestaoNav(['consultar-solicitacoes']);

        expect(visivel.map((group) => group.id)).toEqual(['operacao', 'relatorios']);
        expect(visivel[0]?.items.map((item) => item.href)).toEqual(['/gestao', '/gestao/processos']);
        expect(visivel[1]?.items.map((item) => item.href)).toEqual(['/gestao/resultados-expresso']);
    });

    it('coloca a caixa de malha fina em Operação, gated por analisar-malha-fina', () => {
        expect(groupOfHref('/gestao/malha-fina')?.id).toBe('operacao');

        const hrefsDe = (groups: ReturnType<typeof filterGestaoNav>) =>
            groups.flatMap((group) => group.items.map((item) => item.href));

        expect(hrefsDe(filterGestaoNav(['analisar-malha-fina']))).toContain('/gestao/malha-fina');
        expect(hrefsDe(filterGestaoNav(['encaminhar-malha-fina']))).not.toContain('/gestao/malha-fina');
    });

    it('mantém resultados do expresso em Relatórios (leitura, não trabalho do dia)', () => {
        expect(groupOfHref('/gestao/resultados-expresso')?.id).toBe('relatorios');
    });

    it('espelha o catálogo no Cmd+K para as permissões do usuário', () => {
        const destinos = destinosComando(['consultar-relatorios', 'manter-parametros']);

        expect(destinos.some((destino) => destino.href === '/gestao/feriados' && destino.grupo === 'Configuração')).toBe(
            true,
        );
        expect(destinos.some((destino) => destino.grupo === 'Relatórios' && destino.href.includes('/relatorios/'))).toBe(
            true,
        );
        expect(destinos.some((destino) => destino.href === '/gestao/processos/fila')).toBe(false);
    });

    it('reprova grupo extra, href duplicado ou cadastro no grupo errado', () => {
        const grupos = [
            ...GESTAO_NAV_GROUPS,
            {
                id: 'avulso',
                label: 'Avulso',
                items: [{ name: 'Feriados (errado)', href: '/gestao/feriados-2', icon: 'tag' as const, permission: null }],
            },
        ];

        const erros = assertGestaoNavHealth(grupos);

        expect(erros.some((erro) => erro.includes('grupos'))).toBe(true);
        expect(erros.some((erro) => erro.includes('único item'))).toBe(true);
    });
});
