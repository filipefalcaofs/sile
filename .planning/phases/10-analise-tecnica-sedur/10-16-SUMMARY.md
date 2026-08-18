---
phase: 10-analise-tecnica-sedur
plan: 16
subsystem: frontend
tags: [hu-082, hu-144, hu-136, ui, inertia, react, fila, sla, semaforo, consulta, detalhe, timeline, leaflet, malha-fina, csv]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur/10-14
    provides: "endpoints server-driven processos.index/fila/show + ProcessoResource (3 identificadores + SLA) + contadores/visaoSetor + status/categoria options"
  - phase: 10-analise-tecnica-sedur/10-05
    provides: "SlaStatus (verde/amarelo/vermelho) + rótulos, refletidos no badge de semáforo da UI"
  - phase: 04-georreferenciamento-e-territorio/04-07
    provides: "MapaSection/MapImovel (Leaflet SSR-safe) reusado no mini-mapa do detalhe"
provides:
  - "Fila do analista HU-144 (resources/js/pages/gestao/processos/fila.tsx): KPI de contadores, abas meus/setor, DataTable por prazo com semáforo, visão do gestor"
  - "Consulta de processos HU-082 (resources/js/pages/gestao/processos/index.tsx): filtros do SAPS, paginação server-side, exportar CSV, seleção + lote de malha fina (HU-136)"
  - "Detalhe do processo HU-082 (resources/js/pages/gestao/processos/show.tsx): 3 identificadores, abas (informações/tramitação), timeline visual com duração, mini-mapa Leaflet permanente"
  - "Componente compartilhado resources/js/components/analise/processo-ui.tsx (SemaforoBadge, CategoriaBadges, formatarDataHora, tipos ProcessoItem/ProcessoSla)"
affects: [10-17-ficha-admin-nav-cmdk, 10-18-smoke-navegavel]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Páginas Inertia/React no padrão TailAdmin do console (PageHeader, Card, DataTable, Badge, KpiCard, Pagination, EmptyState, TableAction) — ZERO dependência nova"
    - "Consulta com filtro-formulário local + GET (router.get preserveState) em vez do useServerTable: evita um request por tecla nos ~16 filtros de texto do SAPS, ainda 100% server-driven"
    - "Mini-mapa reusa MapaSection (Leaflet lazy/SSR-safe da Fase 4); centroide do polígono calculado no cliente; degradação honesta (EmptyState) quando não há geometria"
    - "Semáforo de SLA como componente único (SemaforoBadge) compartilhado entre fila/consulta/detalhe, espelhando SlaStatus do backend"

key-files:
  created:
    - resources/js/pages/gestao/processos/fila.tsx
    - resources/js/pages/gestao/processos/index.tsx
    - resources/js/pages/gestao/processos/show.tsx
    - resources/js/components/analise/processo-ui.tsx
    - tests/Feature/Analise/ProcessoUiSmokeTest.php
  modified:
    - app/Http/Controllers/Gestao/ProcessoController.php

key-decisions:
  - "Filtro-formulário com botão Filtrar (GET) na consulta, não busca-por-tecla: ~16 filtros de texto tornam o setFilter imediato do useServerTable inviável (1 request/tecla). Mantém server-driven, preserva estado e paginação por querystring"
  - "geo.poligono exposto APENAS no show() do ProcessoController (não no Resource): o mini-mapa precisa da geometria real (anti-fachada), mas a lista/fila não devem carregar polígonos. Mudança aditiva e file-disjunta de 10-15/10-17"
  - "Coluna 'tempo na etapa' do plano substituída pelo semáforo+prazo (sla.restante já vem pronto do backend): o analysis_stage_started_at não é exposto pelo Resource; sem fachada, usa só dado real"
  - "Ação/seleção de malha fina e botão Exportar CSV só aparecem para quem tem a permissão (encaminhar-malha-fina) / sempre (CSV é leitura) — degradação por permissão"
  - "Cmd+K (busca global HU-082 RN-009) NÃO entra aqui: é componente de layout, de responsabilidade do 10-17 (único editor do gestao-layout)"

patterns-established:
  - "components/analise/processo-ui.tsx como ponto único do shape ProcessoResource + helpers de SLA/categoria reusados pelas 3 páginas"
  - "Abas client-side (role=tab) para meus/setor (fila, via GET) e informações/tramitação (detalhe, via estado local)"

