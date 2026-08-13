---
phase: 10-analise-tecnica-sedur
plan: 17
subsystem: ui-console
tags: [hu-135, hu-140, hu-142, hu-085, hu-138, hu-082, ficha-saps, autosave, precedentes, mini-mapa, command-search, cmd-k, nav-permissao, inertia-react, anti-fachada]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "09"
    provides: "AnalysisRecordController show/autosave/finalizar/nova-revisao/diff + PrecedenteController; AnalysisRecordResource (sugerido x escolhido, editavel, engine_available); rota gestao.processos.ficha.show renderiza gestao/ficha-analise/show"
  - phase: 10-analise-tecnica-sedur
    plan: "04"
    provides: "SectorController/StandardTextController (CRUD setores + textos-padrão versionado) + rotas gestao.setores.* / gestao.textos-padrao.*"
  - phase: 10-analise-tecnica-sedur
    plan: "14"
    provides: "ProcessoBuscaController (gestao.processos.busca) + rotas gestao.processos.index/fila/show consumidas pela nav"
  - phase: 10-analise-tecnica-sedur
    plan: "15"
    provides: "rotas de ação gestao.processos.decidir/pendencias.store/malha-fina.store/tvl.store/tvl.download (redirect Inertia em decidir/pendência/malha-fina; JSON {document, download_url} em TVL)"
  - phase: 04-territorio
    plan: "Fase 4"
    provides: "componente de mapa Leaflet reusável (MapImovel/MapaSection) SSR-safe"
provides:
  - "resources/js/pages/gestao/ficha-analise/show.tsx — ficha SAPS navegável (por CNAE sugerido x escolhido, condicionantes, vagas, parecer, autosave, finalizar, diff, precedentes, mini-mapa, ações de decisão/pendência/malha-fina/TVL)"
  - "resources/js/pages/gestao/setores/index.tsx — CRUD de setores + vínculo de analistas (multiselect)"
  - "resources/js/pages/gestao/textos-padrao/index.tsx — CRUD versionado de textos-padrão (aviso de versão ao editar conteúdo)"
  - "resources/js/components/app/command-search.tsx — busca global Cmd/Ctrl+K montada no gestao-layout"
  - "grupo de navegação 'Análise técnica' no gestao-layout (Fila/Processos/Setores/Textos-padrão) filtrado por permissão"
  - "tests/Feature/Analise/FichaUiSmokeTest — contrato página↔props da ficha (componente + props + localização real)"
affects: [10-18]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Autosave debounce no cliente (setTimeout + useHttp.patch) preservando status_sugerido do motor; PATCH parcial conforme AnalysisRecordRequest (status_escolhido obrigatório por CNAE)"
    - "Endpoints JSON via useHttp (autosave/finalizar/nova-revisao/diff/precedentes/TVL) e ações de redirect Inertia via router.post (decidir/pendência/malha-fina) — escolha por tipo de resposta real do backend, não por suposição"
    - "Mini-mapa Leaflet alimentado por property_polygon_geojson real (centroide do anel externo no cliente); degradação honesta sem polígono (sem coordenada inventada)"
    - "command-search global: atalho Cmd/Ctrl+K + gatilho flutuante (mobile/descoberta), gated por consultar-solicitacoes, consumindo o endpoint leve gestao.processos.busca"
    - "Páginas de console reusando o design system (PageHeader/Card/DataTable/useServerTable/Modal/ConfirmDialog/TableAction/Badge/EmptyState) — ZERO dependência nova"

key-files:
  created:
    - resources/js/pages/gestao/ficha-analise/show.tsx
    - resources/js/pages/gestao/setores/index.tsx
    - resources/js/pages/gestao/textos-padrao/index.tsx
    - resources/js/components/app/command-search.tsx
    - tests/Feature/Analise/FichaUiSmokeTest.php
  modified:
    - resources/js/layouts/gestao-layout.tsx
    - app/Http/Controllers/Gestao/AnalysisRecordController.php
    - app/Http/Controllers/Gestao/SectorController.php

key-decisions:
  - "Página da ficha em gestao/ficha-analise/show (não gestao/processos/ficha como no texto do plano): o controller de 10-09 (já commitado, fonte de verdade) renderiza 'gestao/ficha-analise/show' e nenhum plano da Wave 7 troca esse render. Alinhar a página ao render real garante que a rota renderiza de verdade (anti-fachada) e mantém file-disjunção total com as páginas gestao/processos/* do 10-16"
  - "Ações por caminho de URL literal (ex.: /gestao/processos/{id}/decidir), não route() Ziggy — o projeto não usa Ziggy (nenhum route() em resources/js); os URLs batem com as rotas reais de 10-09/10-15"
  - "ÚNICO editor do gestao-layout na Wave 7: grupo 'Análise técnica' por permissão + command-search montado no layout; AppShell/app-header não tocados"
  - "Dois ajustes mínimos e aditivos de backend para evitar fachada na UI (sem colisão paralela — nenhum plano da Wave 7 toca esses controllers): AnalysisRecordController@show passa 'localizacao' (polígono/endereço reais) para o mini-mapa; SectorController@index passa 'analistasDisponiveis' (usuários com analisar-processos) para o multiselect de vínculo"

