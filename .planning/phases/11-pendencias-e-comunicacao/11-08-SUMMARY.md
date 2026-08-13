---
phase: 11-pendencias-e-comunicacao
plan: 08
subsystem: http
tags: [hu-090, hu-096, central-in-app, notifications, database-channel, historico-unificado, communications, shared-prop, inertia, escopo-dono, auditoria, lgpd]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-01 (canal database nativo notifications + ledger communications + enums/labels), 11-05/06/07 (linhas de communications geradas: pendencia_aberta/respondida/expirada, prazo_vencendo, escalonamento_sla, resultado)"
  - phase: 10-analise-tecnica-sedur
    provides: "ViabilityRequestPolicy::view (escopo dono/representado), permissão consultar-solicitacoes, ProcessoController (padrão server-driven), grupos de rota processos/solicitacoes"
  - phase: 09-fluxo-expresso
    provides: "ResultadoExpressoController + EmailLogController (padrão controller server-driven + auditoria da consulta + per_page via Settings)"
provides:
  - "NotificationCenterController (index/markAsRead/markAllAsRead) sobre o canal database nativo do usuário autenticado — central in-app real (HU-090), portal + gestão, escopo do dono (anti-IDOR)"
  - "Shared prop notificacoes.nao_lidas (HandleInertiaRequests) — badge do sininho com contagem REAL de não-lidas do usuário autenticado, em toda resposta Inertia"
  - "ComunicacaoHistoricoController (portal/gestao) — histórico unificado HU-096 por processo sobre o ledger communications (fonte ÚNICA), escopo de dono/permissão, auditado, LGPD"
  - "NotificationResource + CommunicationResource (shapes de leitura para a UI do 11-09)"
  - "Rotas: portal.notificacoes.* / gestao.notificacoes.* / portal.solicitacoes.comunicacoes / gestao.processos.comunicacoes"
affects:
  - "11-09 (UI: sininho com badge notificacoes.nao_lidas, página da central, página do histórico por processo — consome as rotas/shapes deste plano)"
  - "11-10 (smoke navegável do EP11: abrir pendência → in-app no sininho → marcar lida → histórico do processo mostra a comunicação)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Controller compartilhado portal+gestão derivando o ambiente da rota (routeIs) para escolher a página Inertia, com o dado sempre do usuário autenticado"
    - "Central in-app sobre o canal database NATIVO (notifications()/unreadNotifications()/markAsRead) — sem tabela própria, escopo do dono pela relação (findOrFail → 404 anti-IDOR)"
    - "Shared prop em closure no HandleInertiaRequests (badge): avaliada na serialização, null-safe para guest/visita pública"
    - "Histórico unificado lendo a fonte ÚNICA (ledger communications), NÃO unindo EmailLog/notifications de forma frágil"
    - "Resource com flag de contexto (withErrorMessage) — o controller decide explicitamente o que o portal NÃO vê (LGPD), em vez de depender do guard"

key-files:
  created:
    - "app/Http/Controllers/NotificationCenterController.php"
    - "app/Http/Controllers/ComunicacaoHistoricoController.php"
    - "app/Http/Resources/NotificationResource.php"
    - "app/Http/Resources/CommunicationResource.php"
    - "tests/Feature/Comunicacao/NotificationCenterTest.php"
    - "tests/Feature/Comunicacao/ComunicacaoHistoricoTest.php"
  modified:
    - "app/Http/Middleware/HandleInertiaRequests.php"
    - "routes/portal.php"
    - "routes/gestao.php"

