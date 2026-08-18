---
phase: 05-motor-louos
plan: 02
subsystem: database
tags: [louos, seeds, rule-versions, import, csv, sqlite, auditoria]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 01
    provides: "tabelas louos_quadro7_faixas/quadro10_permissoes/quadro11_condicoes_via, enum Quadro10Permissao, RuleDomain louos_quadro7/10/11/11a"
  - phase: 06-classificacao-risco
    provides: "RuleVersion + RuleVersionService (openDraft/publish), AuditService, padrão de import auditado (RiscoMunicipalImportService/Seeder)"
provides:
  - "Quadro 7 REAL como versão vigente (lei-9148-2016-quadro7): 40 faixas em 24 CNAEs derivadas da Lei 9.148/2016 + modelo TVL/SAPS"
  - "Quadros 10/11/11A MODELADOS como versões vigentes (lei-9148-2016-quadro10/11/11a)"
  - "3 import services auditáveis (não-sobreposição no Q7, rejeição de permissão no Q10, filtro por quadro no Q11/11A)"
  - "Distribuição do Quadro 7 e versões vigentes dos 4 domínios travadas por teste de regressão"
affects: [05-03-motor, 05-04-motor, 05-05-motor, 05-06-mantenedores, 05-09-golden-cases, 07-consulta-previa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seed de Quadro LOUOS = openDraft+publish (versão vigente própria) + import CSV auditado, espelhando RiscoMunicipalSeeder"
    - "Import com upsert idempotente exige índice único no alvo do ON CONFLICT (corrigido por migration aditiva, sem recriar tabela)"
    - "Colunas anuláveis do alvo do upsert gravadas como '' para o re-import casar (NULL é tratado como distinto)"
    - "Um CSV serve a dois domínios (Q11/11A) via coluna discriminadora; o seeder publica duas versões filtrando"

key-files:
  created:
    - database/data/louos/quadro7-faixas.csv
    - database/data/louos/quadro10-permissoes.csv
    - database/data/louos/quadro11-condicoes-via.csv
    - app/Services/Louos/LouosQuadro7ImportService.php
    - app/Services/Louos/LouosQuadro10ImportService.php
    - app/Services/Louos/LouosQuadro11ImportService.php
    - database/seeders/LouosQuadro7Seeder.php
    - database/seeders/LouosQuadro10Seeder.php
    - database/seeders/LouosQuadro11Seeder.php
    - database/migrations/2026_06_14_051944_add_unique_index_to_louos_quadro7_faixas_table.php
    - database/migrations/2026_06_14_052540_add_unique_indexes_to_louos_quadro10_and_quadro11_tables.php
    - tests/Feature/Louos/LouosQuadro7ImportServiceTest.php
    - tests/Feature/Louos/LouosQuadro10ImportServiceTest.php
    - tests/Feature/Louos/LouosQuadro11ImportServiceTest.php
    - tests/Feature/Louos/LouosSeedDistributionTest.php
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Quadro 7 é seed REAL derivado da Lei 9.148/2016 + modelo TVL/SAPS, com proveniência no phpdoc e SUBSTITUÍVEL pela planilha oficial SEDUR"
  - "Migrations aditivas de índice único (Rule 3): o upsert documentado não funciona sem alvo único em SQLite/PostgreSQL — 05-01 só criou índice de leitura"
  - "Verify do Q11 por feature test SQLite em vez de db:seed --env=testing (risco ao banco dev; sem .env.testing — precedente 05-01)"
  - "Faixas sobrepostas rejeitam o CNAE inteiro (nunca carga parcial); permissão desconhecida no Q10 rejeitada sem inserir; JSON inválido no Q11 rejeitado"

patterns-established:
  - "Import de Quadro LOUOS: cabeçalho exato, normalização/validação por linha, rejeição auditável, upsert em lote (chunk 500), observacao preservada no re-import"

# Metrics
duration: 20min
completed: 2026-06-14
---

# Phase 5 Plan 02: Seeds dos Quadros da LOUOS Summary

**Os Quadros da LOUOS carregados como dados versionados: Quadro 7 REAL (40 faixas em 24 CNAEs derivadas da Lei 9.148/2016 + modelo TVL/SAPS) e Quadros 10/11/11A MODELADOS, cada um publicando uma versão vigente própria via RuleVersionService, com imports auditados e a distribuição travada por teste de regressão.**

## Performance

- **Duration:** ~20 min
- **Tasks:** 3
- **Files:** 17 (15 criados, 2 modificados)
- **Commits:** 613d460, 0f040bf, 54a5e81 (+ docs deste SUMMARY)

## Deliverables (com evidência)

### Quadro 7 — REAL (entregável)
- **`database/data/louos/quadro7-faixas.csv`** — layout `cnae,grupo,subgrupo,area_min,area_max,observacao`. **40 faixas** em **24 CNAEs reais** do catálogo (1.331 subclasses). Grupos de uso nR1/nR2/nR3 da LOUOS por faixa de área.
- **Proveniência (anti-fachada):** DERIVADO da Lei nº 9.148/2016 (Quadro 7) + modelo "Enquadramento TVL" do SAPS legado (`docs/legado-saps/14-enquadramento-tvl-louos-faixas-area.jpg`). Dois CNAEs ancorados exatamente na imagem do SAPS: minimercado `4712-1/00` (até 350 m² = nR1-01, acima = nR2-01) e consultoria/escritório `7020-4/00` (até 1.250 m² = nR1-12, acima = nR2-12). Proveniência documentada no phpdoc do import; **SUBSTITUÍVEL pela planilha oficial do Quadro 7 da SEDUR** — muda a carga, não a lógica.
- **`LouosQuadro7ImportService`** — valida cabeçalho, normaliza CNAE p/ 7 dígitos, valida não-sobreposição de faixas por CNAE (rejeita o CNAE inteiro, nunca carga parcial) e `area_min > area_max`; upsert por `(rule_version_id, cnae_code, area_min)` que **não toca `observacao`** (anotações do mantenedor sobrevivem ao re-import).
- **`LouosQuadro7Seeder`** — publica vigente `lei-9148-2016-quadro7` (sem 4 olhos no seed) e audita o relatório (`log_name 'louos'`, `event 'importacao-quadro7'`, `rules_version 'lei-9148-2016-quadro7'`).

### Quadros 10 / 11 / 11A — MODELADOS (escopo honesto)
- **`quadro10-permissoes.csv`** — layout `zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal`. **18 permissões** em 5 zonas-exemplo (ZPR-1, ZPR-2, ZCN-1, ZM-1, ZPAM) cobrindo os 3 valores do enum (`permitido`, `permitido_condicionado`, `proibido`), `base_legal` citando a Lei 9.148/2016.
- **`quadro11-condicoes-via.csv`** — layout `quadro,classe_via,grupo_uso,condicoes,base_legal` (coluna `quadro` ∈ {11, 11a}). **8 linhas** (4 do Quadro 11 + 4 do 11A); `condicoes` é JSON (recuos, vagas de carga/descarga, estudo de tráfego).
- **`LouosQuadro10ImportService`** — valida a permissão contra `Quadro10Permissao` e **rejeita valor desconhecido sem inserir**; upsert por `(rule_version_id, zona, grupo_uso, subgrupo)`.
- **`LouosQuadro11ImportService`** — importa só as linhas do `quadro` informado ('11'/'11a'), faz `json_decode` (rejeita JSON inválido); upsert por `(rule_version_id, classe_via, grupo_uso)`.
- **`LouosQuadro10Seeder`** publica vigente `lei-9148-2016-quadro10`; **`LouosQuadro11Seeder` publica DUAS versões vigentes** (`lei-9148-2016-quadro11` e `lei-9148-2016-quadro11a`) do mesmo CSV, filtrando por `quadro`. Ambos auditam (`importacao-quadro10/11/11a`).
- **Escopo honesto:** estrutura real, carga oficial por zona/atributo viário **pendente SEDUR** (SIGIS/CA 2000; "Quadro 11"↔11B). O motor (05-04/05) degrada para `pendente` sem a zona real — nunca inventa permissão.

### Versões publicadas (RuleVersionService, HU-046 reusado)
`lei-9148-2016-quadro7` · `lei-9148-2016-quadro10` · `lei-9148-2016-quadro11` · `lei-9148-2016-quadro11a` — todas vigentes únicas por domínio.

### Teste de rejeição (âncora da revisão)
`LouosQuadro10ImportServiceTest::test_rejeita_permissao_desconhecida_sem_inserir` — CSV com permissão `talvez` é rejeitada (`rejeitados[]`) e NÃO inserida, enquanto a permissão válida do mesmo arquivo entra. Espelha `test_rejeita_faixas_sobrepostas_do_mesmo_cnae` do Quadro 7. Ambos falhavam antes da validação (RED confirmado).

## Evidência (verificação fresca)

- `php artisan test --compact --filter=Louos` → **27 passed** (182 assertions).
- `php artisan test --compact --exclude-group postgis` → **546 passed** (2.763 assertions), zero falhas. Cadeia coerente: 519 baseline original → 532 (05-01, +13) → **546 (05-02, +14)**; todos os commits puramente aditivos (0 deleções).
- `vendor/bin/pint --dirty --format agent` → passed em cada task.
- Anti-fachada: a não-sobreposição é validada sobre o dado real (CNAE a CNAE) no `LouosSeedDistributionTest`; o Quadro 7 vem de fonte legal real com proveniência, não de fixture sintético.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Faltavam índices únicos para o upsert idempotente**
- **Found during:** Task 1 (e confirmado na Task 2).
- **Issue:** As tabelas `louos_quadro7_faixas/quadro10_permissoes/quadro11_condicoes_via` do 05-01 só têm índice de LEITURA. O `upsert()` documentado no plano (e a idempotência testada) gera `INSERT ... ON CONFLICT (...)`, que **exige alvo único** em SQLite/PostgreSQL — sem ele o import falha. `risk_classifications` (padrão a espelhar) tem `unique(rule_version_id, cnae_code)` justamente para isso.
- **Fix:** 2 migrations ADITIVAS (não recriam tabela): `add_unique_index_to_louos_quadro7_faixas_table` e `add_unique_indexes_to_louos_quadro10_and_quadro11_tables`. Colunas anuláveis do alvo (subgrupo no Q10, grupo_uso no Q11) são gravadas como `''` pelo import para o ON CONFLICT casar no re-import (NULL seria tratado como distinto).
- **Files:** as 2 migrations; imports correspondentes.
- **Committed in:** 613d460 (Q7), 0f040bf (Q10/Q11).

**2. [Verificação mais segura] Verify do Quadro 11 por teste SQLite em vez de `db:seed --env=testing`**
- **Found during:** Task 2.
- **Issue:** O comando de verify `php artisan db:seed --class=LouosQuadro11Seeder --env=testing` rodaria contra o banco de DESENVOLVIMENTO (não há `.env.testing`; a config SQLite vive só no `phpunit.xml` — precedente registrado no 05-01-SUMMARY) e exigiria a migration aplicada lá.
- **Fix:** Criado `LouosQuadro11ImportServiceTest` (SQLite, RefreshDatabase) provando o filtro por `quadro`, a publicação das DUAS versões vigentes e a idempotência — cobertura mais forte e sem risco ao banco dev.
- **Committed in:** 0f040bf.

**Total deviations:** 2 (1 blocking auto-fixed, 1 verificação substituída por evidência equivalente mais segura). Sem scope creep; nada de fachada.

## Âncoras de domínio (para 05-03/04/05/09)
- Quadro 7: **40 faixas, 24 CNAEs** distintos, sem sobreposição (regressão em `LouosSeedDistributionTest`).
- Quadro 10: **18 permissões**. Quadro 11: **4** condições vigentes. Quadro 11A: **4** condições vigentes.
- `DatabaseSeederTest` trava: 1 versão vigente por domínio LOUOS + 40 faixas + auditoria `louos/importacao-quadro7`; contagem de Parameter inalterada (33).

## Next Plan Readiness (05-03+)
- O motor (`LouosEnquadramentoService`) tem o dado vigente para consumir: Quadro 7 enquadra por CNAE+área; Quadros 10/11/11A resolvem por `RuleVersion::vigente`/`naData`/`versao` (sandbox HU-143).
- Degradação honesta preservada: sem zona real (Fase 4 `indisponivel`), o Quadro 10 não deve ser consultado — consolidado `pendente`. O dado modelado existe para ligar quando a SEDUR entregar a base.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
