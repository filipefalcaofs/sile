# Caixa de Malha Fina — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar a Caixa de Malha Fina — caixa operacional dedicada (`/gestao/malha-fina`) que lista os processos com `in_fine_mesh = true`, com filtros das caixas existentes, abertura do processo original e ação de conclusão (baixa) auditada.

**Architecture:** Controller invokable próprio (`MalhaFinaCaixaController`) espelhando o padrão da tela de Vistorias (universo por critério de domínio + abas + KPIs), reutilizando o motor de filtros da Caixa do Setor (`ProcessoQueryService::aplicarFiltros` + `processo-filtros.tsx`). A baixa usa o `MalhaFinaService::resolver()` existente, estendido com observação opcional e `resolved_by_user_id`. Nenhuma regra de negócio nova de entrada: o universo é a flag `in_fine_mesh` (HU-136), ortogonal ao status.

**Tech Stack:** Laravel 13, Inertia v3, React 19, Tailwind 4, PHPUnit 12, Spatie Permission + Activity Log, Vitest.

**Spec:** `docs/superpowers/specs/2026-09-22-caixa-malha-fina-design.md`

## Global Constraints

- TDD estrito (Red-Green-Refactor): nenhum código de produção sem teste falhando antes.
- UI, mensagens, commits e comentários em pt-BR; identificadores em inglês.
- Conventional commits em português, minúsculas, sem ponto final.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- Malha fina é ORTOGONAL ao status (RN-001 HU-136): NUNCA transicionar `status` nem `analysis_status`.
- Invariante: `in_fine_mesh = true` ⇔ existe encaminhamento com `resolved_at` null (mantido pelo `MalhaFinaService`).
- Auditoria síncrona via `AuditService` em toda ação (RN-002 transversal).
- Sem emoji em código, UI e documentação.
- Permissões pelo catálogo Spatie existente — nenhum mecanismo paralelo.

---

### Task 1: Permissão `analisar-malha-fina`

**Files:**
- Modify: `app/Support/PermissionCatalog.php` (após a entrada `encaminhar-malha-fina`, ~linha 125-129)
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`
- Test: `tests/Feature/Analise/AnalisarMalhaFinaPermissionTest.php` (criar)

**Interfaces:**
- Produces: permissão `analisar-malha-fina` (string) atribuída aos papéis `gestor` e `administrador` no seed; NÃO atribuída a `analista` nem `apoio`. Tasks 3/4/5 dependem dela.

- [ ] **Step 1: Escrever o teste falhando**

Criar `tests/Feature/Analise/AnalisarMalhaFinaPermissionTest.php`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Permissão analisar-malha-fina (Caixa de Malha Fina): separada de
 * encaminhar-malha-fina — quem encaminha não opera necessariamente a caixa.
 * Atribuída no seed apenas a gestor e administrador; os responsáveis finais
 * são definidos pelo admin nas telas de perfis/usuários.
 */
class AnalisarMalhaFinaPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permissao_existe_no_catalogo_seedado(): void
    {
        $this->assertDatabaseHas('permissions', [
            'name' => 'analisar-malha-fina',
            'guard_name' => 'web',
        ]);
    }

    public function test_gestor_e_administrador_recebem_analisar_malha_fina(): void
    {
        $gestor = User::factory()->gestor()->create();
        $administrador = User::factory()->administrador()->create();

        $this->assertTrue($gestor->can('analisar-malha-fina'));
        $this->assertTrue($administrador->can('analisar-malha-fina'));
    }

    public function test_analista_e_apoio_nao_recebem_analisar_malha_fina(): void
    {
        $analista = User::factory()->analista()->create();
        $apoio = User::factory()->apoio()->create();

        $this->assertFalse($analista->can('analisar-malha-fina'));
        $this->assertFalse($apoio->can('analisar-malha-fina'));
        // Quem encaminha (analista tem encaminhar-malha-fina) não opera a caixa.
        $this->assertTrue($analista->can('encaminhar-malha-fina'));
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `php artisan test --compact tests/Feature/Analise/AnalisarMalhaFinaPermissionTest.php`
Expected: FAIL — `assertDatabaseHas('permissions', ...)` falha (permissão não existe).

- [ ] **Step 3: Implementar**

Em `app/Support/PermissionCatalog.php`, imediatamente após a entrada `encaminhar-malha-fina`:

```php
            [
                'name' => 'analisar-malha-fina',
                'label' => 'Analisar malha fina',
                'description' => 'Acessa e opera a Caixa de Malha Fina (consulta e baixa de encaminhamentos).',
                'group' => 'Análise técnica',
            ],
```

Em `database/seeders/RolesAndPermissionsSeeder.php`:
1. No array `$permissions`, após `'encaminhar-malha-fina',` adicionar `'analisar-malha-fina',`.
2. Na lista do papel `gestor`, após `'encaminhar-malha-fina',` adicionar `'analisar-malha-fina',`.
3. Na lista do papel `administrador`, após `'encaminhar-malha-fina',` adicionar `'analisar-malha-fina',`.
4. NÃO adicionar a `analista` nem a `apoio`.

- [ ] **Step 4: Rodar e verificar que passa**

Run: `php artisan test --compact tests/Feature/Analise/AnalisarMalhaFinaPermissionTest.php`
Expected: PASS (3 testes).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/PermissionCatalog.php database/seeders/RolesAndPermissionsSeeder.php tests/Feature/Analise/AnalisarMalhaFinaPermissionTest.php
git commit -m "feat: adiciona permissão analisar-malha-fina separada de encaminhar"
```

---

### Task 2: Colunas de baixa + `resolver()` com observação + `resolverAbertos()`

**Files:**
- Create: migration via `php artisan make:migration add_resolution_fields_to_fine_mesh_referrals_table --no-interaction`
- Modify: `app/Models/FineMeshReferral.php`
- Modify: `database/factories/FineMeshReferralFactory.php`
- Modify: `app/Services/Analise/MalhaFinaService.php`
- Test: `tests/Feature/Analise/MalhaFinaResolucaoTest.php` (criar)

