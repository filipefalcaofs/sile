# Dashboards P0 — Visão geral e SLA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** reformar `GET /gestao` como Visão geral da operação e evoluir `GET /gestao/relatorios/sla` com aging, atrasados por etapa e % de decisões humanas no prazo — sem rota nova e sem Minha Mesa.

**Architecture:** estender `SlaVencimentosService` (estoque/atraso/aging/cumprimento) e `IndicadoresViabilidadeService` (volume operacional, série entrada×saída, decisões por `flow`). Home e SLA compartilham o mesmo `builderSemOrdem` para atrasados. Cumprimento usa um `ReportFilters` à parte (período), para não recortar a lista “agora”.

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, Inertia v3, React 19, ECharts (wrapper `resources/js/components/ui/chart/`).

**Origem:** `docs/superpowers/specs/2026-09-18-dashboards-p0-design.md`

## Global Constraints

- TDD: teste falhando primeiro; RED pelo motivo certo; depois o mínimo para GREEN.
- Agregação em SQL; sem loop PHP sobre processos. Merge de duas séries diárias (entrada/saída) em coleção de pontos já agregados é permitido.
- Taxa sem denominador → `null` (travessão). Contagem vazia → `0`. Sem `delta`.
- `analysis_due_at` + `AnalysisSlaService` são a fonte de prazo. `Carbon::now()` (respeita `setTestNow`); nunca `NOW()` SQL no aging.
- `vendor/bin/pint --format agent` só nos PHP tocados da task. Sem `--dirty`.
- `git add` só dos arquivos da task; nunca `git add -A`.
- Commits em pt-BR, conventional, sem ponto final.
- Sem `Cache::remember`. Sem evento de auditoria novo em `GET /gestao`.
- Fora: Minha Mesa, território espacial, retrabalho, carga, funil, painel admin, setor na home.

## File map

| Arquivo | Papel |
|---|---|
| `app/Services/Relatorios/IndicadoresViabilidadeService.php` | `volumeProtocolos`, `serieFluxo`, `decisoesPorFlow` |
| `app/Services/Relatorios/SlaVencimentosService.php` | `aging`, `atrasadosPorEtapa`, `cumprimento`, `estoqueTotal`, `estoquePorStatus` |
| `app/Http/Controllers/Gestao/RelatorioController.php` | Dois bags de filtro; `resumo` estendido |
| `app/Http/Controllers/Gestao/DashboardController.php` | `kpis.operacao`; remove cadastro |
| `resources/js/pages/gestao/dashboard.tsx` | Visão geral |
| `resources/js/pages/gestao/relatorios/sla.tsx` | Aging + cumprimento + `data_de`/`data_ate` |
| `tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php` | Volume/série/flow |
| `tests/Feature/Relatorios/SlaVencimentosTest.php` | Aging, etapa, cumprimento, HTTP |
| `tests/Feature/Relatorios/DashboardKpisTest.php` | Home operacional |
| `tests/Feature/Dashboard/GestaoDashboardKpisTest.php` | Sem KPIs de cadastro |

---

### Task 1: Volume operacional, série de fluxo e decisões por flow

**Files:**
- Modify: `app/Services/Relatorios/IndicadoresViabilidadeService.php`
- Test: `tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php`

**Interfaces:**
- Consumes: `ReportFilters::from()`/`to()`; `decisoesBase()` (privado, já existe); `baseQuery()` (privado).
- Produces:
  - `volumeProtocolos(ReportFilters $f): int`
  - `serieFluxo(ReportFilters $f): list<array{dia: string, entrada: int, saida: int}>`
  - `decisoesPorFlow(ReportFilters $f): array{total: int, expresso: int, humano: int}`
- `humano` = `flow = analise_tecnica`. `volumeProtocolos` e a série de entrada excluem `rascunho` e `cancelada`. `porPeriodo()` (HU-123) não muda.

- [ ] **Step 1: Escrever os testes que falham**

Acrescentar em `IndicadoresViabilidadeServiceTest` (reusar `protocolada()` e `decisao()`):

```php
#[Test]
public function volume_protocolos_ignora_rascunho_e_cancelada(): void
{
    $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-10 09:00:00')]);
    $this->protocolada([
        'status' => ViabilityRequestStatus::Cancelada,
        'protocoled_at' => Carbon::parse('2026-03-10 10:00:00'),
    ]);
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Rascunho,
        'protocoled_at' => null,
    ]);

    $filtros = ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);

    $this->assertSame(1, $this->service()->volumeProtocolos($filtros));
}

#[Test]
public function serie_fluxo_casa_entrada_e_saida_por_dia(): void
{
    $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-10 09:00:00')]);
    $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-10 16:00:00')]);
    $this->decisao(DecisionOutcome::Deferida, '2026-03-10 11:00:00');
    $this->decisao(DecisionOutcome::Indeferida, '2026-03-11 11:00:00');

    $serie = $this->service()->serieFluxo(ReportFilters::fromArray([
        'data_de' => '2026-03-01',
        'data_ate' => '2026-03-31',
    ]));

    $this->assertSame([
        ['dia' => '2026-03-10', 'entrada' => 2, 'saida' => 1],
        ['dia' => '2026-03-11', 'entrada' => 0, 'saida' => 1],
    ], $serie);
}

#[Test]
public function decisoes_por_flow_separa_expresso_e_humano(): void
{
    $this->decisao(DecisionOutcome::Deferida, '2026-03-10 11:00:00');
    ViabilityDecision::factory()->create([
        'viability_request_id' => $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-08')])->id,
        'flow' => 'analise_tecnica',
        'outcome' => DecisionOutcome::Indeferida,
        'tvl_product_number' => null,
        'decided_at' => Carbon::parse('2026-03-10 12:00:00'),
    ]);

    $totais = $this->service()->decisoesPorFlow(ReportFilters::fromArray([
        'data_de' => '2026-03-01',
        'data_ate' => '2026-03-31',
    ]));

    $this->assertSame(2, $totais['total']);
    $this->assertSame(1, $totais['expresso']);
    $this->assertSame(1, $totais['humano']);
}
```