key-decisions:
  - "Central in-app servida por UM controller compartilhado (portal+gestão): o User é o mesmo notifiable; o ambiente sai de routeIs('gestao.*') só para escolher a página Inertia. markAsRead/markAllAsRead são idênticos nos dois ambientes (back())"
  - "Anti-IDOR por escopo da relação: markAsRead usa $request->user()->notifications()->findOrFail($id) — a notificação de outro usuário cai em 404 (não vaza existência), em vez de 403 explícito"
  - "Badge é shared prop notificacoes.nao_lidas; a LISTA da central vai na prop lista (paginador) — chaves distintas para o badge global não ser sobrescrito pela prop da página da central"
  - "ComunicacaoHistoricoController tem 2 métodos (portal/gestao) e NÃO um show único: os bindings das rotas têm nomes distintos ({solicitacao} no portal, {viabilityRequest} na gestão) e o escopo/LGPD diverge — ambos delegam ao render() privado"
  - "LGPD por decisão explícita do controller (withErrorMessage), não por detecção de guard no Resource: o portal omite error_message; o status honesto (ex.: falhou) permanece visível"
  - "Histórico NÃO paginado (lista cronológica completa do processo, volume limitado por processo); a central in-app É paginada (per_page via Settings ui.notificacoes.per_page, default 15)"
  - "Sem permissão nova: central é do dono (sem gate extra); histórico reusa consultar-solicitacoes (gestão) / policy view (portal). Permissões seguem 24"

patterns-established:
  - "Shared prop de domínio (badge) em closure null-safe no HandleInertiaRequests"
  - "Resource com setter de contexto (withErrorMessage) para campos sensíveis controlados pelo controller (LGPD)"

# Metrics
duration: ~40min
completed: 2026-06-15
---

# Phase 11 Plan 08: Central in-app + Histórico unificado (HU-090/HU-096) Summary

**A superfície de LEITURA das comunicações do EP11: a central in-app (HU-090) sobre o canal database NATIVO — o usuário (portal E gestão, o mesmo User notifiable) lista suas notificações lidas/não-lidas paginadas, marca uma e marca todas, sempre no escopo do dono (anti-IDOR via findOrFail na relação) — com o badge do sininho ganhando dado REAL por shared prop (`notificacoes.nao_lidas`) em toda resposta Inertia; e o histórico unificado (HU-096) por processo lendo a fonte de verdade ÚNICA (ledger `communications`, todos os canais/tipos, status honesto, ordenado por data), gated por dono (portal) / consultar-solicitacoes (gestão), auditado (RN-002) e com LGPD respeitada (error_message só na retaguarda). Este plano é o ÚNICO route-owner de portal.php/gestao.php e editor do HandleInertiaRequests na fase; ZERO permissão nova, ZERO dependência nova. Filtros do plano 10/10 verdes; suíte completa 1194 SQLite + 29 postgis verde.**

## Performance

- **Duration:** ~40 min
- **Completed:** 2026-06-15
- **Tasks:** 2
- **Files criados:** 6 / **modificados:** 3

## Accomplishments

- **Central in-app (HU-090)** real sobre o canal `database` nativo: `index` (lidas + não-lidas, paginado), `markAsRead` (uma) e `markAllAsRead` (todas), servindo portal e gestão pelo MESMO controller/User notifiable.
- **Escopo do dono garantido** (anti-IDOR): a marcação resolve a notificação pela relação `notifications()` do usuário autenticado — a de outro usuário cai em 404.
- **Badge do sininho com dado REAL**: shared prop `notificacoes.nao_lidas` no `HandleInertiaRequests` (closure null-safe), presente em toda resposta Inertia.
- **Histórico unificado (HU-096)** por processo lendo o ledger `communications` como fonte ÚNICA (todos os canais email/in_app/whatsapp e todos os tipos), com status honesto, ordenado por data; integra o que as Waves 2-3/07 gravam.
- **Escopo + auditoria + LGPD**: portal pela policy `view` (dono/representado); gestão por `consultar-solicitacoes` (reuso, sem permissão nova); consulta auditada (`notificacoes`/`historico-consultado`, RN-002); `error_message` interno só na gestão.
- **Único route-owner da fase**: todas as rotas e o shared prop concentrados aqui, sem conflito com os pares paralelos das ondas anteriores.

## Task Commits

