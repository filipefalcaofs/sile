---
phase: 12-auditoria-e-compliance
plan: 07
subsystem: backend
tags: [lgpd, hu-102, personal-data, consentimentos, retencao, pruning, activity-log, minimizacao, rn-002]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance (12-01)
    provides: "coluna personal_data em activity_log + AuditService::log(..., bool $personalData = false) aditivo"
  - phase: 12-auditoria-e-compliance (12-03)
    provides: "permissão monitorar-lgpd (admin) + reuso de retencao.access_logs.dias / ui.dashboard.acessos_janela_dias (HU-014)"
  - phase: 12-auditoria-e-compliance (12-05)
    provides: "ProcessoController::show com prop explicacao entre processo e timeline (serialização sobre o MESMO método)"
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "LegalTerm::current('lgpd') + LegalTermAcceptance + AuditModelsPruned (log_name='retencao', event='pruning-access-logs') + 403 auditado no ponto único"
provides:
  - "LgpdMonitorService::consentimentos()/retencao()/acessosDadoPessoal() — 3 agregações REAIS minimizadas (HU-102)"
  - "Gestao\\LgpdMonitorController@index — painel gated por monitorar-lgpd, auditado (personal_data) e minimizado; pendência DPO registrada honesta"
  - "Rota gestao.lgpd.index (GET gestao/lgpd) sob permission:monitorar-lgpd"
  - "Marcação personal_data=true nos call sites REAIS de leitura de PII de terceiro: ProcessoController::show, AccessHistoryController, UserManagementController (updateRole + toggleActivation)"
  - "Props do Inertia 'gestao/lgpd/index': consentimentos, retencao, acessosDadoPessoal, direitosTitular"
