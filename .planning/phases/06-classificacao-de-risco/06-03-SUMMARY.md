---
phase: 06-classificacao-de-risco
plan: 03
subsystem: database
tags: [risco-sanitario, visa, condicionante-pergunta, regras-como-dados, seed-csv-oficial, enum, eloquent, sqlite, auditoria]

# Dependency graph
requires:
  - phase: 06-01-fundacao-regras-versionadas
    provides: "rule_versions + RuleVersionService.openDraft/publish (quatro olhos, fecha vigente anterior, auditoria) + scopes vigente/naData/versao + enum RuleDomain (RiscoSanitario isSensitive)"
  - phase: 06-02-classificacao-risco-municipal
    provides: "Padrão de import de CSV oficial (RiscoMunicipalImportService: SplFileObject READ_CSV, EXPECTED_HEADER, upsert idempotente, relatório auditado no seeder); tabela tipada por domínio com FK rule_version_id; RiscoMunicipalSeeder integrado ao DatabaseSeeder"
  - phase: 02-administracao-base
    provides: "CnaeImportService/CnaeSeeder (database/data/, upsert); Cnae.code normalizado (FK lógica cnae_code)"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002) + HasAuditoria"
provides:
  - "Enum RiscoSanitario (baixo/medio/alto) com fromVisa(), label() e severity() — dimensão VISA, distinta da municipal (existe 'médio')"
  - "Enum TipoRespostaCondicionante (booleano_sim_nao + selecao reservado)"
  - "Tabela tabular risk_sanitary_classifications (FK rule_version_id, cnae_code, risco_sanitario, macroarea, autorizado_*, exige_rt, observacao) — dimensão SANITÁRIA separada da municipal"
  - "Tabela tabular risk_condicionantes (FK rule_version_id, cnae_code nullable, pergunta, tipo_resposta, regra_reclassificacao jsonb, texto_parecer) — condicionante-pergunta que reclassifica o risco (mecanismo 'DI')"
  - "Models SanitaryRiskClassification e RiskCondicionante (HasAuditoria, casts enum/array/boolean, relação ruleVersion) + factories"
  - "RiscoSanitarioImportService: import real da planilha VISA com relatório {lidos, classificacoes, condicionantes, rejeitados, avisos, por_nivel}, dedup por nível mais restritivo e reclassifica_para derivado do texto"
  - "CSV oficial VISA commitado em database/data/risco/ (fonte reprodutível do seed)"
  - "RiscoSanitarioSeeder: publica versão vigente (rules_version 'visa-unificada-2026-04-30') + import + auditoria, integrado ao DatabaseSeeder"
