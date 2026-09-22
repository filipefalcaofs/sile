# Distribuição pelo Apoio na Caixa do Setor — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Caixa do setor com abas "Para distribuir"/"Distribuídos", seleção múltipla com tramitação em lote pelo Apoio e redistribuição de analista bloqueada após a conclusão da análise.

**Architecture:** O fluxo Motor → Caixa do setor → Apoio → Caixa da analista já existe (ViabilityRequest + DistribuicaoService + perfil apoio). Este plano adiciona: (1) regra única de "permite redistribuição" no enum `AnalysisStatus`; (2) filtro por visão + contadores + flag `pode_redistribuir` no `CaixaSetorController`; (3) `DistribuicaoService::redistribuir` (sem recalcular SLA) + rota; (4) UI com abas, checkboxes e ação em lote reutilizando a rota de distribuição existente.

**Tech Stack:** Laravel 13 + PHPUnit 12 (feature tests, `LazilyRefreshDatabase`), Inertia v3 + React 19 + Tailwind 4.

**Spec:** `docs/superpowers/specs/2026-09-22-distribuicao-apoio-caixa-setor-design.md`

## Global Constraints

- TDD estrito (Red-Green-Refactor): nenhum código de produção sem teste falhando antes.
- Commits por task, conventional commits em pt-BR, minúsculas, sem ponto final.
- O repo tem mudanças não relacionadas não commitadas: **commitar apenas os arquivos da task** (`git add` com caminhos explícitos, nunca `git add -A`).
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- Redistribuição NÃO recalcula `analysis_due_at` — o prazo é do processo, não da pessoa.
- Redistribuição bloqueada quando `analysis_status` é `analise_concluida` ou posterior (convite/vistoria) — regra única em `AnalysisStatus::permiteRedistribuicao()`.
- Nenhum item novo de menu: é a mesma tela `/gestao/caixa-setor`.
- UI, mensagens e nomes de testes em pt-BR; código (variáveis, métodos) em inglês.
- Auditoria síncrona por processo via `AuditService` (padrão já estabelecido no `DistribuicaoService`).

---

### Task 1: Regra única `AnalysisStatus::permiteRedistribuicao()`

**Files:**
- Modify: `app/Enums/AnalysisStatus.php`
- Test: `tests/Unit/Enums/AnalysisStatusTest.php` (criar com `php artisan make:test --unit Enums/AnalysisStatusTest --no-interaction`)

**Interfaces:**
- Produces: `AnalysisStatus::permiteRedistribuicao(): bool` — `true` para `ParaDistribuir`, `Encaminhado`, `Analisar`, `EmAnalise`; `false` para `AnaliseConcluida`, `EmConvite`, `ConviteRespondido`, `ConviteCancelado`, `ConviteExpirado`, `Vistoriar`, `Vistoriado`. Consumido pela Task 2 (flag `pode_redistribuir` no payload) e pela Task 3 (bloqueio no service).

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Unit/Enums/AnalysisStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\AnalysisStatus;
use PHPUnit\Framework\TestCase;

/**
 * Regra única da redistribuição (spec 2026-09-22): a troca da analista
 * responsável pelo apoio/gestor só é permitida ANTES da conclusão da análise.
 */
class AnalysisStatusTest extends TestCase
{
    public function test_redistribuicao_permitida_antes_da_conclusao(): void
    {
        $permitidos = [
            AnalysisStatus::ParaDistribuir,
            AnalysisStatus::Encaminhado,
            AnalysisStatus::Analisar,
            AnalysisStatus::EmAnalise,
        ];

        foreach ($permitidos as $status) {
            $this->assertTrue($status->permiteRedistribuicao(), "{$status->value} deveria permitir redistribuição.");
        }
    }

