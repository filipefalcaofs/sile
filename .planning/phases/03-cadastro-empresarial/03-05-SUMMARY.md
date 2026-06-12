---
phase: 03-cadastro-empresarial
plan: 05
subsystem: portal
tags: [companies, links, policy, immutable, anti-lockout, audit, laravel, inertia, tdd]

requires:
  - phase: 03-cadastro-empresarial
    plan: 04
    provides: CompanyPolicy view/update/manageCnaes/endLink, CompanyController index/create/store, ordem de rotas (literais antes de empresas/{company})
  - phase: 01-fundacao
    provides: CurrentRepresentation/ResolveRepresentation ([01-07]), HasAuditoria/AuditService, render callback global do 403 (CA-04), padrao anti-lockout em FormRequest::after ([02-05])
provides:
  - CompanyController show (props completas - contrato da tela 03-08) e update (CNPJ imutavel)
  - UpdateCompanyRequest (regras do store SEM cnpj - imutavel por omissao)
  - CompanyLinkController destroy (encerramento do proprio vinculo, nunca delete fisico)
  - EndCompanyLinkRequest (after() com protecao do ultimo responsavel ativo)
  - Rotas portal.empresas.show/update e portal.empresas.vinculo.destroy
  - Shape das props de show (contrato direto da pagina de detalhe 03-08)
affects: [03-08-portal-detalhe (consome props de show/abilities), 03-06-company-cnaes (Gate manageCnaes ja exposto em abilities)]

tech-stack:
  added: []
  patterns:
    - "Atributo imutavel por OMISSAO nas regras do FormRequest: validated() nunca contem o campo, o update o ignora (padrao CPF [01-08])"
    - "Encerramento de vinculo via ended_at/ended_reason - NUNCA delete fisico (historico preservado)"
    - "Protecao anti-orfao no FormRequest::after() contando apenas o papel responsavel (precedente anti-lockout [02-05])"
    - "Evento de negocio explicito (AuditService->log) coexiste com o 'updated' automatico do HasAuditoria"

key-files:
  created:
    - app/Http/Requests/Portal/UpdateCompanyRequest.php
    - app/Http/Controllers/Portal/CompanyLinkController.php
    - app/Http/Requests/Portal/EndCompanyLinkRequest.php
    - tests/Feature/Companies/CompanyUpdateTest.php
    - tests/Feature/Companies/EndCompanyLinkTest.php
  modified:
    - app/Http/Controllers/Portal/CompanyController.php
    - routes/portal.php
    - tests/Feature/Companies/RedesimImportTest.php

key-decisions:
  - "CNPJ imutavel por OMISSAO: o UpdateCompanyRequest simplesmente nao inclui cnpj nas regras; validated() nunca o contem e o update o ignora (sem readonly explicito, padrao CPF [01-08])"
  - "show usa props sem ->component() (a pagina portal/empresas/detalhe so nasce no 03-08); asserts via has()/where() sobre as props (decisao herdada do 03-04)"
  - "A protecao do ultimo responsavel conta APENAS o papel responsavel: um procurador unico encerra normalmente se ha responsavel ativo"
  - "Auditoria do encerramento e um evento de negocio explicito 'encerramento-vinculo' (AuditService), independente do 'updated' tecnico do HasAuditoria do CompanyUser ([02-05])"

requirements-completed: [HU-024, HU-028]

duration: 9min
completed: 2026-06-12
---

# Phase 3 Plan 05: Atualizar Empresa e Encerrar Vínculo (HU-024/HU-028) Summary

**Backend de HU-024 (atualizar dados empresariais com CNPJ imutável e auditoria de diff) e HU-028 (encerrar o próprio vínculo com proteção do último responsável ativo e histórico preservado): `show` com props completas para a tela de detalhe (03-08), `update` autorizado por policy com CNPJ imutável por omissão nas regras, e `CompanyLinkController::destroy` que encerra via `ended_at`/`ended_reason` (nunca delete físico), auditado com evento de negócio próprio.**

## Performance