affects: [12-10-pagina-painel-lgpd, 12-12-smoke-verificacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Marcação personal_data ADITIVA: só acrescenta o parâmetro nomeado ao AuditService::log existente (sem mudar evento/descrição/resultado) — anti-regressão por construção"
    - "Agregação minimizada (LGPD): o painel devolve métricas/contagens/série por ação, NUNCA PII crua"
    - "Retenção honesta: dias parametrizados + último pruning lido da própria trilha; decisões (viability_decisions) ficam FORA do pruning (compliance, flag explícita)"
    - "Pendência externa registrada, nunca simulada: direitos do titular (art. 18) como bloco informativo pendente-dpo, sem rito automatizado inventado"

key-files:
  created:
    - "app/Services/Lgpd/LgpdMonitorService.php"
    - "app/Http/Controllers/Gestao/LgpdMonitorController.php"
    - "tests/Feature/Lgpd/LgpdMonitorTest.php"
    - "tests/Feature/Lgpd/PersonalDataMarkingTest.php"
  modified:
    - "app/Http/Controllers/Gestao/ProcessoController.php"
    - "app/Http/Controllers/Gestao/AccessHistoryController.php"
    - "app/Http/Controllers/Gestao/UserManagementController.php"
    - "routes/gestao.php"

key-decisions:
  - "personal_data marcado SÓ nos pontos REAIS de leitura/gestão de PII de terceiro (show de processo, acessos de outra conta, gestão de usuários) — sem marcar a listagem de usuários (PII não sensível, sem audit existente) nem por excesso"
  - "Janela de acessosDadoPessoal reusa ui.dashboard.acessos_janela_dias (default 7) — ZERO parâmetro novo (12-03 é dono do catálogo); reuso documentado"
  - "Retenção: só access_logs é prunável; decisoes_fora_do_pruning=true comunica que viability_decisions são preservadas por compliance"
  - "Direitos do titular (eliminação/anonimização art. 18) = pendência DPO/SEDUR registrada no payload (status pendente-dpo), nunca um rito inventado"
  - "Painel audita a própria consulta com personalData: true (listar acessos a PII é, em si, acesso a dado de auditoria sensível); 403 reusa o ponto único (bootstrap/app.php)"

patterns-established:
  - "LgpdMonitorService: read-model de compliance puro sobre fontes reais (LegalTerm/LegalTermAcceptance, activity_log, Settings) — a 12-10 consome as métricas, jamais PII crua"
  - "Único route-owner da Wave 3 em routes/gestao.php; bloco gestao.lgpd.index adjacente ao bloco de auditoria (coesão de compliance)"

# Metrics
duration: ~11min
completed: 2026-06-15
---

# Phase 12 Plan 07: Monitoramento de conformidade LGPD (HU-102) Summary

**`LgpdMonitorService` agrega três fontes REAIS minimizadas — consentimentos (aceite da versão vigente do termo via `LegalTerm::current('lgpd')` × `LegalTermAcceptance`), retenção (`retencao.access_logs.dias` + data/quantidade do último pruning lido da trilha; decisões FORA do pruning) e acessos a dado pessoal (contagem/série de `activity_log` com `personal_data=true`) — exposto pelo `Gestao\LgpdMonitorController` gated por `monitorar-lgpd`, auditado (`personalData: true`) e minimizado; e a marca `personal_data=true` passa a ser gravada de forma ADITIVA nos call sites REAIS de leitura de PII de terceiro (detalhe de processo, histórico de acessos de outra conta, gestão de usuários), sem regressão.**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-06-15T09:07:26Z
- **Completed:** 2026-06-15T09:18:39Z
- **Tasks:** 2 (TDD estrito RED→GREEN em cada)
- **Files:** 4 criados, 4 modificados

## Accomplishments

- **Marcação real de PII (base de medição do painel):** `personalData: true` acrescentado às chamadas `AuditService::log` JÁ existentes em `ProcessoController::show` (`consulta-processo`), `AccessHistoryController` (`consulta-acessos` de terceiro) e `UserManagementController` (`papel-alterado`, `usuario-inativado`, `usuario-reativado`). Mudança estritamente aditiva — só o parâmetro, sem tocar a prop `explicacao` da 12-05, gates ou demais auditoria.
- **Painel LGPD real e minimizado:** `LgpdMonitorService` com 3 agregações sobre dado real; `LgpdMonitorController@index` monta as seções, audita a própria consulta (`personalData: true`) e renderiza `gestao/lgpd/index` (métricas, nunca PII crua). Pendência DPO de eliminação/anonimização registrada honesta no payload.
- **Rota única da Wave 3:** `gestao.lgpd.index` (GET `gestao/lgpd`) sob `permission:monitorar-lgpd`; o 403 reusa o ponto único auditado (bootstrap/app.php). ZERO dependência/parâmetro/permissão novos.

## API do LgpdMonitorService (contrato para 12-10/12-12)

```php
LgpdMonitorService::consentimentos(): array
// { sem_termo_publicado, versao_vigente, publicado_em, total_usuarios,
//   aceitaram_vigente, pendentes_reaceite, percentual_aceite }

LgpdMonitorService::retencao(): array
// { access_logs_dias, ultimo_pruning_em, ultimo_pruning_removidos,
//   decisoes_fora_do_pruning }  // decisoes_fora_do_pruning sempre true (compliance)

LgpdMonitorService::acessosDadoPessoal(): array
// { janela_dias, desde, total, por_evento: [{ log_name, event, total }] }
// conta SÓ activity_log.personal_data=true na janela (ui.dashboard.acessos_janela_dias, default 7)
```

- **consentimentos:** % de aceite da **versão vigente** do termo (`LegalTerm::current('lgpd')`); ao publicar uma nova versão, `aceitaram_vigente` zera e todos viram `pendentes_reaceite` (re-aceite). Sem termo publicado: `sem_termo_publicado=true`, métricas zeradas (gate desarmado — honesto).
- **retencao:** `access_logs_dias` de `Settings::get('retencao.access_logs.dias', 365)`; `ultimo_pruning_em`/`ultimo_pruning_removidos` do último `activity_log` com `log_name='retencao'`/`event='pruning-access-logs'` (lido via `getProperty('removidos')`); `null` honesto quando nunca houve pruning.
- **acessosDadoPessoal:** total + série por ação (`log_name`+`event`), contando apenas `personal_data=true` na janela parametrizada — métrica, sem despejar PII.

## Call sites marcados personal_data=true

| Controller | Evento auditado | Natureza |
|---|---|---|
| `ProcessoController::show` | `consulta-processo` (`analise`) | Detalhe do processo de um cidadão (dados da empresa/requerente) |
| `AccessHistoryController::__invoke` | `consulta-acessos` (`acessos`) | Histórico de acessos de OUTRA conta |
| `UserManagementController::updateRole` | `papel-alterado` (`usuarios`) | Gestão da conta de um terceiro |
| `UserManagementController::toggleActivation` | `usuario-inativado` / `usuario-reativado` (`usuarios`) | Gestão da conta de um terceiro |

Não marcado (honesto, sem excesso): `UserManagementController::index` — a listagem expõe nome/e-mail/CPF mascarado (PII não sensível) e NÃO tem chamada de auditoria existente; marcá-la exigiria criar auditoria nova (fora do escopo aditivo).

## Rota e props do Inertia (contrato para 12-10)

- **Rota:** `GET gestao/lgpd` → `gestao.lgpd.index` → `Gestao\LgpdMonitorController@index`, sob `permission:monitorar-lgpd` (dentro do grupo `auth:gestao` + `permission:acessar-gestao` + `lgpd.accepted`).
- **Componente:** `gestao/lgpd/index`.
- **Props:** `consentimentos`, `retencao`, `acessosDadoPessoal` (shapes acima) e `direitosTitular` = `{ status: 'pendente-dpo', descricao }` (bloco informativo honesto — sem rito automatizado).

## Task Commits

1. **Task 1: marca personal_data nos call sites reais de leitura de PII** — `c4e7f68` (feat, TDD)
2. **Task 2: painel de monitoramento LGPD (service + controller + rota)** — `8a4d508` (feat, TDD)

_Execução paralela com 12-08 (Wave 3): os commits do irmão aparecem intercalados no histórico (`fc9572c`, `713e3ca`); os meus estão íntegros._

## Files Created/Modified

- `app/Services/Lgpd/LgpdMonitorService.php` — 3 agregações reais minimizadas (consentimentos/retencao/acessosDadoPessoal).
- `app/Http/Controllers/Gestao/LgpdMonitorController.php` — painel gated/auditado/minimizado + pendência DPO.
- `app/Http/Controllers/Gestao/ProcessoController.php` — `personalData: true` no `consulta-processo` (aditivo; prop `explicacao` da 12-05 intacta).
- `app/Http/Controllers/Gestao/AccessHistoryController.php` — `personalData: true` no `consulta-acessos`.
- `app/Http/Controllers/Gestao/UserManagementController.php` — `personalData: true` em `papel-alterado`/`usuario-inativado`/`usuario-reativado`.
- `routes/gestao.php` — import + bloco `gestao.lgpd.index` (único route-owner da Wave 3).
- `tests/Feature/Lgpd/PersonalDataMarkingTest.php` — 4 testes (show/acessos/papel/inativação marcam personal_data).
- `tests/Feature/Lgpd/LgpdMonitorTest.php` — 7 testes (consentimentos versão vigente + sem termo, acessos na janela, retenção + pruning, retenção sem pruning, painel auditado, 403 auditado).

## Decisions Made

- Marcação `personal_data` SÓ nos pontos REAIS de leitura/gestão de PII de terceiro; listagem de usuários não marcada (PII não sensível + sem audit existente) — honesto, sem excesso e estritamente aditivo.
- Janela de acessos reusa `ui.dashboard.acessos_janela_dias` (default 7) e retenção reusa `retencao.access_logs.dias` (default 365) — ZERO parâmetro novo (12-03 é dono do catálogo).
- `decisoes_fora_do_pruning=true` comunica explicitamente que `viability_decisions` são preservadas (compliance); só `access_logs` é prunável.
- Direitos do titular (art. 18) registrados como `pendente-dpo` no payload — pendência externa real, nunca rito simulado.

## Deviations from Plan

None - plano executado exatamente como escrito. Escopo respeitado: apenas os 8 arquivos de `files_modified`, mudança de marcação estritamente aditiva, sem dependência/parâmetro/permissão novos, único editor de `routes/gestao.php` na Wave 3.

## Issues Encountered

- **Suíte completa não executada em isolamento (paralelismo da Wave 3):** o irmão 12-08 edita `AppServiceProvider` e cria `app/Services/Abuso/Detectors/*` + `tests/Feature/Abuso/*` em andamento no mesmo working tree; rodar a suíte inteira agora incluiria arquivos em construção do irmão (mesmo gotcha documentado por 12-01/12-05). Mitigação: validei meu escopo via filtros + anti-regressão ampla dos domínios adjacentes (auditoria/LGPD/permissões/seeders), que não inclui o diretório `Abuso` do irmão. Pint escopado aos meus arquivos e `git add` por pathspec (nunca `-A`/`.`).

## Verificação (evidência fresca)

- **RED Task 1:** `php artisan test --compact --filter=PersonalDataMarkingTest` → **4 failed** (`personal_data=0` onde se esperava `true`) — RED pelo motivo certo (auditoria existe, marca ausente).
- **GREEN Task 1 + anti-regressão:** `--filter="PersonalDataMarkingTest|Processo|AccessHistory|UserManagement"` → **78 passed, 422 assertions**.
- **RED Task 2:** `--filter=LgpdMonitorTest` → **5 errors** (classe `LgpdMonitorService` inexistente) + **2 failed** (rota 404) — RED pelo motivo certo.
- **GREEN Task 2:** `--filter=LgpdMonitorTest` → **7 passed, 38 assertions**.
- **Verify combinado do plano:** `--filter="PersonalDataMarkingTest|LgpdMonitorTest"` → **11 passed, 46 assertions**.
- **Rota:** `php artisan route:list --path=gestao/lgpd` → `GET|HEAD gestao/lgpd … gestao.lgpd.index … Gestao\LgpdMonitorController`.
- **Anti-regressão fresca (suítes tocadas + novas):** `--filter="PersonalDataMarkingTest|LgpdMonitorTest|Processo|AccessHistory|UserManagement"` → **86 passed, 461 assertions**.
- **Anti-regressão ampla (domínios adjacentes):** `--filter="Auditoria|Lgpd|ParameterSeeder|DatabaseSeeder|RolesAndPermissions|ManageRoles"` → **139 passed, 1141 assertions** (auditoria/LGPD intactos; baseline de 85 parâmetros / 27 permissões inalterada — confirma ZERO parâmetro/permissão novo).
- **Pint** (escopado aos 8 arquivos do plano): **passed**. **Lints:** 0 erros.

## Next Phase Readiness

- **12-10 (página do painel LGPD):** consome `gestao/lgpd/index` com as props `consentimentos`/`retencao`/`acessosDadoPessoal`/`direitosTitular` (shapes documentados acima). Estados a renderizar honestamente: `sem_termo_publicado=true` (sem termo), `ultimo_pruning_em=null` (nunca podado), `direitosTitular.status='pendente-dpo'` e a métrica de acessos como série/contagem (nunca PII).
- **12-12 (smoke/verificação):** rota `gestao.lgpd.index` sob `monitorar-lgpd` (admin), auditada com `personal_data` e com 403 auditado para quem não tem a permissão.
- Sem blockers. Sem configuração externa. Pendência registrada (não bloqueante deste plano): rito de eliminação/anonimização do titular (DPO/SEDUR).

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