affects: [06-04-encaminhamento-gatilhos, 06-05-motor-risco, 06-06-mantenedores-publicacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dimensões de risco SEPARADAS por domínio: municipal (risk_classifications) e sanitária (risk_sanitary_classifications) em tabelas e versões distintas (RuleDomain), nunca misturadas — RN-009 provada por teste"
    - "Condicionante operacionalizada como pergunta com regra_reclassificacao jsonb (resposta_gatilho/reclassifica_para/fundamento) — a resposta do requerente reclassifica o risco (mecanismo 'DI')"
    - "Planilha com sub-atividades por CNAE: nível mais restritivo prevalece (severity), divergência vira aviso auditável — risco sanitário nunca rebaixado silenciosamente"
    - "reclassifica_para DERIVADO do texto da condicionante (alto/medio), nunca fixado — a fonte oficial tem ambos os casos"

key-files:
  created:
    - app/Enums/RiscoSanitario.php
    - app/Enums/TipoRespostaCondicionante.php
    - database/migrations/2026_06_14_014717_create_risk_sanitary_classifications_table.php
    - database/migrations/2026_06_14_014718_create_risk_condicionantes_table.php
    - app/Models/SanitaryRiskClassification.php
    - app/Models/RiskCondicionante.php
    - database/factories/SanitaryRiskClassificationFactory.php
    - database/factories/RiskCondicionanteFactory.php
    - app/Services/Risco/RiscoSanitarioImportService.php
    - database/data/risco/planilha-unificada-cnae-30-04-26.csv
    - database/seeders/RiscoSanitarioSeeder.php
    - tests/Unit/Risco/RiscoSanitarioImportServiceTest.php
    - tests/Feature/Risco/RiscoSanitarioSeederTest.php
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php
    - phpunit.xml

key-decisions:
  - "Dimensão sanitária em tabela própria (risk_sanitary_classifications) e enum próprio (RiscoSanitario, com 'médio') — SEPARADA da municipal; decisão travada, comprovada pelo teste test_dimensoes_municipal_e_sanitaria_sao_separadas (1331 municipal × 261 sanitária)"
  - "A planilha VISA tem a mesma subclasse CNAE em mais de uma linha (sub-atividades); a classificação dedup por (rule_version_id, cnae_code) mantém o NÍVEL MAIS RESTRITIVO (severity) — risco sanitário nunca é rebaixado silenciosamente — e registra a divergência em avisos[] (único conflito real: 8129-0/00 medio→alto)"
  - "reclassifica_para é derivado do texto da condicionante (regex 'Alto Risco'→alto, 'Médio Risco'→medio); 65 condicionantes vão para alto e 2 para medio (7739-0/03, 8112-5/00). Fixar 'alto' classificaria errado os casos de médio — fachada"
  - "SanitaryRiskClassification declara protected \$table='risk_sanitary_classifications' (a convenção pluralizaria para sanitary_risk_classifications) — descoberto pelo teste"
  - "Migrations com timestamp explícito 014717/014718 (após rule_versions 013329 e risk_classifications 014716): make:migration geraria 2026_06_13_* e ordenaria ANTES da FK rule_versions, quebrando a constraint"
  - "Condicionantes via firstOrCreate(rule_version_id+cnae_code+pergunta) dentro de withoutEvents — idempotente sem 67 activities espúrias; auditoria é o relatório explícito do seeder (mantenedores 06-06 auditam o CRUD via HasAuditoria)"
  - "Verificação SQLite via 'php artisan test --exclude-group postgis' (precedente 06-01/06-02); memory_limit fixado em 512M no phpunit.xml para o result cache não estourar 128M ao final da run"

patterns-established:
  - "Risco sanitário como dado versionado: FK rule_version_id + cnae_code + nível enum (com médio) + flags operacionais (macroarea, autorizado_ev, autorizado_mei, exige_rt)"
  - "Condicionante-pergunta: regra_reclassificacao jsonb com resposta_gatilho/reclassifica_para/fundamento — contrato do motor 06-05 e dos mantenedores 06-06"
  - "Contagem assertada sobre o CSV REAL (285→261, 83/120/58, 67 condicionantes), sem fixture sintético — anti-fachada"

# Metrics
duration: 22min
completed: 2026-06-14
---

# Phase 6 Plan 03: Dimensão Sanitária (VISA) e Condicionantes Summary

**Dimensão SANITÁRIA de risco como dado versionado em tabela própria (`risk_sanitary_classifications`), separada da municipal, com enum `RiscoSanitario` (baixo/medio/alto) e a condicionante operacionalizada como pergunta que reclassifica o risco (`risk_condicionantes`, regra_reclassificacao jsonb). Seed REAL da planilha VISA: 285 linhas → 261 classificações (83/120/58) + 67 condicionantes-pergunta, com o golden case 1031-7/00 reclassificando de Baixo para Alto quando o produto não é artesanal.**

## Performance

- **Duration:** ~22 min
- **Started:** 2026-06-14T01:59:00Z
- **Completed:** 2026-06-14T02:21:53Z
- **Tasks:** 3 (todas TDD RED→GREEN→pint)
- **Files created:** 13 | **modified:** 3

## Accomplishments
- Enum `RiscoSanitario` (baixo/medio/alto) com `fromVisa()` (rejeita nível desconhecido), `label()` e `severity()` — dimensão VISA com "médio", DISTINTA da municipal (baixo_a/baixo_b/alto).
- Enum `TipoRespostaCondicionante` (booleano_sim_nao + selecao reservado).
- Tabelas tabulares `risk_sanitary_classifications` e `risk_condicionantes` (FK `rule_version_id`), rodando em SQLite.
- `RiscoSanitarioImportService`: import real da planilha VISA (SplFileObject READ_CSV, header de 15 colunas validado por array), dedup por nível mais restritivo, condicionante-pergunta com reclassifica_para derivado do texto, relatório com avisos auditáveis.
- `RiscoSanitarioSeeder` integrado ao `DatabaseSeeder` (publica versão vigente + import + auditoria).
- Suíte SQLite: **475/475 verde** (466 baseline + 9 novos), sem regressão; dimensões municipal × sanitária comprovadamente separadas.

## Task Commits

Cada task foi commitada atomicamente (TDD RED→GREEN):

1. **Task 1: Enums + migrations + models + factories** - `66bba8d` (feat)
2. **Task 2: RiscoSanitarioImportService + CSV VISA commitado** - `3f646ca` (feat)
3. **Task 3: RiscoSanitarioSeeder + DatabaseSeeder + testes** - `29df2f9` (feat)

**Fix de verificação:** `bc8d4e8` (test: memory_limit 512M no phpunit.xml)
**Plan metadata:** este SUMMARY (docs).

## Contrato dos artefatos (insumo do motor 06-05 e dos mantenedores 06-06)

### Tabela `risk_sanitary_classifications` (dimensão SEPARADA da municipal)
`id`, `rule_version_id` (FK `rule_versions`, cascadeOnDelete), `cnae_code` (string 7, dígitos — FK lógica para `Cnae.code`), `risco_sanitario` (cast `RiscoSanitario`), `macroarea` (string, nullable), `autorizado_escritorio_virtual` (bool, default false), `autorizado_mei` (bool, default false), `exige_rt` (bool, default false), `observacao` (text, nullable), `timestamps`. Índices: `unique(rule_version_id, cnae_code)`, `index(cnae_code)`. **Nome explícito no model** (`protected $table`), pois a convenção pluralizaria a classe.

### Tabela `risk_condicionantes`
`id`, `rule_version_id` (FK, cascadeOnDelete), `cnae_code` (string 7, **nullable** — geral ou por CNAE), `pergunta` (text), `tipo_resposta` (cast `TipoRespostaCondicionante`, default `booleano_sim_nao`), `regra_reclassificacao` (jsonb/array, nullable), `texto_parecer` (text, nullable), `timestamps`. Índice: `index(rule_version_id, cnae_code)`. Sem índice único (pergunta é texto longo); unicidade lógica garantida pelo import via `firstOrCreate(rule_version_id+cnae_code+pergunta)`.

### Shape de `regra_reclassificacao` (jsonb)
```json
{
  "resposta_gatilho": true,
  "reclassifica_para": "alto",
  "fundamento": "Desde que ... não seja diferente de produto artesanal. Caso seja, será considerado Alto Risco."
}
```
Quando a resposta do requerente == `resposta_gatilho`, o motor (06-05) reclassifica o risco para `reclassifica_para` e registra o `fundamento` na decisão. `reclassifica_para` ∈ {`baixo`,`medio`,`alto`} ou `null` (indeterminado → motor encaminha para análise com o fundamento). Na carga oficial: 65 condicionantes → `alto`, 2 → `medio` (7739-0/03, 8112-5/00); nenhuma `null` (as 2 linhas sem nível detectável têm pergunta '−' e não geram condicionante — ex.: 2093-2/00, competência da VISA estadual).

### Relatório do `RiscoSanitarioImportService::import(RuleVersion $version, string $csvPath): array`
```
{
  lidos: int,            // linhas de dados lidas (285 na planilha oficial)
  classificacoes: int,   // subclasses CNAE classificadas (261)
  condicionantes: int,   // condicionantes-pergunta únicas (67)
  rejeitados: string[],  // [] na planilha oficial
  avisos: string[],      // divergências de nível resolvidas (1: CNAE 8129000)
  por_nivel: { baixo: int, medio: int, alto: int }  // {83, 120, 58}
}
```
Sobre o CSV oficial VISA: `lidos=285`, `classificacoes=261`, `condicionantes=67`, `por_nivel={baixo:83, medio:120, alto:58}` (soma 261), `rejeitados=[]`, `avisos` com 1 entrada (8129-0/00: níveis medio+alto → mantido alto).

### Versão de regra
Domínio `risco_sanitario`, `version`/`rules_version` = **`'visa-unificada-2026-04-30'`**, `source` = "Planilha Unificada CNAE 30.04.26 (Vigilância Sanitária)". Auditoria do seed: `log_name 'risco'`, `event 'importacao-classificacao-sanitaria'`, `rules_version 'visa-unificada-2026-04-30'`, `properties` = relatório do import.

### Golden case (condicionante-pergunta real da VISA)
CNAE **1031-7/00** (FABRICAÇÃO DE CONSERVAS DE FRUTAS): classificação base **baixo**; condicionante-pergunta "O resultado do exercício da atividade econômica será diferente de produto artesanal?" com `regra_reclassificacao = {resposta_gatilho: true, reclassifica_para: "alto", fundamento: "...Caso seja, será considerado Alto Risco."}`. Resposta "Sim" → reclassifica para Alto Risco.

## Files Created/Modified
- `app/Enums/RiscoSanitario.php` - Níveis VISA (baixo/medio/alto) + fromVisa()/label()/severity().
- `app/Enums/TipoRespostaCondicionante.php` - booleano_sim_nao + selecao (reservado).
- `database/migrations/2026_06_14_014717_create_risk_sanitary_classifications_table.php` - Tabela sanitária separada.
- `database/migrations/2026_06_14_014718_create_risk_condicionantes_table.php` - Condicionante-pergunta com regra_reclassificacao jsonb.
- `app/Models/SanitaryRiskClassification.php` - Model (HasAuditoria, $table explícito, casts, ruleVersion()).
- `app/Models/RiskCondicionante.php` - Model (HasAuditoria, casts enum+array, PHPDoc do shape).
- `database/factories/SanitaryRiskClassificationFactory.php` / `RiskCondicionanteFactory.php` - Factories.
- `app/Services/Risco/RiscoSanitarioImportService.php` - Import real da planilha VISA (dedup por severity, reclassifica_para derivado, avisos).
- `database/data/risco/planilha-unificada-cnae-30-04-26.csv` - CSV oficial VISA commitado.
- `database/seeders/RiscoSanitarioSeeder.php` - Publica versão vigente + import + auditoria.
- `database/seeders/DatabaseSeeder.php` - Registra `RiscoSanitarioSeeder` após `RiscoMunicipalSeeder`.
- `tests/Unit/Risco/RiscoSanitarioImportServiceTest.php` - Enum + import (285→261, 83/120/58, golden, médio, conflito, idempotência).
- `tests/Feature/Risco/RiscoSanitarioSeederTest.php` - Publicação da versão + carga + dimensões separadas + idempotência.
- `tests/Feature/Seeders/DatabaseSeederTest.php` - +asserções sanitárias (261/67, versão vigente, auditoria).
- `phpunit.xml` - +`<ini memory_limit=512M>` (result cache não estourar 128M).

## Decisions Made
- **Dimensão sanitária 100% separada da municipal**: tabela própria + enum próprio (com "médio") + domínio `risco_sanitario`. Provado por `test_dimensoes_municipal_e_sanitaria_sao_separadas` (1331 municipal × 261 sanitária, versões distintas).
- **Dedup por nível mais restritivo** para subclasses CNAE repetidas na planilha (sub-atividades): severity decide; divergência de nível vira aviso auditável. Risco sanitário nunca é rebaixado silenciosamente.
- **reclassifica_para derivado do texto** (não fixo): a fonte tem 65 casos "Alto Risco" e 2 "Médio Risco". Fixar "alto" seria fachada.
- **firstOrCreate + withoutEvents** para condicionantes: idempotente e sem activities espúrias no import (auditoria = relatório explícito do seeder).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Correctness] Dedup por nível mais restritivo para CNAE repetido na planilha VISA**
- **Found during:** Task 2 (import sobre o CSV real).
- **Issue:** O plano assume `unique(rule_version_id, cnae_code)` e upsert por essa chave. A planilha VISA enumera sub-atividades: 14 subclasses aparecem em 2-4 linhas (24 linhas extras), e o `upsert` com chaves repetidas no mesmo lote falha no SQLite ("ON CONFLICT ... cannot affect row a second time"). Em 1 caso há divergência REAL de nível (8129-0/00: medio×2 + alto).
- **Fix:** Dedup antes do upsert mantendo o nível mais restritivo (`RiscoSanitario::severity()`) — risco sanitário nunca rebaixado silenciosamente — e registro da divergência em `avisos[]` (nunca perda silenciosa). 285 linhas → 261 classificações.
- **Files modified:** app/Services/Risco/RiscoSanitarioImportService.php
- **Verification:** test_import_carrega_classificacoes_sanitarias (261; 83/120/58) e test_import_mantem_nivel_mais_restritivo_em_cnae_duplicado (8129-0/00 → alto + aviso) verdes.
- **Committed in:** 3f646ca