    public function test_redistribuicao_bloqueada_apos_conclusao_ou_em_convite_ou_vistoria(): void
    {
        $bloqueados = [
            AnalysisStatus::AnaliseConcluida,
            AnalysisStatus::EmConvite,
            AnalysisStatus::ConviteRespondido,
            AnalysisStatus::ConviteCancelado,
            AnalysisStatus::ConviteExpirado,
            AnalysisStatus::Vistoriar,
            AnalysisStatus::Vistoriado,
        ];

        foreach ($bloqueados as $status) {
            $this->assertFalse($status->permiteRedistribuicao(), "{$status->value} não deveria permitir redistribuição.");
        }
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Unit/Enums/AnalysisStatusTest.php`
Expected: FAIL — `Error: Call to undefined method App\Enums\AnalysisStatus::permiteRedistribuicao()`

- [ ] **Step 3: Implementar o método no enum**

Em `app/Enums/AnalysisStatus.php`, adicionar após o método `grupo()`:

```php
    /**
     * A redistribuição (troca da analista responsável pelo apoio/gestor) só é
     * permitida antes da conclusão da análise — depois de concluída, ou já em
     * convite/vistoria, o responsável está consolidado.
     */
    public function permiteRedistribuicao(): bool
    {
        return match ($this) {
            self::ParaDistribuir, self::Encaminhado, self::Analisar, self::EmAnalise => true,
            self::AnaliseConcluida, self::EmConvite, self::ConviteRespondido,
            self::ConviteCancelado, self::ConviteExpirado, self::Vistoriar, self::Vistoriado => false,
        };
    }
```

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Unit/Enums/AnalysisStatusTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/AnalysisStatus.php tests/Unit/Enums/AnalysisStatusTest.php
git commit -m "feat: adiciona regra de redistribuição ao status da análise"
```

---

### Task 2: Visões (abas) no `CaixaSetorController@index`

**Files:**
- Modify: `app/Http/Controllers/Gestao/CaixaSetorController.php` (método `index`, linhas 44-117)
- Test: `tests/Feature/Analise/CaixaSetorTest.php`

**Interfaces:**
- Consumes: `AnalysisStatus::permiteRedistribuicao(): bool` (Task 1).
- Produces: props Inertia `visao: 'para_distribuir'|'distribuidos'`, `contadores: {para_distribuir: int, distribuidos: int}` e, em cada item de `processos.data`, `pode_redistribuir: bool`. A Task 4 (frontend) consome exatamente esses nomes.

- [ ] **Step 1: Escrever os testes que falham**

Em `tests/Feature/Analise/CaixaSetorTest.php`, adicionar o import `use App\Enums\AnalysisStatus;` e estes dois testes ao final da classe:

```php
    public function test_visao_padrao_lista_apenas_processos_sem_responsavel(): void
    {
        // Aba "Para distribuir" (padrão): só o que ainda não tem analista.
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $livre = $this->processoNaCaixa($setor);
        $atribuido = $this->processoNaCaixa($setor);
        $atribuido->forceFill(['assigned_user_id' => $analista->id, 'assigned_at' => now()])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $ids = collect($props['processos']['data'])->pluck('id')->all();

        $this->assertContains($livre->id, $ids);
        $this->assertNotContains($atribuido->id, $ids, 'Processo já distribuído não aparece na aba Para distribuir.');
        $this->assertSame('para_distribuir', $props['visao']);
        $this->assertSame(1, $props['contadores']['para_distribuir']);
        $this->assertSame(1, $props['contadores']['distribuidos']);
    }

    public function test_visao_distribuidos_lista_atribuidos_com_flag_de_redistribuicao(): void
    {
        // Aba "Distribuídos": só atribuídos; pode_redistribuir é falso após a
        // conclusão da análise (regra única AnalysisStatus::permiteRedistribuicao).
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $emAnalise = $this->processoNaCaixa($setor);
        $emAnalise->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        $concluido = $this->processoNaCaixa($setor);
        $concluido->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        $livre = $this->processoNaCaixa($setor);

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=distribuidos')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $itens = collect($props['processos']['data']);

        $this->assertSame('distribuidos', $props['visao']);
        $this->assertFalse($itens->contains('id', $livre->id), 'Processo sem responsável não aparece na aba Distribuídos.');
        $this->assertTrue($itens->firstWhere('id', $emAnalise->id)['pode_redistribuir']);
        $this->assertFalse($itens->firstWhere('id', $concluido->id)['pode_redistribuir']);
    }
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php --filter=visao`
Expected: FAIL — os dois testes falham (`$props['visao']` indefinido / processo atribuído presente na lista padrão).

- [ ] **Step 3: Implementar as visões no controller**

Em `app/Http/Controllers/Gestao/CaixaSetorController.php`, substituir o corpo do `index` (da linha do `$sectorIds` até o `return Inertia::render`) por:

```php
        $sectorIds = $request->user()->sectors()->pluck('sectors.id');

        // Visões da caixa (abas): "para_distribuir" (padrão — sem responsável)
        // e "distribuidos" (acompanhamento, com a analista atribuída).
        $visao = $request->input('visao') === 'distribuidos' ? 'distribuidos' : 'para_distribuir';

        $base = ViabilityRequest::query()
            ->whereIn('sector_id', $sectorIds)
            ->where('status', ViabilityRequestStatus::EmAnalise->value);

        $contadores = [
            'para_distribuir' => (clone $base)->whereNull('assigned_user_id')->count(),
            'distribuidos' => (clone $base)->whereNotNull('assigned_user_id')->count(),
        ];

        $processos = $base
            ->when(
                $visao === 'para_distribuir',
                fn ($query) => $query->whereNull('assigned_user_id'),
                fn ($query) => $query->whereNotNull('assigned_user_id'),
            )
            ->with(['company', 'sector:id,name', 'assignedTo:id,name'])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => [
                'id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
                // Processo SEDUR e endereço (usabilidade SEDUR 19/09, item 05).
                'bap' => $processo->external_reference,
                'imovel' => implode(' - ', array_filter([
                    trim(implode(', ', array_filter([$processo->address_street, $processo->address_number]))),
                    $processo->address_neighborhood,
                ])),
                'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
                'cnpj' => $processo->company?->formatted_cnpj,
                'status' => $processo->status->value,
                'status_label' => $processo->status->label(),
                'analysis_stage' => $processo->analysis_stage?->value,
                'analysis_stage_label' => $processo->analysis_stage?->label(),
                'sector' => $processo->sector?->name,
                'assigned_user_id' => $processo->assigned_user_id,
                'assigned_to' => $processo->assignedTo?->name,
                'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
                // Redistribuir só antes da conclusão (regra única do enum).
                'pode_redistribuir' => $processo->assigned_user_id !== null
                    && ($processo->analysis_status?->permiteRedistribuicao() ?? false),
            ]);
```

Manter intactos o bloco `$podeDistribuir`/`$podeAssumir`/`$analistas` e o `$this->audit->log(...)`. No `return Inertia::render`, adicionar as duas props novas:

```php
        return Inertia::render('gestao/caixa-setor/index', [
            'processos' => $processos,
            'visao' => $visao,
            'contadores' => $contadores,
            'filtros' => [
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'podeDistribuir' => $podeDistribuir,
            'podeAssumir' => $podeAssumir,
            'analistas' => $analistas,
        ]);
```

Atualizar também o docblock do `index` para mencionar as duas visões.

- [ ] **Step 4: Rodar e confirmar o GREEN (arquivo inteiro)**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS (todos — os 12 existentes + 2 novos = 14). Atenção: os testes existentes usam processos sem responsável, então continuam verdes na visão padrão.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/CaixaSetorController.php tests/Feature/Analise/CaixaSetorTest.php
git commit -m "feat: adiciona visões para distribuir e distribuídos na caixa do setor"
```

---

### Task 3: Redistribuição (service + rota + controller)

**Files:**
- Modify: `app/Services/Analise/DistribuicaoException.php`
- Modify: `app/Services/Analise/DistribuicaoService.php`
- Modify: `app/Http/Controllers/Gestao/CaixaSetorController.php` (novo método `redistribuir`)
- Modify: `routes/gestao.php` (grupo `caixa-setor`, ~linha 639-650)
- Test: `tests/Feature/Analise/DistribuicaoServiceTest.php` e `tests/Feature/Analise/CaixaSetorTest.php`

**Interfaces:**
- Consumes: `AnalysisStatus::permiteRedistribuicao(): bool` (Task 1).
- Produces: `DistribuicaoService::redistribuir(ViabilityRequest $request, User $novaAnalista, User $ator): void` (lança `DistribuicaoException`); `DistribuicaoException::analiseConcluida(ViabilityRequest $request): self`; rota `POST /gestao/caixa-setor/redistribuir` (name `gestao.caixa-setor.redistribuir`, middleware `permission:distribuir-processos`) aceitando `request_ids[]` (um item) + `analista_id` — mesmo payload do `distribuir`, reutilizando `DistribuirProcessoRequest`. A Task 4 consome essa rota.

- [ ] **Step 1: Escrever os testes que falham (service)**

Em `tests/Feature/Analise/DistribuicaoServiceTest.php`, adicionar o import `use App\Enums\AnalysisStatus;`, este helper e estes três testes ao final da classe:

```php
    /**
     * Processo já atribuído a uma analista, em etapa de análise com prazo
     * corrente — o estado de onde a redistribuição parte.
     */
    private function processoAtribuido(Sector $sector, User $analista, AnalysisStatus $status = AnalysisStatus::EmAnalise): ViabilityRequest
    {
        $request = $this->processoNaCaixa($sector);

        $request->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now()->subDay(),
            'analysis_stage' => AnalysisStage::Analise,
            'analysis_stage_started_at' => now()->subDay(),
            'analysis_due_at' => now()->addDays(9),
            'analysis_status' => $status,
        ])->save();