A factory de decisão defaulta `flow = expresso`. `decisao()` do teste irmão não seta `flow` — a primeira decisão do terceiro teste é expressa.

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact --filter='volume_protocolos_ignora_rascunho_e_cancelada|serie_fluxo_casa_entrada_e_saida_por_dia|decisoes_por_flow_separa_expresso_e_humano'`

Expected: FAIL — métodos inexistentes.

- [ ] **Step 3: Implementar os três métodos**

Em `IndicadoresViabilidadeService`, após `porPeriodo()`:

```php
public function volumeProtocolos(ReportFilters $f): int
{
    return $this->baseOperacao($f)->count();
}

/**
 * @return list<array{dia: string, entrada: int, saida: int}>
 */
public function serieFluxo(ReportFilters $f): array
{
    $entrada = collect($this->baseOperacao($f)
        ->groupBy('dia')
        ->orderBy('dia')
        ->get([
            DB::raw('date(viability_requests.protocoled_at) as dia'),
            DB::raw('count(*) as total'),
        ]))
        ->keyBy(fn ($linha): string => (string) $linha->dia);

    $saida = collect($this->decisoesBase($f)
        ->groupBy('dia')
        ->orderBy('dia')
        ->get([
            DB::raw('date(viability_decisions.decided_at) as dia'),
            DB::raw('count(*) as total'),
        ]))
        ->keyBy(fn ($linha): string => (string) $linha->dia);

    return $entrada->keys()
        ->merge($saida->keys())
        ->unique()
        ->sort()
        ->values()
        ->map(fn (string $dia): array => [
            'dia' => $dia,
            'entrada' => (int) ($entrada->get($dia)?->total ?? 0),
            'saida' => (int) ($saida->get($dia)?->total ?? 0),
        ])
        ->all();
}

/**
 * @return array{total: int, expresso: int, humano: int}
 */
public function decisoesPorFlow(ReportFilters $f): array
{
    $porFlow = $this->decisoesBase($f)
        ->groupBy('viability_decisions.flow')
        ->get([
            'viability_decisions.flow as flow',
            DB::raw('count(*) as total'),
        ])
        ->keyBy(fn ($linha): string => (string) $linha->flow);

    return [
        'total' => $this->decisoesBase($f)->count(),
        'expresso' => (int) ($porFlow->get('expresso')?->total ?? 0),
        'humano' => (int) ($porFlow->get('analise_tecnica')?->total ?? 0),
    ];
}

/**
 * @return Builder<ViabilityRequest>
 */
private function baseOperacao(ReportFilters $f): Builder
{
    return $this->baseQuery($f)->whereNotIn('viability_requests.status', [
        ViabilityRequestStatus::Rascunho->value,
        ViabilityRequestStatus::Cancelada->value,
    ]);
}
```

- [ ] **Step 4: GREEN + Pint**

Run: `php artisan test --compact --filter='volume_protocolos_ignora_rascunho_e_cancelada|serie_fluxo_casa_entrada_e_saida_por_dia|decisoes_por_flow_separa_expresso_e_humano'`

Expected: PASS (3 testes).

Run: `vendor/bin/pint --format agent app/Services/Relatorios/IndicadoresViabilidadeService.php tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Services/Relatorios/IndicadoresViabilidadeService.php tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php
git commit -m "$(cat <<'EOF'
feat: agrega volume, fluxo e decisões por flow na operação

EOF
)"
```

---

### Task 2: Aging, atrasados por etapa e cumprimento

**Files:**
- Modify: `app/Services/Relatorios/SlaVencimentosService.php`
- Test: `tests/Feature/Relatorios/SlaVencimentosTest.php`

**Interfaces:**
- Consumes: `builderSemOrdem(ReportFilters $f)` (privado); `ReportFilters::from()`/`to()`; `Carbon::now()`.
- Produces:
  - `aging(ReportFilters $f): list<array{faixa: string, label: string, total: int}>` — sempre as 5 faixas, nesta ordem: `0_50`, `50_80`, `80_100`, `acima_100`, `indeterminada`.
  - `atrasadosPorEtapa(ReportFilters $f): list<array{etapa: string, label: string, total: int}>` — `distribuicao` e `analise` (0 é fato).
  - `cumprimento(ReportFilters $f): array{dentro_sla: int, com_prazo: int, taxa: float|null, data_de: string|null, data_ate: string|null}`
- Aging e atrasados por etapa **ignoram** `data_de`/`data_ate` (usam só setor/analista via `builderSemOrdem`). Cumprimento **usa** o período do bag (join em `viability_decisions`). Sem `from`/`to`, cumprimento conta o histórico inteiro — o default da janela é injetado na Task 3.

- [ ] **Step 1: Escrever os testes que falham**

No `SlaVencimentosTest`, relógio já é `2026-06-15 12:00:00`. Estender `emAndamento` não é preciso: passar attrs via factory extra.

```php
public function test_aging_classifica_faixas_e_indeterminada(): void
{
    $agora = Carbon::parse('2026-06-15 12:00:00');

    // 25% do prazo (0_50): started 4d atrás, due daqui a 12d.
    $this->emAndamento('VIA-2026-A0001', $agora->copy()->addDays(12)->toDateTimeString());
    ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0001')
        ->update(['analysis_stage_started_at' => $agora->copy()->subDays(4)]);

    // 60% (50_80)
    $this->emAndamento('VIA-2026-A0002', $agora->copy()->addDays(4)->toDateTimeString());
    ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0002')
        ->update(['analysis_stage_started_at' => $agora->copy()->subDays(6)]);

    // 90% (80_100)
    $this->emAndamento('VIA-2026-A0003', $agora->copy()->addDay()->toDateTimeString());
    ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0003')
        ->update(['analysis_stage_started_at' => $agora->copy()->subDays(9)]);

    // >100%
    $this->emAndamento('VIA-2026-A0004', '2026-06-14 12:00');

    // indeterminada: sem started_at
    $this->emAndamento('VIA-2026-A0005', $agora->copy()->addDays(5)->toDateTimeString());
    ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0005')
        ->update(['analysis_stage_started_at' => null]);

    $aging = collect(app(SlaVencimentosService::class)->aging(ReportFilters::fromArray([])))
        ->keyBy('faixa');

    $this->assertSame(1, $aging['0_50']['total']);
    $this->assertSame(1, $aging['50_80']['total']);
    $this->assertSame(1, $aging['80_100']['total']);
    $this->assertSame(1, $aging['acima_100']['total']);
    $this->assertSame(1, $aging['indeterminada']['total']);
}

