---
phase: 05-motor-louos
plan: 03
subsystem: motor
tags: [louos, motor, quadro7, enquadramento, rule-versions, auditoria, tdd, sqlite]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 01
    provides: "DTOs EnquadramentoInput/EnquadramentoResult (contrato + constantes de status), RuleDomain LouosQuadro7/10/11/11a, model LouosQuadro7Faixa, enum ResultadoViabilidade"
  - phase: 05-motor-louos
    plan: 02
    provides: "Quadro 7 vigente real (lei-9148-2016-quadro7): 40 faixas em 24 CNAEs derivadas da Lei 9.148/2016"
  - phase: 06-classificacao-risco
    provides: "RuleVersion (scopes vigente/naData/versao), AuditService, padrão de motor RiscoClassificationService"
provides:
  - "LouosEnquadramentoService::enquadrar(EnquadramentoInput): EnquadramentoResult — núcleo do motor LOUOS"
  - "Quadro 7 REAL: enquadramento por área (CNAE + área → grupo/subgrupo de uso) sobre as faixas vigentes"
  - "resolveVersion() em 3 modos (vigente / na data / versão específica sandbox HU-143)"
  - "Helpers de dimensão dimIdentificado/dimNaoEncontrado/dimIndisponivel (reutilizáveis pelo 05-04)"
  - "consolidar() provisório (Pendente honesto) e placeholders honestos de quadro10/11/11a"
