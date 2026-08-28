# Convite — comportamento (Fase 2a) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajustar o comportamento do "convite" (hoje = subsistema de Pendência) conforme a SEDUR: prazo de **48 horas úteis**, expiração → **indeferimento automático + arquivo virtual**, novo estado **Cancelado com parecer**, e **rótulos visíveis** pendência→convite. SEM renomear classes/tabela/valor canônico (isso é a Fase 2b).

**Architecture:** Reusa o subsistema `AnalysisPendency`/`PendenciaService`/`pendencias:expirar` (nomes internos mantidos). Adiciona: coluna de parecer/cancelamento; um cálculo de prazo em horas úteis no `BusinessDeadlineCalculator`; a transição canônica `em_pendencia → indeferida`; um serviço procedural de indeferimento por prazo (espelha `IndeferirSemBapService`, gera `ViabilityDecision`); `cancelar()` no serviço; reescrita do comando de expiração. Arquivo virtual = `indeferida ∉ STATUS_FILA` (já verdadeiro).

**Tech Stack:** Laravel 11 (PHP 8.4), PostgreSQL, PHPUnit (`php artisan test`, SQLite :memory:), Inertia/React.

**Escopo — FORA (outras fases):** rename interno de domínio pendência→convite / `em_pendencia`→`em_convite` (Fase 2b); wiring do eixo `analysis_status` convite (em_convite/respondido/cancelado/expirado) e a fila (Fase 4); vistoria (Fase 3); desfechos cassado/revogado/desativado (spec 2). Ref: `docs/superpowers/specs/2026-07-14-status-analise-processo-design.md` §5.2/§6.

> **Nota de wiring do eixo:** o eixo `analysis_status` (Fase 1) NÃO é tocado aqui. Suas transições de convite (`em_convite`…) partem de `em_analise`, cuja progressão depende da fila (Fase 4). Serão ligadas lá. Este plano opera no eixo CANÔNICO (`ViabilityRequestStatus`).

---

### Task 1: Estado `Cancelada` no enum

**Files:**
- Modify: `app/Enums/AnalysisPendencyStatus.php`
- Test: `tests/Unit/Analise/AnalysisPendencyStatusTest.php`

- [ ] **Step 1: Write failing test** `tests/Unit/Analise/AnalysisPendencyStatusTest.php`:

```php
<?php

namespace Tests\Unit\Analise;

use App\Enums\AnalysisPendencyStatus;
use PHPUnit\Framework\TestCase;

class AnalysisPendencyStatusTest extends TestCase
{
    public function test_cancelada_existe_com_rotulo(): void
    {
        $this->assertSame('Cancelada', AnalysisPendencyStatus::Cancelada->label());
        $this->assertCount(4, AnalysisPendencyStatus::cases());
    }
}
```

- [ ] **Step 2:** Run `php artisan test tests/Unit/Analise/AnalysisPendencyStatusTest.php` → FAIL (no `Cancelada`).

- [ ] **Step 3:** In `app/Enums/AnalysisPendencyStatus.php` add `case Cancelada = 'cancelada';` after `Expirada` (line 14), and `self::Cancelada => 'Cancelada',` in `label()`.

- [ ] **Step 4:** Run test → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Enums/AnalysisPendencyStatus.php tests/Unit/Analise/AnalysisPendencyStatusTest.php
git commit -m "feat(convite): estado Cancelada no AnalysisPendencyStatus

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Migration + model — parecer/cancelamento

**Files:**
- Create: `database/migrations/2026_07_14_110000_add_cancelamento_to_analysis_pendencies.php`
- Modify: `app/Models/AnalysisPendency.php` (fillable + casts)
- Test: `tests/Feature/Analise/AnalysisPendencyCancelamentoTest.php`

