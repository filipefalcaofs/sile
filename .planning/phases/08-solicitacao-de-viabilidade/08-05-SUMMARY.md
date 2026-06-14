---
phase: 08-solicitacao-de-viabilidade
plan: 05
subsystem: portal
tags: [solicitacao-viabilidade, hu-061, rn-007, rn-002, policy, representacao, duplicidade, auditoria, toggle, inertia-react, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 01
    provides: "aggregate ViabilityRequest (status default rascunho, origin, requester_user_id/created_by_user_id) + enums Status/Origin + factory states draft/protocoled/cancelled + HasAuditoria"
  - phase: 08-solicitacao-de-viabilidade
    plan: 02
    provides: "toggle features.solicitacao_viabilidade (catálogo + fallback config) lido via Settings::enabled"
  - phase: 08-solicitacao-de-viabilidade
    plan: 03
    provides: "ViabilityServiceType::active() (id/code/name/flow_hint) — select do tipo de serviço no store"
  - phase: 03-cadastro-empresarial
    plan: "04/05"
    provides: "CompanyPolicy + effectiveUser + escopo por vínculo (Company::links) — padrão espelhado pela ViabilityRequestPolicy e pela validação de empresa"
  - phase: 01-identidade
    plan: "02/07"
    provides: "CurrentRepresentation (grantor) + Context acting_for_user_id + HasAuditoria/RecordActivityAction (RN-002)"
provides:
  - "App\\Policies\\ViabilityRequestPolicy (auto-discovery) — view/update/protocol/cancel pelo usuário efetivo (dono/representado)"
  - "App\\Http\\Controllers\\Portal\\SolicitacaoController (store rascunho + index Minhas solicitações escopado ao dono)"
  - "App\\Http\\Requests\\Portal\\StoreSolicitacaoRequest (tipo de serviço ATIVO + empresa com vínculo ATIVO do efetivo)"
  - "App\\Services\\Solicitacao\\DuplicateRequestDetector::detect(Company, ?int): ?array — reincidência por CNPJ (alerta, nunca bloqueio, RN-007)"
  - "rotas portal.solicitacoes.{index,store} (auth:web + verified + lgpd.accepted + ResolveRepresentation)"
  - "config sile.solicitacao.duplicidade.janela_dias (constante de config, default 180 — ressalva SEDUR)"
affects: [08-06-imovel-geometry, 08-07-area, 08-08-anexos, 08-09-simulacao, 08-10-protocolo, 08-11-consulta-protocolo, 08-12-cancelar, 08-13-ui, 08-15-atendimento-presencial]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Policy de aggregate da fase 8 espelhando CompanyPolicy: effectiveUser = CurrentRepresentation::grantor() ?? user; auto-discovery resolve ViabilityRequest→ViabilityRequestPolicy (sem registro explícito, como CompanyPolicy)"
    - "Store em nome do efetivo: requester_user_id = usuário efetivo (representado), created_by_user_id = ator real; status NÃO é setado (default 'rascunho' do banco — fora do fillable); auditoria created enriquecida com acting_for pelo RecordActivityAction"
    - "Detecção de reincidência como ALERTA não-bloqueante (RN-007): serviço dedicado retorna o processo anterior (link real) calculado ANTES de criar; nunca lança/bloqueia (direito de petição)"
    - "Janela de reincidência como CONSTANTE de config (config/sile.php), não Parameter de catálogo — evita tocar o ParameterSeeder (contagens travadas em 48); definição é pendência SEDUR (default honesto)"
    - "per_page por constante (DEFAULT_PER_PAGE=15 + whitelist) na listagem do portal — precedente [08-03], sem parâmetro dedicado para não tocar o seeder"

key-files:
  created:
    - app/Policies/ViabilityRequestPolicy.php
    - app/Http/Controllers/Portal/SolicitacaoController.php
    - app/Http/Requests/Portal/StoreSolicitacaoRequest.php
    - app/Services/Solicitacao/DuplicateRequestDetector.php
    - tests/Feature/Solicitacao/ViabilityRequestPolicyTest.php
    - tests/Feature/Solicitacao/DuplicateRequestDetectorTest.php
    - tests/Feature/Solicitacao/CriarSolicitacaoTest.php
  modified:
    - routes/portal.php
    - config/sile.php

key-decisions:
  - "ViabilityRequestPolicy espelha a CompanyPolicy ([03-04]): effectiveUser() = app(CurrentRepresentation::class)->grantor() ?? $user. view = dono/representado (qualquer status); update/protocol = dono E status rascunho; cancel = dono E status em {rascunho, protocolada}. Auto-discovery (sem registro explícito, como a CompanyPolicy)."
  - "requester_user_id = usuário EFETIVO (beneficiário/representado); created_by_user_id = $request->user() (ator real 'em nome de'). status fora do fillable → ViabilityRequest::create() cai no default 'rascunho' do banco; a auditoria created (HasAuditoria) loga só os fillable e é enriquecida com acting_for_user_id pelo RecordActivityAction quando em representação."
  - "DuplicateRequestDetector::detect(Company, ?int $excludeRequestId = null): ?array — mesma empresa (company_id = CNPJ), NÃO cancelada e (created_at na janela OU status protocolada = ativo); retorna o mais recente {request_id, protocol_number, status, created_at} ou null. NUNCA bloqueia. Por inscrição imobiliária degrada (lote bloqueado pendente SEDUR — só por CNPJ nesta fase)."
  - "Janela de reincidência em config/sile.php (sile.solicitacao.duplicidade.janela_dias, default 180) com default inline no serviço — NÃO é Parameter de catálogo (não toca ParameterSeeder; contagens travadas em 48). A definição oficial de duplicidade é pendência SEDUR — default honesto e ajustável sem deploy."
  - "Toggle features.solicitacao_viabilidade: o store degrada de forma comunicada (back()->with('status', ...) sem criar) quando off, espelhando features.procuracoes; o index permanece acessível (ver as próprias solicitações) e expõe solicitacaoEnabled para a UI (08-13)."
  - "Escopo por empresa no StoreSolicitacaoRequest::after(): a empresa precisa de vínculo ATIVO do usuário efetivo (Company::links whereNull ended_at) — não se cria solicitação para empresa de terceiro; mensagem pt-BR em company_id."

patterns-established:
  - "Fluxo de criação 'em nome de' no portal: efetivo (requester) × ator (created_by) + escopo por vínculo ativo no FormRequest + auditoria com acting_for — molde para contingência/atendimento presencial (08-14/08-15)"
  - "Listagem 'Minhas X' do portal escopada ao requester efetivo, server-driven (busca/ordenação whitelist/per_page constante) com shape de status {value,label,public_label} — molde para a consulta de protocolo (08-11)"

# Metrics
duration: ~8 min
completed: 2026-06-14
---

# Phase 8 Plan 05: Criar Rascunho da Solicitação de Viabilidade (HU-061) Summary

**O processo formal começou: o cidadão CRIA o rascunho da solicitação de viabilidade pelo portal (origem `portal` direto + tipo de serviço ativo do 08-03 + empresa da Fase 3), com a autorização nascendo integrada à representação da Fase 1. A `ViabilityRequestPolicy` espelha a `CompanyPolicy` (auto-discovery, `effectiveUser = CurrentRepresentation::grantor() ?? user`): só o dono/representado vê a própria solicitação, edita/protocola apenas em rascunho e cancela enquanto não decidido. O `SolicitacaoController@store` grava de verdade um rascunho real (status `rascunho` pelo default do banco) com `requester_user_id` = usuário efetivo (o representado quando "em nome de") e `created_by_user_id` = ator real, tudo auditado (RN-002) e com `acting_for` quando em representação. O `DuplicateRequestDetector` (RN-007) aponta o processo anterior mais recente da mesma empresa por CNPJ — recente (janela de config, default 180 dias) ou ativo (protocolada) — como ALERTA com link real, NUNCA bloqueando (direito de petição). O `index` "Minhas solicitações" lista server-driven somente as do requerente efetivo. O toggle `features.solicitacao_viabilidade` degrada de forma comunicada no store quando desligado. ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): 21 testes novos (5 policy + 8 detector + 8 fluxo de criação); suíte completa 735/735 (3737 asserções, inclui 16 @group postgis com o container de pé).**

