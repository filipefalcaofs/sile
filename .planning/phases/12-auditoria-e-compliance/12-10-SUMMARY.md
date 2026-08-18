---
phase: 12-auditoria-e-compliance
plan: 10
subsystem: ui
tags: [auditoria, explicabilidade, lgpd, inertia, react, server-driven, hu-098, hu-099, hu-100, hu-101, hu-102, ssr-safe]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-04 — página gestao/auditoria/index (registros paginados, fonte, filtros, fonteOptions, perPageOptions) + rota gestao.auditoria.export (CSV)"
  - phase: 12-auditoria-e-compliance
    provides: "12-05 — prop ADITIVA explicacao (DecisionExplanationResource: por_cnae[].passos[] + flag registrado/legado) no show de ResultadoExpresso e Processo"
  - phase: 12-auditoria-e-compliance
    provides: "12-07 — página gestao/lgpd/index (consentimentos, retencao, acessosDadoPessoal, direitosTitular)"
  - phase: 10-analise-tecnica-sedur
    provides: "Padrões de UI server-driven (resultados-expresso/index, processos/show, emails/index) + design system (DataTable, Card, Badge, PerPageSelect, Pagination, KpiCard)"
provides:
  - "Página gestao/auditoria/index: trilha unificada server-driven (seletor de fonte + filtros que disparam visita Inertia + tabela paginada + export CSV preservando os filtros) — HU-098/100/101"
  - "Componente components/auditoria/decision-explanation: passo a passo por CNAE na ordem canônica com marca honesta de legado/não registrado — HU-099"
  - "Página gestao/lgpd/index: painel minimizado (consentimentos % aceite, retenção/pruning, acessos a dado pessoal em métrica) + pendência DPO honesta — HU-102"
  - "Prop explicacao consumida em resultados-expresso/show (card abaixo da decisão) e processos/show (aba Explicabilidade quando há decisão)"
affects:
  - "12-11 (navegação/Cmd+K): a sidebar deve apontar para /gestao/auditoria (consultar-auditoria) e /gestao/lgpd (monitorar-lgpd)"
  - "12-12 (smoke/verificação navegável): exercita trilha (3 fontes + CSV), explicabilidade no detalhe e painel LGPD"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Página server-driven com múltiplos filtros via router.get (preserveState/preserveScroll/replace): datas/fonte/per_page imediatos, campos textuais com debounce 350ms — o cliente NUNCA recalcula"
    - "Export de arquivo via tag <a href> (download real do navegador), nunca visita Inertia, preservando os MESMOS filtros + formato=csv"
    - "Colunas de DataTable que adaptam ao recorte (atividade/alteracoes/acessos), com o diff attribute_changes (HU-098) renderizado na fonte alteracoes"
    - "Componente de explicabilidade que itera por_cnae[].passos[] genericamente (mesmo shape de trace e legado) e é SSR-safe (Array.isArray + guards de objeto antes de .map)"
    - "Consumo estritamente ADITIVO da prop explicacao nos show.tsx (renderiza só quando presente; nenhuma prop/lógica existente alterada)"

key-files:
  created:
    - "resources/js/pages/gestao/auditoria/index.tsx"
    - "resources/js/pages/gestao/lgpd/index.tsx"
    - "resources/js/components/auditoria/decision-explanation.tsx"
  modified:
    - "resources/js/pages/gestao/resultados-expresso/show.tsx"
    - "resources/js/pages/gestao/processos/show.tsx"

key-decisions:
  - "auditoria/index NÃO usa o hook useServerTable (que sempre injeta sort/direction/search inexistentes nesta tela); usa router.get direto com fonte+filtros+per_page — server-driven honesto, sem parâmetros enganosos na URL/CSV"
  - "Export CSV é uma tag <a href> (download real), não Link Inertia — visita Inertia não baixa stream; preserva fonte + MESMOS filtros + formato=csv apontando para a rota gestao.auditoria.export"
  - "Filtros de log_name/event/resultado como inputs de texto (match exato), não selects — a 12-04 não entregou listas de opções; fabricar enum seria fachada"
  - "Filtros de entidade (tipo/id), log/resultado escondidos na fonte 'acessos' (não se aplicam ao access_logs); o backend já ignora, a UI só não polui"
  - "Explicabilidade embutida como card próprio em resultados-expresso/show (abaixo da decisão) e como aba condicional em processos/show (só aparece quando há decisão = prop presente)"
  - "decision-explanation renderiza entrada/resultado_parcial como pares chave→valor legíveis (scalars; objetos compactados) — mostra o que o motor gravou sem despejar estrutura crua"
  - "Painel LGPD minimizado: KPIs + tabela de acessos por ação (contagem), nunca PII; estados honestos (sem_termo_publicado, pruning nunca executado, pendente-dpo)"