**Interfaces:**
- Consumes: `MalhaFinaService::encaminhar(ViabilityRequest, User, string, ?int): FineMeshReferral` (existente).
- Produces:
  - `MalhaFinaService::resolver(FineMeshReferral $referral, User $ator, ?string $observacao = null): void` — assinatura estendida (Task 3 consome).
  - `MalhaFinaService::resolverAbertos(ViabilityRequest $request, User $ator, ?string $observacao = null): int` — baixa todos os encaminhamentos abertos do processo; retorna quantos foram baixados (Task 3 consome).
  - `FineMeshReferral::resolvedBy(): BelongsTo` (relation `resolved_by_user_id` → User; Tasks 4/5 consomem).
  - Colunas `fine_mesh_referrals.resolved_by_user_id` (FK users, nullable) e `resolution_note` (text, nullable).

- [ ] **Step 1: Escrever o teste falhando**

Criar `tests/Feature/Analise/MalhaFinaResolucaoTest.php`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\FineMeshReferral;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Baixa da malha fina (Caixa de Malha Fina): resolver() grava quem baixou
 * (resolved_by_user_id) e a observação OPCIONAL (resolution_note), audita por
 * encaminhamento e mantém o invariante da flag in_fine_mesh — SEM tocar no
 * status do processo (ortogonal, RN-001). resolverAbertos() baixa todos os
 * encaminhamentos abertos do processo (a caixa lista processos, não
 * encaminhamentos).
 */
class MalhaFinaResolucaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function ator(): User
    {
        return User::factory()->create();
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create([
            'status' => $status,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ])->fresh();
    }

    public function test_resolver_grava_quem_baixou_e_a_observacao_e_audita(): void
    {
        $ator = $this->ator();
        $processo = $this->processo();
        $referral = app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Divergência de metragem.');

        app(MalhaFinaService::class)->resolver($referral, $ator, 'Conferido em campo: metragem correta.');

        $referral->refresh();
        $this->assertNotNull($referral->resolved_at);
        $this->assertSame($ator->id, $referral->resolved_by_user_id);
        $this->assertSame('Conferido em campo: metragem correta.', $referral->resolution_note);
        $this->assertSame($ator->id, $referral->resolvedBy?->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-resolver',
        ]);

        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
    }

    public function test_resolver_sem_observacao_grava_nota_nula(): void
    {
        $ator = $this->ator();
        $processo = $this->processo();
        $referral = app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Revisão de rotina.');

        app(MalhaFinaService::class)->resolver($referral, $ator);

        $referral->refresh();
        $this->assertNotNull($referral->resolved_at);
        $this->assertSame($ator->id, $referral->resolved_by_user_id);
        $this->assertNull($referral->resolution_note);
    }

    public function test_resolver_abertos_baixa_todos_e_desliga_a_flag_sem_mudar_o_status(): void
    {
        $ator = $this->ator();
        $processo = $this->processo(ViabilityRequestStatus::Deferida);
        $service = app(MalhaFinaService::class);
        $service->encaminhar($processo, $ator, 'Primeiro motivo.');
        $service->encaminhar($processo, $ator, 'Segundo motivo.');

        $baixados = $service->resolverAbertos($processo->fresh(), $ator, 'Baixa em conjunto.');

        $this->assertSame(2, $baixados);
        $this->assertSame(0, $processo->fresh()->fineMeshReferrals()->whereNull('resolved_at')->count());
        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
        // RN-001: o deferido continua deferido — a baixa não muda o desfecho.
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'resolution_note' => 'Baixa em conjunto.',
            'resolved_by_user_id' => $ator->id,
        ]);
    }

    public function test_resolver_abertos_sem_encaminhamento_aberto_retorna_zero(): void
    {
        $ator = $this->ator();
        $processo = $this->processo();

        $baixados = app(MalhaFinaService::class)->resolverAbertos($processo, $ator);

        $this->assertSame(0, $baixados);
        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaResolucaoTest.php`
Expected: FAIL — coluna `resolved_by_user_id` não existe / método `resolverAbertos` não existe.

- [ ] **Step 3: Implementar**

3a. Migration — `php artisan make:migration add_resolution_fields_to_fine_mesh_referrals_table --no-interaction`, conteúdo:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baixa da malha fina (Caixa de Malha Fina): quem baixou e a observação
     * opcional da conclusão. resolved_at já existia (HU-136); estas colunas
     * tornam a aba Concluídas da caixa auto-suficiente e a rastreabilidade
     * explícita no registro (a auditoria segue no activity_log).
     */
    public function up(): void
    {
        Schema::table('fine_mesh_referrals', function (Blueprint $table) {
            $table->foreignId('resolved_by_user_id')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('fine_mesh_referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn('resolution_note');
        });
    }
};
```

3b. `app/Models/FineMeshReferral.php` — adicionar `'resolved_by_user_id'` e `'resolution_note'` ao atributo `#[Fillable([...])]` e adicionar a relation:

```php
    /**
     * Usuário que deu baixa no encaminhamento (null enquanto aberto).
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
```

3c. `database/factories/FineMeshReferralFactory.php` — state `resolvido()` passa a gravar quem baixou:

```php
    public function resolvido(): static
    {
        return $this->state(fn () => [
            'resolved_at' => now(),
            'resolved_by_user_id' => User::factory(),
        ]);
    }
```

3d. `app/Services/Analise/MalhaFinaService.php` — substituir o método `resolver` e adicionar `resolverAbertos` + normalização da observação:

```php
    /**
     * Dá baixa em um encaminhamento (resolved_at = agora) SEM mexer no status,
     * registrando QUEM baixou (resolved_by_user_id) e a observação OPCIONAL da
     * conclusão (resolution_note — só quando há informação complementar da
     * análise). Ao resolver o ÚLTIMO encaminhamento aberto do processo, baixa
     * a flag in_fine_mesh — mantendo o invariante "in_fine_mesh = existe
     * encaminhamento aberto". A baixa também é auditada por processo.
     */
    public function resolver(FineMeshReferral $referral, User $ator, ?string $observacao = null): void
    {
        $observacao = $this->observacaoNormalizada($observacao);

        DB::transaction(function () use ($referral, $ator, $observacao): void {
            $referral->forceFill([
                'resolved_at' => now(),
                'resolved_by_user_id' => $ator->id,
                'resolution_note' => $observacao,
            ])->save();

            $request = $referral->viabilityRequest;

            $aindaAberto = $request->fineMeshReferrals()->whereNull('resolved_at')->exists();

            if (! $aindaAberto) {
                $request->forceFill(['in_fine_mesh' => false])->save();
            }

            $this->audit->log('analise', 'malha-fina-resolver', "Encaminhamento à malha fina resolvido (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'fine_mesh_referral_id' => $referral->id,
                'in_fine_mesh' => $request->in_fine_mesh,
                'ator_id' => $ator->id,
                'observacao' => $observacao,
            ], $request);
        });
    }

    /**
     * Baixa TODOS os encaminhamentos abertos do processo (a Caixa de Malha
     * Fina lista processos, não encaminhamentos) com a mesma observação
     * opcional — cada baixa é auditada individualmente e a flag desliga na
     * última. Retorna quantos encaminhamentos foram baixados (0 = o processo
     * não estava na malha fina).
     */
    public function resolverAbertos(ViabilityRequest $request, User $ator, ?string $observacao = null): int
    {
        $abertos = $request->fineMeshReferrals()->whereNull('resolved_at')->get();

        foreach ($abertos as $referral) {
            $this->resolver($referral, $ator, $observacao);
        }

        return $abertos->count();
    }

    /**
     * Observação opcional da baixa: só espaços em branco vira null.
     */
    private function observacaoNormalizada(?string $observacao): ?string
    {
        $observacao = $observacao !== null ? trim($observacao) : null;

        return $observacao === '' ? null : $observacao;
    }
```

- [ ] **Step 4: Rodar e verificar que passa**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaResolucaoTest.php`
Expected: PASS (4 testes).

Run também a regressão do service existente: `php artisan test --compact tests/Feature/Analise/MalhaFinaServiceTest.php`
Expected: PASS (a assinatura estendida é retrocompatível — observação é opcional).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/ app/Models/FineMeshReferral.php database/factories/FineMeshReferralFactory.php app/Services/Analise/MalhaFinaService.php tests/Feature/Analise/MalhaFinaResolucaoTest.php
git commit -m "feat: registra quem baixou e observação opcional na baixa da malha fina"
```

---

### Task 3: Endpoint de conclusão `POST /gestao/malha-fina/{viabilityRequest}/concluir`

**Files:**
- Create: `app/Http/Requests/Gestao/ConcluirMalhaFinaRequest.php` (via `php artisan make:request Gestao/ConcluirMalhaFinaRequest --no-interaction`)
- Modify: `app/Http/Controllers/Gestao/MalhaFinaController.php`
- Modify: `routes/gestao.php` (após a rota de vistorias, ~linha 657-659)
- Test: `tests/Feature/Analise/MalhaFinaConcluirTest.php` (criar)

**Interfaces:**
- Consumes: `MalhaFinaService::resolverAbertos(ViabilityRequest, User, ?string): int` (Task 2); permissão `analisar-malha-fina` (Task 1).
- Produces: rota `gestao.malha-fina.concluir` → `/gestao/malha-fina/{viabilityRequest}/concluir` (Task 5 consome via `router.post`).

- [ ] **Step 1: Escrever o teste falhando**

Criar `tests/Feature/Analise/MalhaFinaConcluirTest.php`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Conclusão pela Caixa de Malha Fina: POST .../concluir baixa todos os
 * encaminhamentos abertos do processo (resolverAbertos), com observação
 * OPCIONAL, SEM mudar o status (ortogonal — RN-001). Gated por
 * analisar-malha-fina: quem só encaminha (encaminhar-malha-fina — analista)
 * NÃO conclui (403 auditado no ponto único).
 */
class MalhaFinaConcluirTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function revisor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function processoEmMalhaFina(User $ator): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Revisão de enquadramento.');

        return $processo->fresh();
    }

    public function test_conclui_a_malha_fina_com_observacao_opcional(): void
    {
        $revisor = $this->revisor();
        $processo = $this->processoEmMalhaFina($revisor);

        $this->actingAs($revisor, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [
                'observacao' => 'Enquadramento confirmado.',
            ])
            ->assertRedirect();

        $processo->refresh();
        $this->assertFalse((bool) $processo->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'resolved_by_user_id' => $revisor->id,
            'resolution_note' => 'Enquadramento confirmado.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-resolver',
        ]);
    }

    public function test_conclui_sem_observacao(): void
    {
        $revisor = $this->revisor();
        $processo = $this->processoEmMalhaFina($revisor);

        $this->actingAs($revisor, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [])
            ->assertRedirect();

        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'resolved_by_user_id' => $revisor->id,
            'resolution_note' => null,
        ]);
    }

    public function test_concluir_processo_fora_da_malha_fina_avisa_sem_falhar(): void
    {
        $revisor = $this->revisor();
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        $this->actingAs($revisor, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_analista_sem_analisar_malha_fina_recebe_403_auditado(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = $this->processoEmMalhaFina($analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [])
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        // Nada foi baixado.
        $this->assertTrue((bool) $processo->fresh()->in_fine_mesh);
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaConcluirTest.php`
Expected: FAIL — 404/405 (rota não existe).

- [ ] **Step 3: Implementar**

3a. `app/Http/Requests/Gestao/ConcluirMalhaFinaRequest.php`:

```php
<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Conclusão da análise de malha fina pela caixa dedicada. A autorização é o
 * middleware permission:analisar-malha-fina da rota. A observação é OPCIONAL
 * — só quando há informação complementar da análise a registrar na trilha.
 */
class ConcluirMalhaFinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'observacao' => 'observação',
        ];
    }
}
```

3b. `app/Http/Controllers/Gestao/MalhaFinaController.php` — adicionar o método (e os imports `App\Http\Requests\Gestao\ConcluirMalhaFinaRequest`):

```php
    /**
     * Conclusão pela Caixa de Malha Fina: baixa TODOS os encaminhamentos
     * abertos do processo (a caixa lista processos, não encaminhamentos) com
     * observação opcional, SEM mudar o status (ortogonal — RN-001). Processo
     * sem encaminhamento aberto volta com aviso controlado, nunca falha
     * silenciosa. Gated por analisar-malha-fina na rota.
     */
    public function concluir(ConcluirMalhaFinaRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $baixados = $this->malhaFina->resolverAbertos(
            $viabilityRequest,
            $request->user(),
            $request->validated('observacao'),
        );

        if ($baixados === 0) {
            return back()->with('warning', 'O processo não tinha encaminhamento aberto na malha fina.');
        }

        return back()->with('status', 'Análise da malha fina concluída.');
    }