## Performance

- **Duration:** ~8 min (1º ao 3º commit: 08:49:12 → 08:56:44 -03)
- **Completed:** 2026-06-14
- **Tasks:** 3 (policy; detector + config; controller/request/rotas)
- **Files:** 7 criados + 2 modificados — ZERO dependência nova

## Accomplishments

- **`ViabilityRequestPolicy`** integrada à representação (espelha `CompanyPolicy`): dono/representado opera; update/protocol só em rascunho; cancel enquanto não decidido (CA-04). Auto-discovery.
- **`SolicitacaoController@store`** cria o rascunho REAL em `DB::transaction`: `requester` = efetivo, `created_by` = ator, `origin` = portal, `service_type_id`/`company_id` validados.
- **`StoreSolicitacaoRequest`**: tipo de serviço ATIVO (`Rule::exists where active`) + empresa com vínculo ATIVO do efetivo (`after()`), mensagens pt-BR.
- **`DuplicateRequestDetector`** (RN-007): aponta o processo anterior por CNPJ (alerta + link), recente ou ativo, ignorando canceladas; nunca bloqueia.
- **`SolicitacaoController@index`** "Minhas solicitações" escopado ao requerente efetivo (busca por protocolo/empresa, ordenação whitelist, per_page constante).
- **Toggle** `features.solicitacao_viabilidade`: store degrada comunicado quando off; index expõe `solicitacaoEnabled`.
- **Auditoria RN-002** automática na criação (created/HasAuditoria) com `acting_for_user_id` quando em representação.