public function test_atrasados_por_etapa_separa_distribuicao_e_analise(): void
{
    $this->emAndamento('VIA-2026-E0001', '2026-06-14 12:00'); // analise, vencido
    $distribuicao = $this->emAndamento('VIA-2026-E0002', '2026-06-14 10:00');
    $distribuicao->forceFill(['analysis_stage' => 'distribuicao'])->save();

    $noPrazo = $this->emAndamento('VIA-2026-E0003', '2026-06-20 12:00');
    $noPrazo->forceFill(['analysis_stage' => 'distribuicao'])->save();

    $porEtapa = collect(app(SlaVencimentosService::class)->atrasadosPorEtapa(ReportFilters::fromArray([])))
        ->keyBy('etapa');

    $this->assertSame(1, $porEtapa['distribuicao']['total']);
    $this->assertSame(1, $porEtapa['analise']['total']);
}

public function test_cumprimento_exclui_expresso_sem_prazo_e_degrada_taxa(): void
{
    $analista = $this->analista;

    $noPrazo = ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Deferida,
        'protocoled_at' => Carbon::parse('2026-06-01 09:00'),
        'analysis_due_at' => Carbon::parse('2026-06-12 12:00'),
    ]);
    ViabilityDecision::factory()->create([
        'viability_request_id' => $noPrazo->id,
        'flow' => 'analise_tecnica',
        'decided_by_user_id' => $analista->id,
        'outcome' => 'deferida',
        'tvl_product_number' => null,
        'decided_at' => Carbon::parse('2026-06-10 12:00'),
    ]);

    $atrasada = ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Indeferida,
        'protocoled_at' => Carbon::parse('2026-06-01 09:00'),
        'analysis_due_at' => Carbon::parse('2026-06-08 12:00'),
    ]);
    ViabilityDecision::factory()->create([
        'viability_request_id' => $atrasada->id,
        'flow' => 'analise_tecnica',
        'decided_by_user_id' => $analista->id,
        'outcome' => 'indeferida',
        'tvl_product_number' => null,
        'decided_at' => Carbon::parse('2026-06-10 12:00'),
    ]);

    $expressa = ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Deferida,
        'protocoled_at' => Carbon::parse('2026-06-01 09:00'),
        'analysis_due_at' => null,
    ]);
    ViabilityDecision::factory()->create([
        'viability_request_id' => $expressa->id,
        'flow' => 'expresso',
        'decided_by_user_id' => null,
        'outcome' => 'deferida',
        'tvl_product_number' => null,
        'decided_at' => Carbon::parse('2026-06-10 12:00'),
    ]);

    $service = app(SlaVencimentosService::class);
    $ok = $service->cumprimento(ReportFilters::fromArray([
        'data_de' => '2026-06-01',
        'data_ate' => '2026-06-30',
    ]));

    $this->assertSame(2, $ok['com_prazo']);
    $this->assertSame(1, $ok['dentro_sla']);
    $this->assertSame(50.0, $ok['taxa']);

    $vazio = $service->cumprimento(ReportFilters::fromArray([
        'data_de' => '2020-01-01',
        'data_ate' => '2020-01-31',
    ]));
    $this->assertSame(0, $vazio['com_prazo']);
    $this->assertNull($vazio['taxa']);
}

public function test_periodo_nao_altera_aging_nem_resumo(): void
{
    $this->emAndamento('VIA-2026-P0001', '2026-06-14 12:00');

    $service = app(SlaVencimentosService::class);
    $agora = $service->resumo(ReportFilters::fromArray([]));
    $passado = $service->resumo(ReportFilters::fromArray([
        'data_de' => '2020-01-01',
        'data_ate' => '2020-01-31',
    ]));

    $this->assertSame($agora['vencidos'], $passado['vencidos']);
    $this->assertSame(
        $service->aging(ReportFilters::fromArray([]))[3]['total'],
        $service->aging(ReportFilters::fromArray([
            'data_de' => '2020-01-01',
            'data_ate' => '2020-01-31',
        ]))[3]['total'],
    );
}
```

Conferir o valor de `DecisionOutcome` (`deferida`/`indeferida`) no enum antes de gravar — usar o case, não string crua, se o factory exigir enum.

- [ ] **Step 2: RED**

Run: `php artisan test --compact --filter='test_aging_classifica_faixas_e_indeterminada|test_atrasados_por_etapa_separa_distribuicao_e_analise|test_cumprimento_exclui_expresso_sem_prazo_e_degrada_taxa|test_periodo_nao_altera_aging_nem_resumo'`

Expected: FAIL — métodos inexistentes.

- [ ] **Step 3: Implementar**

Em `SlaVencimentosService`:

```php
private const FAIXAS_AGING = [
    '0_50' => '0–50%',
    '50_80' => '50–80%',
    '80_100' => '80–100%',
    'acima_100' => '>100%',
    'indeterminada' => 'Indeterminada',
];

