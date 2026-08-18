---
phase: 03-cadastro-empresarial
plan: 06
subsystem: portal
tags: [companies, cnae, pivot, service, transaction, audit, search, json, laravel, tdd]

requires:
  - phase: 03-cadastro-empresarial
    plan: 04
    provides: CompanyPolicy manageCnaes (vinculo ATIVO do usuario efetivo), helpers de teste (portalUser/companyLinkedTo)
  - phase: 03-cadastro-empresarial
    plan: 05
    provides: rotas empresas/{company} DEPOIS das literais, abilities.manageCnaes no show
  - phase: 02-administracao-base
    provides: tabela cnaes oficial com active e formatted_code, padrao de busca [02-04], padrao de auditoria de conjunto antes/depois [02-06]
  - phase: 01-fundacao
    provides: AuditService (assinatura travada [01-02]), 403 auditado globalmente (CA-04)
provides:
  - CompanyCnaeService setPrimary/syncSecondaries transacionais — UNICO ponto de escrita do pivot company_cnae
  - PUT /portal/empresas/{company}/cnae-principal (portal.empresas.cnae-principal) com payload {cnae_id}
  - PUT /portal/empresas/{company}/cnaes-secundarios (portal.empresas.cnaes-secundarios) com payload {cnaes[]}
  - GET /portal/cnaes (portal.cnaes.search) — busca JSON da tabela oficial, so ativos, max 20
  - Eventos de auditoria 'cnae-principal' (cnae_anterior/cnae_novo) e 'cnaes-secundarios' (antes/depois) — insumo do motor de regras (Fases 5/6)
affects: [03-08-portal-detalhe (consome endpoints e busca no picker de CNAEs), 05-motor-louos (le auditoria de troca de CNAE), 06-classificacao-risco (CNAE principal/secundarios da empresa)]

tech-stack:
  added: []
  patterns:
    - "Escrita de pivot com invariante EXCLUSIVAMENTE via service em DB::transaction (demote->promote); controller so delega"
    - "Sync calculado preservando a linha do principal no payload (conjunto exato dos secundarios, padrao syncPermissions [02-06])"
    - "Endpoint JSON de busca para selects do portal: so registros ativos, limite tecnico, shape minimo (id/formatted_code/description)"
    - "Validacao 'present'+array permite remocao total; invariante de dominio (principal fora dos secundarios) no FormRequest::after"

key-files:
  created:
    - app/Services/CompanyCnaeService.php
    - app/Http/Controllers/Portal/CompanyCnaeController.php
    - app/Http/Controllers/Portal/CnaeSearchController.php
    - app/Http/Requests/Portal/UpdatePrimaryCnaeRequest.php
    - app/Http/Requests/Portal/UpdateSecondaryCnaesRequest.php
    - tests/Feature/Companies/PrimaryCnaeTest.php
    - tests/Feature/Companies/SecondaryCnaesTest.php
  modified:
    - routes/portal.php

key-decisions:
  - "Demote->promote no setPrimary: o antigo principal PERMANECE vinculado como secundario (linha do pivot atualizada, nunca removida) — troca de principal nao perde historico de vinculo"
  - "syncSecondaries monta payload exato incluindo o principal atual com is_primary=true: sync() remove apenas secundarios desmarcados, preservando o principal (Pitfall 7)"
  - "Limite de 20 itens da busca e constante tecnica (MAX_RESULTS) — refinamento vem da busca, nao de paginacao; nao e parametro de negocio"
  - "Selecao manual restrita a CNAEs ativos vale SO para os endpoints do portal; import REDESIM segue aceitando inativos com aviso (regras distintas preservadas, [03-03])"

requirements-completed: [HU-025, HU-026]

duration: 10min
completed: 2026-06-12
---

# Phase 3 Plan 06: Vínculo de CNAEs da Empresa (HU-025/HU-026) Summary