## Rotas (nomes exatos — insumo de 08-06/08-11/08-13)

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `portal/solicitacoes` | `portal.solicitacoes.index` | Minhas solicitações (server-driven, escopo do requerente efetivo) |
| POST | `portal/solicitacoes` | `portal.solicitacoes.store` | criar rascunho (origin portal) |

Ambas no grupo `auth:web` + `verified` + `lgpd.accepted` + `ResolveRepresentation` (rotas LITERAIS; as `{solicitacao}` entram em 08-06/08-11).

## Assinaturas e contratos (insumo dos planos seguintes)

- **`ViabilityRequestPolicy`** (auto-discovery `ViabilityRequest`→`ViabilityRequestPolicy`):
  - `view(User, ViabilityRequest): bool` — dono/representado (qualquer status).
  - `update(User, ViabilityRequest): bool` — dono E `status === Rascunho`.
  - `protocol(User, ViabilityRequest): bool` — dono E `status === Rascunho` (fluxo do protocolo no 08-10).
  - `cancel(User, ViabilityRequest): bool` — dono E `status` em `{Rascunho, Protocolada}` (regra fina dos canceláveis fica no 08-12).
  - privado `effectiveUser(User): User` = `app(CurrentRepresentation::class)->grantor() ?? $user`.
- **`SolicitacaoController::store(StoreSolicitacaoRequest): RedirectResponse`** — cria rascunho (status default do banco), `origin = Portal`, `requester_user_id` = efetivo, `created_by_user_id` = ator; flash `status` + (condicional) `duplicateAlert`; redirect para `portal.solicitacoes.index`.
- **`StoreSolicitacaoRequest`**: `service_type_id` (required, integer, exists em `viability_service_types` com `active=true`); `company_id` (required, integer, exists em `companies` + `after()` exige vínculo ATIVO do efetivo).
- **`DuplicateRequestDetector::detect(Company $company, ?int $excludeRequestId = null): ?array`** → `null` ou `['request_id'=>int, 'protocol_number'=>string|null, 'status'=>string, 'created_at'=>string|null]`. Janela: `config('sile.solicitacao.duplicidade.janela_dias', 180)`.

## Shape das props da listagem (`portal/solicitacoes/index` — contrato do 08-13)

- `solicitacoes`: paginação Inertia. `data[]` = `{ id, protocol_number, status: { value, label, public_label }, service_type, company: { legal_name, formatted_cnpj } | null, created_at }`.
- `filters`: `{ search, sort, direction, per_page }` (sort em `['protocol_number','created_at']`, default `created_at` desc).
- `perPageOptions`: `[10, 15, 25, 50]`.
- `solicitacaoEnabled`: bool (reflete o toggle para a UI degradar de forma comunicada).

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: ViabilityRequestPolicy + ViabilityRequestPolicyTest** — `01efdf5` (feat) — RED: 4 erros (classe inexistente) + 1 falha (Gate nega) → GREEN: 5/5 (21 asserções).
2. **Task 2: DuplicateRequestDetector + config janela_dias + DuplicateRequestDetectorTest** — `5da70ff` (feat) — RED: 8 erros (classe inexistente) → GREEN: 8/8 (14 asserções).
3. **Task 3: SolicitacaoController (store+index) + StoreSolicitacaoRequest + rotas + CriarSolicitacaoTest** — `0c3172f` (feat) — RED: 4 erros (rota inexistente) + 4 falhas (sem validação/auditoria) → GREEN: 8/8 (41 asserções).

**Plan metadata:** `docs(08-05)` (este SUMMARY + STATE).

## Decisions Made

