---
phase: 07-consulta-previa-viabilidade
plan: 09
subsystem: ui
tags: [consulta-viabilidade, historico, inertia-react, portal-autenticado, snapshot-imutavel, resultado-da-epoca, escopo-dono, navegacao-portal, acessibilidade, hu-060]

# Dependency graph
requires:
  - phase: 07-consulta-previa-viabilidade
    plan: 07
    provides: "Rota autenticada portal.viabilidade.historico + HistoricoConsultaController (listagem paginada forUser) + contrato do item (id, entry_type, resultado, resultado_label, input, created_at, result)"
  - phase: 07-consulta-previa-viabilidade
    plan: 08
    provides: "Componente ResultadoViabilidade (export nomeado) + tipos TS do contrato (ResultadoConsulta, EntradaConsulta, VereditoResultado)"
  - phase: 03-cadastro-empresarial
    provides: "Padrão de listagem do portal (portal/empresas/index.tsx): PageHeader + Card + DataTable + Pagination + EmptyState, com Page.layout PortalLayout"
provides:
  - "Página autenticada portal/viabilidade/historico.tsx (PortalLayout): lista as consultas do dono (data/tipo/entrada/veredito), paginada, com EmptyState e CTA Nova consulta"
  - "Visualização do snapshot salvo em Modal reusando ResultadoViabilidade (07-08) — resultado da época, sem reprocessar"
  - "Item de navegação 'Consultas de viabilidade' no grupo Serviços do portal (sidebar) → /portal/viabilidade/historico"
  - "Asserção Inertia reativada: component('portal/viabilidade/historico') + escopo do dono + exigência de autenticação"