patterns-established:
  - "UI consome o tipo de resposta real do endpoint: JSON → useHttp; redirect Inertia (back()->with) → router.post — evita ação que 'parece' funcionar mas não recarrega"

# Metrics
duration: ~30min
completed: 2026-06-15
---

# Phase 10 Plan 17: UI do console — ficha SAPS, admin e navegação (HU-135/140/142/085/138/082) — Summary

**A camada de tela da análise humana no console SEDUR. (1) `gestao/ficha-analise/show.tsx`: a ficha SAPS navegável — enquadramento por CNAE com a sugestão do motor (HU-140) ao lado da decisão do analista e a divergência destacada, condicionantes (sugeridas pelo motor + texto livre + inserção da biblioteca de textos-padrão), vagas (informadas × exigidas → veredito conforme/não conforme + vistoria), parecer com picker de textos-padrão, autosave debounced (PATCH parcial, RN-008), finalizar, nova revisão, diff entre revisões (RN-007), painel de precedentes (HU-142, com 'sem precedentes' e zona pendente SEDUR explícitos), mini-mapa Leaflet do polígono REAL do imóvel e as ações de decisão/pendência/malha-fina/emitir-baixar TVL — cada uma consumindo a rota real de 10-09/10-15, gated por permissão. (2) Telas admin `setores/index.tsx` (CRUD + multiselect de analistas, RN-005) e `textos-padrao/index.tsx` (CRUD versionado com aviso de incremento de versão ao editar conteúdo, RN-005). (3) Navegação: grupo 'Análise técnica' (Fila/Processos/Setores/Textos-padrão) filtrado por permissão e o `command-search` (Cmd/Ctrl+K + gatilho flutuante) montados no `gestao-layout` — ÚNICO editor do layout na Wave 7. Verificação com evidência fresca: `npx tsc --noEmit` verde, `npm run build` verde, `FichaUiSmokeTest` 2/2 e a suíte de Análise completa 177/177 (812 asserções) — zero regressão após os ajustes nos controllers. ZERO dependência nova.**

## Performance
- **Duração:** ~30 min (início 2026-06-15T00:10:44Z, fim 2026-06-15T00:40:58Z)
- **Tasks:** 3 + 1 correção de coordenação (4 commits atômicos)
- **Files:** 5 criados + 3 modificados — ZERO dependência nova

## O que foi entregue

### Task 1 — Ficha SAPS (`gestao/ficha-analise/show.tsx`)
- **Por CNAE (espelha prints SAPS 03–05):** `status_sugerido` (badge, leitura) × `status_escolhido` (radios Deferida/Indeferida/Análise); divergência destacada com pedido de justificativa (HU-140/HU-145); grupo de uso, valor TLL (exibe "pendente — tabela de taxas/DAM", nunca inventa), gatilhos e fundamentação do motor.
- **Modo manual (FA-01):** banner quando `engine_available=false` (sem sugestão automática; análise integralmente humana).
- **Condicionantes:** checkboxes das sugeridas pelo motor + adição em texto livre + inserção da biblioteca de textos-padrão (HU-085 RN-009).
- **Vagas:** informadas (requerente) × exigidas (norma) → veredito conforme/não conforme + checkbox de vistoria (print 07/08).
- **Parecer:** textarea + picker de textos-padrão ativos.
- **Autosave:** PATCH debounce de `autosaveDebounceMs` (config), indicador salvando/salvo/erro; payload parcial conforme `AnalysisRecordRequest`.
- **Ações (por permissão, rotas reais):** Salvar rascunho, Finalizar (JSON 10-09), Nova revisão (JSON 10-09), Comparar revisões (diff 10-09), Decidir (redirect 10-15), Abrir pendência (redirect 10-15), Encaminhar malha fina (redirect 10-15, gate `encaminhar-malha-fina`), Emitir/baixar TVL (JSON `{download_url}` 10-15, gate `emitir-tvl`, abre a URL temporária assinada).
- **Precedentes (HU-142):** processos do imóvel (link/resultado/serviço/analista/data) + estatística do CNAE na zona, com "sem precedentes" e degradação honesta da zona explícitos; consome `GET processos/{id}/precedentes`.
- **Mini-mapa:** `MapaSection`/`MapImovel` (Fase 4) com o polígono real (`property_polygon_geojson`); sem polígono, mensagem honesta; zona/via comunicadas como pendentes SEDUR.