        return $request;
    }

    public function test_redistribuir_troca_a_analista_mantendo_o_prazo_e_audita_a_troca(): void
    {
        // O prazo é do processo, não da pessoa: a troca NÃO recalcula o SLA.
        Carbon::setTestNow('2026-03-10 09:00:00');

        $sector = Sector::factory()->create();
        $analistaA = $this->analistaDoSetor($sector);
        $analistaB = $this->analistaDoSetor($sector);
        $apoio = User::factory()->create();
        $request = $this->processoAtribuido($sector, $analistaA);
        $prazoOriginal = $request->analysis_due_at->toDateTimeString();

        $this->service()->redistribuir($request, $analistaB, $apoio);

        $fresh = $request->fresh();
        $this->assertSame($analistaB->id, $fresh->assigned_user_id);
        $this->assertSame($prazoOriginal, $fresh->analysis_due_at->toDateTimeString(), 'A redistribuição não recalcula o SLA.');
        $this->assertSame($sector->id, $fresh->sector_id, 'O processo não sai da caixa do setor (RN-004).');

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'redistribuir')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'A redistribuição deve ser auditada de forma síncrona (RN-006).');
        $this->assertSame($analistaA->id, $activity->properties['analista_anterior_id']);
        $this->assertSame($analistaB->id, $activity->properties['assigned_user_id']);
        $this->assertSame($apoio->id, $activity->properties['ator_id']);

        Carbon::setTestNow();
    }

    public function test_redistribuir_e_bloqueada_apos_a_conclusao_da_analise(): void
    {
        $sector = Sector::factory()->create();
        $analistaA = $this->analistaDoSetor($sector);
        $analistaB = $this->analistaDoSetor($sector);
        $request = $this->processoAtribuido($sector, $analistaA, AnalysisStatus::AnaliseConcluida);

        try {
            $this->service()->redistribuir($request, $analistaB, User::factory()->create());
            $this->fail('Esperava DistribuicaoException para análise concluída.');
        } catch (DistribuicaoException) {
            // esperado
        }

        $this->assertSame($analistaA->id, $request->fresh()->assigned_user_id);
        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'analise',
            'event' => 'redistribuir',
            'subject_id' => $request->id,
        ]);
    }

    public function test_redistribuir_exige_analista_vinculada_ao_setor(): void
    {
        $sector = Sector::factory()->create();
        $analistaA = $this->analistaDoSetor($sector);
        $forasteiro = User::factory()->create();
        $request = $this->processoAtribuido($sector, $analistaA);

        try {
            $this->service()->redistribuir($request, $forasteiro, User::factory()->create());
            $this->fail('Esperava DistribuicaoException para analista fora do setor.');
        } catch (DistribuicaoException) {
            // esperado
        }

        $this->assertSame($analistaA->id, $request->fresh()->assigned_user_id);
    }
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Analise/DistribuicaoServiceTest.php --filter=redistribuir`
Expected: FAIL — `Error: Call to undefined method App\Services\Analise\DistribuicaoService::redistribuir()`

- [ ] **Step 3: Implementar exceção + service**

Em `app/Services/Analise/DistribuicaoException.php`, adicionar:

```php
    public static function analiseConcluida(ViabilityRequest $request): self
    {
        return new self("Solicitação #{$request->id} já teve a análise concluída — não pode ser redistribuída.");
    }