/**
 * @return list<array{faixa: string, label: string, total: int}>
 */
public function aging(ReportFilters $f): array
{
    $agora = Carbon::now()->toDateTimeString();
    $progresso = $this->progressoSql();
    $faixaSql = "CASE
        WHEN analysis_stage_started_at IS NULL THEN 'indeterminada'
        WHEN analysis_due_at <= analysis_stage_started_at THEN 'indeterminada'
        WHEN analysis_due_at <= ? THEN 'acima_100'
        WHEN {$progresso} < 0.5 THEN '0_50'
        WHEN {$progresso} < 0.8 THEN '50_80'
        WHEN {$progresso} < 1.0 THEN '80_100'
        ELSE 'acima_100'
    END";

    $ocorrenciasProgresso = substr_count($faixaSql, $progresso);
    $bindings = array_merge([$agora], array_fill(0, $ocorrenciasProgresso, $agora));

    $totais = (clone $this->builderSemOrdem($f))
        ->selectRaw("{$faixaSql} as faixa, count(*) as total", $bindings)
        ->groupBy('faixa')
        ->pluck('total', 'faixa');

    return collect(self::FAIXAS_AGING)
        ->map(fn (string $label, string $faixa): array => [
            'faixa' => $faixa,
            'label' => $label,
            'total' => (int) ($totais[$faixa] ?? 0),
        ])
        ->values()
        ->all();
}

/**
 * @return list<array{etapa: string, label: string, total: int}>
 */
public function atrasadosPorEtapa(ReportFilters $f): array
{
    $totais = (clone $this->builderSemOrdem($f))
        ->where('analysis_due_at', '<', Carbon::now())
        ->groupBy('analysis_stage')
        ->get([
            'analysis_stage',
            DB::raw('count(*) as total'),
        ])
        ->keyBy(fn ($linha): string => (string) $linha->analysis_stage);

    return collect(AnalysisStage::cases())
        ->map(fn (AnalysisStage $etapa): array => [
            'etapa' => $etapa->value,
            'label' => $etapa->label(),
            'total' => (int) ($totais->get($etapa->value)?->total ?? 0),
        ])
        ->all();
}

/**
 * @return array{dentro_sla: int, com_prazo: int, taxa: float|null, data_de: string|null, data_ate: string|null}
 */
public function cumprimento(ReportFilters $f): array
{
    $base = ViabilityDecision::query()
        ->join('viability_requests', 'viability_requests.id', '=', 'viability_decisions.viability_request_id')
        ->whereNotNull('viability_requests.analysis_due_at')
        ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('viability_decisions.decided_at', '>=', $from))
        ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('viability_decisions.decided_at', '<=', $to))
        ->when($f->setorId(), fn (Builder $q, int $setor): Builder => $q->where('viability_requests.sector_id', $setor))
        ->when($f->analistaId(), fn (Builder $q, int $analista): Builder => $q->where('viability_requests.assigned_user_id', $analista));

    $comPrazo = (clone $base)->count();
    $dentro = (clone $base)
        ->whereColumn('viability_decisions.decided_at', '<=', 'viability_requests.analysis_due_at')
        ->count();

    return [
        'dentro_sla' => $dentro,
        'com_prazo' => $comPrazo,
        'taxa' => $comPrazo > 0 ? round($dentro / $comPrazo * 100, 1) : null,
        'data_de' => $f->from()?->toDateString(),
        'data_ate' => $f->to()?->toDateString(),
    ];
}

private function progressoSql(): string
{
    if (DB::connection()->getDriverName() === 'pgsql') {
        return '(EXTRACT(EPOCH FROM CAST(? AS timestamp)) - EXTRACT(EPOCH FROM analysis_stage_started_at))'
            .' / NULLIF(EXTRACT(EPOCH FROM analysis_due_at) - EXTRACT(EPOCH FROM analysis_stage_started_at), 0)';
    }

    return '(strftime(\'%s\', ?) - strftime(\'%s\', analysis_stage_started_at)) * 1.0'
        .' / NULLIF(strftime(\'%s\', analysis_due_at) - strftime(\'%s\', analysis_stage_started_at), 0)';
}
```

Imports: `AnalysisStage`, `ViabilityDecision`, `Builder`, `DB`.

Se o `selectRaw` + `groupBy('faixa')` falhar no SQLite (alias), trocar `groupBy` por `groupByRaw` com a mesma expressão CASE e os mesmos bindings.

- [ ] **Step 4: GREEN + Pint**

Run: `php artisan test --compact --filter='test_aging_classifica|test_atrasados_por_etapa|test_cumprimento_exclui|test_periodo_nao_altera|test_resumo_agrega|test_builder_respeita|test_linha_projeta'`

Expected: PASS (novos + regressão do resumo/builder/linha).

Run: `vendor/bin/pint --format agent app/Services/Relatorios/SlaVencimentosService.php tests/Feature/Relatorios/SlaVencimentosTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Services/Relatorios/SlaVencimentosService.php tests/Feature/Relatorios/SlaVencimentosTest.php
git commit -m "$(cat <<'EOF'
feat: agrega aging, etapa e cumprimento no relatório de SLA

