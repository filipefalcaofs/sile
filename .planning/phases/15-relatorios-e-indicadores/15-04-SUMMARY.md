---
phase: 15-relatorios-e-indicadores
plan: 04
subsystem: infra
tags: [feriados, holidays, dias-uteis, business-duration, hu-137, hu-129, auditoria, cache]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    provides: BusinessDeadlineCalculator (seam HU-137; dueAt em horas-calendário)
  - phase: 10-analise-tecnica-sedur
    provides: AnalysisSlaService que reusa o calculator (rede anti-regressão do dueAt)
  - phase: 15-relatorios-e-indicadores
    provides: 15-01 (config sile.relatorios.cache_ttl_segundos) + HasAuditoria/Settings
provides:
  - "Holiday: cadastro de feriado auditado e versionado (HU-137)"
  - "HolidayProvider (contrato) + DatabaseHolidayProvider cacheado"
  - "BusinessDeadlineCalculator::businessDurationBetween (duração em minutos úteis — HU-129)"
  - "binding singleton HolidayProvider→DatabaseHolidayProvider"
affects: [15-05-tempo-por-etapa, relatorios-saps, dashboard]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Fonte de feriados atrás de contrato (HolidayProvider) — seam centralizado de dias úteis"
    - "Dependência de provider OPCIONAL no construtor (?Type = null) para preservar call sites diretos e degradar honesto"
    - "Duração em tempo útil (businessDurationBetween) SEPARADA do prazo forward (dueAt) — Pitfall 3 do RESEARCH"
    - "updateOrCreate idempotente com chave Carbon (casa o cast date Y-m-d H:i:s)"

key-files:
  created:
    - app/Models/Holiday.php
    - database/migrations/2026_06_15_000003_create_holidays_table.php
    - database/factories/HolidayFactory.php
    - database/seeders/HolidaySeeder.php
    - app/Services/Expresso/HolidayProvider.php
    - app/Services/Expresso/DatabaseHolidayProvider.php
    - tests/Feature/Relatorios/HolidayCadastroTest.php
    - tests/Unit/Expresso/BusinessDeadlineCalculatorTest.php
  modified:
    - app/Services/Expresso/BusinessDeadlineCalculator.php
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "dueAt/isOverdue MANTIDOS em horas-calendário (prazo SLA/BAP HU-134/144 inalterado — anti-regressão Fases 9/10); prazo em dias úteis fica pendente do critério legal SEDUR"
  - "Provider injetado por construtor OPCIONAL (?HolidayProvider = null): new BusinessDeadlineCalculator (sem container) segue funcionando e degrada honesto (só fins de semana)"
  - "hasOfficialCalendar = existe feriado NÃO-recorrente (municipal específico); nacionais recorrentes do seeder NÃO caracterizam o calendário oficial (pendência SEDUR)"
  - "Seeder semeia só os 8 feriados nacionais de data fixa (Lei 662/1949 e 6.802/1980); lista municipal de Salvador e feriados móveis = pendência SEDUR (degradação honesta, nada inventado)"
  - "updateOrCreate com chave Carbon (não string): o binding casa com o valor do cast date, garantindo idempotência"

patterns-established:
  - "Seam de dias úteis: feriados atrás de HolidayProvider, descontados num único lugar (calculator)"
  - "Duração útil ≠ prazo forward: businessDurationBetween é operação nova, não reusa dueAt"

# Metrics
duration: ~15 min
completed: 2026-06-15
---

# Phase 15 Plan 04: Cadastro de Feriados (HU-137) + Duração em Tempo Útil (HU-129) Summary

**`Holiday` auditado + `HolidayProvider`/`DatabaseHolidayProvider` cacheado + `BusinessDeadlineCalculator::businessDurationBetween` (minutos úteis descontando fim de semana/feriado), com `dueAt`/SLA intactos e degradação honesta sem calendário municipal oficial.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-06-15T19:57:40Z
- **Completed:** 2026-06-15T20:12:30Z
- **Tasks:** 3 (4 commits: T1, T2, T3 + split TDD)
- **Files modified:** 10 (8 criados + 2 alterados)

## Accomplishments