```

3c. `routes/gestao.php` — imediatamente após o bloco da rota de vistorias (`Route::get('vistorias', VistoriaConsultaController::class)...->name('vistorias.index');`), adicionar:

```php
        // Caixa de Malha Fina: a fila do revisor — processos com in_fine_mesh
        // (ortogonal ao status, HU-136). A consulta (GET) lista com os filtros
        // das caixas (ProcessoQueryService) e audita; a conclusão (POST) dá
        // baixa nos encaminhamentos abertos SEM mexer no status, com
        // observação opcional. Gated por analisar-malha-fina — quem só
        // encaminha (encaminhar-malha-fina) não opera a caixa.
        Route::middleware('permission:analisar-malha-fina')->prefix('malha-fina')->name('malha-fina.')->group(function () {
            Route::get('/', MalhaFinaCaixaController::class)->name('index');
            Route::post('{viabilityRequest}/concluir', [MalhaFinaController::class, 'concluir'])->name('concluir');
        });
```

Nota: o `Route::get('/', MalhaFinaCaixaController::class)` falhará até a Task 4 criar o controller. Para manter a Task 3 verde sozinha, registrar nesta task APENAS a rota do `concluir` (sem o grupo GET):

```php
        // Conclusão da malha fina pela caixa dedicada (a listagem GET chega na
        // task da caixa). Baixa os encaminhamentos abertos SEM mexer no status.
        Route::post('malha-fina/{viabilityRequest}/concluir', [MalhaFinaController::class, 'concluir'])
            ->middleware('permission:analisar-malha-fina')
            ->name('malha-fina.concluir');
```

- [ ] **Step 4: Rodar e verificar que passa**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaConcluirTest.php`
Expected: PASS (4 testes).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Gestao/ConcluirMalhaFinaRequest.php app/Http/Controllers/Gestao/MalhaFinaController.php routes/gestao.php tests/Feature/Analise/MalhaFinaConcluirTest.php
git commit -m "feat: adiciona endpoint de conclusão da malha fina pela caixa"
```

---

### Task 4: Controller da caixa `GET /gestao/malha-fina`

**Files:**
- Create: `app/Http/Controllers/Gestao/MalhaFinaCaixaController.php`
- Modify: `routes/gestao.php` (adicionar a rota GET ao lado do POST da Task 3)
- Test: `tests/Feature/Analise/MalhaFinaCaixaTest.php` (criar)

**Interfaces:**
- Consumes: `ProcessoQueryService::aplicarFiltros(Builder, array): Builder` e `ProcessoQueryService::CHAVES_FILTRO_CAIXA` / `::CATEGORIAS` (existentes); `AnalysisStatus::options()` (existente); `FineMeshReferral::resolvedBy()` (Task 2); permissão `analisar-malha-fina` (Task 1).
- Produces: rota `gestao.malha-fina.index` → `/gestao/malha-fina`; página Inertia `gestao/malha-fina/index` com props `processos` (paginado), `kpis`, `abas`, `filtros`, `servicoOptions`, `analysisStatusOptions`, `categoriaOptions`, `perPageOptions` (Task 5 consome).

- [ ] **Step 1: Escrever o teste falhando**

Criar `tests/Feature/Analise/MalhaFinaCaixaTest.php`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\FineMeshReferral;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Caixa de Malha Fina (GET /gestao/malha-fina): a fila do revisor. Universo
 * = processos com in_fine_mesh (existe encaminhamento aberto — invariante do
 * MalhaFinaService); aba Concluídas = fora da malha fina com baixa registrada.
 * Filtros das caixas (ProcessoQueryService). Gated por analisar-malha-fina;
 * a consulta é auditada (malha-fina-consulta).
 */
class MalhaFinaCaixaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function revisor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function processo(array $attributos = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attributos))->fresh();
    }

    private function emMalhaFina(ViabilityRequest $processo, User $ator): ViabilityRequest
    {
        app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Revisão de rotina.');

        return $processo->fresh();
    }

    public function test_caixa_lista_apenas_processos_em_malha_fina(): void
    {
        $revisor = $this->revisor();
        $dentro = $this->emMalhaFina($this->processo(), $revisor);
        $fora = $this->processo();

        $this->actingAs($revisor, 'gestao')
            ->get('/gestao/malha-fina')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('gestao/malha-fina/index')
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $dentro->id)
                ->where('processos.data.0.encaminhado_por', $revisor->name)
                ->where('processos.data.0.motivo', 'Revisão de rotina.')
            );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-consulta',
        ]);
    }

    public function test_filtro_por_protocolo_se_aplica(): void
    {
        $revisor = $this->revisor();
        $alvo = $this->emMalhaFina($this->processo(), $revisor);
        $this->emMalhaFina($this->processo(), $revisor);

        $this->actingAs($revisor, 'gestao')
            ->get('/gestao/malha-fina?protocolo='.$alvo->protocol_number)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $alvo->id)
            );
    }

    public function test_aba_concluidas_lista_quem_saiu_da_malha_fina(): void
    {
        $revisor = $this->revisor();
        $concluido = $this->emMalhaFina($this->processo(), $revisor);
        $aberto = $this->emMalhaFina($this->processo(), $revisor);

        app(MalhaFinaService::class)->resolverAbertos($concluido->fresh(), $revisor, 'Tudo certo.');

        $this->actingAs($revisor, 'gestao')
            ->get('/gestao/malha-fina?aba=concluidas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $concluido->id)
                ->where('processos.data.0.concluido_por', $revisor->name)
                ->where('processos.data.0.observacao', 'Tudo certo.')
            );
    }

    public function test_sem_permissao_analisar_malha_fina_recebe_403(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/malha-fina')
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaCaixaTest.php`
Expected: FAIL — 404 (rota GET não existe).

- [ ] **Step 3: Implementar**

