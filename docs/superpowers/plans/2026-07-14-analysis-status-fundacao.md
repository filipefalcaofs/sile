# Status da análise — Fundação (Fase 1) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduzir o eixo operacional `analysis_status` (enum + coluna + máquina de estados + timeline própria + inicialização + exibição/filtro/seleção manual) na tela de processos, paralelo ao `ViabilityRequestStatus` canônico, sem tocar no motor expresso.

**Architecture:** Campo paralelo manual em `viability_requests.analysis_status` (enum `AnalysisStatus`). Transições manuais do analista validadas por `AnalysisStatusStateMachine` (espelha `ViabilityRequestStateMachine`), gravadas em `analysis_status_transitions` (timeline interna) + auditoria. Inicializa `null → para_distribuir` no mesmo ponto em que o processo entra em análise (`FluxoExpressoService::encaminharAnalise`). Estados de convite/vistoria existem no enum mas são acionados por evento nas Fases 2/3 (fora deste plano). Override do gestor força transição com justificativa.

**Tech Stack:** Laravel 11 (PHP 8.4, enums nativos), PostgreSQL, PHPUnit (`php artisan test`), Inertia + React (TSX), Vite (`npm run build`, `npx tsc --noEmit`).

**Escopo (Fase 1 de 4 do spec):** Este plano é só a fundação. Fora daqui: rename de domínio pendência→convite + expiração=indeferir (Fase 2), vistoria (Fase 3), integração fila/Encaminhado (Fase 4). Referência: `docs/superpowers/specs/2026-07-14-status-analise-processo-design.md`.

---

### Task 1: Enum `AnalysisStatus`

**Files:**
- Create: `app/Enums/AnalysisStatus.php`
- Test: `tests/Unit/Analise/AnalysisStatusTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Analise;

use App\Enums\AnalysisStatus;
use PHPUnit\Framework\TestCase;

class AnalysisStatusTest extends TestCase
{
    public function test_todos_os_11_estados_existem_com_rotulo(): void
    {
        $this->assertCount(11, AnalysisStatus::cases());
        $this->assertSame('Para distribuir', AnalysisStatus::ParaDistribuir->label());
        $this->assertSame('Em convite', AnalysisStatus::EmConvite->label());
        $this->assertSame('Vistoriado', AnalysisStatus::Vistoriado->label());
    }

    public function test_grupo_agrupa_por_fase_operacional(): void
    {
        $this->assertSame('Convite', AnalysisStatus::ConviteCancelado->grupo());
        $this->assertSame('Vistoria', AnalysisStatus::Vistoriar->grupo());
        $this->assertSame('Distribuição', AnalysisStatus::ParaDistribuir->grupo());
        $this->assertSame('Análise', AnalysisStatus::EmAnalise->grupo());
    }

    public function test_options_devolve_value_label_para_o_front(): void
    {
        $options = AnalysisStatus::options();
        $this->assertContains(['value' => 'para_distribuir', 'label' => 'Para distribuir'], $options);
        $this->assertCount(11, $options);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Analise/AnalysisStatusTest.php`