### Task 2 — Telas admin
- **`setores/index.tsx` (HU-138):** lista (nome/analistas/processos/situação), criar/editar (modal), toggle de ativação (RN-004, com aviso) e modal de vínculo de analistas (multiselect → `PUT setores/{id}/analistas`).
- **`textos-padrao/index.tsx` (HU-085):** lista filtrável por categoria/situação, criar/editar (modal com aviso de que editar o conteúdo incrementa a versão — RN-005), toggle de ativação, versão exibida por linha.

### Task 3 — Navegação + busca global + smoke
- **`gestao-layout.tsx`:** grupo "Análise técnica" (Fila → `analisar-processos`; Processos → `consultar-solicitacoes`; Setores → `manter-setores`; Textos-padrão → `manter-parametros`), itens com `visible` derivado de `auth.permissions`.
- **`command-search.tsx`:** Cmd/Ctrl+K + gatilho flutuante (acessível no mobile) → modal de busca consumindo `gestao.processos.busca` (debounce 250ms) e navegação ao processo; gated por `consultar-solicitacoes` (quem não consulta não vê o atalho).
- **`FichaUiSmokeTest`:** componente `gestao/ficha-analise/show` + props essenciais (ficha revisão/per_cnae/editável, textosPadrao, autosaveDebounceMs, processo) e a `localizacao` real (polígono/endereço); + caso sem polígono entrega `localizacao.poligono = null` (anti-fachada).

## Rotas consumidas pela ficha (todas reais)
| Ação | Método/rota | Resposta | Tratamento na UI |
|---|---|---|---|
| Abrir/recarregar | GET `processos/{id}/ficha` | Inertia `gestao/ficha-analise/show` | página |
| Autosave | PATCH `processos/{id}/ficha` | JSON | useHttp.patch (debounce) |
| Finalizar | POST `processos/{id}/ficha/finalizar` | JSON | useHttp.post → reload |
| Nova revisão | POST `processos/{id}/ficha/nova-revisao` | JSON | useHttp.post → reload |
| Diff | GET `processos/{id}/ficha/diff?de&para` | JSON | useHttp.get (modal) |
| Precedentes | GET `processos/{id}/precedentes` | JSON | useHttp.get (mount) |
| Decidir | POST `processos/{id}/decidir` | redirect Inertia | router.post |
| Pendência | POST `processos/{id}/pendencias` | redirect Inertia | router.post |
| Malha fina | POST `processos/malha-fina` | redirect Inertia | router.post |
| Emitir TVL | POST `processos/{id}/tvl` | JSON `{download_url}` | useHttp.post → abre URL assinada |

## Mapa CA → evidência
| HU / RN | Evidência |
|---|---|
| HU-135 — ficha por CNAE/condicionantes/vagas/parecer/autosave | `FichaUiSmokeTest` (componente + props) + `ficha-analise/show.tsx` (grep `autosave`) |
| HU-140 — sugerido × escolhido + modo manual | `ficha-analise/show.tsx` (badge sugerido, radios escolhido, banner engine_available=false) |
| HU-142 — precedentes + "sem precedentes" | `ficha-analise/show.tsx` (grep `precedentes`; consome /precedentes) |
| HU-135 RN-007 — diff entre revisões | `ficha-analise/show.tsx` (modal de diff → /ficha/diff) |
| HU-086/087/088/089/132/136/083 — ações | `ficha-analise/show.tsx` (decidir/pendência/malha-fina/TVL nas rotas 10-15) |
| HU-138 — setores + vínculo de analistas | `setores/index.tsx` (grep `analistas`) |
| HU-085 — textos-padrão versionados | `textos-padrao/index.tsx` (grep `versão`/`version`) |
| HU-082 RN-009 — busca global Cmd+K | `command-search.tsx` (grep `processos/busca`) montado no layout |

## Verification (evidência fresca)
- `npx tsc --noEmit` → **verde** (exit 0).
- `npm run build` → **verde** (vite build OK; chunks `ficha-analise/show`, `setores`, `textos-padrao`, `gestao-layout`, `command-search` gerados).
- `php artisan test --compact --filter=FichaUiSmokeTest` → **2/2 (37 asserções)**.
- `php artisan test --compact --exclude-group postgis tests/Feature/Analise` → **177/177 (812 asserções)** — zero regressão após os ajustes em `AnalysisRecordController`/`SectorController`.
- `vendor/bin/pint --dirty --format agent` → **passed** (em cada task com PHP alterado).
- Greps de aceite: `autosave` + `precedentes` + `mapa/MapaSection` em `ficha-analise/show.tsx`; `analistas` em `setores/index.tsx`; `versão/version` em `textos-padrao/index.tsx`; `Análise` em `gestao-layout.tsx`; `processos/busca` em `command-search.tsx`.