```

Em `app/Services/Analise/DistribuicaoService.php`, adicionar após o método `assumir`:

```php
    /**
     * Redistribuição (apoio/gestor troca a analista responsável): só ANTES da
     * conclusão da análise e SEM recalcular o SLA — o prazo é do processo, não
     * da pessoa; trocar o responsável não zera atraso. Audita a troca com a
     * analista anterior e a nova.
     */
    public function redistribuir(ViabilityRequest $request, User $novaAnalista, User $ator): void
    {
        $this->garantirVinculoDeSetor($request, $novaAnalista);

        $status = $request->analysis_status;

        if ($status === null || ! $status->permiteRedistribuicao()) {
            throw DistribuicaoException::analiseConcluida($request);
        }

        DB::transaction(function () use ($request, $novaAnalista, $ator): void {
            $analistaAnterior = $request->assigned_user_id;

            $request->forceFill([
                'assigned_user_id' => $novaAnalista->id,
                'assigned_at' => now(),
            ])->save();

            $this->audit->log('analise', 'redistribuir', "Processo redistribuído para outra analista do setor (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'sector_id' => $request->sector_id,
                'analista_anterior_id' => $analistaAnterior,
                'assigned_user_id' => $novaAnalista->id,
                'ator_id' => $ator->id,
            ], $request);
        });
    }
