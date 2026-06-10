---
phase: 02-administracao-base
plan: 07
subsystem: admin
tags: [parametros, feature-toggle, auditoria, criptografia, inertia, react]

# Dependency graph
requires:
  - phase: 02-01
    provides: "permissão manter-parametros no RolesAndPermissionsSeeder"
  - phase: 02-02
    provides: "registry parameters + Settings banco/cache/fallback + catálogo de 10 chaves + Settings::enabled"
  - phase: 02-06
    provides: "padrões de tela administrativa (cards/forms) e rotas por permissão em routes/gestao.php"
provides:
  - "Tela /gestao/parametros agrupada por domínio com validação dinâmica do catálogo (RN-007)"
  - "Histórico auditado por parâmetro com sensível mascarado [criptografado] (CA-07/RN-008/RN-009)"
  - "Primeiro feature toggle real: features.procuracoes com degradação controlada (CA-06/RN-011)"
  - "CA-05 provado em call site real da Fase 1 (ui.access_history.per_page → portal.acessos.index)"
affects: [fase-9-notificacoes, fase-11-ia, fase-13-integracoes, fase-14-fluxo-expresso]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Validação dinâmica: FormRequest::after() valida value com as validation_rules do próprio registro do catálogo"
    - "Toggle com degradação controlada: guarda Settings::enabled só no fluxo de criação; leitura e revogação preservadas"
    - "Histórico de parâmetro via AuditService explícito com mascaramento na gravação (nunca HasAuditoria no Parameter)"

key-files:
  created:
    - app/Http/Controllers/Gestao/ParameterController.php
    - app/Http/Requests/Gestao/UpdateParameterRequest.php
    - resources/js/pages/gestao/parametros/index.tsx
    - resources/js/pages/gestao/parametros/historico.tsx
    - tests/Feature/Parameters/ManageParametersTest.php
    - tests/Feature/Parameters/ParameterEffectTest.php
    - tests/Feature/Parameters/ParameterHistoryTest.php
  modified:
    - routes/gestao.php
    - app/Http/Controllers/Portal/ProcurationController.php
    - resources/js/pages/portal/procuracoes/index.tsx

key-decisions:
  - "Toggle real escolhido: features.procuracoes — store bloqueado com aviso pt-BR; destroy (revogação) preservado: segurança do outorgante prevalece sobre o toggle"
  - "Histórico ordena por latest('id') em vez de latest(): alterações no mesmo segundo teriam ordem instável por created_at"
  - "Sensível com campo vazio = manter valor atual, sem gravação e sem activity"

patterns-established:
  - "Sensível nunca sai do servidor: index envia value null + has_admin_value; tela mostra password vazio com placeholder"
  - "requires_connection_test exibe nota sem botão (RN-010 sem teste falso — implementação real na Fase 13)"

# Metrics
duration: 13min
completed: 2026-06-10
---

# Phase 2 Plan 07: Tela de Parâmetros e Toggle Real Summary

**HU-014 fechada com os 7 CAs: tela de parâmetros com validação do catálogo, histórico mascarando sensíveis e o toggle real features.procuracoes degradando procurações de forma comunicada — sem deploy**

## Performance

- **Duration:** 13 min
- **Started:** 2026-06-10T16:18:35Z
- **Completed:** 2026-06-10T16:31:45Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments

- CA-05 provado de ponta a ponta em call site REAL da Fase 1: PUT em `ui.access_history.per_page` muda a paginação de `/portal/acessos` na request seguinte, sem deploy (cache invalidado no saved do model).
- CA-06 com o primeiro toggle real do sistema (`features.procuracoes`): com toggle OFF, novo vínculo é bloqueado com aviso pt-BR e nada é criado; lista continua visível e revogação continua funcionando (degradação controlada e comunicada, nunca silenciosa).
- CA-07 com histórico paginado por parâmetro: valor anterior, valor novo, responsável e data/hora — sensível aparece como `[criptografado]` (anterior E novo) e o segredo não existe em nenhuma coluna de `activity_log`.
- RN-007: validação dinâmica na gravação usando as `validation_rules` do próprio registro do catálogo, com o nome amigável do parâmetro nas mensagens pt-BR.
- RN-009: valor sensível cifrado no banco, jamais devolvido pela UI (prop `value` null + campo password vazio); campo vazio = manter valor atual.
- Suíte completa 196/196 verde no fechamento da wave 5; testes de Procuration e SettingsTest da Fase 1 intocados (`git diff --stat` vazio).

## Task Commits

Each task was committed atomically:

1. **Task 1: Controller de parâmetros com validação dinâmica, mascaramento e auditoria (TDD)** - `00744d2` (test RED) + `c2c6352` (feat GREEN)
2. **Task 2: CA-05 efeito sem deploy + CA-06 toggle real + CA-07 histórico (TDD)** - `cfaa944` (test RED) + `b0025c7` (feat GREEN)
3. **Task 3: Telas de parâmetros, histórico e aviso de procurações** - `fae329f` (feat)

## Files Created/Modified