**CompanyCnaeService transacional como único ponto de escrita do pivot company_cnae (invariante "exatamente um principal" via demote→promote, secundários como conjunto exato preservando o principal), endpoints autorizados por manageCnaes com seleção restrita a CNAEs ativos, busca server-side GET /portal/cnaes (só ativos, máx. 20) e auditoria explícita antes/depois pronta para o motor de regras.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-06-12T19:40:31Z
- **Completed:** 2026-06-12T19:50:50Z
- **Tasks:** 2
- **Files modified:** 8 (7 criados, 1 modificado)

## Accomplishments

### Task 1 — CompanyCnaeService (HU-025/HU-026 núcleo)
- `setPrimary(Company, Cnae)`: em `DB::transaction`, captura o principal anterior, DEMOVE (`wherePivot('is_primary', true)->newPivotQuery()->update(['is_primary' => false])`) e só então PROMOVE (`syncWithoutDetaching([$cnae->id => ['is_primary' => true]])`) — nunca existem dois principais, nem por um instante (Pitfall 7). O antigo principal permanece vinculado como secundário.
- `syncSecondaries(Company, array $cnaeIds)`: em `DB::transaction`, monta o payload EXATO do `sync()` incluindo a linha do principal atual (`is_primary => true`) + os secundários marcados (`is_primary => false`) — remoção total permitida (payload só com o principal), principal sempre intacto.
- Auditoria explícita em ambos (relações não entram no diff do `HasAuditoria`).

### Task 2 — Endpoints e busca (HU-025/HU-026 CA-01..04)
- `CompanyCnaeController` (injeta o service): `updatePrimary` e `updateSecondaries` delegam TODA escrita ao service e respondem `back()->with('status', ...)`. Zero `sync()`/`attach()` no controller (anti-pattern bloqueado).
- `UpdatePrimaryCnaeRequest`: authorize via `manageCnaes` (vínculo ATIVO do usuário efetivo); `Rule::exists('cnaes', 'id')->where('active', true)` com mensagem própria — CNAE inativo/inexistente rejeitado (CA-03).
- `UpdateSecondaryCnaesRequest`: `present`+`array` (remoção total permitida), itens `integer`+`distinct`+exists ativo; `after()` bloqueia o principal atual no conjunto.
- `CnaeSearchController` (invokable): só `active = true`, padrão de busca [02-04] (branch de dígitos apenas quando o termo contém dígitos; `orWhereLike` sem case), `orderBy('code')->limit(20)`.

## Contratos dos endpoints

| Método | Path | Name | Payload | Resposta |
|---|---|---|---|---|
| PUT | `/portal/empresas/{company}/cnae-principal` | `portal.empresas.cnae-principal` | `{ "cnae_id": int }` | redirect back + flash `status: "CNAE principal definido com sucesso."` |
| PUT | `/portal/empresas/{company}/cnaes-secundarios` | `portal.empresas.cnaes-secundarios` | `{ "cnaes": int[] }` (`[]` remove todos) | redirect back + flash `status: "CNAEs secundários atualizados com sucesso."` |
| GET | `/portal/cnaes?search=termo` | `portal.cnaes.search` | query `search` opcional | JSON array (máx. 20) |

Mensagens de validação (pt-BR): `cnae_id.exists` → "O CNAE informado não está ativo na tabela oficial."; `cnaes.*.exists` → "Há CNAE inativo ou inexistente na seleção."; `cnaes.*.distinct` → "Há CNAE duplicado na seleção."; principal no conjunto → erro em `cnaes`: "O CNAE principal não pode ser incluído entre os secundários.". Não vinculado/vínculo encerrado → 403 auditado (`seguranca`/`acesso-negado`).

## Shape do JSON da busca

```json
[
  { "id": 1, "formatted_code": "5611-2/01", "description": "Restaurantes e similares" }
]
```