patterns-established:
  - "Filtro server-driven multi-campo: estado local inicia das props, visita debounced para texto e imediata para datas/selects, filtros vazios omitidos da URL"
  - "DecisionExplanation como componente reutilizável de leitura da explicabilidade — base para qualquer superfície que mostre o passo a passo de uma decisão"

# Metrics
duration: ~30min
completed: 2026-06-15
---

# Phase 12 Plan 10: Frontend da trilha, explicabilidade e painel LGPD Summary

**Três superfícies de leitura navegáveis e server-driven sobre o backend já entregue (12-04/05/07): a página da trilha unificada (`gestao/auditoria/index`) com seletor de fonte, filtros que disparam visita Inertia (`preserveState`/`replace`) e export CSV por download real preservando os mesmos filtros; o componente de explicabilidade passo a passo (`decision-explanation`) embutido no detalhe da decisão marcando honestamente os passos do legado como "não registrado nesta decisão"; e o painel LGPD minimizado (`gestao/lgpd/index`) com consentimentos, retenção/pruning e acessos a dado pessoal em métrica. ZERO dependência nova, build/typecheck limpos e as feature tests dos controllers (94 testes) verdes.**

## Performance

- **Duration:** ~30 min
- **Completed:** 2026-06-15
- **Tasks:** 2
- **Files:** 3 criados, 2 modificados

## Accomplishments

- **HU-098/100/101 — trilha navegável (`gestao/auditoria/index`):** seletor de FONTE (atividade/alteracoes/acessos) em abas, filtros (período, usuário, entidade tipo/id, log, evento, resultado) que disparam visita Inertia server-driven, tabela paginada com colunas que adaptam ao recorte (o diff `attribute_changes` aparece na fonte `alteracoes`), `PerPageSelect` e botão "Exportar CSV" como `<a href>` que preserva fonte + filtros + `formato=csv`. Estado vazio honesto (diferencia "sem registros" de "sem resultado de busca").
- **HU-099 — explicabilidade (`components/auditoria/decision-explanation.tsx`):** renderiza por CNAE o passo a passo na ordem canônica (entrada → risco → LOUOS Quadro 7/10/11/11A → consolidação → desfecho); cada passo mostra entrada/resultado/versão de regra/fundamentação quando registrado e um badge "não registrado nesta decisão" + motivo quando o legado não snapshotou. Banner honesto no topo quando `legado=true`. Nunca inventa nem recomputa.
- **HU-102 — painel LGPD (`gestao/lgpd/index`):** 3 KPIs de visão geral + 3 seções reais — consentimentos (% aceite da versão vigente; estado honesto "sem termo publicado"), retenção (dias + último pruning ou "nunca executado"; nota de decisões fora do pruning) e acessos a dado pessoal (total na janela + tabela por ação, contagem) — tudo minimizado, sem PII crua. Bloco "Direitos do titular" com a pendência DPO honesta (`pendente-dpo`).
- **Aditivo nos detalhes:** `resultados-expresso/show` ganha um card de explicabilidade abaixo da decisão; `processos/show` ganha uma aba "Explicabilidade" que só aparece quando há decisão — nenhuma prop/lógica existente foi alterada.

## Como cada superfície consome as props (insumo de 12-11/12-12)

### `gestao/auditoria/index` (rota `gestao.auditoria.index`, permissão `consultar-auditoria`)
- Consome: `registros` (paginator `{ data, links, from, to, total }`), `fonte`, `filtros` (+`per_page`), `fonteOptions [{value,label}]`, `perPageOptions [10,20,50,100]`.
- Linha de `atividade`/`alteracoes` = shape do `ActivityResource`; linha de `acessos` = `{ id, created_at, event, usuario, email, ip_address, channel }`.
- Filtros e fonte disparam `router.get('/gestao/auditoria', params, { preserveState, preserveScroll, replace })`; paginação via `Pagination` (links do servidor). Nada é recalculado no cliente.
- Export: `<a href="/gestao/auditoria/export?<fonte+filtros>&formato=csv">` (download real, mesmos filtros).

### `components/auditoria/decision-explanation` (prop `explicacao` = `DecisionExplanationData`)
- Itera `explicacao.por_cnae[].passos[]` (shape uniforme de trace e legado); renderiza `desfecho`, `rules_versions` (achatado) e `fundamentacao`.
- Flag-chave: `passo.registrado === false` → badge "não registrado nesta decisão" + `motivo`; `explicacao.legado === true` → banner honesto.
- Embutido em: `resultados-expresso/show.tsx` (card "Explicabilidade da decisão", sempre presente) e `processos/show.tsx` (aba "Explicabilidade", só quando `explicacao` não é null).

### `gestao/lgpd/index` (rota `gestao.lgpd.index`, permissão `monitorar-lgpd`)
- Consome: `consentimentos` (% aceite/pendentes/versão), `retencao` (dias + último pruning + decisões fora), `acessosDadoPessoal` (janela + total + `por_evento[]`), `direitosTitular` (`{ status, descricao }`).
- Estados honestos renderizados: `sem_termo_publicado`, `ultimo_pruning_em=null` ("Nunca executado"), `status='pendente-dpo'`. Sempre métrica, nunca PII.