- **Cadastro de feriados (HU-137):** `Holiday` com `HasAuditoria` (RN-002), migration aditiva `holidays` (date unique, `recurring_annually`, `active`), factory e `HolidaySeeder` idempotente que semeia APENAS os 8 feriados nacionais de data fixa — a lista municipal de Salvador é pendência SEDUR (nenhum feriado inventado).
- **Seam de feriados centralizado:** `HolidayProvider` (contrato `isHoliday`/`hasOfficialCalendar`) + `DatabaseHolidayProvider` (lê os ativos com `Cache::remember`, TTL técnico de `sile.relatorios.cache_ttl_segundos`) + binding singleton no `AppServiceProvider`.
- **Duração em tempo útil (HU-129):** `BusinessDeadlineCalculator::businessDurationBetween(from, to): int` — minutos úteis decorridos, dia a dia, descontando fim de semana e feriado ativo (parcial nas pontas); `to <= from ⇒ 0`. É a causa-raiz da distorção do legado (19 dias vs 42h reais), resolvida sem tocar o `dueAt` forward.
- **Anti-regressão:** `dueAt`/`isOverdue` inalterados (horas-calendário); `--filter=AnalysisSlaService` 9/9 e `--filter=IndeferirSemBap` 6/6 verdes graças ao construtor opcional.

## API entregue

```php
interface HolidayProvider {
    public function isHoliday(\DateTimeInterface $date): bool;   // recorrente (mês/dia) OU data exata, só ATIVOS
    public function hasOfficialCalendar(): bool;                 // true só com feriado NÃO-recorrente (municipal) cadastrado
}

// minutos úteis decorridos (descontando sáb/dom + feriado ativo); to<=from ⇒ 0
BusinessDeadlineCalculator::businessDurationBetween(\DateTimeInterface $from, \DateTimeInterface $to): int

// inalterado (prazo SLA/BAP em horas-calendário — anti-regressão Fases 9/10)
BusinessDeadlineCalculator::dueAt(\DateTimeInterface $from, int $hours): Carbon
BusinessDeadlineCalculator::isOverdue(\DateTimeInterface $dueAt, ?\DateTimeInterface $now = null): bool
```

**Como degrada sem feriados oficiais:** o provider injetado é opcional; ausente (ex.: `new BusinessDeadlineCalculator` direto no SLA/BAP), `isHoliday` é sempre `false` e só fins de semana são descontados. Com o provider real mas sem feriado MUNICIPAL cadastrado, `hasOfficialCalendar()` é `false` (ressalva honesta no relatório) e o cálculo desconta fim de semana + os feriados nacionais fixos — nunca um feriado inventado.

## Task Commits

1. **Task 1: Holiday + migration + factory + seeder + HolidayCadastroTest** — `a80c30c` (feat)
2. **Task 2: HolidayProvider + DatabaseHolidayProvider + binding + businessDurationBetween** — `6ec9e9f` (feat)
3. **Task 3: testes cravados de businessDurationBetween** — `94b28a2` (test)

_Tasks 2 e 3 seguiram TDD (RED escrito antes da implementação); o commit do teste é separado do da implementação para respeitar a fronteira de tarefas do plano._

## Files Created/Modified

- `app/Models/Holiday.php` — cadastro auditado (HasAuditoria), casts, scope `active`
- `database/migrations/2026_06_15_000003_create_holidays_table.php` — tabela aditiva (date unique, recurring_annually, active); up/down validados isolados
- `database/factories/HolidayFactory.php` — states `recurring()`/`inactive()`
- `database/seeders/HolidaySeeder.php` — idempotente; só nacionais fixos recorrentes
- `app/Services/Expresso/HolidayProvider.php` — contrato da fonte de feriados
- `app/Services/Expresso/DatabaseHolidayProvider.php` — impl cacheada (TTL técnico de config)
- `app/Services/Expresso/BusinessDeadlineCalculator.php` — `businessDurationBetween` aditivo + provider opcional; dueAt/isOverdue intactos
- `app/Providers/AppServiceProvider.php` — binding singleton HolidayProvider→DatabaseHolidayProvider
- `tests/Feature/Relatorios/HolidayCadastroTest.php` — idempotência, unicidade da data, auditoria
- `tests/Unit/Expresso/BusinessDeadlineCalculatorTest.php` — fim de semana, feriado cadastrado, anti-fachada, intervalo nulo, inativo, end-to-end DB

## Decisions Made

