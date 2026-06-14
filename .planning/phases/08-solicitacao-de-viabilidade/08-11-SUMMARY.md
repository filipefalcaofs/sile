---
phase: 08-solicitacao-de-viabilidade
plan: 11
subsystem: consulta-protocolo
tags: [solicitacao-viabilidade, hu-069, consulta-protocolo, timeline, linguagem-simples, public-label, link-assinado, temporarySignedRoute, signed, throttle, rate-limiter, prazo-estimado, lgpd, rn-002, rn-004, rn-005, rn-006, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: "01"
    provides: "viability_request_transitions (fonte da timeline) + ViabilityRequestStatus::publicLabel()/label() + ViabilityRequestStateMachine (transição com public_label)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "02"
    provides: "parâmetros solicitacao.consulta_publica.assinatura_ttl_dias, solicitacao.prazo_estimado_dias, seguranca.throttle.consulta_protocolo.por_minuto (catálogo + fallback config/sile.php)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "ViabilityRequestPolicy::view (dono/representado, qualquer status, CA-04)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "10"
    provides: "protocolo (número + transição protocolada com public_label) — o que a consulta exibe"
provides:
  - "App\\Services\\Solicitacao\\TimelineSolicitacao::build(ViabilityRequest, bool $publico = false): array — timeline simplificada das transitions (publicLabel) + prazo estimado com ressalva"
  - "App\\Http\\Controllers\\Portal\\ConsultaProtocoloController@show — consulta autenticada (dono/representado) + geração do link público assinado"
  - "App\\Http\\Controllers\\Portal\\ConsultaProtocoloPublicaController@show — consulta pública por link assinado, payload mínimo (LGPD), auditada com causer null + IP"
  - "RateLimiter 'consulta-protocolo' (parametrizado por seguranca.throttle.consulta_protocolo.por_minuto)"
  - "Rotas portal.solicitacoes.show (GET autenticada) e portal.protocolo.publico (GET signed + throttle, pública)"
  - "Páginas portal/solicitacoes/protocolo (PortalLayout) e portal/solicitacoes/protocolo-publico (standalone)"
affects: [08-13-ui-wizard, 08-14-contingencia, 08-16-fechamento-seeds]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "URLs assinadas nativas (URL::temporarySignedRoute) + middleware signed para consulta pública sem login com TTL parametrizável — zero dependência nova"
    - "RateLimiter parametrizado por Settings::get (espelha cnpj-lookup/geocoding/consulta-viabilidade da Fase 3.1/7)"
    - "Timeline derivada SÓ das viability_request_transitions reais (anti-fachada); modo público vs autenticado controla a exposição de dados (LGPD)"
    - "Página Inertia standalone pública (padrão home.tsx) protegida por link assinado, sem PortalLayout"

key-files:
  created:
    - app/Services/Solicitacao/TimelineSolicitacao.php
    - app/Http/Controllers/Portal/ConsultaProtocoloController.php
    - app/Http/Controllers/Portal/ConsultaProtocoloPublicaController.php
    - resources/js/pages/portal/solicitacoes/protocolo.tsx
    - resources/js/pages/portal/solicitacoes/protocolo-publico.tsx
    - tests/Feature/Solicitacao/ConsultarProtocoloTest.php
    - tests/Feature/Solicitacao/ConsultarProtocoloPublicoTest.php
  modified:
    - app/Providers/FortifyServiceProvider.php
    - routes/portal.php

key-decisions:
  - "Timeline montada SÓ das viability_request_transitions reais (fonte de verdade, anti-fachada): cada transição vira uma etapa concluída (rótulo = public_label gravado, fallback publicLabel do destino); a situação atual usa publicLabel(). Nada é inventado."
  - "Modo público (link assinado, LGPD) expõe SÓ data + rótulo amigável por etapa; o motivo interno (reason), o ator e o rótulo técnico (label) são EXCLUSIVOS do modo autenticado. Pendências do requerente também só no autenticado."
  - "Payload da consulta pública é MÍNIMO: protocol_number + status (value/public_label) + protocoled_at + timeline pública. NUNCA empresa/CNPJ, CPF, endereço detalhado, CNAEs ou anexos (LGPD)."
  - "Prazo estimado (RN-005) parametrizado (solicitacao.prazo_estimado_dias) SEMPRE com ressalva honesta de estimativa — a medição real por etapa é da HU-129/Fase 15. Estados terminais (cancelada/deferida/indeferida) não têm prazo em curso (null)."
  - "Pendências do requerente derivadas do status real (rascunho → concluir/protocolar; em_pendencia → ação aguardando), nunca inventadas — nos demais estados não há pendência."
  - "Link público gerado no controller AUTENTICADO via URL::temporarySignedRoute('portal.protocolo.publico', now()->addDays(TTL), ['solicitacao'=>id]); TTL = solicitacao.consulta_publica.assinatura_ttl_dias. Só para solicitações já protocoladas (protocol_number != null); em rascunho o link é null."
  - "Rota pública FORA de auth, com middleware ['signed','throttle:consulta-protocolo']; link inválido/expirado → 403 (InvalidSignatureException, comportamento padrão já tratado em bootstrap/app.php). RateLimiter 'consulta-protocolo' espelha o padrão parametrizado da Fase 3.1/7."
  - "Auditoria RN-002 em ambas as consultas (solicitacoes/consulta-protocolo no autenticado com causer do dono; solicitacoes/consulta-protocolo-publica no público com causer null + IP, resolvido por AuditService/RecordActivityAction)."

patterns-established:
  - "Consulta pública por link assinado: temporarySignedRoute (TTL parametrizável) + signed + throttle parametrizado + payload mínimo LGPD + auditoria anônima (causer null + IP) — gabarito para futuras consultas públicas de processo"
  - "Serviço de apresentação (TimelineSolicitacao) com flag publico controlando a granularidade do que é exposto — reusado pelos dois controllers"

# Metrics
duration: ~35 min (commits ca32767 → fdfc0fe, com leitura de contexto)
completed: 2026-06-14
---

# Phase 8 Plan 11: Consultar protocolo (HU-069) Summary

**A consulta de protocolo (HU-069) entrega o critério 3 do ROADMAP: o protocolo é consultável em linguagem simples, com timeline e prazo estimado. `TimelineSolicitacao::build(ViabilityRequest, bool $publico = false)` monta a timeline SÓ das `viability_request_transitions` reais (anti-fachada): cada transição vira uma etapa concluída com o rótulo amigável (`public_label` da transição, fallback `publicLabel()` do destino) e a situação atual usa `publicLabel()` (RN-004/006). O prazo estimado é parametrizado (`solicitacao.prazo_estimado_dias`) SEMPRE com ressalva honesta — a medição real por etapa é da HU-129/Fase 15 (RN-005). A consulta AUTENTICADA (`ConsultaProtocoloController@show`, rota `portal.solicitacoes.show`) é escopada ao dono/representado pela `ViabilityRequestPolicy::view` (terceiro → 403 auditado, CA-04), entrega timeline + prazo + dados do processo e gera o LINK PÚBLICO assinado (`URL::temporarySignedRoute('portal.protocolo.publico', now()->addDays(TTL), ...)`, TTL = `solicitacao.consulta_publica.assinatura_ttl_dias`). A consulta PÚBLICA (`ConsultaProtocoloPublicaController@show`, rota `portal.protocolo.publico` FORA de auth, com middleware `signed` + `throttle:consulta-protocolo`) abre sem login pelo link assinado, mostra SÓ status simples + timeline pública + prazo, NUNCA dados sensíveis (CPF/CNPJ completo/endereço detalhado) nem anexos (LGPD), e é auditada com causer null + IP (RN-002). Link inválido/expirado → 403. ZERO dependência nova (URLs assinadas e RateLimiter são nativos). TDD estrito: ConsultarProtocoloTest 7/7 + ConsultarProtocoloPublicoTest 5/5 (12/12, 101 asserções); typecheck/build/pint verdes.**

## Performance

- **Duration:** ~35 min (2 commits TDD), com leitura de contexto
- **Tasks:** 3 (timeline+ratelimiter; consulta autenticada; consulta pública assinada) — Tasks 2 e 3 entregues como unidade coesa (ver Desvios)
- **Files:** 7 criados + 2 modificados — ZERO dependência nova

## Accomplishments

- **`TimelineSolicitacao::build(ViabilityRequest, bool $publico = false): array`** — timeline simplificada das transições reais (publicLabel), com prazo estimado parametrizado + ressalva e pendências do requerente (só autenticado).
- **`ConsultaProtocoloController@show`** (rota `portal.solicitacoes.show`, GET autenticada) — `Gate::authorize('view')`, timeline autenticada + dados do processo + link público assinado; auditoria `solicitacoes/consulta-protocolo`.
- **`ConsultaProtocoloPublicaController@show`** (rota `portal.protocolo.publico`, GET `signed`+`throttle:consulta-protocolo`, pública) — payload mínimo (LGPD), timeline pública; auditoria `solicitacoes/consulta-protocolo-publica` com causer null + IP.
- **RateLimiter `consulta-protocolo`** parametrizado (`seguranca.throttle.consulta_protocolo.por_minuto`) no `FortifyServiceProvider` (espelha `consulta-viabilidade`).
- **Páginas** `portal/solicitacoes/protocolo` (PortalLayout, com timeline, prazo, dados e botão "Copiar link público") e `portal/solicitacoes/protocolo-publico` (standalone padrão home.tsx) — mobile-first/acessíveis.
- **Anti-fachada**: timeline 100% das transitions reais; prazo com ressalva explícita (não finge medição); pública não vaza dado sensível; auditoria real em ambas as consultas.

## Contrato para os próximos planos

### Timeline (serviço de apresentação)

```
App\Services\Solicitacao\TimelineSolicitacao::build(
    App\Models\ViabilityRequest $request,
    bool $publico = false,
): array
```

Retorno:
- `status_atual`: `{ value, public_label, label? }` (`label` só no modo autenticado).
- `etapas[]`: `{ rotulo, data }` (público) **+** `{ status, status_label, motivo }` (autenticado).
- `pendencias[]`: lista (vazia no público; derivada do status no autenticado).
- `prazo_estimado`: `{ dias, ressalva }` ou `null` (terminais: cancelada/deferida/indeferida).

### Rotas

- **`GET portal/solicitacoes/{solicitacao}`** (name `portal.solicitacoes.show`) — autenticada, `ViabilityRequestPolicy::view` (dono/representado). Props: `solicitacao`, `timeline`, `publicLink` (string assinada ou null se ainda não protocolada).
- **`GET portal/protocolo/{solicitacao}`** (name `portal.protocolo.publico`) — pública, middleware `signed` + `throttle:consulta-protocolo`. Props: `solicitacao` (mínimo), `timeline` (pública).

### Geração do link público

```php
URL::temporarySignedRoute(
    'portal.protocolo.publico',
    now()->addDays((int) Settings::get('solicitacao.consulta_publica.assinatura_ttl_dias', 30)),
    ['solicitacao' => $request->id],
);
```

- **08-13 (UI wizard)**: a página de detalhe pode reusar `portal.solicitacoes.show`/`protocolo.tsx`; o botão "Copiar link público de acompanhamento" já existe (link gerado server-side).
- **08-14 (contingência)/08-15 (presencial)**: o operador consulta pelo mesmo `portal.solicitacoes.show`.
- **08-16 (fechamento/seeds)**: smoke navegável da consulta pública (gerar link e abrir sem login).

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: TimelineSolicitacao + RateLimiter consulta-protocolo** — `ca32767` (feat) — RED: "Target class [App\\Services\\Solicitacao\\TimelineSolicitacao] does not exist" → GREEN: `--filter=ConsultarProtocoloTest` 2/2.
2. **Tasks 2+3: consulta autenticada + pública assinada** — `fdfc0fe` (feat) — RED: 5 falhas "Route [portal.solicitacoes.show] not defined" (Task 2) e dependência do `portal.protocolo.publico` (Task 3) → GREEN: `--filter="ConsultarProtocoloTest|ConsultarProtocoloPublicoTest"` 12/12 (101 asserções).

## Decisions Made

- **Timeline só das transitions reais** (anti-fachada): nenhuma etapa inventada; `public_label` da transição é a fonte do rótulo amigável.
- **Granularidade por flag `publico`**: o mesmo serviço serve as duas consultas; o público expõe só data + rótulo, o autenticado acrescenta técnico/motivo/pendências (LGPD).
- **Payload público mínimo**: sem empresa/CNPJ/CPF/endereço/CNAEs/anexos — só o que o cidadão precisa para acompanhar.
- **Prazo com ressalva sempre** (RN-005): parametrizado, honesto sobre ser estimativa; terminal = sem prazo.
- **Link público gerado no controller autenticado** (conforme o plano), apontando para a rota pública assinada com TTL parametrizável.
- **403 para link inválido/expirado**: reusa o comportamento padrão do `InvalidSignatureException` (bootstrap/app.php), sem tratamento novo.

## Deviations from Plan

- **Tasks 2 e 3 entregues em um único commit (`fdfc0fe`)** em vez de dois: o controller autenticado (Task 2) gera o link assinado para a rota pública (Task 3) — uma dependência de tempo de execução que o próprio plano prevê ("Geração do link: no controller autenticado (08-11 Task 2)"). Separar produziria um estado intermediário quebrado (a página autenticada daria 500 sem a rota pública registrada). TDD preservado: testes autenticados e públicos escritos RED antes da implementação, todos GREEN ao final. Task 1 permaneceu commit próprio (`ca32767`).
- **Teste direto de `TimelineSolicitacao` na Task 1** (`test_timeline_reflete_as_transicoes_reais_em_linguagem_simples` + `test_timeline_lista_pendencia_do_requerente_no_rascunho`): o plano sugeria pôr os asserts da timeline na Task 2; para honrar o TDD estrito da Task 1 (teste falhando antes do serviço existir), criei o teste de serviço já em ConsultarProtocoloTest. A Task 2 acrescentou os asserts da timeline no nível do controller.
- **Documentos não listados na página autenticada**: o plano cita "link para baixar documentos" como opção; omitido para não criar botão sem dado carregado (anti-fachada) e por ser território do wizard 08-13 (as rotas de download já existem do 08-08). Fora do escopo de HU-069.

## Issues Encountered

- **`pint --dirty` reformatou incidentalmente um arquivo untracked de outro plano** (`app/Http/Controllers/Gestao/ContingenciaController.php`, do 08-14, em andamento na mesma working dir): apenas formatação, NÃO foi staged nem commitado (permanece na working tree do 08-14, que rodará pint igualmente). Nenhum arquivo de outro plano entrou nos meus commits (verificado via `git show --stat`).
- **STATE.md NÃO atualizado por este plano**: está com edições não commitadas do 08-12 (wave paralela). Editá-lo clobraria/mixaria o trabalho concorrente no meu commit (staging é por arquivo inteiro). A consolidação do STATE.md fica para a integração final do orquestrador (conforme a nota de coordenação da wave).

## Verification (evidência fresca)

- **RED Task 1:** "Target class [App\\Services\\Solicitacao\\TimelineSolicitacao] does not exist". **GREEN:** `--filter=ConsultarProtocoloTest` → 2/2.
- **RED Tasks 2/3:** "Route [portal.solicitacoes.show] not defined" / "Route [portal.protocolo.publico] not defined". **GREEN:** `--filter="ConsultarProtocoloTest|ConsultarProtocoloPublicoTest"` → **12/12 (101 asserções)**.
- **`npx tsc --noEmit`** → sem erros. **`npm run build`** → built ok.
- **`vendor/bin/pint --dirty --format agent`** → passed nos meus arquivos.
- **Suíte completa:** `php artisan test --compact` → **816 testes, 809 passaram, 7 erros**. Os **7 erros são EXCLUSIVAMENTE do `ContingenciaTest` (08-14, untracked, em andamento)** — "Route [gestao.contingencia.create/store] not defined" (rotas `gestao.contingencia.*` ainda não adicionadas pelo executor do 08-14). **Nenhuma falha no meu escopo**; os testes @group postgis não foram exercitados nesta execução (não há teste postgis neste plano).

## Next Phase Readiness

- **08-13 (UI revisar/protocolar)**: reusa `portal.solicitacoes.show` + `protocolo.tsx`; o botão "Copiar link público" já entrega o link assinado server-side.
- **08-14 (contingência)/08-15 (presencial)**: o operador acompanha pelo mesmo `portal.solicitacoes.show` (policy view pelo efetivo).
- **08-16 (fechamento/seeds)**: smoke da consulta pública (gerar `temporarySignedRoute` e abrir sem login) + validar o throttle parametrizado.
- **Pendência herdada (registrada, não bloqueia)**: prazo estimado REAL depende da medição por etapa da HU-129/Fase 15 — hoje parametrizado com ressalva honesta.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
