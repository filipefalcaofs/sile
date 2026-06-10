---
phase: 02-administracao-base
plan: 01
subsystem: database
tags: [cnae, ibge-concla, csv, openpyxl, spatie-permission, seeder]

# Dependency graph
requires:
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "Papéis estruturais (cidadao/analista/gestor/administrador) e RolesAndPermissionsSeeder idempotente"
provides:
  - "Dado oficial CNAE-Subclasses 2.3 versionado: database/data/cnaes-subclasses-2-3.csv (1.331 subclasses, hierarquia resolvida)"
  - "Proveniência executável: scripts/convert-cnae-xlsx.py (conversão one-shot do xlsx IBGE/CONCLA com validações internas)"
  - "8 permissões seedadas de forma aditiva: manter-cnaes, manter-usuarios, manter-perfis, manter-parametros, consultar-cnaes + 3 da Fase 1"
affects: [02-02, 02-03, 02-04, 02-05, 02-06, 02-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seeder aditivo: givePermissionTo (nunca sync) em papéis estruturais — re-seed preserva ajustes da interface"
    - "Dado oficial versionado com proveniência documentada por script executável (xlsx → CSV one-shot)"

key-files:
  created:
    - scripts/convert-cnae-xlsx.py
    - database/data/cnaes-subclasses-2-3.csv
  modified:
    - database/seeders/RolesAndPermissionsSeeder.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php

key-decisions:
  - "CSV fiel à fonte: 1.331 subclasses; 9900-8/00 NÃO inventada (divergência com a publicação registrada na proveniência; relatório formal no import do 02-04)"
  - "Códigos no formato oficial no CSV (0111-3/01); normalização para dígitos é responsabilidade do CnaeImportService (02-04)"
  - "syncPermissions → givePermissionTo no seeder (Pitfall 5): re-seed em produção não reverte permissões ajustadas pelo admin via HU-013"

patterns-established:
  - "Seeder aditivo de papéis/permissões: firstOrCreate + givePermissionTo, idempotente sem regressão administrativa"
  - "Dados oficiais em database/data/ com script de conversão versionado em scripts/"

# Metrics
duration: 9min
completed: 2026-06-10
---

# Fase 2 Plano 01: Fundação — dado oficial CNAE + permissões granulares Summary

**CSV oficial IBGE/CONCLA com 1.331 subclasses versionado com proveniência executável, e seeder de permissões tornado aditivo com as 5 permissões granulares da fase (manter-* e consultar-cnaes)**

## Performance

- **Duration:** 9 min
- **Started:** 2026-06-10T14:14:40Z
- **Completed:** 2026-06-10T14:23:12Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- `database/data/cnaes-subclasses-2-3.csv` versionado: 1.331 subclasses com hierarquia completa (21 seções, 87 divisões, 285 grupos, 673 classes) resolvida por carry-forward, validada por contagens executáveis (zero duplicatas, todos os códigos no padrão `DDDD-D/SS`, denominações não vazias).
- Proveniência documentada de forma executável em `scripts/convert-cnae-xlsx.py`: fonte oficial, data de download, divergência 1.331 × 1.332 (ausência conhecida da `9900-8/00` no arquivo de estrutura, confirmada existente na CONCLA) — fidelidade à fonte, sem inventar a linha ausente.
- Seeder de papéis/permissões aditivo (TDD): 8 permissões (`manter-cnaes`, `manter-usuarios`, `manter-perfis`, `manter-parametros`, `consultar-cnaes` + 3 da Fase 1); administrador com todas as `manter-*`; analista/gestor com `consultar-cnaes`; cidadão sem acesso de consulta.
- Teste novo prova que re-seed NÃO remove permissão adicionada por interface a papel estrutural (anti-Pitfall 5) — CA-04 por recurso desbloqueado para os planos 02-02 a 02-07.

## Task Commits

Each task was committed atomically:

1. **Task 1: Converter o xlsx oficial IBGE/CONCLA para CSV versionado** - `dd8d086` (feat)
2. **Task 2 (RED): testes falhando para permissões granulares e seeder aditivo** - `993292d` (test)
3. **Task 2 (GREEN): seeder aditivo com permissões da fase 2** - `7f21d44` (feat)

_REFACTOR: pint sem pendências nos dois arquivos PHP — nenhuma mudança necessária, sem commit de refactor._

## Files Created/Modified

- `scripts/convert-cnae-xlsx.py` - Conversão one-shot xlsx→CSV (openpyxl), carry-forward da árvore, validações internas com exit code 1 em falha, docstring de proveniência
- `database/data/cnaes-subclasses-2-3.csv` - 1 cabeçalho + 1.331 linhas; códigos no formato oficial; pronto para o CnaeImportService (02-04)
- `database/seeders/RolesAndPermissionsSeeder.php` - 8 permissões; `givePermissionTo` (aditivo) no lugar de `syncPermissions`
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` - Asserções estendidas (8 permissões, atribuições por papel) + novo `test_seeder_aditivo_preserva_ajustes_feitos_pela_interface`

## Decisions Made

- **CSV fiel à fonte (1.331, sem 9900-8/00):** a publicação oficial cita 1.332 subclasses; a única ausente do arquivo de estrutura é a `9900-8/00` (confirmada na CONCLA). Não foi inventada — o registro formal da divergência acontece no relatório do import (02-04). Caminho administrativo (CRUD HU-011) cobre a necessidade se surgir.
- **Códigos no formato oficial no CSV:** normalização para dígitos (`0111301`) fica no `CnaeImportService` (lógica de negócio testável em PHP), não na conversão mecânica.
- **Seeder aditivo:** `givePermissionTo` mantém idempotência sem regressão administrativa — re-seed em deploy não desfaz ajustes feitos pelo admin via HU-013 (Pitfall 5 do 02-RESEARCH.md).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- O comentário inicial do seeder continha a string literal `syncPermissions` (explicando a troca), o que violaria o critério de aceite verificável por grep ("o arquivo NÃO contém `syncPermissions`"). Reformulado para "nunca sync" antes do commit GREEN.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Wave 1 (02-02, 02-03) roda em paralelo; permissões granulares já disponíveis para os middlewares `permission:manter-*`/`permission:consultar-cnaes` dos planos seguintes.
- 02-04 (CnaeImportService) desbloqueado: CSV pronto com contrato de cabeçalho estável e contagens verificadas.
- Suíte completa (fechamento da wave) é responsabilidade do orquestrador — verificações deste plano foram escopadas (`tests/Feature/Authorization`: 5/5 verdes).

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