```

- [ ] **Step 4: Rodar e confirmar o GREEN (service)**

Run: `php artisan test --compact tests/Feature/Analise/DistribuicaoServiceTest.php`
Expected: PASS (todos — 6 existentes + 3 novos)

- [ ] **Step 5: Escrever o teste HTTP que falha**

Em `tests/Feature/Analise/CaixaSetorTest.php`, adicionar ao final da classe:

```php
    public function test_apoio_redistribui_processo_para_outra_analista_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analistaA = $this->analistaDoSetor($setor);
        $analistaB = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);
        $processo->forceFill([
            'assigned_user_id' => $analistaA->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analistaB->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analistaB->id, $processo->fresh()->assigned_user_id);
    }

    public function test_redistribuir_retorna_erro_controlado_quando_a_analise_ja_foi_concluida(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analistaA = $this->analistaDoSetor($setor);
        $analistaB = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);
        $processo->forceFill([
            'assigned_user_id' => $analistaA->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analistaB->id,
            ])
            ->assertSessionHas('error');

        $this->assertSame($analistaA->id, $processo->fresh()->assigned_user_id);
    }
```

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php --filter=redistribui`
Expected: FAIL — 404 (rota não existe).

- [ ] **Step 6: Implementar rota + action do controller**

Em `routes/gestao.php`, no grupo `caixa-setor`, dentro do middleware `permission:distribuir-processos` (onde está a rota `distribuir`), adicionar:

```php
                Route::post('redistribuir', [CaixaSetorController::class, 'redistribuir'])->name('redistribuir');
```

Em `app/Http/Controllers/Gestao/CaixaSetorController.php`, adicionar após o método `distribuir`:

```php
    /**
     * Redistribui um processo já atribuído para outra analista do setor (apoio/
     * gestor). O service bloqueia após a conclusão da análise e mantém o prazo
     * original; a falha volta como aviso controlado, nunca silenciosa.
     */
    public function redistribuir(DistribuirProcessoRequest $request): RedirectResponse
    {
        /** @var User $analista */
        $analista = User::query()->findOrFail($request->integer('analista_id'));

        $processo = ViabilityRequest::query()
            ->whereIn('id', $request->input('request_ids'))
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->firstOrFail();

        try {
            $this->distribuicao->redistribuir($processo, $analista, $request->user());
        } catch (DistribuicaoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Processo redistribuído para {$analista->name}.");
    }
```

- [ ] **Step 7: Rodar e confirmar o GREEN (arquivo inteiro)**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS (14 da Task 2 + 2 novos = 16)

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/DistribuicaoException.php app/Services/Analise/DistribuicaoService.php app/Http/Controllers/Gestao/CaixaSetorController.php routes/gestao.php tests/Feature/Analise/DistribuicaoServiceTest.php tests/Feature/Analise/CaixaSetorTest.php
git commit -m "feat: adiciona redistribuição de processo na caixa do setor"
```

---

### Task 4: Frontend — abas, checkboxes, tramitação em lote e redistribuir

**Files:**
- Modify: `resources/js/pages/gestao/caixa-setor/index.tsx` (reescrita ampla do componente)

**Interfaces:**
- Consumes: props `visao`, `contadores`, `pode_redistribuir` (Task 2); rotas `POST /gestao/caixa-setor/distribuir` (existente, aceita `request_ids[]`) e `POST /gestao/caixa-setor/redistribuir` (Task 3).
- Produces: tela final do fluxo do Apoio.

Nota de TDD: o padrão do projeto para telas server-driven é testar as props Inertia no PHPUnit (já feito nas Tasks 2-3) — não há suíte de componentes React para páginas de gestão. A verificação desta task é `npm run build` (typecheck + bundle) mais a suíte PHPUnit verde.

- [ ] **Step 1: Reescrever o componente**

Substituir `resources/js/pages/gestao/caixa-setor/index.tsx` por:

```tsx
import { Head, router, useForm } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Select from '@/components/form/select';
import { ArrowRightIcon, GroupIcon, UserCircleIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

type Visao = 'para_distribuir' | 'distribuidos';

interface ProcessoItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    imovel: string;
    empresa: string | null;
    cnpj: string | null;
    status: string;
    status_label: string;
    analysis_stage: string | null;
    analysis_stage_label: string | null;
    sector: string | null;
    assigned_user_id: number | null;
    assigned_to: string | null;
    analysis_due_at: string | null;
    pode_redistribuir: boolean;
}

