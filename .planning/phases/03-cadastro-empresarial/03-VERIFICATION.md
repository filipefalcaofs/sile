---
phase: 03-cadastro-empresarial
verified: 2026-06-13T14:40:00Z
status: passed
score: 4/4 success criteria verificados · 8/8 HUs com evidência
tests:
  group: tests/Feature/Companies
  command: php artisan test --compact tests/Feature/Companies
  result: 83 passed, 392 assertions, 0 falhas
re_verification:
  previous: none (verificação inicial)
human_verification:
  - test: "Reconfirmar chamada viva ao provider de CNPJ (BrasilAPI)"
    expected: "Buscar CNPJ no cadastro preenche dados reais da RFB; consulta auditada em activity_log"
    why_human: "Integração externa de rede; testes automatizados são herméticos (Http::fake). Evidência viva já registrada no 03-09-SUMMARY (Playwright + lookup real), não re-executada nesta verificação."
  - test: "Validação visual mobile + acessibilidade (WCAG/eMAG) das telas do portal"
    expected: "Telas index/cadastrar/detalhe responsivas e acessíveis em viewport mobile"
    why_human: "Critério transversal de pronto da fase; não verificável por grep/teste estrutural."
notes:
  - "Success Criteria 2: o TRANSPORTE real REDESIM é a HU-103 (Fase 13), explicitamente fora do escopo desta fase pelo próprio critério. A lógica de importação está completa atrás de contrato e testada — não é gap."
  - "Divergência de tracking (não bloqueia o goal, não editado por instrução): ROADMAP.md marca os planos 03-06..03-09 como [ ] e REQUIREMENTS.md marca HU-025/HU-026 como 'Pending'. O código real e os SUMMARYs comprovam conclusão. Recomenda-se atualizar o tracking."
---

# Fase 3: Cadastro Empresarial — Relatório de Verificação

**Goal da fase:** Empresas são cadastradas e mantidas com seus CNAEs e vínculos com usuários, prontas para sustentar solicitações de viabilidade.
**Verificado em:** 2026-06-13
**Status:** passed
**Re-verificação:** Não — verificação inicial (nenhum VERIFICATION.md anterior)

## Conquista do Goal

Verificação goal-backward: partiu-se dos 4 Success Criteria do ROADMAP, confirmando que cada um é VERDADE no código real (controllers, services, models, FormRequests, rotas, páginas React) e coberto por feature tests passando — não confiando apenas nos SUMMARYs.

### Verdades Observáveis (Success Criteria)

| # | Success Criteria | Status | Evidência |
|---|---|---|---|
| 1 | Requerente consulta dados de um CNPJ e cadastra a empresa com seus dados | ✓ VERIFICADO | `CnpjLookupController` + provider real `BrasilApiCnpjLookup` (HTTP real, base_url parametrizada, cache 24h só de sucesso); `CompanyController::store` transacional (empresa + vínculo responsável). Telas `cadastrar.tsx` (lookup vivo via `useHttp`) e fluxo de submit real. Testes: `CnpjLookupTest` (12), `CompanyRegistrationTest` (9) |
| 2 | Dados no formato REDESIM são importados e criam/atualizam o cadastro — lógica real atrás de contrato (conexão = HU-103/Fase 13) | ✓ VERIFICADO | `RedesimImportService` (upsert por CNPJ, validação por item com transação atômica, sync de CNAEs, relatório auditado `redesim-import-v1`); comando `redesim:importar`. Transporte real é Fase 13, por design do critério. Testes: `RedesimImportTest` (15) |
| 3 | Empresa possui CNAE principal e CNAEs secundários vinculados a partir da tabela oficial | ✓ VERIFICADO | `CompanyCnaeService` (único ponto de escrita do pivot; `setPrimary` demote→promote, `syncSecondaries` conjunto exato); endpoints PUT `cnae-principal`/`cnaes-secundarios`; `CnaeSearchController` (só ativos, máx. 20); `cnae-picker.tsx` (busca incremental real). Testes: `PrimaryCnaeTest` (7), `SecondaryCnaesTest` (10) |
| 4 | Usuário consulta empresas vinculadas, atualiza dados e encerra vínculo, com auditoria | ✓ VERIFICADO | `CompanyController::index` (server-driven, escopo por vínculo do usuário efetivo) + `update` (CNPJ imutável); `CompanyLinkController::destroy` (encerra via `ended_at`/`ended_reason`, nunca delete físico) + proteção do último responsável em `EndCompanyLinkRequest::after()`. Auditoria via `HasAuditoria` + eventos explícitos. Testes: `MyCompaniesTest` (9), `CompanyUpdateTest` (7), `EndCompanyLinkTest` (8) |

**Score:** 4/4 Success Criteria verificados.

### Cobertura por Requirement (HU-021..HU-028)