- **`dueAt` permanece em horas-calendário (anti-regressão):** o prazo SLA/BAP (HU-134/144) das Fases 9/10 não muda de comportamento. A HU-129 precisava de DURAÇÃO decorrida (método novo), não do prazo forward — o RESEARCH (Pitfall 3) já alertava. Tornar o PRAZO em dias úteis depende de confirmação SEDUR do critério legal e fica registrado como pendência.
- **Provider opcional no construtor (`?HolidayProvider = null`):** preserva os dois call sites que instanciam `new BusinessDeadlineCalculator` sem argumentos (AnalysisSlaServiceTest e IndeferirSemBapTest, fora do escopo de arquivos deste plano) e dá a degradação honesta (sem provider = sem feriado). Em produção o container injeta o `DatabaseHolidayProvider` pelo binding.
- **`hasOfficialCalendar` por feriado não-recorrente:** os nacionais fixos (recorrentes) são o piso honesto, mas NÃO caracterizam o calendário municipal oficial (pendência SEDUR). A flag é a base da ressalva visível do relatório.
- **`#[Fillable([...])]` em vez de `protected $fillable`:** seguiu a convenção dos cadastros auditados irmãos (RiskTrigger, GeoLayer, Sector) em vez da letra do plano — mesma semântica, consistência com o projeto.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Idempotência do seeder quebrava com chave de data em string**
- **Found during:** Task 1 (teste de idempotência do `HolidaySeeder`)
- **Issue:** `updateOrCreate(['date' => '2026-01-01'], ...)` não casava o valor armazenado pelo cast `date` (`2026-01-01 00:00:00`); a 2ª execução tentava INSERT e estourava o unique de `date`.
- **Fix:** Passar um `Carbon` na chave de busca — o binding do query builder serializa para `Y-m-d H:i:s`, igual ao stored; idempotência garantida.
- **Files modified:** database/seeders/HolidaySeeder.php
- **Verification:** `--filter=HolidayCadastroTest` 5/5 (idempotência + unicidade + auditoria).
- **Committed in:** a80c30c (Task 1)

**2. [Rule 3 - Coordenação com trabalho paralelo] Migration validada isolada + staging seletivo + Pint por caminhos**
- **Found during:** Tasks 1–3 (há trabalho NÃO commitado do usuário — Fase 14 IA/e-mail/sidebar/vitest)
- **Issue:** (a) `migrate` global dispararia as migrations pendentes da Fase 14; (b) `pint --dirty` e `git add` amplos pegariam arquivos do usuário.
- **Fix:** (a) Migration `holidays` aplicada/revertida/reaplicada via `--path` (isolada), deixando as da Fase 14 intocadas; (b) Pint rodado com CAMINHOS EXPLÍCITOS dos meus 10 arquivos; (c) `git add` seletivo por arquivo. Nada da Fase 14 foi staged/commitado.
- **Files modified:** nenhum do usuário (apenas validação/format dos meus)
- **Verification:** `git status` ao fim mostra os arquivos do usuário intactos; meus 3 commits contêm só os 10 arquivos do plano.
- **Committed in:** a80c30c / 6ec9e9f / 94b28a2

---

**Total deviations:** 2 auto-corrigidas (1 bug de idempotência, 1 coordenação com trabalho paralelo)
**Impact on plan:** Sem mudança de escopo. A correção do bug garante a idempotência exigida; a coordenação preserva o trabalho paralelo do usuário.

## Issues Encountered

- **Anti-regressão do construtor:** adicionar uma dependência obrigatória no construtor do `BusinessDeadlineCalculator` quebraria `new BusinessDeadlineCalculator` em 2 testes fora do escopo (AnalysisSlaServiceTest, IndeferirSemBapTest). Resolvido tornando o provider opcional — preserva os call sites E entrega a degradação honesta, sem editar arquivos fora do plano.
- **Concorrência na mesma frente (Fase 15):** STATE.md e arquivos de relatórios estão sob edição de agentes paralelos (15-03/06/08). Edição de STATE.md feita de forma aditiva (subseção própria) para evitar colisão.

## User Setup Required

None - nenhuma configuração externa.

## Next Phase Readiness

- **Pronto para a HU-129 (15-05 tempo por etapa):** `businessDurationBetween` disponível e injetável; o relatório de tempo por etapa aplica o desconto de tempo útil sobre os pares de transições consecutivas.
- **Pendências registradas (sem fachada):**
  - Lista oficial de feriados MUNICIPAIS de Salvador (e móveis) — pendência SEDUR; até lá `hasOfficialCalendar()=false` e a ressalva honesta aparece no relatório.
  - Wiring do `HolidaySeeder` no `DatabaseSeeder` e invalidação de cache do provider na gravação do CRUD de feriados ficam para o plano que entregar o CRUD (fora do escopo de arquivos deste plano; hoje o efeito é limitado ao TTL do cache, padrão HU-014).
  - Prazo SLA/BAP em dias úteis depende do critério legal SEDUR (decisão de negócio); `dueAt` segue em horas-calendário até a confirmação.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