**2. [Rule 1 - Bug] reclassifica_para derivado do texto da condicionante (não fixado em 'alto')**
- **Found during:** Task 2 (análise dos textos reais das condicionantes).
- **Issue:** O plano sugere `reclassifica_para: "alto"` como padrão. A planilha tem 2 condicionantes que reclassificam para **Médio Risco** (7739-0/03, 8112-5/00) e casos sem nível (transferência de competência à VISA estadual). Fixar "alto" classificaria errado — fachada.
- **Fix:** `reclassificaPara()` deriva o nível do texto (regex "Alto Risco"→alto, "Médio Risco"→medio; senão null + aviso). 65 alto + 2 medio.
- **Files modified:** app/Services/Risco/RiscoSanitarioImportService.php
- **Verification:** test_import_reclassifica_para_medio_quando_a_planilha_indica (8112-5/00 → medio) verde.
- **Committed in:** 3f646ca
- **Nota de extensão:** o relatório ganhou `avisos[]` (além de {lidos, classificacoes, condicionantes, rejeitados, por_nivel}) — aditivo, precedente do RedesimImportService — para tornar dedup/derivação não silenciosos.

**3. [Rule 3 - Blocking] $table explícito no SanitaryRiskClassification**
- **Found during:** Task 2 (primeira run GREEN).
- **Issue:** O teste falhou com "no such table: sanitary_risk_classifications" — a convenção pluralizaria a classe, mas a tabela do plano é `risk_sanitary_classifications`.
- **Fix:** `protected $table = 'risk_sanitary_classifications'`. (RiskCondicionante casa pela convenção, sem ajuste.)
- **Files modified:** app/Models/SanitaryRiskClassification.php
- **Verification:** os 6 testes do import passaram após o ajuste.
- **Committed in:** 3f646ca