| HU | Descrição | Artefato principal | Teste (nº) | Status |
|---|---|---|---|---|
| HU-021 | Consultar dados do CNPJ | `CnpjLookupController`, `BrasilApiCnpjLookup`, `CnpjData`, `cadastrar.tsx` | `CnpjLookupTest` (12) | ✓ SATISFEITO |
| HU-022 | Importar dados da REDESIM | `RedesimImportService`, `ImportRedesimCommand`, `redesim-exemplo.json` | `RedesimImportTest` (15) | ✓ SATISFEITO (transporte = Fase 13) |
| HU-023 | Cadastrar empresa | `CompanyController::store`, `StoreCompanyRequest`, `cadastrar.tsx` | `CompanyRegistrationTest` (9) | ✓ SATISFEITO |
| HU-024 | Atualizar dados empresariais | `CompanyController::show/update`, `UpdateCompanyRequest`, `detalhe.tsx` | `CompanyUpdateTest` (7) | ✓ SATISFEITO |
| HU-025 | Vincular CNAE principal | `CompanyCnaeService::setPrimary`, `UpdatePrimaryCnaeRequest`, `CompanyCnaeController` | `PrimaryCnaeTest` (7) | ✓ SATISFEITO |
| HU-026 | Vincular CNAEs secundários | `CompanyCnaeService::syncSecondaries`, `UpdateSecondaryCnaesRequest`, `CnaeSearchController` | `SecondaryCnaesTest` (10) | ✓ SATISFEITO |
| HU-027 | Consultar empresas vinculadas | `CompanyController::index`, `index.tsx` (DataTable server-driven) | `MyCompaniesTest` (9) | ✓ SATISFEITO |
| HU-028 | Encerrar vínculo empresarial | `CompanyLinkController::destroy`, `EndCompanyLinkRequest` | `EndCompanyLinkTest` (8) | ✓ SATISFEITO |

Fundação transversal: `ValidCnpj` (rule, `ValidCnpjTest` unit), schema `companies`/`company_user`/`company_cnae`, models com `HasAuditoria`, enums e factories (`CompanyFoundationTest`, 6).

## Artefatos Verificados (três níveis)

Todos os artefatos-chave: EXISTEM, são SUBSTANTIVOS (lógica real, sem stubs) e estão WIRED.

| Artefato | Existe | Substantivo | Wired | Status |
|---|---|---|---|---|
| `app/Services/Cnpj/BrasilApiCnpjLookup.php` | ✓ | ✓ HTTP real, cache só sucesso | ✓ bind em `AppServiceProvider` | ✓ |
| `app/Services/Cnpj/CnpjLookup.php` (contrato) | ✓ | ✓ interface | ✓ injetado no controller | ✓ |
| `app/Http/Controllers/Portal/CnpjLookupController.php` | ✓ | ✓ toggle + auditoria 4 saídas | ✓ rota `consultar-cnpj` | ✓ |
| `app/Services/RedesimImportService.php` | ✓ | ✓ upsert/validação/sync/auditoria | ✓ comando + `CompanySeeder` | ✓ |
| `app/Console/Commands/ImportRedesimCommand.php` | ✓ | ✓ relatório pt-BR + exit codes | ✓ `redesim:importar` | ✓ |
| `app/Http/Controllers/Portal/CompanyController.php` | ✓ | ✓ index/create/store/show/update | ✓ 5 rotas portal.empresas.* | ✓ |
| `app/Policies/CompanyPolicy.php` | ✓ | ✓ view/update/manageCnaes/endLink | ✓ Gate nos controllers | ✓ |
| `app/Http/Controllers/Portal/CompanyLinkController.php` | ✓ | ✓ encerra sem delete físico | ✓ rota vinculo.destroy | ✓ |
| `app/Services/CompanyCnaeService.php` | ✓ | ✓ setPrimary/syncSecondaries transacional | ✓ injetado no controller | ✓ |
| `app/Http/Controllers/Portal/CnaeSearchController.php` | ✓ | ✓ só ativos, máx. 20 | ✓ rota portal.cnaes.search | ✓ |
| `resources/js/pages/portal/empresas/index.tsx` | ✓ | ✓ DataTable + EmptyState bifurcado | ✓ render `portal/empresas/index` | ✓ |
| `resources/js/pages/portal/empresas/cadastrar.tsx` | ✓ | ✓ lookup vivo + degradação | ✓ POST consultar-cnpj + store | ✓ |
| `resources/js/pages/portal/empresas/detalhe.tsx` | ✓ | ✓ edição/CNAEs/encerramento | ✓ put/delete endpoints reais | ✓ |
| `resources/js/pages/portal/empresas/cnae-picker.tsx` | ✓ | ✓ busca incremental real | ✓ GET /portal/cnaes | ✓ |
| `database/seeders/CompanySeeder.php` | ✓ | ✓ usa import REAL (carga dev) | ✓ chamado após CnaeSeeder | ✓ |

## Verificação de Key Links (wiring)