3a. Criar `app/Http/Controllers/Gestao/MalhaFinaCaixaController.php`:

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Models\FineMeshReferral;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\ProcessoQueryService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa de Malha Fina — a fila do revisor. Universo: processos com
 * in_fine_mesh (≡ existe encaminhamento aberto — invariante do
 * MalhaFinaService, ortogonal ao status, HU-136). Abas: Em malha fina
 * (abertos) e Concluídas (saíram da malha fina com baixa registrada — quem
 * baixou, quando e a observação). Filtros são os das demais caixas
 * (ProcessoQueryService); KPIs derivados do universo. NENHUMA regra nova de
 * entrada: quem coloca o processo aqui é o encaminhar (humano, abuso ou
 * auditoria preditiva). Gated por analisar-malha-fina; a consulta é auditada.
 */
class MalhaFinaCaixaController extends Controller
{
    /** Itens por página aceitos — padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private AuditService $audit,
        private ProcessoQueryService $processos,
    ) {}

    public function __invoke(Request $request): Response
    {
        $aba = $request->input('aba') === 'concluidas' ? 'concluidas' : 'abertas';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $filtros = $this->filtrosDaCaixa($request);

        // Universo das abas (sem filtros de campo — os totais das abas e os
        // KPIs mostram a fila inteira, padrão da consulta de vistorias).
        $abertos = ViabilityRequest::query()->where('in_fine_mesh', true);
        $concluidos = ViabilityRequest::query()
            ->where('in_fine_mesh', false)
            ->whereHas('fineMeshReferrals', fn (Builder $q) => $q->whereNotNull('resolved_at'));

        $kpis = [
            'em_malha_fina' => (clone $abertos)->count(),
            'entradas_mes' => FineMeshReferral::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'concluidas_mes' => FineMeshReferral::query()
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->startOfMonth())
                ->count(),
            'prazo_vencido' => (clone $abertos)->where('analysis_due_at', '<', now())->count(),
        ];

        $consulta = $this->processos->aplicarFiltros(
            $aba === 'concluidas' ? $concluidos : $abertos,
            $filtros,
        );

        $processos = $consulta
            ->with([
                'company',
                'requester:id,name',
                'sector:id,name',
                'assignedTo:id,name',
                'serviceType:id,name',
                'fineMeshReferrals' => fn ($q) => $q
                    ->with(['referredBy:id,name', 'resolvedBy:id,name'])
                    ->orderByDesc('id'),
            ])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => $this->linha($processo, $aba));

        $this->audit->log('analise', 'malha-fina-consulta', 'Consulta da caixa de malha fina', [
            'aba' => $aba,
            'filtros' => array_filter($filtros, fn ($v): bool => $v !== ''),
            'total' => $processos->total(),
        ]);

        return Inertia::render('gestao/malha-fina/index', [
            'processos' => $processos,
            'kpis' => $kpis,
            'abas' => [
                ['id' => 'abertas', 'label' => 'Em malha fina', 'total' => $kpis['em_malha_fina']],
                ['id' => 'concluidas', 'label' => 'Concluídas', 'total' => (clone $concluidos)->count()],
            ],
            'filtros' => $filtros + ['aba' => $aba, 'per_page' => $perPage],
            'servicoOptions' => $this->servicoOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Linha da caixa. Aba abertas: o encaminhamento ABERTO mais recente (quem
     * encaminhou, motivo, quando entrou). Aba concluídas: a baixa mais
     * recente (quem baixou, quando, observação). O processo é sempre o
     * processo original — a linha aponta para a ficha de análise existente.
     *
     * @return array<string, mixed>
     */
    private function linha(ViabilityRequest $processo, string $aba): array
    {
        $encaminhamento = $aba === 'concluidas'
            ? $processo->fineMeshReferrals->firstWhere(fn (FineMeshReferral $r): bool => $r->resolved_at !== null)
            : $processo->fineMeshReferrals->firstWhere(fn (FineMeshReferral $r): bool => $r->resolved_at === null);

        return [
            'id' => $processo->id,
            'protocol_number' => $processo->protocol_number,
            'bap' => $processo->external_reference,
            'protocoled_at' => $processo->protocoled_at?->toIso8601String(),
            'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
            'requerente' => $processo->requester?->name,
            'servico' => $processo->serviceType?->name,
            'status' => $processo->status->value,
            'status_label' => $processo->status->label(),
            'analysis_status' => $processo->analysis_status?->value,
            'analysis_status_label' => $processo->analysis_status?->label(),
            'setor' => $processo->sector?->name,
            'responsavel' => $processo->assignedTo?->name,
            'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
            'entrada_malha_fina' => $encaminhamento?->created_at?->toIso8601String(),
            'encaminhado_por' => $encaminhamento?->referredBy?->name,
            'motivo' => $encaminhamento?->reason,
            'concluido_em' => $encaminhamento?->resolved_at?->toIso8601String(),
            'concluido_por' => $encaminhamento?->resolvedBy?->name,
            'observacao' => $encaminhamento?->resolution_note,
            'ficha_url' => route('gestao.processos.ficha.show', ['viabilityRequest' => $processo->id]),
        ];
    }

    /**
     * Filtros de pesquisa da caixa (subconjunto do SAPS), crus da query
     * string — o ProcessoQueryService normaliza e ignora os vazios.
     *
     * @return array<string, string>
     */
    private function filtrosDaCaixa(Request $request): array
    {
        $filtros = [];

        foreach (ProcessoQueryService::CHAVES_FILTRO_CAIXA as $chave) {
            $filtros[$chave] = $request->string($chave)->toString();
        }

        return $filtros;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function servicoOptions(): array
    {
        return ViabilityServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($servico): array => ['value' => (string) $servico->id, 'label' => $servico->name])
            ->all();
    }

    /**
     * Categorias da caixa SEM "Malha Fina" — redundante aqui: tudo na aba
     * abertas já é malha fina por definição (e na aba concluídas, nada é).
     *
     * @return list<array{value: string, label: string}>
     */
    private function categoriaOptions(): array
    {
        $opcoes = [];

        foreach (ProcessoQueryService::CATEGORIAS as $value => $label) {
            if ($value === 'malha_fina') {
                continue;
            }

            $opcoes[] = ['value' => $value, 'label' => $label];
        }

        return $opcoes;
    }
}
```

Nota de implementação: `firstWhere` com closure não existe no Eloquent Collection padrão — usar `->first(fn (FineMeshReferral $r): bool => ...)` no lugar (Collection::first aceita callback). Ajustar na implementação:

```php
        $encaminhamento = $aba === 'concluidas'
            ? $processo->fineMeshReferrals->first(fn (FineMeshReferral $r): bool => $r->resolved_at !== null)
            : $processo->fineMeshReferrals->first(fn (FineMeshReferral $r): bool => $r->resolved_at === null);