**4. [Rule 3 - Blocking] Timestamp explícito nas migrations (ordem da FK)**
- **Found during:** Task 1.
- **Issue:** `make:migration` geraria `2026_06_13_*` (relógio local) e ordenaria ANTES de `rule_versions` (2026_06_14_013329), quebrando a FK `rule_version_id`.
- **Fix:** Migrations criadas com timestamp explícito `014717`/`014718` (após `risk_classifications` 014716), preservando a ordem de dependência — mesma convenção do executor anterior nesta fase.
- **Files modified:** as duas migrations do plano.
- **Verification:** RefreshDatabase migra o schema completo em SQLite (475/475).
- **Committed in:** 66bba8d

**5. [Rule 3 - Blocking] memory_limit do phpunit em 512M**
- **Found during:** Verificação final (suíte completa).
- **Issue:** Os 475 testes PASSAM, mas o comando canônico `php artisan test --compact --exclude-group postgis` saía com exit 255: o result cache do PHPUnit é serializado no shutdown e estourava o `memory_limit` baixo (128M) deste host ao final da run. O `artisan test` passa o phpunit a um subprocesso que herda o limite do php.ini.
- **Fix:** `<ini name="memory_limit" value="512M"/>` no `phpunit.xml` — garante saída limpa do comando canônico em qualquer host, sem tocar nenhum teste.
- **Files modified:** phpunit.xml
- **Verification:** `php artisan test --compact --exclude-group postgis` → 475/475, **exit 0**.
- **Committed in:** bc8d4e8

