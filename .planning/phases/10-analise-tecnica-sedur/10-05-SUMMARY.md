---
phase: 10-analise-tecnica-sedur
plan: 05
subsystem: domain
tags: [hu-144, sla, semaforo, business-deadline, settings, hu-014, hu-137-seam, on-the-fly]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    provides: "BusinessDeadlineCalculator (seam de prazo dueAt/isOverdue — HU-137 troca só ali)"
  - phase: 10-analise-tecnica-sedur/10-01
    provides: "parâmetros analise.sla.distribuicao_dias/analise_dias/semaforo.amarelo_percentual + fallback config/sile.php"
  - phase: 10-analise-tecnica-sedur/10-02
    provides: "coluna indexada analysis_due_at + enum AnalysisStage (alinhado a analise.sla.<etapa>_dias)"
provides:
  - "AnalysisSlaService::dueAtFor — materializa o prazo-limite absoluto por etapa (reusa o calculator)"
  - "AnalysisSlaService::statusFor — semáforo on-the-fly (verde/amarelo/vermelho) + tempo restante legível"
  - "AnalysisSlaService::diasDaEtapa — prazo (dias) da etapa, parametrizável com default inline"
  - "SlaStatus (enum verde/amarelo/vermelho + label() pt-BR para o badge)"
affects: [10-07-distribuir-assumir, 10-14-fila, 10-16-badge]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Materializa o prazo absoluto centralizadamente; quem transiciona (10-07) grava o analysis_due_at"
    - "Semáforo calculado ON-THE-FLY a cada leitura — nunca persiste cor"
    - "Reuso do BusinessDeadlineCalculator mantém o seam HU-137 pronto (dias úteis troca só no calculator)"
    - "Settings::get com default inline (distribuicao=2, analise=10, amarelo=80%) — não depende do seeder 10-01"

key-files:
  created:
    - app/Services/Analise/AnalysisSlaService.php
    - app/Services/Analise/SlaStatus.php
    - tests/Feature/Analise/AnalysisSlaServiceTest.php
  modified: []

key-decisions:
  - "statusFor devolve array {status: SlaStatus, restante: string} — direto para Resource/Inertia (fila 10-14 / badge 10-16)"
  - "restante via Carbon::diffForHumans com locale('pt_BR') forçado (app locale é 'en') e syntax relativa a now: 'em 5 dias' / 'há 2 dias'"
  - "Conversão dias→horas (×24) fica neste serviço; a regra de dias úteis (HU-137) entra no calculator sem tocar aqui"
  - "Etapa sem default mapeado cai em FALLBACK_DIAS=10 (jamais prazo zero/infinito) — fallback razoável para etapa futura"

patterns-established:
  - "Serviço de SLA PURO: sem rota, sem binding em provider, sem persistir — só calcula"
  - "Boundary do amarelo é >= limiar; exatamente no vencimento (now == dueAt) ainda é amarelo (isOverdue exige now > dueAt)"

# Metrics
duration: ~18min
completed: 2026-06-14
---

# Phase 10 Plan 05: Fundação do SLA da fila de análise — Summary

**`AnalysisSlaService` materializa o prazo-limite absoluto por etapa (`dueAtFor`) reusando o `BusinessDeadlineCalculator` (seam HU-137) e calcula o semáforo (`statusFor`) verde/amarelo/vermelho + tempo restante ON-THE-FLY, tudo parametrizável (HU-014) com default inline. Serviço puro (sem rota), coberto por `AnalysisSlaServiceTest` 9/9. Zero dependência nova.**

## Performance

- **Tasks:** 2 (commit atômico por task)
- **Files:** 3 criados, 0 modificados (puramente aditivo — não toca a Fase 9 nem outras waves)

## API entregue (contrato para 10-07/10-14/10-16)

### `App\Services\Analise\AnalysisSlaService`

Construtor injeta `BusinessDeadlineCalculator` (auto-resolvível pelo container — sem binding).

| Método | Assinatura | O que faz |
|---|---|---|
| `diasDaEtapa` | `(AnalysisStage $stage): int` | Prazo (dias) da etapa: `Settings::get("analise.sla.{$stage->value}_dias", config(...))` com default inline |
| `dueAtFor` | `(AnalysisStage $stage, ?DateTimeInterface $from = null): Carbon` | `$from ??= now()`; `calculator->dueAt($from, diasDaEtapa × 24)`. Não muta `$from` |
| `statusFor` | `(DateTimeInterface $dueAt, DateTimeInterface $startedAt, ?DateTimeInterface $now = null): array{status: SlaStatus, restante: string}` | Semáforo on-the-fly + tempo restante |

**Lógica do semáforo (`statusFor`)**, nesta ordem:
1. **Vermelho** se `calculator->isOverdue($dueAt, $now)` (estourou: `now > dueAt`).
2. **Amarelo** se a fração decorrida `(now − startedAt) / (dueAt − startedAt)` **>=** o limiar.
3. **Verde** caso contrário.

`restante`: string pt-BR relativa a `$now` — `"em 5 dias"` (no prazo) / `"há 2 dias"` (estourado), via `diffForHumans` com `locale('pt_BR')` forçado e `DIFF_RELATIVE_TO_NOW`.

### `App\Services\Analise\SlaStatus` (enum string)