- [ ] **Step 1: Migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos do cancelamento de convite (relatório SEDUR 2026-07-09): ao cancelar,
 * o analista registra um parecer (motivo). Nullable — só o convite cancelado usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_pendencies', function (Blueprint $table): void {
            $table->text('parecer')->nullable()->after('response');
            $table->timestamp('cancelled_at')->nullable()->after('parecer');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_pendencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['parecer', 'cancelled_at']);
        });
    }
};
```

- [ ] **Step 2: Test** `tests/Feature/Analise/AnalysisPendencyCancelamentoTest.php`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPendencyCancelamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_campos_de_cancelamento_persistem(): void
    {
        $request = ViabilityRequest::factory()->create();
        $user = User::factory()->create();
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create();

        $pendency->update([
            'status' => AnalysisPendencyStatus::Cancelada,
            'parecer' => 'Motivo do cancelamento',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user->id,
        ]);

        $pendency->refresh();
        $this->assertSame(AnalysisPendencyStatus::Cancelada, $pendency->status);
        $this->assertSame('Motivo do cancelamento', $pendency->parecer);
        $this->assertNotNull($pendency->cancelled_at);
        $this->assertSame($user->id, $pendency->cancelled_by_user_id);
    }
}
```

(Confirm `AnalysisPendencyFactory` relation name — the model relation is `viabilityRequest()`. Adjust `->for(...)` if needed.)

- [ ] **Step 3:** Run test → FAIL (columns/fillable missing).

- [ ] **Step 4:** In `app/Models/AnalysisPendency.php`, add `'parecer', 'cancelled_at', 'cancelled_by_user_id'` to the `#[Fillable([...])]` list, and add casts `'cancelled_at' => 'datetime'` to `casts()`. Add a `cancelledBy(): BelongsTo` relation mirroring `requestedBy()` (`return $this->belongsTo(User::class, 'cancelled_by_user_id');`).

- [ ] **Step 5:** `php artisan test tests/Feature/Analise/AnalysisPendencyCancelamentoTest.php` → PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_14_110000_add_cancelamento_to_analysis_pendencies.php app/Models/AnalysisPendency.php tests/Feature/Analise/AnalysisPendencyCancelamentoTest.php
git commit -m "feat(convite): colunas de parecer/cancelamento em analysis_pendencies

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: `BusinessDeadlineCalculator::businessDueAt` (horas úteis, forward)

**Files:**
- Modify: `app/Services/Expresso/BusinessDeadlineCalculator.php`
- Test: `tests/Unit/Expresso/BusinessDueAtTest.php`

- [ ] **Step 1: Test** `tests/Unit/Expresso/BusinessDueAtTest.php`:

```php
<?php

namespace Tests\Unit\Expresso;

use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Expresso\HolidayProvider;
use Carbon\Carbon;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class BusinessDueAtTest extends TestCase
{
    public function test_soma_horas_uteis_pulando_fim_de_semana(): void
    {
        // Sexta 14h + 48h úteis (dia útil = 24h de relógio, só pula fds/feriado):
        // 10h restam sexta → 38h → sáb/dom pulados → segunda 0h..24h=24h → 14h → terça 0..14h.
        $calc = new BusinessDeadlineCalculator();
        $sexta14 = Carbon::parse('2026-07-10 14:00:00'); // sexta
        $due = $calc->businessDueAt($sexta14, 48);
        $this->assertSame('2026-07-14 14:00:00', $due->format('Y-m-d H:i:s')); // terça
    }

    public function test_pula_feriado(): void
    {
        $feriado = new class implements HolidayProvider {
            public function isHoliday(DateTimeInterface $date): bool
            {
                return $date->format('Y-m-d') === '2026-07-13'; // segunda vira feriado
            }
            public function hasOfficialCalendar(): bool { return true; }
        };
        $calc = new BusinessDeadlineCalculator($feriado);
        $sexta14 = Carbon::parse('2026-07-10 14:00:00');
        $due = $calc->businessDueAt($sexta14, 48);
        // segunda(13) é feriado → pula → terça(14) 24h → quarta(15) 14h
        $this->assertSame('2026-07-15 14:00:00', $due->format('Y-m-d H:i:s'));
    }
}
```

> Modelo de "hora útil" adotado: um dia útil contribui 24 horas de relógio; fins de semana e feriados contribuem 0. É o modelo consistente com `businessDurationBetween` (que soma minutos de dias úteis inteiros). Se a SEDUR quiser "horas de expediente" (ex.: 8h/dia), ajustar depois — `[OPEN]` menor, não bloqueia.

- [ ] **Step 2:** Run test → FAIL (no `businessDueAt`). First READ the current `businessDurationBetween` (lines 63-90) to reuse its `isWeekend()`/`isHoliday()` day-walk shape.

- [ ] **Step 3:** Add to `BusinessDeadlineCalculator`:

```php
    /**
     * Vencimento a partir de `$from` somando `$businessHours` horas ÚTEIS —
     * fins de semana e feriados ativos contribuem 0 (um dia útil = 24h de
     * relógio). Espelha o desconto de `businessDurationBetween`, mas projeta
     * para frente. `dueAt` (calendário) permanece intacto para SLA/BAP.
     */
    public function businessDueAt(DateTimeInterface $from, int $businessHours): Carbon
    {
        $cursor = Carbon::instance($from)->copy();
        $restante = max(0, $businessHours) * 60; // minutos úteis a consumir

        while ($restante > 0) {
            if ($cursor->isWeekend() || $this->isHoliday($cursor)) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            $fimDoDia = $cursor->copy()->endOfDay();
            $minutosNoDia = $cursor->diffInMinutes($fimDoDia) + 1;

            if ($minutosNoDia > $restante) {
                return $cursor->copy()->addMinutes($restante);
            }

            $restante -= $minutosNoDia;
            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        return $cursor;
    }
```

> Nota: valide o cálculo contra os asserts do teste; ajuste `+1`/`startOfDay` se a contagem de minutos do dia divergir (o teste é a fonte da verdade). Mantenha `dueAt()` como está.

- [ ] **Step 4:** Run test → PASS (ambos). Rode `php artisan test tests/Feature/Expresso tests/Unit/Expresso` → sem regressão.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Expresso/BusinessDeadlineCalculator.php tests/Unit/Expresso/BusinessDueAtTest.php
git commit -m "feat(convite): businessDueAt (prazo em horas úteis, pula fds/feriado)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Parâmetro do prazo (48h úteis)

**Files:**
- Modify: `config/sile.php:161` (bloco `analise`)
- Modify: `database/seeders/ParameterSeeder.php`
- Test: `tests/Feature/Seeders/ParameterSeederTest.php` (se existir; senão, assert em Task 5)

- [ ] **Step 1:** In `config/sile.php`, no bloco `'pendencia' => [...]` (linha 161), adicione a chave: `'prazo_resposta_horas_uteis' => 48,` (mantenha `prazo_resposta_dias` por ora — não quebrar nada que ainda o leia; a Task 5 passa a usar a nova).

- [ ] **Step 2:** In `database/seeders/ParameterSeeder.php`, junto de `analise.pendencia.prazo_resposta_dias` (linha ~386), adicione:

```php
            'analise.convite.prazo_resposta_horas_uteis' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '48',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:2000'],
                'description' => 'Prazo (horas úteis) para o requerente responder a um convite antes de expirar (indefere automaticamente)',
            ],
```

- [ ] **Step 3:** Run `php artisan test tests/Feature/Seeders` → PASS (se houver um teste que conta/valida parâmetros, ele passa com o novo item; se assertar contagem exata, atualize-a).

- [ ] **Step 4: Commit**

```bash
git add config/sile.php database/seeders/ParameterSeeder.php
git commit -m "feat(convite): parâmetro analise.convite.prazo_resposta_horas_uteis=48

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: `PendenciaService::abrir` usa 48h úteis

**Files:**
- Modify: `app/Services/Analise/PendenciaService.php` (método `abrir`, linhas ~58-69)
- Test: `tests/Feature/Analise/` (arquivo do PendenciaService, se existir; senão criar `PendenciaPrazoTest.php`)

- [ ] **Step 1: Test** — crie/《estenda》 um teste que abre uma pendência e verifica que `due_at` é calculado por horas úteis (não `addDays`). Ex. `tests/Feature/Analise/PendenciaPrazoTest.php`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaService;
use App\Services\Expresso\BusinessDeadlineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendenciaPrazoTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_usa_prazo_em_horas_uteis(): void
    {
        $request = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmAnalise]);
        $analista = User::factory()->create();

        $pendency = app(PendenciaService::class)->abrir($request->fresh(), $analista, 'Complementar documento X');

        $esperado = app(BusinessDeadlineCalculator::class)->businessDueAt($pendency->created_at, 48);
        $this->assertSame(
            $esperado->format('Y-m-d H:i'),
            $pendency->due_at->format('Y-m-d H:i'),
        );
    }
}
```

- [ ] **Step 2:** Run → FAIL (due_at ainda por addDays).