affects: [07-10-smoke (consome o histórico autenticado no E2E)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Histórico do portal como listagem read-only sem server-table: DataTable simples (sem useServerTable/TableToolbar/PerPageSelect) consumindo o paginator de 07-07 (sem busca/sort/per_page no backend)"
    - "Snapshot exibido em Modal com ResultadoViabilidade (mesma view única da consulta pública 07-08) — uma só renderização honesta para consulta e histórico"
    - "Cor do veredito na listagem alinhada à VEREDITO_STYLES do componente: pendente = info (distinto), nunca herda permitido/não permitido"

key-files:
  created:
    - resources/js/pages/portal/viabilidade/historico.tsx
    - tests/Feature/Viabilidade/HistoricoConsultaPaginaTest.php
  modified:
    - resources/js/layouts/portal-layout.tsx

key-decisions:
  - "Listagem com DataTable 'nua' (sem useServerTable): o HistoricoConsultaController (07-07) pagina sem busca/sort/per_page e não envia filters/perPageOptions — usar o server-table quebraria o contrato"
  - "Snapshot da época mostrado em Modal (não reprocessa): a UI deixa explícito 'Resultado registrado em {data}' — coerente com a imutabilidade de viability_queries e o versionamento de regras"
  - "Reuso dos tipos exportados pelo ResultadoViabilidade (EntradaConsulta/ResultadoConsulta/VereditoResultado) no contrato do item — sem duplicar shape"
  - "Ícone da navegação = SearchIcon (consulta = busca/lookup), em vez do EyeIcon citado no plano: EyeIcon já é a ação 'Ver resultado' da linha; SearchIcon evita redundância e é semanticamente melhor"
  - "Veredito 'pendente' renderizado como Badge info (não warning como cita o parêntese do plano): mantém consistência visual com o chip do mesmo veredito exibido no Modal (ResultadoViabilidade)"

patterns-established:
  - "Telas do portal que só listam dados do dono (sem filtros server-side) usam DataTable direto + Pagination com meta from/to/total — sem toolbar"
  - "Detalhe/visualização de snapshot via Modal de largura ampla (max-w-[900px]) hospedando o componente de resultado reutilizável"

# Metrics
duration: ~10 min
completed: 2026-06-14
---

# Phase 7 Plan 09: UI do Histórico Autenticado de Consultas (HU-060) Summary

**O critério 3 do EP07 ganhou sua porta autenticada visual: `portal/viabilidade/historico.tsx` (PortalLayout, portal light) lista as consultas prévias do PRÓPRIO usuário consumindo o paginator real do `HistoricoConsultaController` (07-07) — Data, Tipo (Endereço/CNAE/Inscrição), Entrada (resumo) e Veredito (Badge honesto, `pendente` distinto) — com EmptyState, paginação `from/to/total` e CTA "Nova consulta". A ação "Ver resultado" abre um Modal que renderiza o SNAPSHOT salvo via o componente `ResultadoViabilidade` do 07-08 (resultado da época — não reprocessa, coerente com a imutabilidade da tabela e o versionamento de regras), deixando explícito "Resultado registrado em {data}". A sidebar do portal ganhou o item "Consultas de viabilidade" (grupo Serviços). Provado por typecheck/build verdes e por 2 feature tests Inertia (render do componente + escopo do dono + exigência de autenticação), reativando a asserção `component('portal/viabilidade/historico')` que o 07-07 deixou pendente. Suíte 650/650, zero regressão.**

## Performance

- **Duration:** ~10 min
- **Completed:** 2026-06-14
- **Tasks:** 2 (Task 1 página + navegação; Task 2 feature test)
- **Files:** 3 (2 criados, 1 modificado) — ZERO dependência nova (DS próprio + componente 07-08)

## Accomplishments

- **Página autenticada** do histórico (PortalLayout): DataTable com Data/Tipo/Entrada/Veredito, paginada, EmptyState ("Você ainda não realizou consultas") e CTA para `/portal/viabilidade`.
- **Snapshot da época em Modal**: "Ver resultado" reusa `ResultadoViabilidade` (07-08) com o `result` completo do item — sem reprocessar; aviso explícito de que é o registro da época.
- **Navegação do portal**: item "Consultas de viabilidade" → `/portal/viabilidade/historico` no grupo Serviços (sidebar).
- **Teste de contrato server-side**: render do componente + escopo do dono (2 do usuário, 1 de outro → vê só 2) + autenticação exigida.

## Task Commits

1. **Task 1: página historico.tsx + item de navegação** — `0c8920b` (feat, HU-060)
2. **Task 2: feature test da página (component + escopo do dono + auth)** — `edfab75` (test, HU-060)

**Plan metadata:** este SUMMARY (`docs(07-09)`).

## Files Created/Modified

- `resources/js/pages/portal/viabilidade/historico.tsx` — página autenticada: PageHeader + Card + DataTable (Data/Tipo/Entrada/Veredito/Ações) + Pagination + EmptyState; Modal com `ResultadoViabilidade` para o snapshot; `Historico.layout = PortalLayout`.
- `resources/js/layouts/portal-layout.tsx` — item "Consultas de viabilidade" (SearchIcon) no grupo Serviços; import de `SearchIcon`.
- `tests/Feature/Viabilidade/HistoricoConsultaPaginaTest.php` — 2 testes: render do componente Inertia + escopo do dono; exigência de autenticação.

## Decisions Made

- **DataTable "nua" (sem server-table):** o controller do 07-07 pagina sem busca/sort/per_page e não envia `filters`/`perPageOptions`. Usar `useServerTable`/`TableToolbar`/`PerPageSelect` (padrão de empresas) enviaria parâmetros ignorados e quebraria o contrato — então a listagem é direta, com Pagination via `<Link>` do paginator.
- **Snapshot da época em Modal, sem reprocessar:** o item já carrega o `result` completo (07-07). A UI mostra esse snapshot e avisa "Resultado registrado em {data}" — fiel à imutabilidade de `viability_queries` e ao versionamento de regras (o veredito é o da época).
- **Reuso dos tipos do 07-08:** `EntradaConsulta`/`ResultadoConsulta`/`VereditoResultado` tipam o item do histórico — uma só fonte de verdade do contrato.

## Deviations from Plan

Nenhum bug, correção crítica, bloqueio ou mudança arquitetural. Duas escolhas visuais menores, dentro dos `files_modified` do 07-09 (zero scope creep, zero dependência nova):

- **[Escolha de ícone]** Navegação usa `SearchIcon` em vez do `EyeIcon` citado como exemplo no plano. O plano qualifica "(usar um ícone já existente)"; `EyeIcon` já é a ação "Ver resultado" da linha, então `SearchIcon` (consulta = busca/lookup) evita redundância e é semanticamente melhor para o item de menu.
- **[Cor do veredito]** Badge de `pendente` renderizado como `info` (azul) em vez do `warning` citado no parêntese do plano. Mantém consistência com o chip do MESMO veredito exibido logo abaixo no Modal (`ResultadoViabilidade` usa `info` para pendente) e preserva a regra anti-fachada: `pendente` é visualmente distinto e nunca herda a aparência de permitido/não permitido (`permitido_com_condicoes` já é o `warning`).

**Total deviations:** 0 correções de produção; 2 escolhas visuais menores. **Impacto:** nenhum scope creep; backend (07-07) e componente (07-08) intocados.

## Issues Encountered

- **RED degenerado no teste do componente (esperado):** os feature tests Inertia asseguram o objeto-página da resposta (component + props), não renderizam React nem exigem o arquivo `.tsx` em disco. Como o `component('portal/viabilidade/historico')` e o escopo já vêm do controller do 07-07, o teste passa assim que escrito — a cobertura nova é a ligação explícita do `component()` que o 07-07 havia adiado. A renderização visual (React) é validada por typecheck/build e pela inspeção manual.

## User Setup Required

None - nenhuma configuração de serviço externo é necessária.

## Verification

- `npx tsc --noEmit` → **verde** (sem erros TS).
- `npm run build` → **verde** (chunks `historico-*.js` gerados; `resultado-viabilidade-*.js` reusado).
- `php artisan test --compact --filter=HistoricoConsultaPaginaTest` → **2/2 verde** (14 asserções).
- `php artisan test --compact --exclude-group postgis` → **650/650 verde** (3309 asserções) = 648 baseline (07-08) + 2 novos, **zero regressão**.
- `vendor/bin/pint --dirty --format agent` → passed.
- **Greps de aceitação:** `ResultadoViabilidade`/`PortalLayout` (5) em `historico.tsx`; `/portal/viabilidade/historico` (1) em `portal-layout.tsx`; `component('portal/viabilidade/historico')` (1) no teste.
- **Verificação visual (composer dev):** PENDENTE DE CONFIRMAÇÃO MANUAL — a evidência automatizada (typecheck/build/feature tests) prova o contrato e o wiring; a inspeção em navegador (listagem, Modal com o snapshot, mobile 375px, dark mode) não foi executada por este agente.

## Next Phase Readiness

- **07-10 (smoke E2E):** o histórico autenticado está pronto — logar, consultar, abrir `/portal/viabilidade/historico`, ver a consulta listada e o snapshot no Modal.
- **Bloqueios herdados (não introduzidos aqui):** o veredito `pendente` exibido reflete a degradação honesta do motor LOUOS (zona pendente SEDUR, Fase 13); o histórico apenas mostra o resultado real da época — quando a base de zona chegar, novas consultas gravam o veredito definitivo, sem mudar a lógica nem a UI.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