# Metrics
duration: ~25min
completed: 2026-06-14
---

# Phase 10 Plan 16: UI da Fila, Consulta e Detalhe de Processos — Summary

**Telas React/Inertia da retaguarda do analista: fila priorizada por SLA com semáforo e contadores (HU-144), consulta com os filtros completos do SAPS + paginação server-side + CSV + lote de malha fina (HU-082/HU-136) e detalhe com os 3 identificadores, abas, timeline visual e mini-mapa Leaflet permanente (HU-082 RN-006/007). Tudo consome os endpoints reais de 10-14; ZERO dependência nova; tsc/build verdes e `ProcessoUiSmokeTest` 3/3. Cmd+K fica em 10-17 (único editor do layout).**

## Páginas entregues (o que cada uma consome)

| Página | HU | Consome (10-14) | Destaques |
|---|---|---|---|
| `gestao/processos/fila.tsx` | HU-144 | `processos.fila` → `processos`, `contadores`, `modo`, `visaoSetor` | KPI dos 4 contadores; abas Meus/Setor (GET `?modo=`); DataTable ordenada por prazo com `SemaforoBadge`; visão do gestor (carga por analista + vermelhos) só quando `visaoSetor` vem preenchido |
| `gestao/processos/index.tsx` | HU-082 / HU-136 | `processos.index` (paginado) + `statusOptions`/`categoriaOptions`/`perPageOptions`/`filtros` | Filtro-formulário do SAPS (grupo/status/categoria + texto + datas + serviço/setor/analista por ID); paginação server-side ("Mostrando X–Y de Z"); **Exportar CSV** (`?formato=csv`); **seleção + lote de malha fina** (POST `/gestao/processos/malha-fina`); EmptyState distingue lista vazia de filtro vazio |
| `gestao/processos/show.tsx` | HU-082 RN-006/007 | `processos.show` → `processo`, `timeline`, `geo` | 3 identificadores (processo/BAP/TVL); abas Informações/Tramitação; **timeline visual** com duração por etapa; **mini-mapa Leaflet permanente** (polígono real) com degradação honesta; CTA para a ficha (gated `analisar-processos`) — somente leitura |

## Componentes reusados (design system, ZERO dependência nova)

- Console TailAdmin: `PageHeader`, `Card`/`CardHeader`/`CardContent`, `DataTable` + `ColumnDef`, `Badge`, `KpiCard`, `Pagination`, `EmptyState`, `PerPageSelect`, `TableAction`, `Button`, e os campos `Input`/`Select`/`Label`/`Checkbox`.
- Mapa: `MapaSection`/`MapImovel` (Leaflet lazy + SSR-safe, Fase 4) no mini-mapa do detalhe.
- Novo (próprio): `components/analise/processo-ui.tsx` — `SemaforoBadge` (verde→success / amarelo→warning / vermelho→error, com tempo restante), `CategoriaBadges`, `formatarDataHora`, e os tipos `ProcessoItem`/`ProcessoSla` (shape único do `ProcessoResource`).

## Lote de malha fina (HU-136) e CSV (HU-082 RN-011)

- A consulta tem coluna de seleção (só para quem tem `encaminhar-malha-fina`); selecionando 1+ processos aparece a barra de lote com **motivo obrigatório** (validação no cliente) que faz `router.post('/gestao/processos/malha-fina', { request_ids, motivo })` — o endpoint real é de **10-15** (co-agendado na Wave 7).
- **Exportar CSV** é um link direto para `gestao.processos.index?...&formato=csv` carregando os filtros aplicados — o streaming/auditoria já existe em 10-14.

## Nota de coordenação (Wave 7)

- **Cmd+K (busca global, HU-082 RN-009):** componente de layout — pertence ao **10-17** (único editor de `gestao-layout.tsx`), que monta o command-search consumindo `gestao.processos.busca`. Estas páginas NÃO editam o layout.
- **Rota de malha fina:** `POST /gestao/processos/malha-fina` é de **10-15** (route-owner da Wave 7). A UI já aciona o endpoint real (sem fachada); enquanto 10-15 não publica a rota, o botão retorna 404 honesto (nunca sucesso simulado).
- **Item de menu/nav:** responsabilidade do 10-17 — não alterado aqui.