- [ ] **Step 3:** Em `PendenciaService`, injete `BusinessDeadlineCalculator $prazos` no construtor (junto de `$stateMachine`, `$audit`). No `abrir`, troque o cálculo do prazo:

```php
        $horasUteis = (int) Settings::get(
            'analise.convite.prazo_resposta_horas_uteis',
            config('sile.analise.pendencia.prazo_resposta_horas_uteis', 48),
        );
        // ... dentro do create():
        'due_at' => $this->prazos->businessDueAt(now(), $horasUteis),
```

(Remova o uso de `prazo_resposta_dias`/`addDays` no `abrir`.)

- [ ] **Step 4:** Run → PASS. `php artisan test tests/Feature/Analise` → sem regressão (ajuste testes existentes que assertavam `addDays(15)` no due_at, se houver).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analise/PendenciaService.php tests/Feature/Analise/PendenciaPrazoTest.php
git commit -m "feat(convite): prazo do convite em 48h úteis (businessDueAt) no abrir

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Transição canônica `em_pendencia → indeferida`

**Files:**
- Modify: `app/Services/Solicitacao/ViabilityRequestStateMachine.php:35-41`
- Test: `tests/Feature/` do state machine (localize o existente; senão `tests/Feature/Solicitacao/StateMachinePendenciaIndefereTest.php`)

- [ ] **Step 1: Test:**

```php
<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateMachinePendenciaIndefereTest extends TestCase
{
    use RefreshDatabase;

    public function test_em_pendencia_pode_indeferir(): void
    {
        $sm = app(ViabilityRequestStateMachine::class);
        $this->assertTrue($sm->canTransition(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::Indeferida));
    }
}
```

- [ ] **Step 2:** Run → FAIL.

- [ ] **Step 3:** Em `TRANSITIONS`, mude a linha do `em_pendencia` de `'em_pendencia' => ['em_analise'],` para `'em_pendencia' => ['em_analise', 'indeferida'],`. Atualize o docblock (linhas 26-32) para notar que a expiração do convite pode indeferir a partir de em_pendencia (RN da SEDUR 2026-07-09).

- [ ] **Step 4:** Run → PASS. `php artisan test tests/Feature/Solicitacao tests/Feature/Expresso` → sem regressão.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Solicitacao/ViabilityRequestStateMachine.php tests/Feature/Solicitacao/StateMachinePendenciaIndefereTest.php
git commit -m "feat(convite): permite transição em_pendencia -> indeferida (expiração do convite)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: `IndeferirPorPrazoConviteService` (indeferir por expiração)

**Files:**
- Create: `app/Services/Analise/IndeferirPorPrazoConviteService.php`
- Test: `tests/Feature/Analise/IndeferirPorPrazoConviteTest.php`

Primeiro READ `app/Services/Expresso/IndeferirSemBapService.php` inteiro — este serviço é o MOLDE (cria `ViabilityDecision` procedural com `decided_by_user_id = null`, transiciona, dispara `ResultadoEmitido`). Replique a forma, mudando: guarda para `em_pendencia`, `consolidated_result`/`reason` do convite.