| De | Para | Via | Status |
|---|---|---|---|
| `AppServiceProvider` | `BrasilApiCnpjLookup` | `bind(CnpjLookup::class, BrasilApiCnpjLookup::class)` | ✓ WIRED |
| `BrasilApiCnpjLookup` | parâmetro `integrations.cnpj_lookup.base_url` | `Settings::get(...)` com fallback config | ✓ WIRED |
| `CnpjLookupController` | `activity_log` | `AuditService->log('empresas', 'consulta-cnpj', ...)` | ✓ WIRED |
| `cadastrar.tsx` | `POST /portal/empresas/consultar-cnpj` | `useHttp().post(...)` | ✓ WIRED |
| `cadastrar.tsx` | `POST /portal/empresas` | `useForm().post(...)` | ✓ WIRED |
| `detalhe.tsx` | `PUT cnae-principal` / `PUT cnaes-secundarios` / `DELETE vinculo` | `router.put/delete(...)` | ✓ WIRED |
| `cnae-picker.tsx` | `GET /portal/cnaes` | `useHttp().get(...)` | ✓ WIRED |
| `CompanyCnaeController` | pivot `company_cnae` | delega 100% ao `CompanyCnaeService` (zero sync/attach no controller) | ✓ WIRED |
| `CompanySeeder` | `RedesimImportService` | `import(redesim-exemplo.json)` (lógica de produção) | ✓ WIRED |
| `DatabaseSeeder` | `CompanySeeder` | `call()` após `CnaeSeeder` | ✓ WIRED |

Eventos de auditoria assertados nos testes: `consulta-cnpj`, `importacao-redesim`, `cnae-principal`, `cnaes-secundarios`, `encerramento-vinculo` — confirmando que a trilha (RN-002) é exercida de ponta a ponta.

## Rotas Confirmadas (`route:list --path=portal`)

```
GET    portal/cnaes                                 portal.cnaes.search
GET    portal/empresas                              portal.empresas.index
POST   portal/empresas                              portal.empresas.store
GET    portal/empresas/cadastrar                    portal.empresas.create
POST   portal/empresas/consultar-cnpj               portal.empresas.consultar-cnpj
GET    portal/empresas/{company}                    portal.empresas.show
PUT    portal/empresas/{company}                    portal.empresas.update
PUT    portal/empresas/{company}/cnae-principal     portal.empresas.cnae-principal
PUT    portal/empresas/{company}/cnaes-secundarios  portal.empresas.cnaes-secundarios
DELETE portal/empresas/{company}/vinculo            portal.empresas.vinculo.destroy
```

## Evidência de Testes (fresca)

```
$ php artisan test --compact tests/Feature/Companies
{"tool":"phpunit","result":"passed","tests":83,"passed":83,"assertions":392}
```

Distribuição (soma = 83): CompanyFoundationTest 6 · CnpjLookupTest 12 · RedesimImportTest 15 · CompanyRegistrationTest 9 · CompanyUpdateTest 7 · PrimaryCnaeTest 7 · SecondaryCnaesTest 10 · MyCompaniesTest 9 · EndCompanyLinkTest 8.

## Anti-Patterns / Features de Fachada

Nenhum encontrado. Verificado:
- Telas consomem endpoints reais (sem resultado simulado); lookup só preenche campos efetivamente retornados pelo provider.
- Encerramento de vínculo nunca faz delete físico (`ended_at`/`ended_reason`).
- Escrita do pivot CNAE exclusivamente via service transacional (sem `sync()`/`attach()` solto no controller).
- Toggle `features.cnpj_lookup` OFF bloqueia ANTES de qualquer request HTTP (degradação comunicada, não falha silenciosa).
- Dependência externa (transporte REDESIM) explicitamente bloqueada/registrada para a Fase 13 — não há adaptador falso.

## Verificação Humana Recomendada (não bloqueante)

1. **Chamada viva ao provider de CNPJ (BrasilAPI):** integração externa de rede; os feature tests são herméticos (`Http::fake`). Já há evidência viva registrada no `03-09-SUMMARY` (Playwright com Itaú/Banco do Brasil + `lookup('00000000000191')` → "BANCO DO BRASIL SA"), porém não re-executada nesta verificação.
2. **Validação visual mobile + acessibilidade (WCAG/eMAG):** critério transversal de pronto; não verificável por inspeção estrutural.

## Observações

- **Success Criteria 2 (REDESIM):** o critério já delimita que a conexão com o integrador é a HU-103 (Fase 13). A fase entrega a lógica real de importação atrás de contrato (`RedesimImportService` + comando), completa e testada — portanto NÃO é gap desta fase.
- **Divergência de tracking (não editada, por instrução):** `ROADMAP.md` mantém os planos 03-06..03-09 como `[ ]` e `REQUIREMENTS.md` marca HU-025/HU-026 como "Pending". O código real, as rotas, as telas e os 83 testes verdes comprovam a conclusão. Recomenda-se sincronizar ROADMAP/REQUIREMENTS/STATE.

---

_Verificado: 2026-06-13_
_Verificador: gsd-verifier_
