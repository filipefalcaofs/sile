/**
 * Resolve qual href do menu deve ficar ativo para a URL atual.
 *
 * Em vez de cada item decidir isoladamente (o que acende dois itens quando um
 * href é prefixo de outro, ex.: `/gestao/processos` x `/gestao/processos/fila`),
 * escolhe-se o prefixo mais específico (o mais longo que casa). Assim só um item
 * fica ativo, e páginas de detalhe sem item próprio mantêm o item-pai aceso.
 *
 * O `homeHref` exige correspondência exata, pois é prefixo de todas as rotas do
 * ambiente e, com `startsWith`, acenderia em qualquer página.
 *
 * @param currentPath - Caminho atual já sem query string (ex.: `/gestao/processos/fila`)
 * @param hrefs - Hrefs de todos os itens de menu visíveis
 * @param homeHref - Href base do ambiente (ex.: `/gestao` ou `/portal`)
 * @returns O href ativo, ou `null` quando nenhum item corresponde
 */
export function resolveActiveHref(currentPath: string, hrefs: string[], homeHref: string): string | null {
    let active: string | null = null;

    for (const href of hrefs) {
        const matches = href === homeHref ? currentPath === href : currentPath === href || currentPath.startsWith(`${href}/`);

        if (matches && (active === null || href.length > active.length)) {
            active = href;
        }
    }

    return active;
}