- [ ] **Step 1: Test:**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use App\Services\Analise\IndeferirPorPrazoConviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndeferirPorPrazoConviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_indefere_processo_e_cria_decisao(): void
    {
        $request = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmPendencia]);
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create();

        $decision = app(IndeferirPorPrazoConviteService::class)->indeferir($request->fresh(), $pendency);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);
        $this->assertSame($request->id, $decision->viability_request_id);
        $this->assertNull($decision->decided_by_user_id);
        $this->assertSame('indeferida', $decision->outcome->value);
    }
}
```

(Confirme os campos reais de `ViabilityDecision`/enum `DecisionOutcome` ao replicar de `IndeferirSemBapService` — use os MESMOS nomes de coluna/enum que ele usa; ajuste os asserts de `outcome`/`consolidated_result` conforme o molde.)

- [ ] **Step 2:** Run → FAIL.

- [ ] **Step 3:** Crie o serviço espelhando `IndeferirSemBapService`:
  - Guard: `$request->status === ViabilityRequestStatus::EmPendencia` senão `throw new \InvalidArgumentException(...)`.
  - `DB::transaction`: cria `ViabilityDecision` (`decided_by_user_id => null`, `outcome => DecisionOutcome::Indeferida`, `consolidated_result => 'convite_expirado'` — ou o valor honesto que o molde usa; `reason => 'Indeferido por prazo do convite expirado sem resposta do requerente'`, `per_cnae => []`, `rules_versions => []`), `stateMachine->transition($request, Indeferida, actor: null, reason: ..., publicLabel: ...)`, `audit->log('analise','convite-expirado-indeferido',...)`.
  - Após commit: `ResultadoEmitido::dispatch($request, $decision)` (como o molde).

- [ ] **Step 4:** Run → PASS. `php artisan test tests/Feature/Analise tests/Feature/Expresso` → sem regressão.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analise/IndeferirPorPrazoConviteService.php tests/Feature/Analise/IndeferirPorPrazoConviteTest.php
git commit -m "feat(convite): serviço de indeferimento por expiração do convite (gera ViabilityDecision)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: `PendenciaService::cancelar` (parecer obrigatório)

**Files:**
- Modify: `app/Services/Analise/PendenciaService.php` (novo método `cancelar`)
- Modify: `app/Services/Analise/PendenciaInvalidaException.php` (novo factory `naoCancelavel` se seguir o padrão)
- Test: `tests/Feature/Analise/PendenciaCancelarTest.php`

- [ ] **Step 1: Test:**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendenciaCancelarTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelar_registra_parecer_e_reabre_analise(): void
    {
        $request = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmPendencia]);
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
        ]);
        $analista = User::factory()->create();

        app(PendenciaService::class)->cancelar($pendency, $analista, 'Convite desnecessário — dado já consta.');

        $pendency->refresh();
        $this->assertSame(AnalysisPendencyStatus::Cancelada, $pendency->status);
        $this->assertSame('Convite desnecessário — dado já consta.', $pendency->parecer);
        $this->assertSame($analista->id, $pendency->cancelled_by_user_id);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
    }
}
```

- [ ] **Step 2:** Run → FAIL.

- [ ] **Step 3:** Adicione `cancelar(AnalysisPendency $pendency, User $analista, string $parecer): void` ao `PendenciaService`, espelhando `responder()`:
  - Guard: `$pendency->status === Aberta` e `$pendency->viabilityRequest->status === EmPendencia`, senão `throw PendenciaInvalidaException::naoCancelavel($pendency)`.
  - `DB::transaction`: `$pendency->update(['status' => Cancelada, 'parecer' => $parecer, 'cancelled_at' => now(), 'cancelled_by_user_id' => $analista->id])`; `stateMachine->transition($request, EmAnalise, actor: $analista, reason: 'Convite cancelado pela análise: '.$parecer, publicLabel: ...)`; `audit->log('analise','convite-cancelado',...)`.
  - (Sem evento de notificação novo nesta task — o requerente não precisa ser notificado de cancelamento; confirme com a SEDUR depois se desejam notificar.)
  - Adicione o factory `naoCancelavel(AnalysisPendency $p): self` em `PendenciaInvalidaException` seguindo `naoRespondivel`.

- [ ] **Step 4:** Run → PASS. `php artisan test tests/Feature/Analise` → sem regressão.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analise/PendenciaService.php app/Services/Analise/PendenciaInvalidaException.php tests/Feature/Analise/PendenciaCancelarTest.php
git commit -m "feat(convite): cancelar convite com parecer (reabre análise)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 9: Reescrever `ExpirarPendenciasCommand` — expira → indefere

**Files:**
- Modify: `app/Console/Commands/ExpirarPendenciasCommand.php`
- Modify: `app/Notifications/PendenciaExpiradaNotification.php` (texto: agora indica indeferimento)
- Test: `tests/Feature/Analise/ExpirarConviteIndefereTest.php`

