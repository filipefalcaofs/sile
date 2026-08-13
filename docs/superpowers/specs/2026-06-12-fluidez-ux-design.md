# Fluidez de UX — Motion global e listagens adaptativas

Data: 2026-06-12
Status: aprovado em discussão; aguardando revisão final da spec

## 1. Contexto e objetivo

O SILE (Tailwind v4 + React 19 + Inertia v3) tem transições esparsas e inconsistentes: alguns componentes usam `transition`, a maioria não; modais e dropdowns aparecem/somem de forma abrupta; não há barra de progresso de navegação; não existe tratamento de `prefers-reduced-motion`; e as listagens viram scroll horizontal no celular.

Objetivo: dar fluidez consistente a todo o frontend (portal do cidadão, backoffice de gestão e telas de auth) e tornar isso o padrão do projeto daqui em diante, sem comprometer a acessibilidade (eMAG/gov.br).

## 2. Decisões de escopo (aprovadas pelo usuário)

- Foco: micro-interações + animações de entrada + transição entre páginas + estados de carregamento (tudo).
- Abrangência: portal + gestão + auth; vira padrão permanente do projeto.
- Acessibilidade: respeitar `prefers-reduced-motion` globalmente.
- Abordagem técnica: CSS/Tailwind nativo centralizado (abordagem A) — sem novas dependências de runtime (sem framer-motion ou libs de keyframes).
- Listagens: Padrão 2 — tabela adaptativa integrada ao `DataTable` (cards empilhados abaixo de `md`, tabela completa de `md` para cima).

## 3. Arquitetura da solução

### 3.1 Camada de motion central (`resources/css/app.css`)

Tokens no `@theme` (consumíveis como utilities Tailwind v4):

- Easings: `--ease-fluid: cubic-bezier(0.22, 1, 0.36, 1)` (saída suave, padrão para entradas) e `--ease-snappy: cubic-bezier(0.2, 0, 0, 1)` (micro-interações).
- Durações: `--duration-fast: 150ms`, `--duration-normal: 250ms`, `--duration-slow: 400ms`.
- Animações nomeadas (`--animate-*` + `@keyframes`): `fade-in`, `fade-in-up` (translateY de 8px — sutil, sem CLS perceptível), `fade-in-down`, `scale-in` (de 0.95), `slide-in-right` (para painéis laterais mobile).

Utilities derivadas: `animate-fade-in`, `animate-fade-in-up`, `animate-scale-in` etc. Stagger em listas via `animation-delay` inline incremental, limitado aos ~10 primeiros itens (depois disso, entrada imediata — ninguém espera 30 itens pingarem).

Bloco global de acessibilidade:

```css
@media (prefers-reduced-motion: reduce) {
    *, ::before, ::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

Efeito consciente: spinners e skeletons ficam estáticos para quem pede movimento reduzido — a comunicação de carregamento permanece via `aria-busy` e textos. Para scrolls programáticos (`scrollIntoView({ behavior: 'smooth' })`, como em procurações), helper JS `prefersReducedMotion(): boolean` (via `matchMedia`) escolhe `auto` quando aplicável.

### 3.2 Navegação Inertia

- `createInertiaApp` ganha configuração de progresso: cor `#1351b4` (brand-500), `delay` curto para não piscar em navegações rápidas. Verificar API exata do v3 via `search-docs` antes de implementar.
- Entrada de página: o miolo de conteúdo dos layouts (`AppShell`, usado por portal e gestão, e `auth-layout`) ganha `animate-fade-in` (250ms) keyed pela URL da página, para o conteúdo novo surgir suave a cada navegação. Sem movimento vertical no nível de página (evita sensação de salto em navegação frequente).

### 3.3 Componentes globais

- `Modal`: backdrop com `fade-in` e diálogo com `scale-in`; animação de saída antes de desmontar via hook novo `useExitTransition` (estado `open → closing → unmounted`, conclusão por `onAnimationEnd` com fallback de timeout). `ConfirmDialog` e `Backdrop` herdam o comportamento.
- `Dropdown` (e `dropdown-item`): `fade-in-down` + `scale-in` curtos na abertura (raiz com `transform-origin` no topo). Saída imediata (dropdown fechar rápido é melhor UX que animar).
- `Button`: adicionar `active:scale-[0.98]` e padronizar `transition` com os novos tokens; spinner de loading permanece.
- Inputs, `Select`, `Switch`, `Checkbox`: transições suaves de borda/fundo/foco com `--ease-snappy`/`--duration-fast` (vários já têm `transition` solta; padronizar).
- `Alert` (incluindo flash de sucesso nos layouts): entrada com `fade-in-down`.
- `KpiCard`, `Card`, `PageHeader`, `EmptyState`: entrada com `fade-in-up`; KPIs do dashboard com stagger leve.
- `Table`/`DataTable`: linhas mantêm hover transition; corpo ganha `fade-in` na troca de dados (sem stagger por linha em tabela desktop — só nos cards mobile).