interface Analista {
    id: number;
    name: string;
}

interface Paginado<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface ModalTramitacao {
    tipo: 'distribuir' | 'redistribuir';
    ids: number[];
    descricao: string;
}

interface CaixaSetorIndexProps {
    processos: Paginado<ProcessoItem>;
    visao: Visao;
    contadores: Record<Visao, number>;
    filtros: {
        per_page: number;
    };
    perPageOptions: number[];
    podeDistribuir: boolean;
    podeAssumir: boolean;
    analistas: Analista[];
}

/** Formata a data-hora ISO do prazo para o padrão pt-BR (dd/mm/aaaa hh:mm). */
function formatarPrazo(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const ABAS: { id: Visao; rotulo: string }[] = [
    { id: 'para_distribuir', rotulo: 'Para distribuir' },
    { id: 'distribuidos', rotulo: 'Distribuídos' },
];

export default function CaixaSetorIndex({
    processos,
    visao,
    contadores,
    filtros,
    perPageOptions,
    podeDistribuir,
    podeAssumir,
    analistas,
}: CaixaSetorIndexProps) {
    const linhas = Array.isArray(processos?.data) ? processos.data : [];
    const opcoesAnalista = (Array.isArray(analistas) ? analistas : []).map((analista) => ({
        value: String(analista.id),
        label: analista.name,
    }));

    const [assumindoId, setAssumindoId] = useState<number | null>(null);
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [modal, setModal] = useState<ModalTramitacao | null>(null);

    const tramitacao = useForm<{ request_ids: number[]; analista_id: string }>({
        request_ids: [],
        analista_id: '',
    });

    const selecaoAtiva = visao === 'para_distribuir' && podeDistribuir;
    const todosSelecionados = linhas.length > 0 && linhas.every((item) => selecionados.includes(item.id));

    function navegar(params: { visao?: Visao; per_page?: number }) {
        setSelecionados([]);
        router.get(
            '/gestao/caixa-setor',
            { visao: params.visao ?? visao, per_page: params.per_page ?? filtros.per_page },
            { preserveScroll: true, preserveState: false },
        );
    }

    function alternarSelecao(id: number) {
        setSelecionados((atual) => (atual.includes(id) ? atual.filter((item) => item !== id) : [...atual, id]));
    }

    function alternarTodos() {
        setSelecionados(todosSelecionados ? [] : linhas.map((item) => item.id));
    }

    function assumir(item: ProcessoItem) {
        router.post(
            `/gestao/caixa-setor/${item.id}/assumir`,
            {},
            {
                preserveScroll: true,
                onStart: () => setAssumindoId(item.id),
                onFinish: () => setAssumindoId(null),
            },
        );
    }

    function abrirModal(novo: ModalTramitacao) {
        tramitacao.clearErrors();
        tramitacao.setData({ request_ids: novo.ids, analista_id: '' });
        setModal(novo);
    }

    function confirmarTramitacao() {
        const rota = modal?.tipo === 'redistribuir' ? '/gestao/caixa-setor/redistribuir' : '/gestao/caixa-setor/distribuir';

        tramitacao.post(rota, {
            preserveScroll: true,
            onSuccess: () => {
                setModal(null);
                setSelecionados([]);
                tramitacao.reset();
            },
        });
    }

    const columns: ColumnDef<ProcessoItem>[] = [
        ...(selecaoAtiva
            ? [
                  {
                      id: 'selecao',
                      header: (
                          <input
                              type="checkbox"
                              aria-label="Selecionar todos os processos da página"
                              checked={todosSelecionados}
                              onChange={alternarTodos}
                              className="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
                          />
                      ),
                      cellClassName: 'w-10 whitespace-nowrap',
                      cell: (item: ProcessoItem) => (
                          <input
                              type="checkbox"
                              aria-label={`Selecionar processo ${item.bap ?? item.protocol_number ?? item.id}`}
                              checked={selecionados.includes(item.id)}
                              onChange={() => alternarSelecao(item.id)}
                              className="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
                          />
                      ),
                  } satisfies ColumnDef<ProcessoItem>,
              ]
            : []),
        {
            id: 'processo_sedur',
            header: 'Processo SEDUR',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">{item.bap ?? '—'}</span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.protocol_number ?? 'sem protocolo'}
                    </span>
                </div>
            ),
        },
        {
            id: 'endereco',
            header: 'Endereço',
            cell: (item) => (
                <span className="text-gray-700 dark:text-gray-300">{item.imovel !== '' ? item.imovel : '—'}</span>
            ),
        },
        {
            id: 'etapa',
            header: 'Etapa',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => item.analysis_stage_label ?? '—',
        },
        {
            id: 'responsavel',
            header: 'Analista',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.assigned_to ?? <span className="text-gray-400 dark:text-gray-500">Não atribuído</span>,
        },
        {
            id: 'prazo',
            header: 'Prazo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatarPrazo(item.analysis_due_at),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end gap-2">
                    {podeAssumir && item.assigned_user_id === null && (
                        <TableAction
                            tone="brand"
                            onClick={() => assumir(item)}
                            disabled={assumindoId === item.id}
                            icon={<UserCircleIcon className="size-4.5" />}
                            label="Assumir processo"
                        />
                    )}
                    {podeDistribuir && item.assigned_user_id === null && (
                        <TableAction
                            tone="neutral"
                            onClick={() =>
                                abrirModal({
                                    tipo: 'distribuir',
                                    ids: [item.id],
                                    descricao: `Processo ${item.bap ?? item.protocol_number ?? item.id}`,
                                })
                            }
                            icon={<GroupIcon className="size-4.5" />}
                            label="Distribuir a um analista"
                        />
                    )}
                    {podeDistribuir && item.assigned_user_id !== null && item.pode_redistribuir && (
                        <TableAction
                            tone="neutral"
                            onClick={() =>
                                abrirModal({
                                    tipo: 'redistribuir',
                                    ids: [item.id],
                                    descricao: `Processo ${item.bap ?? item.protocol_number ?? item.id} — hoje com ${item.assigned_to ?? 'analista'}`,
                                })
                            }
                            icon={<GroupIcon className="size-4.5" />}
                            label="Redistribuir para outra analista"
                        />
                    )}
                    <TableAction
                        tone="neutral"
                        href={`/gestao/processos/${item.id}`}
                        icon={<ArrowRightIcon className="size-4.5" />}
                        label="Abrir processo"
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Caixa do setor" />
            <PageHeader title="Caixa do setor" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Processos para distribuição"
                    description="Processos em análise dos seus setores, priorizados por prazo. O apoio ou o gestor seleciona os processos e tramita para um analista do setor; o analista assume um processo. Assumir ou distribuir fixa o responsável — não retira o processo do setor."
                />
                <CardContent>
                    <div className="space-y-5">
                        <div role="tablist" aria-label="Visões da caixa do setor" className="flex border-b border-gray-200 dark:border-gray-800">
                            {ABAS.map((aba) => {
                                const ativa = aba.id === visao;

                                return (
                                    <button
                                        key={aba.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={ativa}
                                        onClick={() => navegar({ visao: aba.id })}
                                        className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                            ativa
                                                ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-200'
                                        }`}
                                    >
                                        {aba.rotulo}
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-theme-xs font-medium ${
                                                ativa
                                                    ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'
                                            }`}
                                        >
                                            {contadores?.[aba.id] ?? 0}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex items-center justify-between gap-3">
                            <div>
                                {selecaoAtiva && selecionados.length > 0 && (
                                    <Button
                                        variant="primary"
                                        onClick={() =>
                                            abrirModal({
                                                tipo: 'distribuir',
                                                ids: selecionados,
                                                descricao: `${selecionados.length} processo(s) selecionado(s)`,
                                            })
                                        }
                                    >
                                        Tramitar selecionados ({selecionados.length})
                                    </Button>
                                )}
                            </div>
                            <PerPageSelect
                                value={filtros.per_page}
                                options={perPageOptions}
                                onChange={(perPage) => navegar({ per_page: perPage })}
                            />
                        </div>

                        <DataTable
                            columns={columns}
                            rows={linhas}
                            rowKey={(item) => item.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={
                                        visao === 'para_distribuir'
                                            ? 'Nenhum processo para distribuir'
                                            : 'Nenhum processo distribuído'
                                    }
                                    description={
                                        visao === 'para_distribuir'
                                            ? 'Não há processos aguardando distribuição nos seus setores.'
                                            : 'Ainda não há processos atribuídos a analistas nos seus setores.'
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={processos?.links ?? []}
                            meta={{ from: processos?.from ?? null, to: processos?.to ?? null, total: processos?.total ?? 0 }}
                        />
                    </div>
                </CardContent>
            </Card>

            <Modal
                isOpen={modal !== null}
                onClose={() => setModal(null)}
                className="m-4 max-w-lg p-6 sm:p-8"
            >
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                    {modal?.tipo === 'redistribuir' ? 'Redistribuir processo' : 'Tramitar processo(s)'}
                </h3>
                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                    {modal?.descricao} — selecione a analista do setor responsável pela análise.
                </p>

                <div className="mt-6">
                    <label htmlFor="tramitar-analista" className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                        Analista
                    </label>
                    <Select
                        id="tramitar-analista"
                        value={tramitacao.data.analista_id}
                        onChange={(value) => tramitacao.setData('analista_id', value)}
                        placeholder={opcoesAnalista.length > 0 ? 'Selecione a analista' : 'Nenhuma analista vinculada ao setor'}
                        options={opcoesAnalista}
                        disabled={opcoesAnalista.length === 0}
                    />
                    {tramitacao.errors.analista_id && (
                        <p className="mt-1.5 text-theme-xs text-error-500">{tramitacao.errors.analista_id}</p>
                    )}
                </div>

                <div className="mt-8 flex justify-end gap-3">
                    <Button variant="outline" onClick={() => setModal(null)} disabled={tramitacao.processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={confirmarTramitacao}
                        loading={tramitacao.processing}
                        disabled={tramitacao.data.analista_id === ''}
                    >
                        Confirmar envio
                    </Button>
                </div>
            </Modal>
        </>
    );
}

CaixaSetorIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
```

Pontos de atenção da implementação:
- A coluna de checkbox só existe quando `visao === 'para_distribuir' && podeDistribuir` (analista não tramita).
- O form envia `request_ids: number[]` — o `DistribuirProcessoRequest` já valida o array; não usar mais `request_id` singular.
- Trocar de aba ou de `per_page` limpa a seleção (`setSelecionados([])` antes do `router.get`).
- O botão "Assumir" e "Distribuir" por linha só aparecem para itens sem responsável; "Redistribuir" só para itens com `pode_redistribuir === true`.

- [ ] **Step 2: Verificar build (typecheck + bundle)**

Run: `npm run build`
Expected: build completo sem erros de TypeScript.

- [ ] **Step 3: Verificar que o menu não mudou**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: PASS (nenhum item novo de menu — mesma rota).

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/gestao/caixa-setor/index.tsx
git commit -m "feat: adiciona seleção em lote e abas na caixa do setor"
```

(Se o deploy for acontecer em seguida, lembrar que `public/build` entra no git — commitar o build junto ou em commit próprio `chore: atualiza build do frontend`.)

---

### Task 5: Verificação de ponta a ponta

**Files:** nenhum (somente verificação).

- [ ] **Step 1: Suíte completa do domínio**

Run: `php artisan test --compact tests/Feature/Analise/ tests/Unit/Enums/AnalysisStatusTest.php`
Expected: PASS — incluindo `FluxoApoioTramitacaoTest` (E2E do fluxo Apoio), `CaixaSetorTest` (16), `DistribuicaoServiceTest` (9).

- [ ] **Step 2: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sem alterações pendentes (ou aplicar e commitar o ajuste).

- [ ] **Step 3: Conferir critério de pronto da spec**

Conferir contra `docs/superpowers/specs/2026-09-22-distribuicao-apoio-caixa-setor-design.md` seção "Critério de pronto": processo recepcionado → aba Para distribuir → checkbox → tramitar → some da aba, aparece em Distribuídos e na fila da analista → analista abre a ficha. Coberto por `FluxoApoioTramitacaoTest` + testes novos.