- [ ] **Step 1: Test:**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirarConviteIndefereTest extends TestCase
{
    use RefreshDatabase;

    public function test_convite_vencido_indefere_e_sai_da_fila(): void
    {
        $request = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmPendencia]);
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->subHour(),
        ]);

        $this->artisan('pendencias:expirar')->assertSuccessful();

        $this->assertSame(AnalysisPendencyStatus::Expirada, $pendency->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);
        $this->assertTrue($request->fresh()->decision()->exists()); // ViabilityDecision criada
    }
}
```

- [ ] **Step 2:** Run → FAIL (hoje só expira, não indefere).

- [ ] **Step 3:** Reescreva o `handle()`: para cada pendência vencida (mesma query `Aberta + due_at < now()`), dentro de transação: `$pendency->update(['status' => Expirada])`, depois chame `app(IndeferirPorPrazoConviteService::class)->indeferir($request, $pendency)` (que faz a decisão + transição + ResultadoEmitido após seu próprio commit — cuide da ordem: siga o padrão de o serviço abrir a própria transação, então NÃO aninhe; chame o serviço fora da transação do update ou deixe o serviço cuidar de tudo). Notifique o analista com `PendenciaExpiradaNotification` (mantido). Atualize o docblock (remova o texto "SEM decisão automática / MANTÉM o estado" — agora indefere). Injete `IndeferirPorPrazoConviteService` via `handle()` (method injection) ou `app()`.
  - Atualize `PendenciaExpiradaNotification` (assunto/corpo/SMS/in-app) para refletir: "Convite expirado — processo indeferido por falta de resposta no prazo".

- [ ] **Step 4:** Run → PASS. `php artisan test tests/Feature/Analise tests/Feature/Comunicacao` → sem regressão (ajuste testes do comando/notificação que assertavam "mantém estado").

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ExpirarPendenciasCommand.php app/Notifications/PendenciaExpiradaNotification.php tests/Feature/Analise/ExpirarConviteIndefereTest.php
git commit -m "feat(convite): expiração do convite indefere o processo (arquivo virtual) + notificação

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 10: Rótulos visíveis pendência → convite

**Files (apenas texto visível ao usuário — NÃO renomear código):**
- Modify TSX: `resources/js/pages/portal/solicitacoes/pendencias.tsx`, `resources/js/pages/gestao/ficha-analise/show.tsx`, `resources/js/pages/portal/solicitacoes/index.tsx`, `resources/js/pages/portal/solicitacoes/protocolo.tsx`, `resources/js/pages/portal/dashboard.tsx`, `resources/js/pages/gestao/processos/fila.tsx`
- Modify labels do status `em_pendencia` (função de rótulo em `processos/index.tsx`, `dashboard.tsx`, `portal/solicitacoes/index.tsx`) → "Em convite"
- Modify Notifications (texto): `app/Notifications/PendenciaSolicitadaNotification.php`, `RespostaPendenciaNotification.php`, `PendenciaExpiradaNotification.php` (já na Task 9)
- Modify `config/sile.php` templates `notificacoes.pendencia.assunto`/`.corpo`
- Modify label canônico: `app/Enums/ViabilityRequestStatus.php` — `EmPendencia => label 'Em convite'` e `publicLabel 'Convite — ação necessária do requerente'` (só rótulo, valor `em_pendencia` intacto)

- [ ] **Step 1:** Troque cada string visível "Pendência/pendência/pendências" por "Convite/convite/convites" nos arquivos acima (títulos, botões, descrições, empty-states, labels de status). Use as linhas mapeadas no design/investigação. NÃO altere identificadores de código, rotas, chaves de tradução internas, nem o valor `em_pendencia`.

- [ ] **Step 2:** Atualize `ViabilityRequestStatus::label()`/`publicLabel()` para `EmPendencia` → "Em convite" / "Convite — ação necessária do requerente".

- [ ] **Step 3:** Verifique:
  - `npx tsc --noEmit` → exit 0.
  - `npm run build` → ok (não commitar `public/build/`).
  - `php artisan test tests/Feature/Analise tests/Feature/Comunicacao tests/Feature/Solicitacao` → ajuste qualquer teste que assertava o texto antigo "Em pendência"/"Pendência" para o novo rótulo. Rode e deixe verde.

- [ ] **Step 4: Commit** (liste explicitamente os arquivos alterados; nunca `git add -A`):

```bash
git add app/Enums/ViabilityRequestStatus.php app/Notifications/PendenciaSolicitadaNotification.php app/Notifications/RespostaPendenciaNotification.php config/sile.php resources/js/pages/portal/solicitacoes/pendencias.tsx resources/js/pages/gestao/ficha-analise/show.tsx resources/js/pages/portal/solicitacoes/index.tsx resources/js/pages/portal/solicitacoes/protocolo.tsx resources/js/pages/portal/dashboard.tsx resources/js/pages/gestao/processos/fila.tsx
git commit -m "feat(convite): rótulos visíveis pendência -> convite (sem renomear código interno)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 11: Ação de cancelar convite (endpoint + UI mínima)