### Checkpoint humano — verificação VISUAL (pendente)
A verificação automatizada cobre o contrato (typecheck/build/smoke), mas a renderização visual da ficha, das telas admin e do Cmd+K (layout SAPS, responsividade mobile, dark mode, comportamento do mapa Leaflet no cliente) NÃO foi validada por mim — exige conferência humana no navegador. Recomenda-se subir a aplicação (`composer run dev`), abrir um processo `em_analise` com ficha (`/gestao/processos/{id}/ficha`), as telas `/gestao/setores` e `/gestao/textos-padrao`, e exercitar o Cmd+K. O smoke navegável ponta a ponta é o 10-18.

## Deviations from Plan
1. **[Rule 3 — Blocking] Página em `gestao/ficha-analise/show` (não `gestao/processos/ficha`).** O texto do plano nomeia `resources/js/pages/gestao/processos/ficha.tsx` e o componente `gestao/processos/ficha`, mas o `AnalysisRecordController@show` (10-09, já commitado e fonte de verdade) renderiza `gestao/ficha-analise/show` e nenhum plano da Wave 7 troca esse render. Seguir o texto criaria uma página inacessível (fachada). A página foi criada no caminho que o controller realmente renderiza — o que também mantém file-disjunção total com as páginas `gestao/processos/*` do 10-16. O `FichaUiSmokeTest` assere o componente real.
2. **[Rule 2 — Missing Critical] `localizacao` no `AnalysisRecordController@show`.** O `show` não entregava dado geográfico; o mini-mapa exigido seria fachada. Acréscimo mínimo e aditivo de `localizacao` (`property_polygon_geojson` + endereço reais, ou `null` honesto). Sem colisão paralela (nenhum plano da Wave 7 toca esse controller). Coberto pelo `FichaUiSmokeTest`.
3. **[Rule 2 — Missing Critical] `analistasDisponiveis` no `SectorController@index`.** O `index` não entregava candidatos para o multiselect de vínculo de analistas; sem isso o "gerenciar analistas" seria fachada. Acréscimo mínimo de `analistasDisponiveis` (usuários ativos com `analisar-processos`, espelhando o padrão do `UserManagementController`). Sem colisão paralela.
4. **route() Ziggy → caminhos literais.** O critério de aceite cita `route()`, mas o projeto não usa Ziggy (nenhum `route()` em `resources/js`); as ações usam os URLs literais reais das rotas de 10-09/10-15 — mesma convenção das demais páginas (ex.: `router.put('/gestao/...')`).
5. **[fix pós-10-15] Pendência/malha-fina via `router.post`.** Codei a ficha antes do 10-15 concluir; ao landar, esses endpoints respondem redirect Inertia (não JSON). Troquei o `useHttp` por `router.post` nessas duas ações (commit `67184db`) para a página recarregar de verdade. TVL/finalizar/nova-revisão/autosave/diff/precedentes permanecem JSON via `useHttp`.

## Coordenação (Wave 7)
- **ÚNICO editor do `gestao-layout`** na Wave 7 — confirmado. Não toquei `routes/gestao.php` (10-15) nem as páginas `gestao/processos/{fila,index,show}.tsx` (10-16). 10-15 e 10-16 concluíram em paralelo durante a execução; staging individual dos meus arquivos (nunca `git add -A`).
- Backend tocado apenas em dois pontos aditivos (`AnalysisRecordController`, `SectorController`), ambos de planos já concluídos (10-09/10-04) e fora do escopo de qualquer plano paralelo da Wave 7 — risco de colisão nulo.
- `STATE.md` **NÃO** alterado (instrução do usuário; consolidação a cargo do orquestrador).

## Authentication Gates
Nenhum — sem CLI/credencial externa neste plano.

## Task Commits
1. **Task 1 — ficha SAPS + `localizacao`** — `34f94e3` (feat).
2. **Task 2 — telas admin + `analistasDisponiveis`** — `09d3ea9` (feat).
3. **Task 3 — navegação + Cmd+K + smoke** — `31c7e70` (feat).
4. **Correção — pendência/malha-fina via Inertia** — `67184db` (fix).

## Next Phase Readiness
- **10-18 (smoke navegável):** exercita ponta a ponta — abrir ficha `em_analise`, autosave, finalizar, decidir, emitir+baixar TVL assinado, e a busca Cmd+K. As páginas e o contrato página↔props estão prontos.
- **Pendência de verificação VISUAL** (checkpoint humano acima) antes de considerar a fase "pronta" do ponto de vista de UX/acessibilidade.
- ZERO dependência nova; reusa o design system do console + o mapa Leaflet da Fase 4.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-15*