- **Duration:** ~9 min
- **Completed:** 2026-06-12
- **Tasks:** 2
- **Files modified:** 7 (5 criados, 2 modificados) + 1 ajuste de escopo

## Accomplishments

### HU-024 — Atualizar dados empresariais
- `CompanyController::show(Request, Company)`: `Gate::authorize('view', $company)` (aceita vínculo ativo OU encerrado — histórico visível), eager loading anti-N+1 (`cnaes` com pivot + `links.user`), props completas para a página de detalhe (03-08).
- `CompanyController::update(UpdateCompanyRequest, Company)`: `Gate::authorize('update', $company)` (exige vínculo ATIVO); `$company->update($request->validated())` → `back()->with('status', ...)`.
- **CNPJ imutável por omissão:** `UpdateCompanyRequest` reaproveita as regras do `StoreCompanyRequest` EXCETO `cnpj` — o campo não entra nas regras, logo `validated()` nunca o contém e o valor enviado é silenciosamente ignorado (padrão CPF [01-08]). Telefone/CEP normalizados em `prepareForValidation`.
- Auditoria `updated` com `attribute_changes` é automática (HasAuditoria, CA-02) — assert espelha exatamente o do `CnaeCrudTest`.
- Não vinculado → 403 auditado globalmente (CA-04, render callback de `AccessDeniedHttpException` no `bootstrap/app.php`, sem código novo).

### HU-028 — Encerrar vínculo
- `CompanyLinkController::destroy(EndCompanyLinkRequest, Company)`: localiza o vínculo ATIVO do usuário efetivo (`firstOrFail`), `update(['ended_at' => now(), 'ended_reason' => ...])` — **nunca delete físico**, histórico preservado.
- `EndCompanyLinkRequest::after()`: proteção do último responsável ativo (CA-03) — bloqueia com `A empresa não pode ficar sem responsável ativo.` contando APENAS o papel `responsavel`; um procurador único encerra normalmente.
- Auditoria de negócio explícita `app(AuditService::class)->log('empresas', 'encerramento-vinculo', ...)` com `empresa_id`, `vinculo_id`, `motivo` (coexiste com o `updated` técnico do HasAuditoria, padrão [02-05]).
- Após encerrar, a empresa permanece na listagem com `link.active === false` (histórico, HU-027/HU-028).

## Shape final das props de `show` (contrato da tela 03-08)

```jsonc
{
  "company": {
    "id": 1,
    "legal_name": "...", "trade_name": "..." | null,
    "formatted_cnpj": "00.000.000/0001-91",
    "legal_nature_code": "2062", "legal_nature": "...",
    "size_code": "01", "size": "...",
    "street": "...", "number": "...", "complement": null,
    "neighborhood": "...", "city": "...", "state": "BA", "zip_code": "40020000",
    "email": "...", "phone": "...",
    "source": { "value": "manual", "label": "Cadastro manual" },
    "redesim_protocol": null,
    "redesim_synced_at": "2026-06-12 10:00:00" | null
  },
  "cnaes": {
    "primary": { "id": 1, "formatted_code": "5611-2/01", "description": "..." } | null,
    "secondaries": [ { "id": 2, "formatted_code": "...", "description": "..." } ]
  },
  "links": [
    {
      "id": 1,
      "user_name": "Fulano",
      "role_label": "Responsável",
      "started_at": "2026-06-12",
      "ended_at": null,
      "ended_reason": null,
      "is_current_user": true
    }
  ],
  "abilities": { "update": true, "manageCnaes": true, "endLink": true }
}
```

`links` é ordenado por `started_at` desc; `is_current_user` compara com o usuário EFETIVO (representado em representação ativa).

## Regra do último responsável ativo

`EndCompanyLinkRequest::after()` adiciona erro em `vinculo` quando o vínculo ativo do usuário efetivo é `responsavel` E não existe outro `responsavel` ativo na empresa (`activeLinks()->where('role', Responsavel)->whereKeyNot($link->id)->exists()` é falso). Procuradores não contam para a proteção.

## Evento de auditoria do encerramento

