import { describe, expect, it } from 'vitest';
import { resolveActiveHref } from './sidebar-active';

const hrefs = [
    '/gestao',
    '/gestao/processos/fila',
    '/gestao/processos',
    '/gestao/setores',
    '/gestao/risco',
    '/gestao/risco/condicionantes',
];
const homeHref = '/gestao';

describe('resolveActiveHref', () => {
    it('marca apenas o item mais específico quando uma rota é prefixo de outra', () => {
        expect(resolveActiveHref('/gestao/processos/fila', hrefs, homeHref)).toBe('/gestao/processos/fila');
    });

    it('mantém o item-pai ativo em páginas de detalhe que não correspondem a nenhum item específico', () => {
        expect(resolveActiveHref('/gestao/processos/123', hrefs, homeHref)).toBe('/gestao/processos');
    });

    it('marca o item de menu correspondente à própria rota da lista', () => {
        expect(resolveActiveHref('/gestao/processos', hrefs, homeHref)).toBe('/gestao/processos');
    });

    it('exige correspondência exata para o homeHref, evitando que ele acenda em rotas filhas', () => {
        expect(resolveActiveHref('/gestao', hrefs, homeHref)).toBe('/gestao');
        expect(resolveActiveHref('/gestao/processos/fila', hrefs, homeHref)).not.toBe('/gestao');
    });

    it('resolve o mesmo conflito de prefixo em outros grupos (risco x condicionantes)', () => {
        expect(resolveActiveHref('/gestao/risco/condicionantes', hrefs, homeHref)).toBe('/gestao/risco/condicionantes');
    });

    it('retorna null quando nenhuma rota corresponde', () => {
        expect(resolveActiveHref('/portal/empresas', hrefs, homeHref)).toBeNull();
    });
});