Somente CNAEs ativos; máximo 20 itens; ordenado por código; chaves exatas `id`/`formatted_code`/`description` (verificado com `assertExactJson`).

## Eventos de auditoria gravados

| Evento | log_name | properties | subject |
|---|---|---|---|
| `cnae-principal` | `empresas` | `{ empresa_id, cnae_anterior: code\|null, cnae_novo: code }` | Company |
| `cnaes-secundarios` | `empresas` | `{ empresa_id, antes: codes[], depois: codes[] }` | Company |

Ambos com `result: sucesso`, gravados DENTRO da transação do service — trocas de CNAE têm trilha completa antes/depois para o motor de regras (Fases 5/6).

## Task Commits

1. **Task 1: CompanyCnaeService transacional com auditoria antes/depois** - `fc88331` (feat)
2. **Task 2: endpoints de CNAE + busca server-side da tabela oficial** - `be500b1` (feat)

_TDD estrito: Task 1 RED 6/6 falhando ("Target class does not exist") → GREEN 6/6 → pint. Task 2 RED 11/11 novos falhando (404 nas rotas) → GREEN 17/17 → pint._

## Files Created/Modified
- `app/Services/CompanyCnaeService.php` - setPrimary/syncSecondaries transacionais; único ponto de escrita do pivot
- `app/Http/Controllers/Portal/CompanyCnaeController.php` - updatePrimary/updateSecondaries delegando ao service
- `app/Http/Controllers/Portal/CnaeSearchController.php` - busca JSON da tabela oficial (só ativos, máx. 20)
- `app/Http/Requests/Portal/UpdatePrimaryCnaeRequest.php` - authorize manageCnaes + exists ativo
- `app/Http/Requests/Portal/UpdateSecondaryCnaesRequest.php` - present/array/distinct/exists ativo + after() do principal
- `routes/portal.php` - 3 rotas novas no grupo lgpd+ResolveRepresentation
- `tests/Feature/Companies/PrimaryCnaeTest.php` - 7 testes (3 service + 4 HTTP)
- `tests/Feature/Companies/SecondaryCnaesTest.php` - 10 testes (3 service + 7 HTTP/busca)

## Decisions Made
- O antigo principal vira secundário na troca (linha do pivot preservada com `is_primary = false`) — manter o vínculo registra que a empresa já exerceu a atividade; remoção é decisão explícita via syncSecondaries.
- `after()` do UpdateSecondaryCnaesRequest retorna cedo quando a empresa ainda não tem principal (conjunto livre) — o bloqueio só faz sentido com principal definido.
- Limite da busca (20) como constante técnica `MAX_RESULTS`, não parâmetro do registry (precedente [02-02]: tamanhos de consulta interna não são parâmetro de negócio).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- MCP Laravel Boost (search-docs/database-schema) não estava carregado nesta sessão (apenas figma/portainer/render/maker-flow/sig-dashboard). Os padrões Eloquent usados (newPivotQuery, syncWithoutDetaching, sync com atributos de pivot) seguiram o código prescrito no 03-RESEARCH.md — validado pelos 17 testes verdes.

## User Setup Required
None - nenhuma configuração de serviço externo.

## Next Phase Readiness
- **03-07/03-08 (telas):** picker de CNAE pode consumir `GET /portal/cnaes` (debounce no front; shape pronto para options) e os dois endpoints PUT; flash `status` já é exibido pelos layouts.
- **Fases 5/6 (motores):** trilha `cnae-principal`/`cnaes-secundarios` com antes/depois disponível em `activity_log`.
- Grupo Companies: 82/82 testes verdes (65 anteriores + 17 deste plano); full-suite fica para o 03-09 como planejado.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- 7 arquivos criados e 1 modificado existem no disco.
- Commits `fc88331` e `be500b1` existem no histórico.
- `php artisan test --compact tests/Feature/Companies` → 82/82 verdes (evidência fresca).
- `vendor/bin/pint --dirty` → sem pendências.