- **Policy por auto-discovery** (como `CompanyPolicy`): `App\Models\ViabilityRequest` → `App\Policies\ViabilityRequestPolicy`, sem registro explícito em provider. `protocol`/`cancel` já entram aqui (prontas para 08-10/08-12) garantindo apenas propriedade + estado, deixando a regra fina (parâmetros) aos planos donos do fluxo.
- **`requester` ≠ `created_by`**: o beneficiário é o efetivo (representado em representação); o ator real é quem está logado. `status` nunca é informado no `create()` — vem do default do banco (`'rascunho'`), consistente com a decisão [08-01] de manter `status` fora do fillable (a StateMachine governa as transições; aqui só o nascimento).
- **Reincidência ALERTA, não bloqueio** (RN-007 honesto): o detector aponta o processo anterior por CNPJ (recente OU ativo) com link real; canceladas não contam; o `excludeRequestId` deixa o contrato pronto para reuso em edição (08-06+). Por inscrição imobiliária degrada (lote bloqueado pendente SEDUR).
- **Janela em config, não no catálogo**: `sile.solicitacao.duplicidade.janela_dias` (default 180) é constante de config com ressalva SEDUR — adicionar Parameter aqui quebraria as contagens de seeder travadas em 48 (precedente [08-03]).
- **`per_page` por constante** na listagem (15 + whitelist), sem parâmetro dedicado — mesma razão (não tocar o ParameterSeeder).

## Deviations from Plan

None — plano executado como escrito. Adições dentro do escopo: (1) `DuplicateRequestDetectorTest` dedicado (RED→GREEN do detector em isolamento, além do teste de fluxo exigido), reforçando a cobertura sem tocar nada fora do plano; (2) `test_minhas_solicitacoes_lista_somente_do_dono` no `CriarSolicitacaoTest` para provar o backend do `index` (sem `->component()`, precedente [03-04]/[08-03] — a tela é o 08-13).

## Issues Encountered

- **Colisão de `protocol_number` único na factory**: dois `ViabilityRequest::factory()->protocoled()` geram o mesmo `VIA-2026-000001` (o state fixa o número). No teste do `index` passei `protocol_number` explícito distinto para os dois registros — bug do teste, não do código (o `store` cria rascunho com `protocol_number` null, sem conflito).
- **Suíte completa com `@group postgis` inline**: rodada com `POSTGIS_TESTS_REQUIRED=true` e o container `sile-pgsql` healthy — 16 testes espaciais passaram junto com os de SQLite.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=ViabilityRequestPolicyTest` → 4 erros (classe inexistente) + 1 falha. **GREEN:** 5/5 (21 asserções).
- **RED Task 2:** `--filter=DuplicateRequestDetectorTest` → 8 erros (classe inexistente). **GREEN:** 8/8 (14 asserções).
- **RED Task 3:** `--filter=CriarSolicitacaoTest` → 4 erros (rota inexistente) + 4 falhas (sem validação/auditoria). **GREEN:** 8/8 (41 asserções).
- **Filtros do plano juntos:** `--filter="ViabilityRequestPolicyTest|DuplicateRequestDetectorTest|CriarSolicitacaoTest"` → **21/21** (76 asserções).
- **`php artisan route:list --path=solicitacoes`** → 2 rotas (`portal.solicitacoes.index`/`.store`).
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **735 testes, 735 passaram, 0 falhas** (3737 asserções; 698 SQLite baseline + 16 @group postgis + 21 novos).

## Next Phase Readiness

- **08-06 (imóvel/geometry):** a `ViabilityRequestPolicy::update` (só rascunho) já protege a edição do imóvel; o rascunho criado aqui é o alvo das próximas etapas (gravar `property_polygon` via ST_* a partir do jsonb). `DuplicateRequestDetector` já aceita `excludeRequestId` para reuso.
- **08-10 (protocolo):** `ViabilityRequestPolicy::protocol` (dono + rascunho) pronta; o store NÃO gera protocol_number (rascunho null) — o `ProtocolNumberGenerator` (08-01) entra no protocolo.
- **08-11 (consulta de protocolo):** o shape de status `{value,label,public_label}` e o escopo por dono são o molde da timeline/consulta.
- **08-12 (cancelar):** `ViabilityRequestPolicy::cancel` (dono + não-decidido) pronta; a regra fina dos estados canceláveis por parâmetro entra lá.
- **08-13 (UI):** rotas `portal.solicitacoes.*`, shape da listagem e `solicitacaoEnabled`/`duplicateAlert` (flash) são os contratos da tela.
- **08-15 (atendimento presencial):** o par requester/created_by + auditoria com acting_for é o molde do "em nome de" pelo atendente.
- **Pendência SEDUR registrada (degrada honesto):** definição oficial de duplicidade/reincidência (janela em config, default 180d); detecção por inscrição imobiliária bloqueada (lote — Fase 13), só por CNPJ aqui.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