EOF
)"
```

---

### Task 3: Estoque operacional (home e SLA compartilham atrasados)

**Files:**
- Modify: `app/Services/Relatorios/SlaVencimentosService.php`
- Test: `tests/Feature/Relatorios/SlaVencimentosTest.php`

**Interfaces:**
- Produces:
  - `estoqueTotal(): int`
  - `estoquePorStatus(): list<array{status: string|null, label: string, grupo: string|null, total: int}>` — só `total > 0`
- Universo: status canônico fora de `rascunho`, `cancelada`, `deferida`, `indeferida`. Sem filtro de período.

- [ ] **Step 1: Teste que falha**

```php
public function test_estoque_conta_nao_terminais_e_agrupa_status_operacional(): void
{
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Protocolada,
        'protocoled_at' => now(),
        'analysis_status' => null,
    ]);
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::EmAnalise,
        'protocoled_at' => now(),
        'analysis_status' => 'em_analise',
        'analysis_due_at' => Carbon::parse('2026-06-14 12:00'),
        'analysis_stage_started_at' => Carbon::parse('2026-06-10 12:00'),
    ]);
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::AguardandoBap,
        'protocoled_at' => now(),
        'analysis_status' => null,
    ]);
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Deferida,
        'protocoled_at' => now(),
    ]);
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Rascunho,
        'protocoled_at' => null,
    ]);

    $service = app(SlaVencimentosService::class);

    $this->assertSame(3, $service->estoqueTotal());

    $porStatus = collect($service->estoquePorStatus())->keyBy(fn (array $i): string => $i['status'] ?? 'null');
    $this->assertSame(2, $porStatus['null']['total']);
    $this->assertSame('Sem etapa operacional', $porStatus['null']['label']);
    $this->assertSame(1, $porStatus['em_analise']['total']);
    $this->assertSame(1, $service->resumo(ReportFilters::fromArray([]))['vencidos']);
}
```

- [ ] **Step 2: RED**

Run: `php artisan test --compact --filter=test_estoque_conta_nao_terminais`

Expected: FAIL — métodos inexistentes.

- [ ] **Step 3: Implementar**

```php
private const STATUS_FORA_ESTOQUE = [
    ViabilityRequestStatus::Rascunho->value,
    ViabilityRequestStatus::Cancelada->value,
    ViabilityRequestStatus::Deferida->value,
    ViabilityRequestStatus::Indeferida->value,
];

public function estoqueTotal(): int
{
    return $this->estoqueBase()->count();
}

/**
 * @return list<array{status: string|null, label: string, grupo: string|null, total: int}>
 */
public function estoquePorStatus(): array
{
    return $this->estoqueBase()
        ->groupBy('analysis_status')
        ->orderByDesc('total')
        ->get([
            'analysis_status',
            DB::raw('count(*) as total'),
        ])
        ->map(function ($linha): array {
            $valor = $linha->analysis_status instanceof AnalysisStatus
                ? $linha->analysis_status
                : AnalysisStatus::tryFrom((string) $linha->analysis_status);

            return [
                'status' => $valor?->value,
                'label' => $valor?->label() ?? 'Sem etapa operacional',
                'grupo' => $valor?->grupo(),
                'total' => (int) $linha->total,
            ];
        })
        ->all();
}

/**
 * @return Builder<ViabilityRequest>
 */
private function estoqueBase(): Builder
{
    return ViabilityRequest::query()->whereNotIn('status', self::STATUS_FORA_ESTOQUE);
}
```

Import `AnalysisStatus`. O `groupBy` com enum cast pode devolver o enum na linha — o `instanceof` cobre.

- [ ] **Step 4: GREEN + Pint**

Run: `php artisan test --compact tests/Feature/Relatorios/SlaVencimentosTest.php`

Expected: PASS.

Run: `vendor/bin/pint --format agent app/Services/Relatorios/SlaVencimentosService.php tests/Feature/Relatorios/SlaVencimentosTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Services/Relatorios/SlaVencimentosService.php tests/Feature/Relatorios/SlaVencimentosTest.php
git commit -m "$(cat <<'EOF'
feat: agrega estoque operacional por status de análise

EOF
)"
```

---

### Task 4: HTTP do SLA — resumo estendido e período só no cumprimento

**Files:**
- Modify: `app/Http/Controllers/Gestao/RelatorioController.php` (`slaVencimentos`)
- Modify: `resources/js/pages/gestao/relatorios/sla.tsx`
- Test: `tests/Feature/Relatorios/SlaVencimentosTest.php`

**Interfaces:**
- Consumes: Task 2 (`aging`, `atrasadosPorEtapa`, `cumprimento`).
- Produces: `resumo` com as chaves novas; `filtros` ecoa `setor`, `analista`, `data_de`, `data_ate`.
- Dois bags: estoque = `only(['setor','analista'])`; cumprimento = setor/analista + período (default `relatorios.dashboard.janela_dias` quando as duas datas vêm vazias).
- Export (`?formato=`) continua no bag de estoque — lista inalterada (RN-005).

- [ ] **Step 1: Testes HTTP que falham**

```php
public function test_endpoint_entrega_aging_etapa_e_cumprimento_com_periodo_default(): void
{
    $this->emAndamento('VIA-2026-000030', '2026-06-14 12:00');

    $this->actingAs($this->consultor(), 'gestao')
        ->get('/gestao/relatorios/sla')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/relatorios/sla', false)
            ->has('resumo.aging', 5)
            ->has('resumo.atrasados_por_etapa', 2)
            ->where('resumo.vencidos', 1)
            ->where('resumo.cumprimento.data_de', '2026-05-16')
            ->where('resumo.cumprimento.data_ate', '2026-06-15')
            ->has('filtros.data_de')
            ->has('filtros.data_ate'));
}

public function test_query_de_periodo_nao_muda_vencidos_da_lista(): void
{
    $this->emAndamento('VIA-2026-000031', '2026-06-14 12:00');

    $this->actingAs($this->consultor(), 'gestao')
        ->get('/gestao/relatorios/sla?data_de=2020-01-01&data_ate=2020-01-31')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumo.em_andamento', 1)
            ->where('resumo.vencidos', 1)
            ->has('relatorio.data', 1)
            ->where('resumo.cumprimento.com_prazo', 0)
            ->where('resumo.cumprimento.taxa', null));
}
```

Default da janela: `Settings::get('relatorios.dashboard.janela_dias', 30)` → de `2026-06-15` menos 30 dias = `2026-05-16`. Confirmar no teste: `now()->subDays(30)->toDateString()`.

- [ ] **Step 2: RED**

Run: `php artisan test --compact --filter='test_endpoint_entrega_aging|test_query_de_periodo_nao_muda'`

Expected: FAIL — `resumo.aging` ausente; `cumprimento` ausente.

- [ ] **Step 3: Controller**

Substituir o corpo de `slaVencimentos` (manter o branch `?formato=` como está, com `$filtros` = `only` setor/analista implícito no builder atual — o request pode trazer datas, mas `builder()` já as ignora). Montar os dois bags **antes** do render:

```php
$recebidos = $request->toReportFilters();
$estoque = ReportFilters::fromArray($recebidos->only(['setor', 'analista']));
$cumprimentoFiltros = $this->filtrosCumprimento($recebidos);