Expected: FAIL — `Class "App\Enums\AnalysisStatus" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

/**
 * Status OPERACIONAL da análise do processo (relatório de teste SEDUR
 * 2026-07-09, pág. 6). Eixo PARALELO ao ViabilityRequestStatus canônico: o
 * analista gerencia este estado durante a análise técnica. Convite/vistoria
 * são acionados por evento (Fases 2/3); aqui só o enum e os grupos.
 */
enum AnalysisStatus: string
{
    case ParaDistribuir = 'para_distribuir';
    case Encaminhado = 'encaminhado';
    case Analisar = 'analisar';
    case EmAnalise = 'em_analise';
    case AnaliseConcluida = 'analise_concluida';
    case EmConvite = 'em_convite';
    case ConviteRespondido = 'convite_respondido';
    case ConviteCancelado = 'convite_cancelado';
    case ConviteExpirado = 'convite_expirado';
    case Vistoriar = 'vistoriar';
    case Vistoriado = 'vistoriado';

    public function label(): string
    {
        return match ($this) {
            self::ParaDistribuir => 'Para distribuir',
            self::Encaminhado => 'Encaminhado para',
            self::Analisar => 'Analisar',
            self::EmAnalise => 'Em análise',
            self::AnaliseConcluida => 'Análise concluída',
            self::EmConvite => 'Em convite',
            self::ConviteRespondido => 'Convite respondido',
            self::ConviteCancelado => 'Convite cancelado',
            self::ConviteExpirado => 'Prazo para convite expirado',
            self::Vistoriar => 'Vistoriar',
            self::Vistoriado => 'Vistoriado',
        };
    }

    public function grupo(): string
    {
        return match ($this) {
            self::ParaDistribuir, self::Encaminhado => 'Distribuição',
            self::Analisar, self::EmAnalise, self::AnaliseConcluida => 'Análise',
            self::EmConvite, self::ConviteRespondido, self::ConviteCancelado, self::ConviteExpirado => 'Convite',
            self::Vistoriar, self::Vistoriado => 'Vistoria',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $s): array => ['value' => $s->value, 'label' => $s->label()],
            self::cases(),
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Analise/AnalysisStatusTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Enums/AnalysisStatus.php tests/Unit/Analise/AnalysisStatusTest.php
git commit -m "feat(analise): enum AnalysisStatus (11 estados operacionais)"
```

---

### Task 2: Migration — coluna + tabela de timeline

**Files:**
- Create: `database/migrations/2026_07_14_100000_add_analysis_status_to_viability_requests.php`
- Create: `database/migrations/2026_07_14_100100_create_analysis_status_transitions_table.php`

- [ ] **Step 1: Write the column migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna do eixo operacional da análise (paralela ao status canônico).
 * Nullable: só materializa quando o processo entra em análise humana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->string('analysis_status')->nullable()->after('analysis_stage');
            $table->index('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropIndex(['analysis_status']);
            $table->dropColumn('analysis_status');
        });
    }
};
```

- [ ] **Step 2: Write the transitions-table migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline INTERNA do eixo operacional (espelha viability_request_transitions,
 * sem public_label — não é a timeline do cidadão). Escrita pela
 * AnalysisStatusStateMachine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['viability_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_status_transitions');
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `php artisan migrate`
Expected: both migrations run; `Done`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_14_100000_add_analysis_status_to_viability_requests.php database/migrations/2026_07_14_100100_create_analysis_status_transitions_table.php
git commit -m "feat(analise): coluna analysis_status + tabela analysis_status_transitions"
```

---

### Task 3: Model `AnalysisStatusTransition` + cast/relation em `ViabilityRequest`

**Files:**
- Create: `app/Models/AnalysisStatusTransition.php`
- Modify: `app/Models/ViabilityRequest.php` (casts + relation)
- Test: `tests/Feature/Analise/AnalysisStatusTransitionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cast_do_analysis_status_e_relation_da_timeline(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $request->analysisStatusTransitions()->create([
            'from_status' => null,
            'to_status' => AnalysisStatus::ParaDistribuir,
        ]);

        $request->refresh();
        $this->assertInstanceOf(AnalysisStatus::class, $request->analysis_status);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $request->analysis_status);
        $this->assertCount(1, $request->analysisStatusTransitions);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $request->analysisStatusTransitions->first()->to_status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusTransitionTest.php`
Expected: FAIL — `Call to undefined method ...analysisStatusTransitions()`.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transição do eixo operacional da análise (timeline interna). Espelha
 * ViabilityRequestTransition, mas sem public_label — não vai à timeline do
 * cidadão. Escrita pela AnalysisStatusStateMachine.
 */
#[Fillable(['from_status', 'to_status', 'reason', 'actor_user_id'])]
class AnalysisStatusTransition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AnalysisStatus::class,
            'to_status' => AnalysisStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class, 'viability_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
```

- [ ] **Step 4: Add the cast + relation to `ViabilityRequest`**

In `app/Models/ViabilityRequest.php`, inside `casts()` (next to `'analysis_stage' => AnalysisStage::class,` at line 74) add:

```php
            'analysis_status' => AnalysisStatus::class,
