# Escritório virtual — R1 Relatório Sede × Abrigados — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Tela dedicada `/gestao/relatorios/escritorio-virtual` que lista, por SEDE de escritório virtual, a sede e seus ABRIGADOS, pesquisável por **número da sede (TVL)** E por **inscrição imobiliária** (os "dois pontos de pesquisa" da reunião), com exportação Excel — reusando o pipeline de export existente.

**Architecture:** Nova query `RelatorioSedeEscritorioVirtualService` sobre `VirtualOfficeInscriptionLock` (ativos) → sede (ViabilityRequest+decision) + abrigados (requests na mesma inscrição com `ViabilityDecision.is_virtual_office_tenant=true`). Controller `RelatorioController::escritorioVirtual` (Inertia render + export), rota sob `consultar-relatorios`. Tela React espelhando `relatorios/produtividade.tsx` (DataTable + ExportMenu + filtros via `router.get`).

**Tech Stack:** Laravel 11, PHPUnit (SQLite :memory:), Inertia/React, Vite.

**Escopo — degradação honesta (dependências não construídas):**
- **Data Vencimento** e **"Exibir Expirados"** dependem de `validade` do produto (desfecho spec-2, NÃO implementado — não há campo `validade`). Nesta R1: coluna Vencimento mostra "—" e o checkbox "Exibir Expirados" fica **visível porém desabilitado** com aviso ("depende da validade do produto — pendente"). NUNCA inventar vencimento.
- Excel usa o pipeline `ReportSource`/`ReportExporter` existente (RN-005: mesmo recorte da tela).

---

### Task 1: Query — sede + abrigados por nº sede / inscrição

**Files:**
- Create: `app/Services/Relatorios/RelatorioSedeEscritorioVirtualService.php`
- Test: `tests/Feature/EscritorioVirtual/RelatorioSedeQueryTest.php`

- [ ] **Step 1: INVESTIGATE** — `app/Models/VirtualOfficeInscriptionLock.php` (scopes/relations: `sede()`, `active`, `property_registration`); `ViabilityRequest` `decision()` + `company` (razão social = `company.trade_name ?: company.legal_name`, ver `ProcessoResource`); `ViabilityDecision` (`tvl_product_number`, `decided_at`, `is_virtual_office_tenant`). Confirm how "abrigados de uma inscrição" são obtidos (requests com decision.is_virtual_office_tenant naquela property_registration).

- [ ] **Step 2: Test** `RelatorioSedeQueryTest` (RefreshDatabase): cria uma sede (request+decision c/ TVL 'TVL-2026-SEDE1', property_registration '111') + lock ativo, e 2 abrigados na '111' (decision is_virtual_office_tenant, TVLs próprios). Assert:
  - `consultar(['sede' => 'TVL-2026-SEDE1'])` retorna linhas da inscrição '111' incluindo a sede + os 2 abrigados, cada linha com `tipo` ('sede'|'abrigado'), `tvl`, `razao_social`, `data_emissao`.
  - `consultar(['inscricao' => '111'])` → mesmo recorte.
  - `consultar(['sede' => 'inexistente'])` → vazio.
  - (paginação) retorna um paginator (LengthAwarePaginator) — server-side.
  Run → FAIL.

- [ ] **Step 3:** Implement `RelatorioSedeEscritorioVirtualService`:
  - `consultar(array $filtros, int $perPage = 15): LengthAwarePaginator` — resolve o conjunto de inscrições-alvo:
    - se `sede` (nº TVL) informado → ache a(s) inscrição(ões) cuja sede tem esse `tvl_product_number` (via lock→sede→decision.tvl_product_number);
    - se `inscricao` informado → aquela property_registration;
    - senão → todas as inscrições com lock ativo.
  - Monte um Builder de `ViabilityRequest` das requests dessas inscrições que são sede (lock.sede) OU abrigado (decision.is_virtual_office_tenant), com `with(['company','decision'])`, ordenado por property_registration + tipo (sede primeiro), paginado.
  - Exponha um mapper de linha `linha(ViabilityRequest): array` → `['tipo', 'tvl', 'razao_social', 'data_emissao', 'inscricao', 'protocolo']` (vencimento fica fora — degradação; a tela renderiza "—").
  Run → PASS.

- [ ] **Step 4: Commit** (`git add` service + test).

