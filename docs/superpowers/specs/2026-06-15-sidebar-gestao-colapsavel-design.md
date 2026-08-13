# Sidebar do console de Gestão — categorias colapsáveis e menu reorganizado

Data: 2026-06-15
Status: aprovado em discussão; aguardando revisão final da spec

## 1. Contexto e objetivo

O menu lateral é montado a partir de grupos (`SidebarGroup` = `label` + `items[]`), definidos nos layouts e renderizados por `app-sidebar.tsx`. Hoje os `label` são apenas cabeçalhos estáticos: não dá para colapsar uma categoria. A barra já tem um colapso geral (290px expandida ↔ 90px só-ícones, via `sidebar-context`), mas não há colapso por categoria.

No console de Gestão (SEDUR) isso incomoda porque o menu tem ~20 itens, e o grupo "Cadastros" sozinho concentra 9 itens de naturezas distintas (motor de regras do licenciamento misturado com controle de acesso).

Objetivo: (a) tornar cada categoria do console de Gestão colapsável individualmente, com estado lembrado; e (b) reorganizar o menu de forma mais didática, agrupando por jornada de uso. Escopo restrito ao console de Gestão — o Portal do Cidadão não muda.

## 2. Decisões de escopo (aprovadas pelo usuário)

- **Escopo**: apenas o console de Gestão (`gestao-layout.tsx`). O Portal do Cidadão permanece com cabeçalhos estáticos.
- **Comportamento de colapso**: acordeão livre com memória — várias categorias podem ficar abertas ao mesmo tempo; o estado é persistido entre páginas e sessões; a categoria da página atual abre sozinha ao navegar.
- **Reorganização**: aprovada a estrutura de 6 categorias (§3.1).
- **Testes**: lógica isolada em funções puras testáveis com vitest (ambiente `node`), sem adicionar `@testing-library`/`jsdom` — mantém a decisão já vigente no projeto (vitest presente, testing-library ausente).

## 3. Arquitetura da solução

### 3.1 Reorganização do menu (conteúdo)

Mantém todos os itens e seus nomes; muda apenas o agrupamento e a ordem (por jornada: ver → atender → analisar → configurar regras → auditar → administrar).

| # | Categoria | Itens |
|---|-----------|-------|
| 1 | **Início** | Painel |
| 2 | **Atendimento e operação** | Consulta territorial · Atendimento presencial · Nova solicitação (contingência) · Resultados do fluxo expresso |
| 3 | **Análise técnica** | Fila de trabalho · Processos · Setores · Textos-padrão |
| 4 | **Regras do licenciamento** | CNAEs · Tipos de serviço · Classificação de risco · Condicionantes · Quadros LOUOS · Simulação de regras · Requisitos documentais |
| 5 | **Auditoria e compliance** | Trilha de auditoria · Conformidade LGPD · Alertas de abuso |
| 6 | **Administração** | Usuários · Perfis · Parâmetros · E-mails |

Racional: separa o motor de regras (core do SILE) num grupo próprio; tira acesso/config do balaio "Cadastros" para "Administração"; separa dashboard (Início) das ações de balcão (Atendimento e operação). O filtro por permissão (`visible`) de cada item é preservado integralmente.

### 3.2 Estrutura de dados e propriedade

- `AppSidebar` ganha o prop opcional `collapsibleGroups?: boolean` (default `false`). Só o `gestao-layout` passa `true`; com isso o Portal continua idêntico.
- O prop é propagado por `app-shell.tsx` (que hoje repassa `groups`, `homeHref`, `subtitle`, `variant`).
- **Coesão**: a definição do grupo "Auditoria e compliance" — hoje hardcoded dentro de `app-sidebar.tsx` e injetada quando `variant === 'console'` — é **movida para `gestao-layout.tsx`**, junto das demais categorias, na posição 5. O componente volta a ser puramente de renderização, sem conhecer rotas/permissões específicas do console. O layout já tem `auth.permissions`, então os mesmos `visible` são mantidos.

### 3.3 Estado e persistência (lógica pura em `sidebar-collapse.ts`)

Novo módulo `resources/js/components/app/sidebar-collapse.ts`, espelhando o padrão de `sidebar-active.ts`. Funções puras:

- `resolveActiveGroup(groups, activeHref)` → `label` da categoria que contém o item ativo, ou `null`.
- `readClosedGroups(storage?)` → lê a lista de categorias fechadas do storage; retorna `[]` se ausente, inválido ou sem `window` (guarda SSR).
- `writeClosedGroups(labels, storage?)` → grava a lista.
- `toggleClosed(closed, label)` → novo array com o label adicionado/removido.
- `withGroupOpen(closed, label)` → novo array sem aquele label (usado para abrir a categoria ativa).

Chave de storage: `sile.sidebar.gestao.closedGroups`. Guarda-se a lista de categorias **fechadas** (não as abertas): assim o padrão inicial é "tudo aberto" como hoje, e categorias novas adicionadas no futuro nascem abertas.

Comportamento no componente (quando `collapsibleGroups`):
- Estado `closedGroups` inicializado por `readClosedGroups()`.
- Uma categoria está aberta quando não está em `closedGroups`.
- Ao montar e sempre que a categoria ativa mudar (mudança de rota), aplica-se `withGroupOpen(closed, activeGroup)` e persiste — é o "abre sozinha ao navegar". O usuário ainda pode fechá-la manualmente depois.
- Toggle no cabeçalho aplica `toggleClosed` e persiste.

### 3.4 Renderização e modo só-ícone

- Quando `collapsibleGroups` e há texto visível (`showText`): o cabeçalho da categoria é um `<button type="button">` com o `label` e o `ChevronDownIcon` (já existe em `components/icons`) à direita, que rotaciona quando aberto. A `<ul>` de itens é renderizada apenas quando a categoria está aberta.
- Modo só-ícone (90px, sem hover): o accordion não atua — todos os ícones continuam aparecendo como hoje (o cabeçalho permanece como o `HorizontalDotsIcon` atual). Como a barra já expande no hover, o accordion reaparece naturalmente ao passar o mouse; não há flyout/popover.
- Quando `collapsibleGroups` é `false` (Portal): renderização atual inalterada (cabeçalho estático, todos os itens sempre visíveis).

## 4. Acessibilidade (eMAG/WCAG — console interno)

- Cabeçalho colapsável é `<button type="button">` com `aria-expanded={aberto}` e `aria-controls` apontando para o `id` da `<ul>` correspondente; a `<ul>` recebe esse `id`.
- `ChevronDownIcon` é decorativo (`aria-hidden`); o estado é comunicado por `aria-expanded`, não só pela rotação.
- Toggle funciona por mouse e teclado (Enter/Espaço nativos do `button`), com foco visível.
- A categoria ativa abrir ao navegar evita que o usuário "perca" o item da página atual dentro de um grupo fechado.

## 5. Testes (TDD)

- Novo `resources/js/components/app/sidebar-collapse.test.ts` (vitest, ambiente `node`), cobrindo:
  - `resolveActiveGroup`: encontra o grupo do item ativo; retorna `null` quando nenhum item corresponde.
  - `readClosedGroups`/`writeClosedGroups`: round-trip com storage mock; tolera JSON inválido e storage ausente (retorna `[]`).
  - `toggleClosed`: adiciona quando ausente, remove quando presente, sem mutar o array original.
  - `withGroupOpen`: remove o label informado; no-op quando ausente.
- Verificação: `npm run test`, `npm run typecheck` e `npm run build`.
- O componente React renderizado não tem teste automatizado (consistente com o estado atual do repo, que testa `sidebar-active.ts` mas não `app-sidebar.tsx`). A lógica crítica fica nas funções puras acima.

## 6. Fora de escopo

- Portal do Cidadão (nenhuma mudança).
- Adicionar `@testing-library/react`/`jsdom` ou qualquer dependência de runtime.
- Redesenho visual da barra (cores, larguras, identidade console/light) — apenas o colapso por categoria e a reorganização do conteúdo.
- Mudanças no colapso geral da barra (290px ↔ 90px) e no `sidebar-context`.
- Parametrização administrável da ordem/visibilidade do menu por interface (o menu segue definido em código e filtrado por permissão).