```

Add the `use App\Enums\AnalysisStatus;` import near the other enum imports at the top of the file. Then add this relation next to the existing `transitions()` method (around line 190):

```php
    /**
     * Timeline interna do eixo operacional da análise (AnalysisStatus).
     *
     * @return HasMany<AnalysisStatusTransition, $this>
     */
    public function analysisStatusTransitions(): HasMany
    {
        return $this->hasMany(AnalysisStatusTransition::class, 'viability_request_id');
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusTransitionTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/AnalysisStatusTransition.php app/Models/ViabilityRequest.php tests/Feature/Analise/AnalysisStatusTransitionTest.php
git commit -m "feat(analise): model AnalysisStatusTransition + cast/relation em ViabilityRequest"
```

---

### Task 4: `AnalysisStatusStateMachine`

**Files:**
- Create: `app/Services/Analise/AnalysisStatusStateMachine.php`
- Create: `app/Services/Analise/InvalidAnalysisStatusTransitionException.php`
- Test: `tests/Feature/Analise/AnalysisStatusStateMachineTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Services\Analise\InvalidAnalysisStatusTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisStatusStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function machine(): AnalysisStatusStateMachine
    {
        return app(AnalysisStatusStateMachine::class);
    }

    public function test_transicao_valida_grava_status_timeline_e_auditoria(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();
        $actor = User::factory()->create();

        $this->machine()->transition($request, AnalysisStatus::AnaliseConcluida, $actor, 'concluída');

        $request->refresh();
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $request->analysis_status);
        $this->assertSame(AnalysisStatus::EmAnalise, $request->analysisStatusTransitions->last()->from_status);
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $request->analysisStatusTransitions->last()->to_status);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'analise', 'event' => 'status-analise']);
    }

    public function test_transicao_invalida_lanca_excecao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $this->expectException(InvalidAnalysisStatusTransitionException::class);
        $this->machine()->transition($request, AnalysisStatus::Vistoriado, null, null);
    }

    public function test_force_ignora_o_grafo_para_override_do_gestor(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();
        $actor = User::factory()->create();

        $this->machine()->transition($request, AnalysisStatus::Vistoriado, $actor, 'override', force: true);

        $this->assertSame(AnalysisStatus::Vistoriado, $request->refresh()->analysis_status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusStateMachineTest.php`
Expected: FAIL — machine/exception classes não existem.

- [ ] **Step 3: Write the exception**

```php
<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use RuntimeException;

class InvalidAnalysisStatusTransitionException extends RuntimeException
{
    public static function para(?AnalysisStatus $from, AnalysisStatus $to): self
    {
        $origem = $from?->value ?? '(inicial)';

        return new self("Transição de status de análise inválida: {$origem} → {$to->value}.");
    }
}
```

- [ ] **Step 4: Write the state machine**

```php
<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Models\AnalysisStatusTransition;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;

/**
 * Máquina de estados do eixo operacional da análise (espelha
 * ViabilityRequestStateMachine). O mapa cobre as transições do grafo do spec;
 * transições dirigidas por evento (convite/vistoria) também passam por aqui nas
 * Fases 2/3. `force: true` é o override do gestor (justificativa obrigatória no
 * caller). Cada transição grava a timeline interna + auditoria.
 *
 * @var array<string, list<string>>
 */
class AnalysisStatusStateMachine
{
    private const array TRANSITIONS = [
        'para_distribuir' => ['encaminhado'],
        'encaminhado' => ['analisar'],
        'analisar' => ['em_analise'],
        'em_analise' => ['analise_concluida', 'em_convite', 'vistoriar'],
        'em_convite' => ['convite_respondido', 'convite_cancelado', 'convite_expirado'],
        'convite_respondido' => ['em_analise'],
        'convite_cancelado' => ['em_analise'],
        'vistoriar' => ['vistoriado'],
        'vistoriado' => ['em_analise'],
        // 'convite_expirado' e 'analise_concluida' são terminais neste eixo.
    ];

    public function __construct(private AuditService $audit) {}

    public function canTransition(?AnalysisStatus $from, AnalysisStatus $to): bool
    {
        if ($from === null) {
            return $to === AnalysisStatus::ParaDistribuir;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        ViabilityRequest $request,
        AnalysisStatus $to,
        ?User $actor = null,
        ?string $reason = null,
        bool $force = false,
    ): AnalysisStatusTransition {
        $from = $request->analysis_status;

        if (! $force && ! $this->canTransition($from, $to)) {
            throw InvalidAnalysisStatusTransitionException::para($from, $to);
        }

        $request->forceFill(['analysis_status' => $to])->save();

        $transition = $request->analysisStatusTransitions()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
        ]);

        $this->audit->log(
            logName: 'analise',
            event: 'status-analise',
            description: "Status de análise {$request->id}: ".($from?->value ?? '(inicial)')."→{$to->value}",
            properties: [
                'viability_request_id' => $request->id,
                'from' => $from?->value,
                'to' => $to->value,
                'forcado' => $force,
            ],
            subject: $request,
        );

        return $transition;
    }
}
```

> Nota: confira a assinatura de `AuditService::log()` em `app/Support/Audit/AuditService.php` (usada em `ViabilityRequestStateMachine`) e alinhe os nomes de parâmetro se divergirem.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusStateMachineTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Analise/AnalysisStatusStateMachine.php app/Services/Analise/InvalidAnalysisStatusTransitionException.php tests/Feature/Analise/AnalysisStatusStateMachineTest.php
git commit -m "feat(analise): AnalysisStatusStateMachine (grafo + override + timeline)"
```

---

### Task 5: Inicialização `null → para_distribuir` no encaminhamento à análise

**Files:**
- Modify: `app/Services/Expresso/FluxoExpressoService.php:167-171`
- Test: `tests/Feature/Analise/AnalysisStatusInicializacaoTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisStatusInicializacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_encaminhar_analise_materializa_para_distribuir(): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
        ]);
        $this->assertNull($request->analysis_status);

        // encaminharAnalise é private; exercite pela porta pública que o chama.
        // Ajuste para o método público real do serviço no seu fluxo (ex.: decidir()).
        app(FluxoExpressoService::class)->decidir($request->fresh());

        $request->refresh();
        $this->assertSame(AnalysisStage::Distribuicao, $request->analysis_stage);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $request->analysis_status);
    }
}
```

> Nota: confirme o método público de `FluxoExpressoService` que chama `encaminharAnalise` (ver `tests/Feature/Analise/EncaminhamentoAnaliseTest.php` para o setup real de solicitação/CNAE) e ajuste a chamada + o factory state para cair no ramo de análise técnica.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusInicializacaoTest.php`
Expected: FAIL — `analysis_status` continua `null`.

- [ ] **Step 3: Add the initialization to the forceFill block**

In `app/Services/Expresso/FluxoExpressoService.php`, in `encaminharAnalise`, extend the existing `forceFill` (lines 167-171) to also set `analysis_status`:

```php
            $request->forceFill([
                'analysis_stage' => AnalysisStage::Distribuicao,
                'analysis_stage_started_at' => $startedAt,
                'analysis_due_at' => $this->sla->dueAtFor(AnalysisStage::Distribuicao, $startedAt),
                'analysis_status' => AnalysisStatus::ParaDistribuir,
            ])->save();
```

Add `use App\Enums\AnalysisStatus;` to the imports at the top of the file.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusInicializacaoTest.php`
Expected: PASS.

- [ ] **Step 5: Run the encaminhamento regression suite**

Run: `php artisan test tests/Feature/Analise/EncaminhamentoAnaliseTest.php`
Expected: PASS (nenhuma regressão no encaminhamento existente).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Expresso/FluxoExpressoService.php tests/Feature/Analise/AnalysisStatusInicializacaoTest.php
git commit -m "feat(analise): inicializa analysis_status=para_distribuir no encaminhamento à análise"
```

---

### Task 6: Expor `analysis_status` no `ProcessoResource` + tipo do front

**Files:**
- Modify: `app/Http/Resources/ProcessoResource.php:37-56`
- Modify: `resources/js/components/analise/processo-ui.tsx` (interface `ProcessoItem`)
- Test: `tests/Feature/Analise/ProcessoConsultaTest.php` (adicionar assert)

- [ ] **Step 1: Write the failing assertion**

Add to an existing consulta test in `tests/Feature/Analise/ProcessoConsultaTest.php` (dentro de um teste que já renderiza a consulta e inspeciona um processo via Inertia), ou crie um teste novo:

```php
    public function test_resource_expoe_analysis_status_e_label(): void
    {
        $request = \App\Models\ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => \App\Enums\AnalysisStatus::EmAnalise])->save();

        $payload = (new \App\Http\Resources\ProcessoResource($request->fresh()))->resolve();

        $this->assertSame('em_analise', $payload['analysis_status']);
        $this->assertSame('Em análise', $payload['analysis_status_label']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Analise/ProcessoConsultaTest.php --filter test_resource_expoe_analysis_status_e_label`
Expected: FAIL — chave `analysis_status` ausente.

- [ ] **Step 3: Add the fields to the resource**

In `app/Http/Resources/ProcessoResource.php`, inside the array returned by `toArray()` (next to `'analysis_stage_label' => ...` around line 52), add:

```php
            'analysis_status' => $this->analysis_status?->value,
            'analysis_status_label' => $this->analysis_status?->label(),
```

- [ ] **Step 4: Add to the TS type**

In `resources/js/components/analise/processo-ui.tsx`, inside `interface ProcessoItem`, after `analysis_stage_label`:

```tsx
    analysis_status: string | null;
    analysis_status_label: string | null;
```

- [ ] **Step 5: Run test + typecheck**

Run: `php artisan test tests/Feature/Analise/ProcessoConsultaTest.php --filter test_resource_expoe_analysis_status_e_label`
Expected: PASS.
Run: `npx tsc --noEmit`
Expected: exit 0.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Resources/ProcessoResource.php resources/js/components/analise/processo-ui.tsx tests/Feature/Analise/ProcessoConsultaTest.php
git commit -m "feat(analise): ProcessoResource expõe analysis_status + label"
```

---

### Task 7: Filtro por `analysis_status` na consulta (backend)

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php:185-189` (lista de chaves) + método que passa `analysisStatusOptions` ao Inertia (`index`, ~linha 80)
- Modify: `app/Services/Analise/ProcessoQueryService.php:62-73` (cláusula `when`)
- Test: `tests/Feature/Analise/ProcessoConsultaTest.php`

- [ ] **Step 1: Write the failing test**

```php
    public function test_filtra_por_analysis_status(): void
    {
        $comStatus = \App\Models\ViabilityRequest::factory()->create();
        $comStatus->forceFill(['analysis_status' => \App\Enums\AnalysisStatus::EmConvite])->save();
        $outro = \App\Models\ViabilityRequest::factory()->create();
        $outro->forceFill(['analysis_status' => \App\Enums\AnalysisStatus::EmAnalise])->save();

        $resultado = app(\App\Services\Analise\ProcessoQueryService::class)
            ->filtered(['analysis_status' => 'em_convite'])
            ->pluck('id');

        $this->assertTrue($resultado->contains($comStatus->id));
        $this->assertFalse($resultado->contains($outro->id));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Analise/ProcessoConsultaTest.php --filter test_filtra_por_analysis_status`
Expected: FAIL — filtro não aplicado (retorna os dois).

- [ ] **Step 3: Apply the filter in the query service**

In `app/Services/Analise/ProcessoQueryService.php`, in `filtered()`, add a `when` clause right after the `status` clause (line 67):

```php
            ->when($this->valor($filtros, 'analysis_status'), fn (Builder $q, string $s) => $q->where('analysis_status', $s))
```

- [ ] **Step 4: Accept the key in the controller + pass options to the view**

In `app/Http/Controllers/Gestao/ProcessoController.php`, add `'analysis_status'` to the `$chaves` array in `filtros()` (lines 185-189). Then in `index()`, add to the Inertia props (next to `'statusOptions' => $this->statusOptions(),` ~line 80):

```php
            'analysisStatusOptions' => AnalysisStatus::options(),
```

Add `use App\Enums\AnalysisStatus;` to the imports.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Analise/ProcessoConsultaTest.php --filter test_filtra_por_analysis_status`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Gestao/ProcessoController.php app/Services/Analise/ProcessoQueryService.php tests/Feature/Analise/ProcessoConsultaTest.php
git commit -m "feat(analise): filtro por analysis_status na consulta de processos"
```

---

### Task 8: Filtro + exibição no front (`processos/index.tsx`)

**Files:**
- Modify: `resources/js/pages/gestao/processos/index.tsx` (interface `FiltrosTexto`, `CHAVES_FILTRO`, prop `analysisStatusOptions`, Select do filtro, coluna de status operacional)

- [ ] **Step 1: Add the prop + filter key**

In `interface FiltrosTexto` add `analysis_status: string;`. Add `'analysis_status'` ao array `CHAVES_FILTRO`. Em `interface ConsultaProps` adicione `analysisStatusOptions: SelectOption[];` e receba-a no componente.

- [ ] **Step 2: Add the Select in the filter form**

Após o `<div>` do filtro "Status" (linhas ~350-359), adicione:

```tsx
                                <div>
                                    <Label htmlFor="filtro-analysis-status">Situação da análise</Label>
                                    <Select
                                        id="filtro-analysis-status"
                                        value={form.analysis_status}
                                        onChange={(valor) => definir('analysis_status', valor)}
                                        placeholder="Todas"
                                        options={analysisStatusOptions}
                                    />
                                </div>
```

- [ ] **Step 3: Add a display column**

Na definição de `columns`, após a coluna `status` (linhas ~275-284), adicione:

```tsx
        {
            id: 'analysis_status',
            header: 'Situação da análise',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.analysis_status_label ? (
                    <Badge color="light" size="sm">
                        {item.analysis_status_label}
                    </Badge>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
```

- [ ] **Step 4: Typecheck + build**

Run: `npx tsc --noEmit`
Expected: exit 0.
Run: `npm run build`
Expected: `✓ built in ...`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/processos/index.tsx
git commit -m "feat(analise): filtro e coluna de situação da análise na consulta"
```

---

### Task 9: Endpoint para setar `analysis_status` (transição manual)

**Files:**
- Create: `app/Http/Requests/Gestao/AnalysisStatusRequest.php`
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (novo método `atualizarStatusAnalise`)
- Modify: `routes/gestao.php` (rota `POST processos/{viabilityRequest}/status-analise` sob `permission:analisar-processos`)
- Test: `tests/Feature/Analise/AnalysisStatusEndpointTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_analista_seta_transicao_valida(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $analista = User::factory()->create();
        $analista->givePermissionTo('analisar-processos');

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista)
            ->post("/gestao/processos/{$request->id}/status-analise", ['status' => 'em_analise'])
            ->assertRedirect();

        $this->assertSame(AnalysisStatus::EmAnalise, $request->refresh()->analysis_status);
    }

    public function test_transicao_invalida_sem_override_falha(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $analista = User::factory()->create();
        $analista->givePermissionTo('analisar-processos');

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista)
            ->post("/gestao/processos/{$request->id}/status-analise", ['status' => 'vistoriado'])
            ->assertSessionHas('flash.error');

        $this->assertSame(AnalysisStatus::Analisar, $request->refresh()->analysis_status);
    }
}
```

> Nota: confirme o nome do papel de gestor e o mecanismo de flash de erro (`flash.error`) no projeto — ver `PublishRiscoVersionRequest`/`RiscoController` que já usam esse padrão — e ajuste os asserts se necessário.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusEndpointTest.php`
Expected: FAIL — rota 404.

- [ ] **Step 3: Write the FormRequest**

```php
<?php

namespace App\Http\Requests\Gestao;

use App\Enums\AnalysisStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da mudança manual de status de análise. A autorização é o
 * middleware permission:analisar-processos da rota. `motivo` é obrigatório
 * quando o destino é convite_cancelado (parecer) ou quando é override.
 */
class AnalysisStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AnalysisStatus::class)],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller method + route**

In `app/Http/Controllers/Gestao/ProcessoController.php` add (injete `AnalysisStatusStateMachine` no construtor):

```php
    public function atualizarStatusAnalise(
        AnalysisStatusRequest $request,
        ViabilityRequest $viabilityRequest,
        AnalysisStatusStateMachine $machine,
    ): RedirectResponse {
        $to = AnalysisStatus::from($request->string('status')->toString());
        $force = $request->boolean('force') && $request->user()->hasRole('gestor');

        try {
            $machine->transition($viabilityRequest, $to, $request->user(), $request->string('motivo')->toString() ?: null, force: $force);
        } catch (InvalidAnalysisStatusTransitionException $e) {
            return back()->with('flash.error', $e->getMessage());
        }

        return back()->with('flash.success', 'Situação da análise atualizada.');
    }
```

Add imports: `use App\Enums\AnalysisStatus;`, `use App\Http\Requests\Gestao\AnalysisStatusRequest;`, `use App\Services\Analise\AnalysisStatusStateMachine;`, `use App\Services\Analise\InvalidAnalysisStatusTransitionException;`, `use Illuminate\Http\RedirectResponse;`.

In `routes/gestao.php`, under the group that already has `permission:analisar-processos` for processos, add:

```php
            Route::post('processos/{viabilityRequest}/status-analise', [ProcessoController::class, 'atualizarStatusAnalise'])->name('processos.status-analise');
```

> Nota: localize o grupo/middleware exato das rotas de processos em `routes/gestao.php` e mantenha o padrão de nomes/prefixos já usado.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Analise/AnalysisStatusEndpointTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Gestao/AnalysisStatusRequest.php app/Http/Controllers/Gestao/ProcessoController.php routes/gestao.php tests/Feature/Analise/AnalysisStatusEndpointTest.php
git commit -m "feat(analise): endpoint para setar status de análise (transição validada + override gestor)"
```

---

### Task 10: UI de seleção do status no detalhe (`processos/show.tsx`)

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (`show`: passar `analysisStatusOptions` e as próximas transições válidas)
- Modify: `app/Enums/AnalysisStatus.php` (método `proximas()` para o front saber as opções manuais)
- Modify: `resources/js/pages/gestao/processos/show.tsx` (dropdown de status + POST via router)
- Test: `tests/Unit/Analise/AnalysisStatusTest.php` (assert `proximas()`)

- [ ] **Step 1: Write the failing test for `proximas()`**

Add to `tests/Unit/Analise/AnalysisStatusTest.php`:

```php
    public function test_proximas_lista_transicoes_manuais(): void
    {
        $this->assertSame(
            ['analise_concluida', 'em_convite', 'vistoriar'],
            array_map(fn (AnalysisStatus $s) => $s->value, AnalysisStatus::EmAnalise->proximas()),
        );
        $this->assertSame([], AnalysisStatus::AnaliseConcluida->proximas());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Analise/AnalysisStatusTest.php --filter test_proximas_lista_transicoes_manuais`
Expected: FAIL — método `proximas()` não existe.

- [ ] **Step 3: Add `proximas()` to the enum**

In `app/Enums/AnalysisStatus.php` add (o mapa espelha o grafo manual da máquina; estados dirigidos por evento — convite_respondido/convite_expirado — não são oferecidos manualmente):

```php
    /**
     * Próximas transições MANUAIS oferecidas ao analista no dropdown. Espelha o
     * grafo da AnalysisStatusStateMachine, omitindo os estados dirigidos por
     * evento (respondido/expirado, setados pelo sistema).
     *
     * @return list<self>
     */
    public function proximas(): array
    {
        return match ($this) {
            self::ParaDistribuir => [self::Encaminhado],
            self::Encaminhado => [self::Analisar],
            self::Analisar => [self::EmAnalise],
            self::EmAnalise => [self::AnaliseConcluida, self::EmConvite, self::Vistoriar],
            self::EmConvite => [self::ConviteCancelado],
            self::Vistoriar => [self::Vistoriado],
            self::Vistoriado, self::ConviteRespondido, self::ConviteCancelado => [self::EmAnalise],
            self::AnaliseConcluida, self::ConviteExpirado => [],
        };
    }
```

- [ ] **Step 4: Pass the data in `show()`**

In `ProcessoController::show`, add to the Inertia props:

```php
            'analysisStatusProximas' => $viabilityRequest->analysis_status
                ? array_map(fn (AnalysisStatus $s) => ['value' => $s->value, 'label' => $s->label()], $viabilityRequest->analysis_status->proximas())
                : [],
```

- [ ] **Step 5: Add the dropdown in `show.tsx`**

No `ShowProps`, adicione `analysisStatusProximas: { value: string; label: string }[];`. No bloco de "Informações do processo" (após o `DescItem` de Status, ~linha 238), renderize um seletor visível quando `podeAnalisar && analysisStatusProximas.length > 0`, que faz `router.post('/gestao/processos/'+processo.id+'/status-analise', { status })`:

```tsx
                                        {podeAnalisar && analysisStatusProximas.length > 0 && (
                                            <DescItem label="Alterar situação da análise">
                                                <select
                                                    className="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                                                    defaultValue=""
                                                    onChange={(e) => {
                                                        if (e.target.value) {
                                                            router.post(
                                                                `/gestao/processos/${processo.id}/status-analise`,
                                                                { status: e.target.value },
                                                                { preserveScroll: true },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <option value="">Selecione…</option>
                                                    {analysisStatusProximas.map((o) => (
                                                        <option key={o.value} value={o.value}>
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </DescItem>
                                        )}
```

Importe `router` de `@inertiajs/react` no topo do arquivo.

- [ ] **Step 6: Run test + typecheck + build**

Run: `php artisan test tests/Unit/Analise/AnalysisStatusTest.php`
Expected: PASS.
Run: `npx tsc --noEmit` → exit 0.
Run: `npm run build` → `✓ built`.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/AnalysisStatus.php app/Http/Controllers/Gestao/ProcessoController.php resources/js/pages/gestao/processos/show.tsx tests/Unit/Analise/AnalysisStatusTest.php
git commit -m "feat(analise): seleção manual da situação da análise no detalhe do processo"
```

---

### Task 11: Suíte completa + regressão

- [ ] **Step 1: Run the analysis + processos suites**

Run: `php artisan test tests/Feature/Analise tests/Unit/Analise`
Expected: PASS (todas).

- [ ] **Step 2: Run the expresso regression (init não quebrou nada)**

Run: `php artisan test tests/Feature/Expresso tests/Feature/Analise/EncaminhamentoAnaliseTest.php`
Expected: PASS.

- [ ] **Step 3: Frontend gate**

Run: `npx tsc --noEmit` → exit 0.
Run: `npm run build` → `✓ built`.

---

## Self-Review (feito ao escrever)

**Cobertura do spec (Fase 1):** enum (T1), coluna+timeline (T2-3), máquina+override (T4), inicialização (T5), resource (T6), filtro backend (T7) + front (T8), endpoint de seleção (T9), UI de seleção (T10), regressão (T11). Invariante §3.1 (status⇒stage) fica coberta implicitamente pela inicialização em `encaminharAnalise` (único ponto que materializa `analysis_status` na Fase 1); a validação explícita da invariante entra na Fase 2 quando outros pontos puderem mexer no status.

**Fora de escopo (outras fases/planos):** convite (rename pendência→convite, cancelada+parecer, 48h úteis, expiração=indeferir), vistoria, integração fila/Encaminhado. Estados de convite/vistoria existem no enum e no grafo, mas só a Fase 1 (transições manuais de análise) é acionável aqui.

**Consistência de tipos:** `AnalysisStatus` (value/label/grupo/options/proximas), `analysis_status`/`analysis_status_label` no resource e no `ProcessoItem`, `atualizarStatusAnalise`, `AnalysisStatusStateMachine::transition(..., force:)`, tabela `analysis_status_transitions` — nomes idênticos entre tasks.

**Pontos a confirmar na execução (marcados nas notas):** assinatura de `AuditService::log`; método público de `FluxoExpressoService` que chama `encaminharAnalise`; nome do papel de gestor + padrão de `flash.error`; grupo/middleware exato das rotas de processos em `routes/gestao.php`.