**Files:**
- Create: `app/Http/Requests/Gestao/CancelarConviteRequest.php`
- Modify: `app/Http/Controllers/Gestao/ProcessoPendenciaController.php` (novo método `cancelar`) — ou controller equivalente do convite
- Modify: `routes/gestao.php` (rota — STAGE APENAS O HUNK, WIP alheio no arquivo)
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (botão "Cancelar convite" + campo de parecer)
- Test: `tests/Feature/Analise/CancelarConviteEndpointTest.php`

- [ ] **Step 1: Test** — analista com `analisar-processos` POSTa cancelamento com parecer; convite vira Cancelada, processo volta a em_analise. Parecer vazio → 422/erro. (Mirror `AnalysisStatusEndpointTest`: `actingAs($user,'gestao')`, factory `analista()`, flash key `error`.)

- [ ] **Step 2:** Run → FAIL (404).

- [ ] **Step 3:** `CancelarConviteRequest` com `rules(): ['parecer' => ['required','string','max:2000']]`. Controller `cancelar(CancelarConviteRequest $r, ViabilityRequest $vr, AnalysisPendency $pendency, PendenciaService $svc)`: chama `$svc->cancelar($pendency, $r->user(), $r->string('parecer')->toString())`; `back()->with('status', 'Convite cancelado.')`. Rota `POST` sob `permission:analisar-processos` (mesmo grupo do status-analise). Confirme o binding (a pendência é do processo).

- [ ] **Step 4:** UI: na ficha, onde hoje há "Abrir pendência" (agora "Abrir convite"), quando há convite aberto, mostrar botão "Cancelar convite" que abre um textarea de parecer e POSTa. Mínimo viável.

- [ ] **Step 5:** `php artisan test ...EndpointTest` → PASS; `npx tsc --noEmit`; `npm run build`.

- [ ] **Step 6: Commit** — commit os arquivos exceto `routes/gestao.php` (WIP alheio); o controller-pai faz o stage do hunk da rota (como na Fase 1 Task 9).

```bash
git add app/Http/Requests/Gestao/CancelarConviteRequest.php app/Http/Controllers/Gestao/ProcessoPendenciaController.php resources/js/pages/gestao/ficha-analise/show.tsx tests/Feature/Analise/CancelarConviteEndpointTest.php
git commit -m "feat(convite): endpoint + UI para cancelar convite com parecer

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 12: Regressão completa

- [ ] `php artisan test tests/Feature/Analise tests/Unit/Analise tests/Feature/Solicitacao tests/Feature/Expresso tests/Feature/Comunicacao tests/Unit/Expresso` → tudo verde.
- [ ] `npx tsc --noEmit` → 0; `npm run build` → ok.

---

## Self-Review (feito ao escrever)

**Cobertura (comportamento SEDUR):** Cancelada+parecer (T1,T2,T8,T11); 48h úteis (T3,T4,T5); em_pendencia→indeferida (T6); indeferir por expiração + ViabilityDecision (T7); expiração reescrita + arquivo virtual (T9, via `indeferida ∉ STATUS_FILA`); rótulos convite (T10). 

**Fora de escopo (declarado):** rename interno pendência→convite / `em_pendencia`→`em_convite` (Fase 2b); wiring do eixo `analysis_status` convite (Fase 4); notificação de cancelamento ao requerente (`[OPEN]` a confirmar com a SEDUR).

**Riscos/notas de execução:** (a) modelo de "hora útil" = dia útil de 24h (não expediente 8h) — `[OPEN]` menor; (b) replicar `IndeferirSemBapService` exige usar os nomes reais de coluna/enum de `ViabilityDecision`/`DecisionOutcome` (o executor lê o molde); (c) ordem de transação entre o comando e o serviço de indeferimento (evitar aninhar transações — deixar o serviço abrir a sua); (d) testes existentes que assertavam "Em pendência"/addDays(15)/"mantém estado" precisam de atualização (T5, T9, T10); (e) `routes/gestao.php` tem WIP alheio — stage por hunk (T11).