- `log_name`: `empresas`
- `event`: `encerramento-vinculo`
- `result`: `sucesso`
- `properties`: `{ empresa_id, vinculo_id, motivo }`
- `subject`: a própria `Company`

## Task Commits

1. **Task 1: show + update com CNPJ imutável (TDD, HU-024)** - `bc09c82` (feat)
2. **Task 2: encerramento de vínculo com proteção do último responsável (TDD, HU-028)** - `7706ee7` (feat)

_TDD estrito: RED verificado em cada task (CompanyUpdateTest 6/7 falhando, EndCompanyLinkTest 8/8 falhando antes da implementação) → GREEN (7/7 e 8/8) → REFACTOR (pint)._

## Files Created/Modified
- `app/Http/Requests/Portal/UpdateCompanyRequest.php` - regras do store SEM cnpj (imutável por omissão)
- `app/Http/Controllers/Portal/CompanyController.php` - métodos show (props completas) e update
- `app/Http/Controllers/Portal/CompanyLinkController.php` - destroy (encerramento, nunca delete físico)
- `app/Http/Requests/Portal/EndCompanyLinkRequest.php` - after() com proteção do último responsável
- `routes/portal.php` - rotas empresas.show/update/vinculo.destroy (DEPOIS das literais)
- `tests/Feature/Companies/CompanyUpdateTest.php` - 7 testes HU-024
- `tests/Feature/Companies/EndCompanyLinkTest.php` - 8 testes HU-028
- `tests/Feature/Companies/RedesimImportTest.php` - ajuste de escopo (1 assert)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Assert do RedesimImportTest colidia com a nova rota {company}**
- **Found during:** Task 2 (verificação da suíte completa de Companies)
- **Issue:** `test_nao_existe_rota_publica_de_import` fazia `POST /portal/empresas/importar-redesim` esperando 404 (`assertNotFound`). Com a rota nova `empresas/{company}` (GET/PUT) + `empresas/{company}/vinculo` (DELETE), o path `importar-redesim` passou a casar com o binding `{company}` para outros verbos, então o POST retorna 405 (verbo não permitido) em vez de 404.
- **Fix:** o invariante real do teste (não existe endpoint público de import — só o comando artisan) continua válido. Ajustei o assert para aceitar 404 OU 405 (`assertContains($status, [404, 405])`) — nenhuma resposta de sucesso é roteável.
- **Files modified:** tests/Feature/Companies/RedesimImportTest.php
- **Verification:** suíte Companies 65/65 verde.
- **Committed in:** 7706ee7 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 ajuste de assert de teste pré-existente, diretamente causado pela rota nova deste plano). Sem scope creep.

## Issues Encountered
- Nenhum além da deviation acima.

## User Setup Required
None - nenhuma configuração de serviço externo. Rotas sob o gate `lgpd.accepted` + `ResolveRepresentation` já existentes.

## Next Phase Readiness
- **03-08 (tela de detalhe):** o shape das props de `show` é o contrato direto da página `portal/empresas/detalhe`; adicionar a verificação `->component()` quando a página existir.
- **03-06 (CNAEs da empresa):** `Gate manageCnaes` já exposto em `abilities.manageCnaes` do detalhe.

## Verification
- `php artisan test --compact tests/Feature/Companies` — 65 testes verdes.
- `php artisan test --compact --filter=CompanyUpdateTest` — 7 verdes; `--filter=EndCompanyLinkTest` — 8 verdes.
- `grep "'cnpj'"` ausente nas rules do UpdateCompanyRequest (imutável por omissão).
- `grep "delete()"` ausente no CompanyLinkController (sem delete físico).
- `grep "encerramento-vinculo"` presente no CompanyLinkController.
- `php artisan route:list` — empresas/{company} (show/update) e empresas/{company}/vinculo (destroy) DEPOIS das literais.
- `vendor/bin/pint --dirty` — sem pendências.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- Os 5 arquivos criados e 2 modificados existem no disco.
- Os 2 commits de tarefa (`bc09c82`, `7706ee7`) existem no histórico.