```bash
git commit -m "feat(ev): query do relatório sede x abrigados (filtro nº sede + inscrição)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Controller + rota + export

**Files:**
- Modify: `app/Http/Controllers/Gestao/RelatorioController.php` (novo método `escritorioVirtual`)
- Modify: `routes/gestao.php` (rota no grupo `relatorios.` — hunk-stage se houver WIP alheio)
- Modify/Create: fonte de export para o recorte sede×abrigado (adaptar `EscritorioVirtualReportSource` OU criar `RelatorioSedeExportSource` usando a query da Task 1)
- Test: `tests/Feature/EscritorioVirtual/RelatorioSedeEndpointTest.php`

- [ ] **Step 1: INVESTIGATE** — leia `RelatorioController::tempo` (padrão Inertia render + `exportar()` + `formato()` + `auditarConsulta()`), `RelatorioFiltersRequest` (quais chaves aceita — talvez precise aceitar `sede`/`inscricao`), o `ReportExporter`/`ReportSource` (como o export é servido). Veja como `produtividade`/`tempo` alternam render vs export por `?formato`.

- [ ] **Step 2: Test** `RelatorioSedeEndpointTest`: usuário com `consultar-relatorios` (User::factory()->analista() ou dar a permissão) GET `/gestao/relatorios/escritorio-virtual?sede=TVL-2026-SEDE1` → 200, componente Inertia `gestao/relatorios/escritorio-virtual`, props com as linhas (sede+abrigados). GET com `?formato=xlsx` → resposta de download (mesmo recorte). Sem `consultar-relatorios` → 403. Run → FAIL.

- [ ] **Step 3:** Add `RelatorioController::escritorioVirtual(RelatorioFiltersRequest $request)`: se `formato` → `exportar(...)` com a fonte sede×abrigado; senão `Inertia::render('gestao/relatorios/escritorio-virtual', ['relatorio' => <paginator via RelatorioSedeEscritorioVirtualService>, 'filtros' => [...], 'perPageOptions' => ...])`; auditar a consulta. Adicione a rota `Route::get('escritorio-virtual', [RelatorioController::class, 'escritorioVirtual'])->name('escritorio-virtual')` no grupo `permission:consultar-relatorios`. Garanta que `RelatorioFiltersRequest`/`ReportFilters` aceitem `sede`+`inscricao` (adicione as chaves + accessors se preciso). Run → PASS.

- [ ] **Step 4: Commit** (hunk-stage routes/gestao.php).

```bash
git commit -m "feat(ev): endpoint + export do relatório sede de escritório virtual

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Tela React

**Files:**
- Create: `resources/js/pages/gestao/relatorios/escritorio-virtual.tsx`
- (opcional) Modify: item de menu em `resources/js/layouts/gestao-layout.tsx` (link p/ o novo relatório)
- Verify: `npx tsc --noEmit`, `npm run build`

- [ ] **Step 1: INVESTIGATE** — copie a estrutura de `resources/js/pages/gestao/relatorios/produtividade.tsx` (form de filtros + `router.get` + `DataTable` + `ExportMenu` + `Pagination`). Veja os componentes de filtro (Input/Select), `PerPageSelect`, `EmptyState`.

- [ ] **Step 2:** Implement the page:
  - Filtros: `sede` (nº TVL) e `inscricao` (Input de texto) + botões Pesquisar/Limpar (via `router.get('/gestao/relatorios/escritorio-virtual', params)`).
  - DataTable server-side: colunas **Tipo** (badge Sede|Abrigado), **Nº TVL**, **Razão Social**, **Data Emissão**, **Vencimento** (renderiza "—" — degradação honesta), **Inscrição**.
  - Checkbox **"Exibir Expirados"** — renderizado `disabled` com tooltip/nota "Depende da validade do produto (pendente)".
  - Botão **Gerar Excel** via `ExportMenu`/link `?formato=xlsx` (mesmo recorte).
  - Empty state (sede não encontrada / sem abrigados). Contraste AA.

- [ ] **Step 3:** `npx tsc --noEmit` → 0; `npm run build` → ok (NÃO commitar public/build).

- [ ] **Step 4: Commit** (só o `.tsx` + layout se tocado).

```bash
git commit -m "feat(ev): tela do relatório sede de escritório virtual (filtros nº sede + inscrição)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Regressão + verificação

- [ ] `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Relatorios` → verde.
- [ ] `npx tsc --noEmit` → 0; `npm run build` → ok.

---

## Self-Review

**Cobertura (spec telas §3 / inventário T04):** tela dedicada (T3); filtros nº sede + inscrição — pedido explícito da Lisa (T1/T2); listagem sede×abrigados com Tipo + TVL da sede (T1); Excel = mesmo recorte (T2, RN-005); paginação server-side (T1). CA-R1-01/02/03 cobertos; CA-R1-04 (Exibir Expirados) **degradado honesto** (validade bloqueada — spec-2).

**Bloqueado/degradado:** Data Vencimento + Exibir Expirados (validade = desfecho spec-2, sem campo). Renderizam "—"/disabled com aviso; nunca inventam.

**Notas de execução:** (a) `RelatorioFiltersRequest`/`ReportFilters` podem precisar aceitar `sede`+`inscricao` (adicionar chaves + accessors). (b) routes/gestao.php tem WIP alheio → hunk-stage. (c) decisão: adaptar `EscritorioVirtualReportSource` (que hoje exporta só sedes is_virtual_office) para o recorte sede×abrigado da Task 1, OU criar uma fonte nova reusando a query — preferir a query da Task 1 como fonte única de verdade (RN-005). (d) "abrigado" na query = request com `ViabilityDecision.is_virtual_office_tenant=true` na inscrição (M2).