```

3b. `routes/gestao.php` — transformar a rota solta da Task 3 no grupo completo (substituir o bloco do POST isolado por):

```php
        // Caixa de Malha Fina: a fila do revisor — processos com in_fine_mesh
        // (ortogonal ao status, HU-136). A consulta (GET) lista com os filtros
        // das caixas (ProcessoQueryService) e audita; a conclusão (POST) dá
        // baixa nos encaminhamentos abertos SEM mexer no status, com
        // observação opcional. Gated por analisar-malha-fina — quem só
        // encaminha (encaminhar-malha-fina) não opera a caixa.
        Route::middleware('permission:analisar-malha-fina')->prefix('malha-fina')->name('malha-fina.')->group(function () {
            Route::get('/', MalhaFinaCaixaController::class)->name('index');
            Route::post('{viabilityRequest}/concluir', [MalhaFinaController::class, 'concluir'])->name('concluir');
        });
```

Adicionar o import `use App\Http\Controllers\Gestao\MalhaFinaCaixaController;` no topo de `routes/gestao.php` (ordem alfabética junto aos demais).

- [ ] **Step 4: Rodar e verificar que passa**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaCaixaTest.php`
Expected: PASS (4 testes).

Run também a regressão do endpoint de conclusão: `php artisan test --compact tests/Feature/Analise/MalhaFinaConcluirTest.php`
Expected: PASS (a rota POST migrou para o grupo com o mesmo path e name).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/MalhaFinaCaixaController.php routes/gestao.php tests/Feature/Analise/MalhaFinaCaixaTest.php
git commit -m "feat: adiciona caixa de malha fina com abas, KPIs e filtros das caixas"
```

---

### Task 5: Página React da caixa + item de menu

**Files:**
- Create: `resources/js/pages/gestao/malha-fina/index.tsx`
- Modify: `resources/js/navigation/gestao-nav.ts` (grupo `operacao` e `relatorios`)
- Test: `resources/js/navigation/gestao-nav.test.ts` (modify)

**Interfaces:**
- Consumes: props da página definidas na Task 4; rotas `/gestao/malha-fina` (GET) e `/gestao/malha-fina/{id}/concluir` (POST, Task 3); componentes existentes `ProcessoFiltros` (+ `FILTROS_VAZIOS`, `ProcessoFiltrosValores`), `PageHeader`, `KpiCard`, `Card`/`CardContent`, `DataTable` (`ColumnDef`), `PerPageSelect`, `Pagination` (`PaginationLink`), `Badge`, `TableAction`, `EmptyState`, `Modal`, `Button`, `GestaoLayout`.
- Produces: item de menu "Malha fina" (`/gestao/malha-fina`, ícone `shield`, permission `analisar-malha-fina`) no grupo Operação, após "Vistorias".

**Decisão de navegação (limite da normativa de menu):** o grupo Operação já tem 8 itens — o limite de grupo misto enforced por `assertGestaoNavHealth`. Pela árvore de decisão de `menu-navegacao.mdc`, "Resultados do fluxo expresso" é leitura/recorte (não trabalho do dia) e está deslocado em Operação. Para abrir espaço, MOVER "Resultados do fluxo expresso" de Operação para **Relatórios** (grupo homogêneo, limite 12, hoje com 7) e então adicionar "Malha fina" em Operação.

- [ ] **Step 1: Escrever o teste falhando**

Em `resources/js/navigation/gestao-nav.test.ts`:

1. Atualizar o teste `'esconde grupos e itens sem permissão, mas mantém o Painel'` (o item "Resultados do fluxo expresso" muda de grupo):

```ts
    it('esconde grupos e itens sem permissão, mas mantém o Painel', () => {
        const visivel = filterGestaoNav(['consultar-solicitacoes']);

        expect(visivel.map((group) => group.id)).toEqual(['operacao', 'relatorios']);
        expect(visivel[0]?.items.map((item) => item.href)).toEqual(['/gestao', '/gestao/processos']);
        expect(visivel[1]?.items.map((item) => item.href)).toEqual(['/gestao/resultados-expresso']);
    });
```

2. Adicionar o teste novo:

```ts
    it('coloca a caixa de malha fina em Operação, gated por analisar-malha-fina', () => {
        expect(groupOfHref('/gestao/malha-fina')?.id).toBe('operacao');

        const hrefsDe = (groups: ReturnType<typeof filterGestaoNav>) =>
            groups.flatMap((group) => group.items.map((item) => item.href));

        expect(hrefsDe(filterGestaoNav(['analisar-malha-fina']))).toContain('/gestao/malha-fina');
        expect(hrefsDe(filterGestaoNav(['encaminhar-malha-fina']))).not.toContain('/gestao/malha-fina');
    });

    it('mantém resultados do expresso em Relatórios (leitura, não trabalho do dia)', () => {
        expect(groupOfHref('/gestao/resultados-expresso')?.id).toBe('relatorios');
    });
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: FAIL — `/gestao/malha-fina` não existe no catálogo; `resultados-expresso` ainda em `operacao`.

- [ ] **Step 3: Implementar o menu**

Em `resources/js/navigation/gestao-nav.ts`:

1. No grupo `operacao`, REMOVER o item `Resultados do fluxo expresso` e, após o item `Vistorias`, ADICIONAR:

```ts
            { name: 'Malha fina', href: '/gestao/malha-fina', icon: 'shield', permission: 'analisar-malha-fina' },
```

2. No grupo `relatorios`, ADICIONAR ao final da lista de itens:

```ts
            {
                name: 'Resultados do fluxo expresso',
                href: '/gestao/resultados-expresso',
                icon: 'list',
                permission: 'consultar-solicitacoes',
            },
```

- [ ] **Step 4: Rodar o teste do menu e verificar que passa**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: PASS (incluindo `assertGestaoNavHealth()` sem erros — Operação volta a 8 itens).