1. **Task 1 (central in-app + badge + rotas)** — `d7fb005` (feat) — TDD: RED (rotas inexistentes → 5 erros) → GREEN (NotificationCenterTest 5/5).
2. **Task 2 (histórico unificado HU-096 + rotas)** — `5af97f5` (feat) — TDD: RED (rotas inexistentes → 5 erros) → GREEN (ComunicacaoHistoricoTest 5/5).

## Contratos para o 11-09 (insumo direto)

### Rotas (names) — central in-app (HU-090)
| Name | Método/Path | Ação |
|---|---|---|
| `portal.notificacoes.index` | GET `portal/notificacoes` | lista paginada |
| `portal.notificacoes.ler` | POST `portal/notificacoes/{notification}/ler` | marca uma (back) |
| `portal.notificacoes.ler-todas` | POST `portal/notificacoes/ler-todas` | marca todas (back) |
| `gestao.notificacoes.index` | GET `gestao/notificacoes` | lista paginada |
| `gestao.notificacoes.ler` | POST `gestao/notificacoes/{notification}/ler` | marca uma (back) |
| `gestao.notificacoes.ler-todas` | POST `gestao/notificacoes/ler-todas` | marca todas (back) |

- `{notification}` = id (uuid) da notificação; marcar uma de outro usuário → 404.
- `index` renderiza `portal/notificacoes/index` ou `gestao/notificacoes/index` com a prop **`lista`** (paginador). `markAsRead`/`markAllAsRead` retornam `back()` (a página recarrega via Inertia e o badge atualiza).

### Rotas (names) — histórico unificado (HU-096)
| Name | Método/Path | Escopo |
|---|---|---|
| `portal.solicitacoes.comunicacoes` | GET `portal/solicitacoes/{solicitacao}/comunicacoes` | dono/representado (policy view); não-dono → 403 |
| `gestao.processos.comunicacoes` | GET `gestao/processos/{viabilityRequest}/comunicacoes` | `consultar-solicitacoes`; sem permissão → 403 |

- Renderizam `portal/solicitacoes/comunicacoes` / `gestao/processos/comunicacoes` com props **`processo`** (`{id, protocol_number}`) e **`comunicacoes`** (lista ordenada por data desc).

### Shared prop (badge do sininho)
```
notificacoes: { nao_lidas: number }   // contagem real de não-lidas do usuário autenticado; 0 sem login
```
Presente em TODA resposta Inertia (HandleInertiaRequests). O sininho lê daqui — não precisa de request extra.

### Shape `NotificationResource` (item de `lista.data`)
```
{ id: string(uuid), type: string(FQCN da Notification), data: object (title/summary/url do toDatabase),
  lida: boolean, read_at: string|null (ISO-8601), created_at: string (ISO-8601) }
```

### Shape `CommunicationResource` (item de `comunicacoes`)
```
{ id: number, channel: 'email'|'in_app'|'whatsapp', channel_label: string,
  type: string (pendencia_aberta|pendencia_respondida|pendencia_expirada|prazo_vencendo|escalonamento_sla|resultado),
  type_label: string, status: 'na_fila'|'enviado'|'falhou'|'bloqueado'|'desativado', status_label: string,
  title: string, summary: string|null, recipient: {id, name}|null,
  queued_at|sent_at|failed_at|created_at: string|null (ISO-8601),
  error_message?: string|null }   // PRESENTE só na gestão (LGPD); ausente no portal
```

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-090 — central lista (lidas/não-lidas, paginada) | NotificationCenterTest::test_portal_lista_notificacoes_e_expoe_badge_de_nao_lidas / test_gestao_lista_marca_e_expoe_badge |
| HU-090 — marcar uma / marcar todas | NotificationCenterTest::test_portal_marca_uma_notificacao_como_lida / test_portal_marca_todas_as_notificacoes_como_lidas |
| HU-090 — escopo do dono (anti-IDOR) | NotificationCenterTest::test_usuario_nao_marca_notificacao_de_outro_anti_idor |
| HU-090 — badge real (shared prop) | NotificationCenterTest (assertSame em notificacoes.nao_lidas, portal+gestão) |
| HU-096 — histórico unificado por processo, ordenado, fonte única | ComunicacaoHistoricoTest::test_gestao_lista_historico_unificado_por_processo_ordenado_e_audita |
| HU-096 RN — escopo gestão (consultar-solicitacoes) + auditoria | ComunicacaoHistoricoTest::test_gestao_..._audita / test_gestao_sem_consultar_solicitacoes_recebe_403_auditado |
| HU-096 RN — escopo portal (dono) + auditoria | ComunicacaoHistoricoTest::test_portal_dono_lista_historico_e_audita / test_portal_nao_dono_recebe_403_auditado |
| HU-096 — LGPD (error_message só na gestão) | ComunicacaoHistoricoTest::test_error_message_visivel_na_gestao_e_oculto_no_portal_lgpd |