### 3.4 DataTable adaptativo (Padrão 2)

`ColumnDef<T>` ganha anotação opcional:

```ts
mobile?: {
    role?: 'title' | 'detail' | 'actions' | 'hidden';
    label?: string; // rótulo no card; default: header quando for string
};
```

Defaults sem anotação: primeira coluna = `title`, demais = `detail`. Comportamento do `DataTable`:

- `>= md`: tabela atual, inalterada.
- `< md`: lista (`<ul>`) de cards — `title` no topo (conteúdo da célula primária), pares rótulo/valor para `detail`, ações no rodapé do card alinhadas à direita, colunas `hidden` omitidas.
- Alternância por CSS (`md:hidden` / `hidden md:block`): `display: none` remove o bloco inativo da árvore de acessibilidade — sem conteúdo duplicado para leitores de tela.
- Ordenação em mobile: quando houver colunas `sortable` e `onSortChange`, um select compacto "Ordenar por" acima dos cards (paridade com o cabeçalho clicável da tabela).
- Loading: skeleton em formato de card no modo mobile (hoje só existe skeleton de linha).
- Empty state: mesmo `EmptyState` nos dois modos.
- Entrada: cards com `fade-in-up` + stagger; tabela com `fade-in` simples.

### 3.5 Migrações de telas

- `portal/procuracoes/index.tsx`: as duas tabelas cruas (`GrantedTable`, `ReceivedTable`) migram para `DataTable` client-side (sem ordenação — `sort` já é opcional), ganhando o modo card automaticamente.
- `portal/empresas/index.tsx` e todas as páginas da gestão que usam `DataTable` (cnaes, usuários, perfis, parâmetros, e-mails): ganham o modo card apenas anotando `mobile` nas colunas onde o default não bastar.

### 3.6 Convenção permanente

Nova rule de projeto `.cursor/rules/motion.mdc` (curta, sempre aplicada): use os tokens/utilities de motion do `app.css`; nada de durações/easings improvisados; toda animação nova precisa funcionar sob `prefers-reduced-motion`; listagens novas usam `DataTable` (que já é adaptativo).

## 4. Acessibilidade

- `prefers-reduced-motion` coberto globalmente em CSS + helper JS para scroll programático.
- Modo de alto contraste (`html.acc-contrast`) não é afetado: animações não alteram cores/contraste.
- Modal mantém o padrão WAI-ARIA atual (foco, trap, ESC); a animação de saída não pode atrasar a devolução de foco além da duração da animação (250ms máx.).
- Tabela/cards alternados por `display` — sem duplicação na árvore de acessibilidade.

## 5. Testes

Lógica nova client-side: `useExitTransition`, derivação de papéis mobile das colunas, `prefersReducedMotion`. O projeto não tem runner JS (só PHPUnit, que não cobre client-side).

Decisão em aberto (ver §7): adicionar `vitest` + `@testing-library/react` como devDependencies para cobrir essa lógica via TDD. Sem isso, a fase fica limitada a verificação visual manual + typecheck (`npm run typecheck`) — abaixo do padrão de qualidade do projeto para código com lógica.

Backend: nenhuma mudança de comportamento; feature tests PHPUnit existentes continuam valendo (as páginas seguem renderizando os mesmos componentes Inertia com as mesmas props).

## 6. Fora de escopo

- Novas dependências de runtime (framer-motion etc.).
- Redesenho visual de telas (cores, espaçamentos, hierarquia) — apenas motion e o modo card das listagens.
- View transitions API do navegador (experimental; reavaliar no futuro).
- Animações em e-mails ou PDFs.

## 7. Decisões em aberto

1. **Runner de testes JS**: aprovar a adição de `vitest` + `@testing-library/react` (devDependencies) para testar a lógica nova? Recomendado: sim — é o que permite TDD real no frontend daqui em diante; sem impacto no bundle de produção.