- `app/Http/Controllers/Gestao/ParameterController.php` - index agrupado por group (sensível zerado), update com auditoria mascarada, history paginado via Activity
- `app/Http/Requests/Gestao/UpdateParameterRequest.php` - validação dinâmica via validation_rules do catálogo; sensível vazio pula validação (manter)
- `routes/gestao.php` - 3 rotas sob permission:manter-parametros com binding `{parameter:key}`
- `app/Http/Controllers/Portal/ProcurationController.php` - guarda Settings::enabled('procuracoes') no store + prop procuracoesEnabled no index; destroy sem guarda
- `resources/js/pages/gestao/parametros/index.tsx` - tela agrupada com campo por tipo, badge Administrado, link Histórico e nota RN-010
- `resources/js/pages/gestao/parametros/historico.tsx` - tabela com badge [criptografado] e paginação padrão
- `resources/js/pages/portal/procuracoes/index.tsx` - aviso âmbar e form de novo vínculo oculto com toggle off
- `tests/Feature/Parameters/ManageParametersTest.php` - 8 testes (CA-01..04, RN-007, RN-009)
- `tests/Feature/Parameters/ParameterEffectTest.php` - 5 testes (CA-05, CA-06, regressão toggle ligado)
- `tests/Feature/Parameters/ParameterHistoryTest.php` - 3 testes (CA-07, mascaramento, CA-04)

## Decisions Made

- **Toggle real: features.procuracoes** (recomendação do RESEARCH aceita no CONTEXT). Comportamento degradado definido: `store` responde `back()->with('status', 'A funcionalidade de procurações está temporariamente desativada pelo administrador.')` SEM criar vínculo; `index` permanece acessível (histórico íntegro); `destroy` NUNCA é bloqueado — a segurança do outorgante (revogar poder concedido) prevalece sobre o toggle. Representação ativa existente não é tocada (escopo registrado).
- **Toggles futuros nascem sobre este mecanismo**: integrações (Fase 13), IA (Fase 11), notificações (Fase 9) e fluxo expresso (Fase 14) registram suas chaves `features.*` no catálogo e usam `Settings::enabled()` — nenhum mecanismo novo é necessário.
- **latest('id') no histórico**: duas alterações no mesmo segundo compartilham created_at e a ordem ficaria instável (sqlite tende a retornar rowid asc); id serial desc garante o mais recente primeiro de forma determinística.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Ordenação instável do histórico com latest()**

- **Found during:** Task 2 (history)
- **Issue:** O snippet do plano usava `->latest()` (created_at desc); duas alterações no mesmo segundo têm created_at idêntico e a ordem retornada seria indefinida — o teste de CA-07 (anterior '5' → novo '10' no item 0) ficaria flaky
- **Fix:** `->latest('id')` — id autoincremento desc dá ordem estável e correta
- **Files modified:** app/Http/Controllers/Gestao/ParameterController.php
- **Verification:** test_historico_exibe_anterior_novo_responsavel_e_data verde de forma determinística
- **Committed in:** b0025c7 (Task 2 commit)

**2. [Rule 3 - Blocking] Stubs de páginas Inertia nas tasks de backend**

- **Found during:** Tasks 1 e 2 (GREEN)
- **Issue:** O Inertia Testing valida que o arquivo do componente existe (`gestao/parametros/index` e `gestao/parametros/historico`); sem o tsx os testes de component falham mesmo com o backend correto
- **Fix:** Stubs mínimos criados nos commits de backend (mesmo padrão registrado no 02-06); Task 3 os substituiu pelas telas reais
- **Files modified:** resources/js/pages/gestao/parametros/index.tsx, resources/js/pages/gestao/parametros/historico.tsx
- **Verification:** Suíte verde nas Tasks 1 e 2; telas reais na Task 3 com typecheck/build verdes
- **Committed in:** c2c6352 e b0025c7

---

**Total deviations:** 2 auto-fixed (1 bug de instabilidade, 1 blocking)
**Impact on plan:** Correções necessárias para determinismo e para o resolver de páginas do Inertia. Sem scope creep.

**Nota de TDD (não é desvio):** no RED da Task 2, `test_alteracao_de_parametro_tem_efeito_imediato_sem_deploy` (CA-05) e `test_toggle_desligado_preserva_revogacao` já passaram — esperado por design: ambos PROVAM mecanismo existente (Settings banco/cache do 02-02 + call site da Fase 1; destroy nunca ganhou guarda). Os 4 demais testes falharam pelo motivo certo e dirigiram a implementação.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- HU-014 completa: mecanismo definitivo de parametrização administrável entregue (registry + tela + histórico + toggle com degradação controlada). Fases futuras só registram chaves novas no catálogo.
- Toggles de integrações/IA/notificações/fluxo expresso nascem nas Fases 13/11/9/14 sobre `Settings::enabled()` — sem retrabalho de mecanismo.
- RN-010 (teste de conexão) permanece como contrato (`requires_connection_test` + nota na tela, sem botão falso) até a Fase 13 implementar contra homologação real.
- Resta o 02-08 (fechamento da fase: DatabaseSeeder + smoke humano) para concluir a Fase 2.

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