- [ ] **Step 5: Criar a página `resources/js/pages/gestao/malha-fina/index.tsx`**

```tsx
import { Head, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import ProcessoFiltros, { FILTROS_VAZIOS, type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

type Aba = 'abertas' | 'concluidas';

interface MalhaFinaItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    protocoled_at: string | null;
    empresa: string | null;
    requerente: string | null;
    servico: string | null;
    status: string;
    status_label: string;
    analysis_status: string | null;
    analysis_status_label: string | null;
    setor: string | null;
    responsavel: string | null;
    analysis_due_at: string | null;
    entrada_malha_fina: string | null;
    encaminhado_por: string | null;
    motivo: string | null;
    concluido_em: string | null;
    concluido_por: string | null;
    observacao: string | null;
    ficha_url: string;
}

interface Kpis {
    em_malha_fina: number;
    entradas_mes: number;
    concluidas_mes: number;
    prazo_vencido: number;
}

interface AbaItem {
    id: Aba;
    label: string;
    total: number;
}

interface Paginado<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface MalhaFinaIndexProps {
    processos: Paginado<MalhaFinaItem>;
    kpis: Kpis;
    abas: AbaItem[];
    filtros: ProcessoFiltrosValores & { aba: Aba; per_page: number };
    servicoOptions: { value: string; label: string }[];
    analysisStatusOptions: { value: string; label: string }[];
    categoriaOptions: { value: string; label: string }[];
    perPageOptions: number[];
}

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
function formatarDataHora(iso: string | null): string {
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

export default function MalhaFinaIndex({
    processos,
    kpis,
    abas,
    filtros,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
    perPageOptions,
}: MalhaFinaIndexProps) {
    const linhas = Array.isArray(processos?.data) ? processos.data : [];
    const [concluindo, setConcluindo] = useState<MalhaFinaItem | null>(null);
    const [observacao, setObservacao] = useState('');
    const [enviando, setEnviando] = useState(false);

    const { aba, per_page: _perPage, ...filtrosDeCampo } = filtros;

    function navegar(params: { aba?: Aba; per_page?: number; filtros?: ProcessoFiltrosValores }) {
        const filtrosAtuais = params.filtros ?? filtrosDeCampo;
        router.get(
            '/gestao/malha-fina',
            {
                aba: params.aba ?? aba,
                per_page: params.per_page ?? filtros.per_page,
                ...Object.fromEntries(Object.entries(filtrosAtuais).filter(([, v]) => v !== '')),
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function abrirConclusao(item: MalhaFinaItem) {
        setObservacao('');
        setConcluindo(item);
    }

    function concluir() {
        if (concluindo === null) {
            return;
        }

        router.post(
            `/gestao/malha-fina/${concluindo.id}/concluir`,
            { observacao },
            {
                preserveScroll: true,
                onStart: () => setEnviando(true),
                onFinish: () => setEnviando(false),
                onSuccess: () => setConcluindo(null),
            },
        );
    }

    const columns: ColumnDef<MalhaFinaItem>[] = [
        {
            id: 'processo',
            header: 'Processo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 tabular-nums dark:text-white/90">
                        {item.protocol_number ?? '—'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.bap ? `BAP ${item.bap}` : 'sem BAP'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        Entrada: {formatarDataHora(item.protocoled_at)}
                    </span>
                </div>
            ),
        },
        {
            id: 'requerente',
            header: 'Requerente',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.empresa ?? item.requerente ?? '—'}</span>
                    {item.empresa && item.requerente && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.requerente}</span>
                    )}
                    {item.servico && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.servico}</span>
                    )}
                </div>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col gap-1">
                    <Badge size="sm" color="brand">{item.status_label}</Badge>
                    {item.analysis_status_label && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                            {item.analysis_status_label}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'responsavel',
            header: 'Responsável',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.responsavel ?? <span className="text-gray-400 dark:text-gray-500">Não atribuído</span>,
        },
        {
            id: 'malha_fina',
            header: aba === 'concluidas' ? 'Baixa' : 'Encaminhamento',
            cell: (item) => (
                <div className="flex flex-col">
                    {aba === 'concluidas' ? (
                        <>
                            <span className="text-gray-700 dark:text-gray-300">
                                {item.concluido_por ?? '—'} · {formatarDataHora(item.concluido_em)}
                            </span>
                            {item.observacao && (
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.observacao}</span>
                            )}
                        </>
                    ) : (
                        <>
                            <span className="text-gray-700 dark:text-gray-300">
                                {item.encaminhado_por ?? 'Sistema'} · {formatarDataHora(item.entrada_malha_fina)}
                            </span>
                            {item.motivo && (
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.motivo}</span>
                            )}
                        </>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end gap-2">
                    <TableAction
                        tone="brand"
                        href={item.ficha_url}
                        icon={<ArrowRightIcon className="size-4.5" />}
                        label="Abrir processo"
                    />
                    {aba === 'abertas' && (
                        <TableAction tone="success" onClick={() => abrirConclusao(item)}>
                            Concluir
                        </TableAction>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Caixa de Malha Fina" />
            <PageHeader
                title="Caixa de Malha Fina"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Em malha fina" value={kpis.em_malha_fina} tone="brand" note="aguardando análise" />
                <KpiCard label="Entradas no mês" value={kpis.entradas_mes} tone="info" note="encaminhamentos" />
                <KpiCard label="Prazo vencido" value={kpis.prazo_vencido} tone="error" note="exigem atenção" />
                <KpiCard label="Concluídas no mês" value={kpis.concluidas_mes} tone="success" note="baixas da malha fina" />
            </div>

            <Card>
                <CardContent className="border-t-0">
                    <div className="space-y-5">
                        <ProcessoFiltros
                            valores={filtrosDeCampo}
                            servicoOptions={servicoOptions}
                            analysisStatusOptions={analysisStatusOptions}
                            categoriaOptions={categoriaOptions}
                            onAplicar={(valores) => navegar({ filtros: valores })}
                            onLimpar={() => navegar({ filtros: FILTROS_VAZIOS })}
                        />

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Situação da malha fina">
                                {abas.map((abaItem) => (
                                    <button
                                        key={abaItem.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={aba === abaItem.id}
                                        onClick={() => navegar({ aba: abaItem.id })}
                                        className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition ${
                                            aba === abaItem.id
                                                ? 'border-brand-500 bg-brand-500 text-white'
                                                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/[0.03]'
                                        }`}
                                    >
                                        {abaItem.label}
                                        <span
                                            className={`inline-flex min-w-6 justify-center rounded-full px-1.5 text-xs font-semibold ${
                                                aba === abaItem.id
                                                    ? 'bg-white/20 text-white'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300'
                                            }`}
                                        >
                                            {abaItem.total}
                                        </span>
                                    </button>
                                ))}
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
                                    title={aba === 'concluidas' ? 'Nenhuma baixa de malha fina' : 'Nenhum processo em malha fina'}
                                    description={
                                        aba === 'concluidas'
                                            ? 'Processos concluídos na malha fina aparecem aqui.'
                                            : 'Processos entram aqui quando encaminhados à malha fina — pela ficha, em lote na consulta ou pela detecção de abuso.'
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

            <Modal isOpen={concluindo !== null} onClose={() => setConcluindo(null)} className="max-w-lg p-6">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Concluir análise da malha fina</h2>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    O processo {concluindo?.protocol_number ?? ''} sai da malha fina e segue o fluxo normal, sem mudança
                    de status. A baixa registra quem concluiu e quando.
                </p>
                <label htmlFor="concluir-observacao" className="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Observação (opcional)
                </label>
                <textarea
                    id="concluir-observacao"
                    value={observacao}
                    onChange={(evento) => setObservacao(evento.target.value)}
                    rows={3}
                    maxLength={2000}
                    placeholder="Registre aqui apenas se houver informação complementar da análise"
                    className="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                />
                <div className="mt-5 flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={() => setConcluindo(null)} disabled={enviando}>
                        Cancelar
                    </Button>
                    <Button type="button" variant="primary" onClick={concluir} disabled={enviando}>
                        Concluir malha fina
                    </Button>
                </div>
            </Modal>
        </>
    );
}

MalhaFinaIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
```

Nota de implementação: se os componentes `Button`, `Badge`, `KpiCard` ou `Modal` tiverem props ligeiramente diferentes das usadas acima, conferir os arquivos em `resources/js/components/ui/` e ajustar — os padrões acima seguem `vistorias/index.tsx` e `processo-filtros.tsx`. Verificar também se `Button` aceita `variant="primary"`/`"outline"` (usado em `processo-filtros.tsx` — sim).

- [ ] **Step 6: Verificar tipagem e testes do frontend**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: PASS.

Run: `npx tsc --noEmit` (ou o script de typecheck do projeto, se existir em `package.json`)
Expected: sem erros nos arquivos novos/alterados.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/gestao/malha-fina/index.tsx resources/js/navigation/gestao-nav.ts resources/js/navigation/gestao-nav.test.ts
git commit -m "feat: adiciona tela da caixa de malha fina e item no menu Operação"
```

---

### Task 6: Verificação final e build

**Files:**
- Modify: `public/build` (regenerado pelo build — entra no git neste repositório)

**Interfaces:**
- Consumes: todas as tasks anteriores.

- [ ] **Step 1: Rodar toda a suíte de testes de malha fina e navegação**

```bash
php artisan test --compact tests/Feature/Analise/MalhaFinaServiceTest.php tests/Feature/Analise/MalhaFinaEndpointTest.php tests/Feature/Analise/MalhaFinaResolucaoTest.php tests/Feature/Analise/MalhaFinaConcluirTest.php tests/Feature/Analise/MalhaFinaCaixaTest.php tests/Feature/Analise/AnalisarMalhaFinaPermissionTest.php
npx vitest run resources/js/navigation/gestao-nav.test.ts
```

Expected: tudo PASS.

- [ ] **Step 2: Rodar a suíte completa do backend**

Run: `php artisan test --compact`
Expected: PASS (sem regressões — em especial caixa do setor, vistorias e consulta de processos, que compartilham `ProcessoQueryService`).

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sem alterações pendentes (ou aplicar e commitar o ajuste).

- [ ] **Step 4: Build do frontend (public/build entra no git neste repositório)**

```bash
npm run build
git add public/build
git commit -m "chore: regenera build do frontend com a caixa de malha fina"
```

Expected: build sem erros; `public/build` commitado.

- [ ] **Step 5: Verificação manual guiada (evidência de ponta a ponta)**

Com o ambiente local de pé (`composer run dev`):
1. Login como gestor → menu Operação exibe "Malha fina".
2. Encaminhar um processo à malha fina (ficha ou lote na consulta) → ele aparece na aba "Em malha fina".
3. Filtrar por protocolo → recorte correto.
4. "Abrir processo" → abre a ficha original do processo.
5. "Concluir" com observação → processo some da aba "Em malha fina" e aparece em "Concluídas" com quem baixou, quando e a observação; status do processo inalterado.
6. Login como analista → item "Malha fina" não aparece no menu; acesso direto à URL retorna 403.

---

## Self-Review

- **Spec coverage:** permissão separada (Task 1), baixa com observação opcional via `resolver()` sem mudar status (Tasks 2/3), caixa com universo `in_fine_mesh` + abas + KPIs + filtros das caixas (Task 4), colunas incl. entrada na malha fina e encaminhado por/motivo (Task 4 `linha()`), abertura do processo original na ficha (Tasks 4/5), menu em Operação com ícone existente (Task 5), auditoria consulta/encaminhar/resolver (Tasks 2/4), sem atribuição própria de revisor (nenhuma task a cria — decisão do usuário), TDD (todas as tasks), build de `public/build` (Task 6).
- **Desvio descoberto no plano (não estava na spec):** o grupo Operação estava no limite de 8 itens; "Resultados do fluxo expresso" foi movido para Relatórios (leitura/recorte, pela árvore de decisão da normativa de menu) para abrir espaço — teste de navegação atualizado na Task 5.
- **Placeholder scan:** sem TBD/TODO; todo passo de código tem código completo.
- **Type consistency:** `resolverAbertos(ViabilityRequest, User, ?string): int` (Task 2) = consumido na Task 3; props da página (Task 4) = consumidas na Task 5; `resolvedBy()` (Task 2) = usado na Task 4; nomes de rota `gestao.malha-fina.index`/`concluir` consistentes entre Tasks 3/4/5.