## Task Commits

1. **Task 1: páginas gestao/auditoria/index e gestao/lgpd/index** — `2340788` (feat)
2. **Task 2: componente decision-explanation + integração nos 2 show** — `ce618dd` (feat)
3. **Refinamento: formato=csv explícito no export + docblock do painel lgpd** — `f242b7d` (refactor)

_Execução paralela com 12-09 (Wave 4): o irmão concluiu o backend do abuso e commitou (`c69c1bc`, `87828c0`, `a70fb42`) intercalado; toquei SOMENTE os 5 arquivos `.tsx` do meu `files_modified`, `git add` por pathspec (nunca `-A`/`.`). Nenhum toque em `routes/gestao.php` nem controllers._

## Files Created/Modified

- `resources/js/pages/gestao/auditoria/index.tsx` — trilha unificada server-driven (fonte+filtros+tabela paginada+export CSV).
- `resources/js/pages/gestao/lgpd/index.tsx` — painel LGPD minimizado (consentimentos/retenção/acessos a PII) + pendência DPO.
- `resources/js/components/auditoria/decision-explanation.tsx` — passo a passo da decisão (HU-099), SSR-safe, com marca de legado.
- `resources/js/pages/gestao/resultados-expresso/show.tsx` — consome `explicacao` (card abaixo da decisão imutável).
- `resources/js/pages/gestao/processos/show.tsx` — consome `explicacao` (aba "Explicabilidade" quando há decisão).

## Decisions Made

- `useServerTable` foi descartado para a trilha: o hook sempre injeta `sort`/`direction`/`search` que a `AuditoriaController` não lê; usar `router.get` direto evita parâmetros enganosos na URL e no link de export.
- Export CSV é `<a href>` (download real do navegador), não `Link` Inertia — uma visita Inertia não baixa o stream CSV.
- `log_name`/`event`/`resultado` como inputs de texto (match exato), não selects: a 12-04 não entregou listas de opções e fabricar enum seria fachada.
- Explicabilidade como card próprio (expresso) e aba condicional (processo) — aditivo, sem mexer no que já existia.

## Deviations from Plan

Nenhum desvio funcional. Os dois adendos foram refinamentos para os contratos de verificação:
- O link de export passou a conter `formato=csv` literal no fonte (em vez de só via `URLSearchParams`), e um comentário cita a rota `gestao.auditoria.export` — atende o `via`/`pattern` do key_link sem mudar o comportamento (a rota de export entrega CSV com os mesmos filtros).
- A página LGPD ganhou um docblock que cita `gestao/lgpd/index` (satisfaz o `contains: "lgpd"` do artifact e documenta as props consumidas).

Nenhum era um bug; ambos consolidados no commit `f242b7d`.

## Issues Encountered

- **Working tree compartilhado com 12-09 (Wave 4):** o irmão concluiu e commitou seu backend durante a execução; ao verificar o tree estava coerente (tudo do 12-09 commitado), restando apenas meus 5 `.tsx`. Mitigação: `git add` por pathspec dos meus arquivos, nunca `-A`/`.`.

## Verificação (evidência fresca)

- `npm run typecheck` (`tsc --noEmit`): **exit 0** (sem erro de tipos; `verbatimModuleSyntax` respeitado com `import type`).
- `npm run build` (vite client + SSR): **exit 0**; chunks `auditoria-*.js` e `lgpd-*.js` emitidos no manifest.
- `php artisan test --compact --filter="ResultadoExpresso|Processo|DecisionExplanation"`: **94 passed, 517 assertions** (anti-regressão dos controllers que servem as props consumidas — inclui o `explicacao` de 12-05).
- Greps das acceptance criteria: `formato=csv` e `auditoria.export` presentes na trilha; `lgpd` e `passos` presentes; `explicacao`/`DecisionExplanation` nos dois `show.tsx`.
- `ReadLints` nos 5 arquivos: **0 erros**.

## User Setup Required

None — nenhuma configuração de serviço externo. Frontend puro consumindo backend já entregue.

## Next Phase Readiness

- **12-11 (navegação/Cmd+K):** as 3 superfícies estão alcançáveis pelas rotas; a sidebar/Cmd+K deve adicionar entradas para `/gestao/auditoria` (gate `consultar-auditoria`) e `/gestao/lgpd` (gate `monitorar-lgpd`). A explicabilidade já está embutida no detalhe (sem entrada de nav própria). NÃO adicionei nada ao `gestao-layout` (dono é a 12-11).
- **12-12 (smoke navegável):** a trilha (3 fontes + filtros + CSV), a explicabilidade no detalhe (expresso e processo) e o painel LGPD estão prontos para o smoke; o render fino é validado lá.
- Sem blockers. ZERO dependência nova; SÓ frontend.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