## Verificação (evidência fresca)

- `vendor/bin/pint --dirty --format agent` → **passed**.
- `php artisan test --compact --filter="NotificationCenterTest|ComunicacaoHistoricoTest"` → **10 passed / 43 assertions**.
- `php artisan route:list --path=notificacoes` → 6 rotas (portal + gestão); `--path=comunicacoes` → 2 rotas (portal + gestão).
- Suíte completa: `php artisan test --compact --exclude-group postgis` → **1194 passed / 6068 assertions**; `--group postgis` → **29 passed / 184 assertions**. Sem regressão (incl. o shared prop em toda resposta Inertia).

## Deviations from Plan

- **`ComunicacaoHistoricoController` com 2 métodos (`portal`/`gestao`) em vez de um `show` único** (o plano escreveu "@show(ViabilityRequest)"): os bindings das rotas têm nomes distintos (`{solicitacao}` no portal, `{viabilityRequest}` na gestão) — o implicit binding exige o nome do parâmetro batendo com a variável — e o escopo/LGPD diverge (policy view + error_message oculto no portal; permissão + error_message visível na gestão). Ambos delegam ao `render()` privado; o must_have ("controller serve os dois ambientes sobre communications") é cumprido.
- **Prop da lista da central chamada `lista`** (não `notificacoes`) para não colidir com o shared prop `notificacoes.nao_lidas` (a prop da página sobrescreveria o objeto compartilhado). O badge segue em `notificacoes.nao_lidas`, conforme o plano.
- **Testes de render inspecionam `viewData('page')`** em vez de `assertInertia(...)->component(...)`: as páginas `.tsx` são do 11-09 e o `AssertableInertia` exige o componente em disco. É o padrão já estabelecido no repo (ProcessoConsultaTest) para endpoints que precedem a UI.

## Issues Encountered

- **Artefato de teste de guards (não é bug de produção)**: em `test_error_message_..._lgpd`, um `actingAs(analista, 'gestao')` seguido de `actingAs($dono)` (sem guard) deixava o guard padrão como `gestao`, e o `actingAs` sem argumento setava o usuário no guard errado → o `auth:web` da rota do portal redirecionava (302). Causa raiz confirmada (`Auth::shouldUse` muda o driver default). Correção: `actingAs($dono, 'web')` explícito no request do portal. O código de produção (guards independentes por ambiente) está correto.
- **`*/` dentro de docblock**: o texto `pendencia_*/...` no docblock do teste fechava o comentário antes da hora (syntax error). Reescrito sem a sequência `*/`.

## Next Phase Readiness

- **11-09 (UI)**: tem as rotas (names), os shapes (`NotificationResource`/`CommunicationResource`), o shared prop do badge (`notificacoes.nao_lidas`) e os componentes-alvo (`portal|gestao/notificacoes/index`, `portal/solicitacoes/comunicacoes`, `gestao/processos/comunicacoes`). `npm run typecheck` ao tocar TSX.
- **11-10 (smoke)**: o ciclo navegável fecha — abrir pendência (Wave 3) → badge incrementa → central lista → marcar lida → histórico do processo mostra a comunicação (fonte única).
- **Sem bloqueios.** ZERO permissão nova (permanecem 24), ZERO dependência nova; este plano é o único editor de rotas/shared prop da fase.

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