affects: [05-04-motor, 05-05-motor, 05-09-golden-cases, 07-consulta-previa, 08-solicitacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Motor LOUOS espelhando RiscoClassificationService: DTO readonly + resolução de versão + dimensões com shape estável + auditoria RN-002"
    - "Helpers de dimensão genéricos (status/motivo/versao_regra + $dados) reutilizáveis por todos os Quadros — espelham TerritoryService::dim*"
    - "Degradação honesta: dimensão indisponivel/nao_encontrado força consolidado pendente, nunca veredito inventado"

key-files:
  created:
    - app/Services/Louos/LouosEnquadramentoService.php
    - tests/Feature/Louos/LouosEnquadramentoQuadro7Test.php
  modified: []

key-decisions:
  - "Dimensão quadro7 inclui faixa: {area_min, area_max} além de grupo/subgrupo/motivo/versao_regra (estende o shape sugerido no 05-01, conforme exigido pelo plano)"
  - "Helpers de dimensão usam o operador + $dados (precedente TerritoryService) para servir aos shapes distintos de cada Quadro sem duplicar status/motivo/versao_regra"
  - "Placeholders de quadro10/11/11a mantêm o contrato (permissao/condicionante_ref/condicoes = null) com status indisponivel — 05-04 substitui pela lógica real"
  - "consolidar() recebe as 4 dimensões já preparando a assinatura final, mas retorna Pendente fixo nesta etapa — 05-05 reescreve"

patterns-established:
  - "enquadrar() normaliza o CNAE para 7 dígitos (preg_replace), audita uma vez por execução com rulesVersion = versão do Quadro 7"

# Metrics
duration: 18min
completed: 2026-06-14
---

# Phase 5 Plan 03: Motor de Enquadramento do Quadro 7 Summary

**O núcleo do motor LOUOS (`LouosEnquadramentoService::enquadrar`) entrega a primeira dimensão real — o Quadro 7 (enquadramento por área): resolve a versão da regra em 3 modos, casa a faixa de área do CNAE sobre as faixas vigentes reais e devolve grupo/subgrupo de uso, auditando a execução com a versão aplicada. Sem fachada: CNAE sem faixa devolve nao_encontrado e, enquanto zona/via não são avaliadas, o consolidado fica pendente.**

## Performance

- **Duration:** ~18 min
- **Tasks:** 1 (TDD RED → GREEN → REFACTOR/pint)
- **Files:** 2 criados (service + teste)
- **Commits:** efc6e7d (feat) + docs deste SUMMARY

## Contrato entregue (insumo direto de 05-04 e 05-05)

### `enquadrar(EnquadramentoInput $input): EnquadramentoResult`
- Normaliza `cnaePrincipal` para 7 dígitos (`preg_replace('/\D/','')` — precedente do risco).
- Avalia a dimensão `quadro7` (real) e monta `quadro10`/`quadro11`/`quadro11a` como placeholders honestos (status `indisponivel`).
- Monta `versoes` (mapa `quadro7/quadro10/quadro11/quadro11a` → `versao_regra` de cada dimensão).
- Consolida via `consolidar()` (Pendente provisório) e **audita uma vez**: `log_name 'louos'`, `event 'enquadramento'`, `result 'sucesso'`, `rules_version` = versão do Quadro 7, `properties` = `{cnae, area, quadro7_status, resultado_consolidado, versoes}`.

### Shape final da dimensão `quadro7`
- **Identificado:** `{status: 'identificado', motivo: null, versao_regra, grupo, subgrupo, faixa: {area_min, area_max}}`.
- **Não encontrado (FA-02):** `{status: 'nao_encontrado', motivo, versao_regra, grupo: null, subgrupo: null, faixa: null}` — motivos: `'CNAE sem enquadramento parametrizado no Quadro 7 vigente'` (versão existe, CNAE não) ou `'Quadro 7 sem versão vigente'` (sem versão).
- **Casamento da faixa:** `area_min <= area AND (area_max IS NULL OR area_max >= area)` — limite inferior inclusivo, teto inclusivo, `area_max` nula = sem teto. `orderBy('area_min')` para determinismo (faixas não se sobrepõem — validado na carga do 05-02).

### `resolveVersion(RuleDomain $domain, EnquadramentoInput $input): ?RuleVersion` (3 modos)
1. `versoesOverride[$domain->value]` presente → `RuleVersion::versao($domain, $v)->first()` (sandbox HU-143).
2. senão `data` presente → `RuleVersion::naData($domain, $data)->first()` (reprodução por época).
3. senão → `RuleVersion::vigente($domain)->first()`.

### Helpers de dimensão (privados, reutilizáveis pelo 05-04)
- `dimIdentificado(?versao, dados)`, `dimNaoEncontrado(motivo, ?versao, dados)`, `dimIndisponivel(motivo, ?versao, dados)` — devolvem `{status, motivo, versao_regra} + $dados`, espelhando `TerritoryService::dim*`. O 05-04 reaproveita para Quadro 10 (`permissao`, `condicionante_ref`) e Quadro 11/11A (`condicoes`).

### Consolidado provisório (decisão honesta)
- `consolidar()` retorna `{resultado: 'pendente', fundamentacao: [], condicionantes: [], motivo: 'Enquadramento por área realizado; permissão por zona e condições pela via ainda não avaliadas'}`. A assinatura já recebe as 4 dimensões; **05-05 reescreve** com a lógica final dos Quadros.

## Evidência (verificação fresca)

- `php artisan test --compact --filter=LouosEnquadramentoQuadro7Test` → **8 passed** (32 assertions).
- `php artisan test --compact --filter=Louos` → **39 passed** (240 assertions; inclui 05-02 e o 05-06 do executor paralelo).
- `php artisan test --compact --exclude-group postgis` → **558 passed** (2.823 assertions), zero falhas. Cadeia: 546 (05-02) + 8 (este 05-03) + 4 (05-06 paralelo) = **558**.
- `vendor/bin/pint --dirty --format agent` → passed.
- Critérios de aceite (grep) confirmados: `public function enquadrar(EnquadramentoInput`, `RuleVersion::versao`, `logName: 'louos'`, `STATUS_NAO_ENCONTRADO`.
- **Anti-fachada:** o teste 5 prova que CNAE sem regra recebe `nao_encontrado` (grupo null, nunca inventado); o teste 6 prova consolidado `pendente` com quadro10/11/11a `indisponivel`; testes 1/2/6/7 rodam sobre a **carga real seedada** (`LouosQuadro7Seeder`, minimercado 4712-1/00 → nR1-01 até 350 m², nR2-01 acima).

## Testes (RED confirmado: classe inexistente antes da implementação)
1. `test_enquadra_cnae_por_area_na_faixa_correta` — dado real, 4712-1/00 área 100 → nR1/nR1-01, versão `lei-9148-2016-quadro7`.
2. `test_area_acima_do_limite_cai_na_faixa_superior` — dado real, área 500 → nR2/nR2-01.
3. `test_limite_inferior_e_inclusivo` — faixas disjuntas [0,99] e [100,500], área == 100 → faixa 2 (prova inclusividade do limite inferior).
4. `test_faixa_sem_teto_aceita_qualquer_area_acima` — faixa [0, null], área 999999 → identificado, faixa.area_max null.
5. `test_cnae_sem_faixa_retorna_nao_encontrado` (FA-02) — versão vigente, CNAE inexistente → nao_encontrado, grupo/subgrupo/faixa null.
6. `test_consolidado_fica_pendente_enquanto_motor_incompleto` — resultado() === Pendente; quadro10/11/11a indisponivel.
7. `test_enquadramento_e_auditado_com_versao_de_regras` (CA-02/RN-002) — Activity `louos`/`enquadramento`, rules_version `lei-9148-2016-quadro7`, properties coerentes.
8. `test_modo_sandbox_resolve_versao_especifica` (HU-143, extra) — vigente classifica 7020-4/00 como nR1; override de versão (rascunho) reclassifica a mesma área como nR2 sem afetar a vigente.

## Deviations from Plan

Nenhuma deviation de código — o plano foi executado como escrito.

**Adição de cobertura (não-deviation):** além dos 7 testes listados, foi adicionado o teste 8 (`test_modo_sandbox_resolve_versao_especifica`) para cobrir o modo sandbox (`RuleVersion::versao`), que é um dos 3 modos exigidos pelo plano mas não tinha teste nominal — reforça o acceptance criteria `grep "RuleVersion::versao"`.

## Issues Encountered

- **Execução paralela no mesmo working directory (05-06 mantenedores):** durante a execução, o executor paralelo do 05-06 criou arquivos (`LouosMaintenanceService.php`, `LouosMaintenanceTest.php`) e modificou seeders/roles, e commitou `9dc0680` antes do meu commit. Mitigação: **commit atômico com `git add` por caminho explícito** (apenas os 2 arquivos do 05-03), nunca `-A`/`.`. Meu commit `efc6e7d` saiu com 2 files changed; o trabalho do paralelo permaneceu no commit deles. A suíte fresca pós-merge (HEAD combinado) está verde (558/558).

## Next Plan Readiness (05-04 / 05-05)
- **05-04** substitui `quadro10NaoAvaliado()`/`quadro11NaoAvaliado()` pela lógica real (Quadro 10 por zona via TerritoryResult; Quadro 11/11A por via), reaproveitando os helpers `dim*` e o `resolveVersion()` (3 modos) já prontos. Degradação honesta preservada: sem zona real (Fase 4 `indisponivel`), Quadro 10 não é consultado e o consolidado segue pendente.
- **05-05** reescreve `consolidar()` (que já recebe as 4 dimensões) com o veredito final fundamentado.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