// ... formato: continuar exportando com $estoque (não o período)

return Inertia::render('gestao/relatorios/sla', [
    'resumo' => array_merge($this->slaVencimentos->resumo($estoque), [
        'aging' => $this->slaVencimentos->aging($estoque),
        'atrasados_por_etapa' => $this->slaVencimentos->atrasadosPorEtapa($estoque),
        'cumprimento' => $this->slaVencimentos->cumprimento($cumprimentoFiltros),
    ]),
    'relatorio' => $this->slaVencimentos
        ->builder($estoque)
        ->paginate($this->perPage($request))
        ->withQueryString()
        ->through(fn (ViabilityRequest $r): array => $this->slaVencimentos->linha($r)),
    'setores' => /* inalterado */,
    'analistas' => /* inalterado */,
    'filtros' => array_merge(
        $estoque->only(['setor', 'analista']),
        [
            'data_de' => $cumprimentoFiltros->from()?->toDateString(),
            'data_ate' => $cumprimentoFiltros->to()?->toDateString(),
        ],
    ),
    'perPageOptions' => self::PER_PAGE_OPTIONS,
]);
```

Método privado no controller:

```php
private function filtrosCumprimento(ReportFilters $recebidos): ReportFilters
{
    $bag = $recebidos->only(['setor', 'analista', 'data_de', 'data_ate']);

    if ($recebidos->from() === null && $recebidos->to() === null) {
        $janela = (int) Settings::get(
            'relatorios.dashboard.janela_dias',
            config('sile.relatorios.dashboard.janela_dias', 30),
        );
        $bag['data_de'] = now()->subDays($janela)->toDateString();
        $bag['data_ate'] = now()->toDateString();
    }

    return ReportFilters::fromArray($bag);
}
```

Import `Settings`. `auditarConsulta` continua com `$recebidos` (o que o usuário mandou) ou com o bag efetivo — preferir `$recebidos->aplicados()` + as datas efetivas do cumprimento, para a trilha mostrar o recorte real:

```php
$this->auditarConsulta(
    'consulta-sla-vencimentos',
    'Consulta do relatório de SLA e vencimentos',
    ReportFilters::fromArray(array_merge(
        $recebidos->aplicados(),
        $cumprimentoFiltros->only(['data_de', 'data_ate']),
    )),
);
```

- [ ] **Step 4: Página SLA**

Em `sla.tsx`:

1. Estender `ResumoSla` e `FiltrosAplicados` / `FiltrosForm` com os tipos da spec §6.3.
2. Form inicial: `data_de`/`data_ate` a partir de `filtros`.
3. `visitar` envia as quatro chaves quando preenchidas.
4. Após os 3 KPIs atuais, grid:
   - `KpiCard` “Decisões no prazo” = `formatarPercentual(resumo.cumprimento.taxa)` com note `de ${com_prazo} decisões humanas com prazo` ou `sem decisões humanas com prazo`.
   - Card ECharts barras empilhadas de `resumo.aging` (uma categoria “Estoque”, quatro+indeterminada stacks). Vazio se `aging.every(f => f.total === 0)` → `GraficoSemDados` local (copiar o bloco da home: div “Sem dados no período.”).
   - Card barras `atrasados_por_etapa`.
5. No form, dois `Input type="date"` (mesmo padrão de `indicadores.tsx`) com `aria-label` “Período de” / “Período até”, **antes** dos selects de setor/analista. Texto de ajuda: “O período recorta só o cumprimento. Estoque, aging e lista são o momento atual.”

Não alterar colunas da tabela nem o `ExportMenu`.

- [ ] **Step 5: GREEN**

Run: `php artisan test --compact tests/Feature/Relatorios/SlaVencimentosTest.php`

Expected: PASS (incluindo 403 e CSV).

Run: `vendor/bin/pint --format agent app/Http/Controllers/Gestao/RelatorioController.php`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Gestao/RelatorioController.php resources/js/pages/gestao/relatorios/sla.tsx tests/Feature/Relatorios/SlaVencimentosTest.php
git commit -m "$(cat <<'EOF'
feat: expõe aging e cumprimento na tela de SLA

EOF
)"
```

---

### Task 5: Home — controller e testes

**Files:**
- Modify: `app/Http/Controllers/Gestao/DashboardController.php`
- Modify: `tests/Feature/Relatorios/DashboardKpisTest.php`
- Modify: `tests/Feature/Dashboard/GestaoDashboardKpisTest.php`

**Interfaces:**
- Consumes: Task 1 (`volumeProtocolos`, `serieFluxo`, `decisoesPorFlow`) + Task 2/3 (`resumo`, `estoqueTotal`, `estoquePorStatus`) + `ExpressoQuedaService::taxaRespostaExpressa`.
- Produces: `kpis.operacao` no shape da spec §5.4, ou `null` sem `consultar-relatorios`. Remove `cnaes`, `usuarios`, `perfis`, `acessos`, `relatorios`.

- [ ] **Step 1: Reescrever os testes (RED)**

`DashboardKpisTest` — trocar asserts de `kpis.relatorios.*` por `kpis.operacao.*`. Manter o dataset (protocoladas + decisões + transições do expresso). Ajustar:

```php
->where('kpis.operacao.protocolos', 3)
->where('kpis.operacao.decisoes.total', 4)
->where('kpis.operacao.decisoes.expresso', 2)
->where('kpis.operacao.decisoes.humano', 2)
->where('kpis.operacao.taxa_expressa', fn ($taxa) => (float) $taxa === 50.0)
->where('kpis.operacao.meta_expressa', null)
->missing('kpis.operacao.delta')
->missing('kpis.relatorios')
```

O teste `bloco_relatorios_e_null_sem_a_permissao` vira `operacao_e_null_sem_consultar_relatorios` (`kpis.operacao`, null).

O teste de janela vazia:

```php
->where('kpis.operacao.protocolos', 0)
->where('kpis.operacao.decisoes.total', 0)
->where('kpis.operacao.taxa_expressa', null)
->where('kpis.operacao.serie_fluxo', [])
->where('kpis.operacao.estoque_total', 0)
->where('kpis.operacao.atrasados', 0)
```

Acrescentar:

```php
#[Test]
public function atrasados_da_home_coincidem_com_resumo_do_sla(): void
{
    Carbon::setTestNow(self::AGORA);

    $vencido = ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::EmAnalise,
        'protocoled_at' => Carbon::parse(self::DENTRO_DA_JANELA),
        'analysis_due_at' => Carbon::parse('2026-06-14 12:00'),
        'analysis_stage' => 'analise',
        'analysis_stage_started_at' => Carbon::parse('2026-06-10 12:00'),
    ]);
    $this->assertNotNull($vencido->id);

    $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    $vencidosSla = app(\App\Services\Relatorios\SlaVencimentosService::class)
        ->resumo(\App\Services\Relatorios\ReportFilters::fromArray([]))['vencidos'];

    $this->actingAs($gestor, 'gestao')
        ->get('/gestao')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.operacao.atrasados', $vencidosSla)
            ->where('kpis.operacao.atrasados', 1));
}

#[Test]
public function rascunho_nao_entra_em_protocolos(): void
{
    Carbon::setTestNow(self::AGORA);
    ViabilityRequest::factory()->create([
        'status' => ViabilityRequestStatus::Rascunho,
        'protocoled_at' => Carbon::parse(self::DENTRO_DA_JANELA),
    ]);

    $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

    $this->actingAs($gestor, 'gestao')
        ->get('/gestao')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('kpis.operacao.protocolos', 0));
}
```

`GestaoDashboardKpisTest`:

```php
public function test_administrador_nao_recebe_kpis_de_cadastro(): void
{
    $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

    $this->actingAs($admin, 'gestao')
        ->get('/gestao')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/dashboard')
            ->missing('kpis.cnaes')
            ->missing('kpis.usuarios')
            ->missing('kpis.perfis')
            ->missing('kpis.acessos')
            ->has('kpis.operacao'));
}

public function test_analista_recebe_operacao_nula(): void
{
    $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

    $this->actingAs($analista, 'gestao')
        ->get('/gestao')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.operacao', null)
            ->missing('kpis.cnaes'));
}
```

Apagar o teste antigo que asserta `kpis.cnaes.ativos`.

- [ ] **Step 2: RED**

Run: `php artisan test --compact tests/Feature/Relatorios/DashboardKpisTest.php tests/Feature/Dashboard/GestaoDashboardKpisTest.php`

Expected: FAIL — shape antigo ainda é o que o controller envia.

- [ ] **Step 3: Reescrever o controller**

```php
public function __invoke(
    Request $request,
    IndicadoresViabilidadeService $indicadores,
    ExpressoQuedaService $quedas,
    SlaVencimentosService $sla,
): Response {
    $user = $request->user();

    return Inertia::render('gestao/dashboard', [
        'kpis' => [
            'operacao' => $user->can('consultar-relatorios')
                ? $this->operacao($indicadores, $quedas, $sla)
                : null,
        ],
    ]);
}

/**
 * @return array<string, mixed>
 */
private function operacao(
    IndicadoresViabilidadeService $indicadores,
    ExpressoQuedaService $quedas,
    SlaVencimentosService $sla,
): array {
    $janelaDias = (int) Settings::get('relatorios.dashboard.janela_dias', 30);
    $filtros = ReportFilters::fromArray([
        'data_de' => now()->subDays($janelaDias)->format('Y-m-d'),
        'data_ate' => now()->format('Y-m-d'),
    ]);
    $estoqueFiltros = ReportFilters::fromArray([]);
    $expressa = $quedas->taxaRespostaExpressa($filtros);
    $decisoes = $indicadores->decisoesPorFlow($filtros);

    return [
        'janela_dias' => $janelaDias,
        'protocolos' => $indicadores->volumeProtocolos($filtros),
        'decisoes' => $decisoes,
        'estoque_total' => $sla->estoqueTotal(),
        'atrasados' => $sla->resumo($estoqueFiltros)['vencidos'],
        'taxa_expressa' => $expressa['taxa'],
        'meta_expressa' => $expressa['meta'],
        'serie_fluxo' => $indicadores->serieFluxo($filtros),
        'estoque_por_status' => $sla->estoquePorStatus(),
    ];
}
```

Remover imports mortos (`AccessLog`, `Cnae`, `User`, `Role`, `Permission`, `TempoAnaliseService`).

- [ ] **Step 4: GREEN + Pint**

Run: `php artisan test --compact tests/Feature/Relatorios/DashboardKpisTest.php tests/Feature/Dashboard/GestaoDashboardKpisTest.php tests/Feature/Relatorios/SlaVencimentosTest.php tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php`

Expected: PASS.