## Desvios do plano

**1. [Rule 2 — funcionalidade crítica] Geometria do imóvel exposta no `show()` para o mini-mapa**
- **Por quê:** o must-have "mini-mapa Leaflet permanente com o polígono" exige a geometria, e o `ProcessoResource` (10-14) não a expunha. Sem o dado, o mapa seria fachada.
- **O que foi feito:** `ProcessoController::show()` passou a enviar `geo.poligono` = `property_polygon_geojson` (dado real, já existente no modelo) APENAS no detalhe — não no Resource, para não inflar lista/fila com polígonos.
- **Escopo/risco:** aditivo e file-disjunto de 10-15 (rotas) e 10-17 (ficha/admin/nav/layout); 10-14 já está concluído (sem colisão). Pint verde; `ProcessoConsultaTest`/`ProcessoFilaTest` seguem 26/26.
- **Commit:** `06b3050`.

**2. Coluna "tempo na etapa" da fila → semáforo + prazo.** O `analysis_stage_started_at` não é exposto pelo Resource; em vez de inventar o valor, a fila usa o `sla.restante` (tempo restante real do backend) + a data do prazo. Sem fachada.

**3. Filtros serviço/setor/analista por ID (não por dropdown).** O backend (10-14) não fornece listas de opções para esses filtros; foram expostos como campos numéricos honestos (úteis inclusive para o deep-link `?analista=` da visão do gestor). Dropdowns dependeriam de novas props no controller (fora do escopo desta UI).

## Checkpoint humano — verificação visual (pendente)

O smoke automatizado cobre o contrato página↔props, mas a **avaliação visual/UX real** depende de inspeção humana no navegador (autenticado na retaguarda):

- [ ] `/gestao/processos/fila` — KPIs, abas Meus/Setor, ordenação por prazo, cores do semáforo (verde/amarelo/vermelho), visão do gestor; responsivo (mobile) e dark mode.
- [ ] `/gestao/processos` — filtros aplicam e preservam estado; paginação; Exportar CSV baixa o conjunto filtrado; seleção + lote de malha fina (com 10-15 ativo); EmptyState.
- [ ] `/gestao/processos/{id}` — 3 identificadores, abas, timeline com durações, mini-mapa renderiza o polígono (e EmptyState quando sem geometria); CTA da ficha conforme permissão.

Insumo direto do **smoke navegável (10-18)**.

## Verificação (evidência fresca)

- `npx tsc --noEmit` → **exit 0** (verde) após cada task e no fechamento.
- `npm run build` → **built in 644ms**, 688 módulos; chunks novos `fila`, `show`, `processos` (index), `processo-ui`, `mapa-section` presentes.
- `php artisan test --compact --filter=ProcessoUiSmokeTest` → **3/3 (47 asserções)**.
- Regressão: `php artisan test --compact --filter="ProcessoConsultaTest|ProcessoFilaTest|ProcessoUiSmokeTest|ProcessoBuscaGlobalTest"` → **26/26 (188 asserções)** — a prop `geo` no `show()` não quebrou 10-14.
- `vendor/bin/pint --dirty --format agent` → **passed** (controller + teste).

## Task Commits

1. **Task 1 — fila (HU-144) + consulta (HU-082) + componente SLA** — `d08ba4e` (feat)
2. **Task 2 — detalhe (timeline + mini-mapa) + geo no show()** — `06b3050` (feat)
3. **Task 3 — ProcessoUiSmokeTest (contrato página↔props)** — `b4eb2f1` (test)

## Pendências conhecidas (registradas, não simuladas)

- **Malha fina lote depende de 10-15** (rota `processos.malha-fina`) — UI já aciona o endpoint real.
- **Cmd+K depende de 10-17** (layout) — fora do escopo desta UI.
- **Anexos / DAM / vistoria / precedentes no detalhe:** não estão no payload do `show` (10-14); a análise/precedentes vivem na ficha (10-17). Não foram renderizadas abas de fachada.
- **Zona/via oficiais no mapa:** Quadro 10 pendente na SEDUR (CONTEXT) — o mini-mapa mostra o polígono; overlays de zona entram quando a base vier oficial.
- **Filtros serviço/setor/analista por ID:** evoluem para dropdown quando o controller fornecer as listas de opções.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