| Case | value | `label()` (badge pt-BR) |
|---|---|---|
| `Verde` | `verde` | No prazo |
| `Amarelo` | `amarelo` | Em alerta |
| `Vermelho` | `vermelho` | Vencido |

> O "tempo restante" NÃO está no enum — sai como valor separado em `statusFor`.

## Parâmetros lidos (HU-014) e defaults inline

| Chave (Settings) | Fallback config/sile.php | Default inline (call site) |
|---|---|---|
| `analise.sla.distribuicao_dias` | 2 | 2 (via `DEFAULT_DIAS['distribuicao']`) |
| `analise.sla.analise_dias` | 10 | 10 (via `DEFAULT_DIAS['analise']`) |
| `analise.sla.semaforo.amarelo_percentual` | 80 | 80 |

Constantes internas (não-parâmetros): `HORAS_POR_DIA = 24` (conversão dias→horas), `FALLBACK_DIAS = 10` (etapa sem default mapeado). A gravação de um `Parameter` invalida o cache da chave (`Parameter::saved`) — efeito sem deploy, provado nos testes (`analise_dias='5'`, `amarelo_percentual='50'`).

## Seam HU-137 (dias úteis / feriados)

`dueAtFor` delega o cálculo do vencimento ao `BusinessDeadlineCalculator->dueAt()`. Quando a contagem em **dias úteis** entrar (HU-137), a regra de pular fins de semana/feriados muda **só no calculator** — `AnalysisSlaService` e seus call sites (10-07/10-14/10-16) não mudam. Hoje a contagem é honesta em horas-calendário (sem calendário de feriados inventado).

## Como as waves seguintes consomem

- **10-07 (distribuir/assumir):** ao transicionar de etapa, grava `analysis_due_at = $sla->dueAtFor($stage, $stageStartedAt)` e `analysis_stage_started_at` via `forceFill` (colunas fora do fillable, 10-02).
- **10-14 (fila com SLA):** ordena por `analysis_due_at` (índice de 10-02) e, por linha, chama `statusFor($request->analysis_due_at, $request->analysis_stage_started_at)` para o contador/semáforo. Cor nunca vem do banco.
- **10-16 (badge):** usa `SlaStatus::label()` + `restante` no componente da UI.

## Mapa CA → teste (todos verdes)

| HU / RN | Teste |
|---|---|
| HU-144 CA-01 — prazo (due_at) por etapa materializado | `test_due_at_da_distribuicao_e_de_dois_dias_por_default`, `test_due_at_da_analise_e_de_dez_dias_por_default`, `test_due_at_usa_agora_quando_a_origem_e_omitida` |
| HU-144 RN-002 — limiar do semáforo parametrizável | `test_limiar_do_semaforo_e_parametrizavel_sem_deploy` |
| HU-144 — prazo parametrizável sem deploy | `test_due_at_respeita_o_parametro_administravel_sem_deploy` |
| HU-144 — semáforo on-the-fly | `test_semaforo_verde_quando_recem_iniciado`, `test_semaforo_amarelo_ao_atingir_o_limiar`, `test_semaforo_vermelho_quando_estourado`, `test_status_for_devolve_o_tempo_restante_legivel` |
| HU-144 CA-02 / HU-137 — seam dias úteis | reuso do `BusinessDeadlineCalculator` (troca futura só lá) |

## Task Commits

1. **Task 1: SlaStatus + dueAtFor (prazo por etapa, reusa calculator)** — `c7eca14` (feat)
2. **Task 2: statusFor (semáforo on-the-fly + restante)** — `6908def` (feat)

_TDD estrito em ambas: RED confirmado pelo motivo certo (classe/método ausente) antes do GREEN; commit único por task (teste + implementação coesos no mesmo arquivo de domínio)._

## Verification (evidência fresca)

- `vendor/bin/pint --dirty --format agent` → **passed**.
- `php artisan test --compact --filter=AnalysisSlaServiceTest` → **9/9** (12 asserções).
- `php artisan test --compact --exclude-group postgis` → **987/987** (5021 asserções) — baseline intacta com o serviço novo presente.
- Greps de aceite OK: `BusinessDeadlineCalculator` e `analise.sla` (Task 1); `amarelo_percentual` e `isOverdue` (Task 2) presentes em `AnalysisSlaService.php`.

## Deviations from Plan

None — plano executado como escrito. Detalhe de implementação dentro da discrição do plano: `restante` usa `locale('pt_BR')` explícito (o `APP_LOCALE` da app é `en`) com sintaxe relativa a `now` (`em X` / `há X`), em vez de `antes`/`depois` — mais claro para o badge e em conformidade com a regra de idioma.

## Issues Encountered

Waves paralelas (10-04 setores, 10-06 precedentes) tinham arquivos não-commitados no working tree durante a execução. Conforme o protocolo, apenas os arquivos do escopo 10-05 foram staged (commits atômicos, nunca `git add -A`) e o `STATE.md` NÃO foi alterado — consolidação a cargo do orquestrador da fase.

## Next Phase Readiness

- **10-07** materializa `analysis_due_at` na transição usando `dueAtFor`.
- **10-14/10-16** consomem `statusFor` para a fila ordenada e o badge do semáforo.
- Seam HU-137 pronto: dias úteis entra no `BusinessDeadlineCalculator` sem tocar este serviço.
- ZERO dependência nova; serviço puro (sem rota, sem binding).

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