Run: `vendor/bin/pint --format agent app/Http/Controllers/Gestao/DashboardController.php tests/Feature/Relatorios/DashboardKpisTest.php tests/Feature/Dashboard/GestaoDashboardKpisTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Gestao/DashboardController.php tests/Feature/Relatorios/DashboardKpisTest.php tests/Feature/Dashboard/GestaoDashboardKpisTest.php
git commit -m "$(cat <<'EOF'
feat: troca a home da gestão por KPIs operacionais

EOF
)"
```

---

### Task 6: Home — página React

**Files:**
- Modify: `resources/js/pages/gestao/dashboard.tsx`

**Interfaces:**
- Consumes: `kpis.operacao` da Task 5.
- Produces: título “Visão geral da operação”; 5 cards; dois gráficos; atalhos de módulo. Sem pizza de risco, sem KPIs de cadastro, sem “Sessão atual”.

- [ ] **Step 1: Substituir a página**

Manter `GestaoLayout`, `KpiCard`, `Chart`, `Card`, ícones já importados no arquivo. Shape:

```ts
interface DecisoesKpi {
    total: number;
    expresso: number;
    humano: number;
}

interface SerieFluxoPonto {
    dia: string;
    entrada: number;
    saida: number;
}

interface EstoqueStatusItem {
    status: string | null;
    label: string;
    grupo: string | null;
    total: number;
}

interface OperacaoKpis {
    janela_dias: number;
    protocolos: number;
    decisoes: DecisoesKpi;
    estoque_total: number;
    atrasados: number;
    taxa_expressa: number | null;
    meta_expressa: number | null;
    serie_fluxo: SerieFluxoPonto[];
    estoque_por_status: EstoqueStatusItem[];
}

interface DashboardProps {
    kpis: { operacao: OperacaoKpis | null };
}
```

Cards (só se `operacao`):

| key | href |
|---|---|
| protocolos | `` `/gestao/processos?data_de=${de}&data_ate=${ate}` `` — `de`/`ate` = hoje − `janela_dias` / hoje, `YYYY-MM-DD` no cliente a partir de `janela_dias` **ou** preferir links sem datas calculadas no client se a janela puder divergir: usar o note “nos últimos N dias” e link `/gestao/processos` se as datas não vierem nas props. Spec pede querystring: calcular com a mesma regra `subDays(janela_dias)` no render (aceitável; o número do card já veio do server). |
| decisoes | `/gestao/relatorios/indicadores` |
| estoque | `/gestao/processos` |
| atrasados | `/gestao/relatorios/sla` |
| expressa | `/gestao/relatorios/quedas` |

Envolver cada `KpiCard` em `Link` com `className` que não quebre o card (o `KpiCard` atual não tem `href`; o padrão da home velha não linkava — envolver o card).

Gráfico 1 — `serie_fluxo` linha dupla (`entrada`, `saída`), `minInterval: 1`.
Gráfico 2 — barras horizontais `estoque_por_status` (`yAxis` category = `label`, `xAxis` value). Clique: `router.visit(\`/gestao/processos?analysis_status=${encodeURIComponent(status)}\`)` só quando `status !== null`; fatia nula → `/gestao/processos` sem o param.

`formatarPercentual` e `GraficoSemDados` permanecem. Remover `volumeChartOption` de uma série, `riscoChartOption`, bloco `indicators` de cadastro, card “Sessão atual”, `formatarMinutos`.

`PageHeader title="Visão geral da operação"`. `Head title="Visão geral da operação"`.

Atalhos de módulo: o array `modules` atual, inalterado, abaixo dos gráficos.

- [ ] **Step 2: Conferir que os testes Inertia ainda passam**

Run: `php artisan test --compact tests/Feature/Relatorios/DashboardKpisTest.php tests/Feature/Dashboard/GestaoDashboardKpisTest.php`

Expected: PASS (Inertia não monta o React; o shape é o que importa).

Não há teste de componente React no projeto para esta página — a prova é o assertInertia + o arquivo compilável. Se `npm` estiver no PATH: `npx tsc --noEmit` (ou o script de types do `package.json`). Falha de tipo = corrigir o shape.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/gestao/dashboard.tsx
git commit -m "$(cat <<'EOF'
feat: redesenha a home da gestão como visão da operação

EOF
)"
```

---

### Task 7: Verificação cruzada

**Files:** nenhum novo — só evidência.

- [ ] **Step 1: Suíte das superfícies tocadas**

Run: `php artisan test --compact tests/Feature/Relatorios/SlaVencimentosTest.php tests/Feature/Relatorios/DashboardKpisTest.php tests/Feature/Dashboard/GestaoDashboardKpisTest.php tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php tests/Feature/Routing/EnvironmentAccessTest.php`

Expected: PASS. `EnvironmentAccessTest` ainda asserta `gestao/dashboard` para admin.

- [ ] **Step 2: Conferir o spec**

Abrir `docs/superpowers/specs/2026-09-18-dashboards-p0-design.md` e marcar mentalmente §§5–9. Tudo que está lá tem task. O que não tem (Minha Mesa, setor na home, cache) permanece fora.

- [ ] **Step 3: Sem commit obrigatório** — só se houver ajuste residual de Pint ou tipo. Se houver, commit `style:` / `fix:` pontual nos arquivos já da feature.

---

## Spec coverage (self-review)

| Spec | Task |
|---|---|
| Home 5 KPIs + entrada×saída + estoque barras | 1, 3, 5, 6 |
| Sem KPIs admin / sessão / pizza risco | 5, 6 |
| `operacao` null sem permissão | 5 |
| Atrasados home ≡ `resumo.vencidos` | 3, 5 |
| Aging 5 faixas + indeterminada | 2, 4 |
| Atrasados por etapa | 2, 4 |
| Cumprimento exclui expressa sem prazo; taxa null | 2, 4 |
| Período não mexe estoque/lista | 2, 4 |
| Sem rota nova / sem cache / sem audit na home | 4, 5 |
| Drill-down rotas existentes | 6 |
| Testes 1–13 | 1–5 (13 = has Inertia no 4 e 5) |