**6. [Verificação] `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- Mesma natureza e racional do 06-01/06-02 (sem `.env.testing`; o grupo postgis exige container). Migrations provadas em SQLite pelo `RefreshDatabase`.

---

**Total deviations:** 6 (2 correctness/bug essenciais à fidelidade do dado, 3 blocking, 1 de verificação).
**Impact on plan:** Sem scope creep nos domínios — todos os arquivos dentro do `files_modified` do 06-03, exceto `phpunit.xml` (infra de teste compartilhada, fix de verificação). Nada das waves 06-04 (encaminhamento/gatilhos/parâmetros) ou 06-05 (motor `RiscoClassificationService`) foi tocado. As deviations 1 e 2 são exigência anti-fachada: o dado real da VISA tem repetições e níveis de reclassificação variados que o plano (escrito antes da leitura do CSV) não previa.

## Issues Encountered
- **CRLF no CSV oficial:** `git add` avisou que o CSV (CRLF) será normalizado para LF. Sem impacto: o import faz `trim()` de cada campo; as contagens são idênticas com LF ou CRLF (mesmo comportamento do 06-02).
- **24 linhas duplicadas de CNAE na planilha VISA:** investigadas linha a linha antes de modelar — a maioria é repetição idêntica (sub-atividades), 1 com divergência real de nível (8129-0/00). Resolvido por dedup com nível mais restritivo + aviso (deviation 1).

## User Setup Required
None - sem configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **06-04 (encaminhamento/gatilhos):** consome `SanitaryRiskClassification.risco_sanitario` (dimensão sanitária separada) e a condicionante-pergunta; aqui entram os parâmetros `risco.mapa_encaminhamento` / `risco.dimensao_tvl`.
- **06-05 (motor `RiscoClassificationService`):** lê a versão vigente (`RuleVersion::vigente`/`naData`) e as `risk_sanitary_classifications` por `cnae_code`; aplica a reclassificação da condicionante via `regra_reclassificacao` (resposta_gatilho → reclassifica_para). Dimensões municipal e sanitária prontas e separadas.
- **06-06 (mantenedores):** CRUD sobre `risk_sanitary_classifications` e `risk_condicionantes` (auditado via `HasAuditoria`) + publicação por quatro olhos (`RuleVersionService.publish`); `tipo_resposta=selecao` reservado para perguntas de múltipla escolha sem migração.
- Sem blockers introduzidos por este plano.

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
