# Escritório virtual — Motor M1 (fluxo da SEDE) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o fluxo da SEDE de escritório virtual: capturar a pergunta "quero ser sede?", detectar o gatilho (CNAE 8211-3/00 + sede=Sim) e mandar à análise, permitir o analista confirmar "Sede de Escritório Virtual" na ficha, e no deferimento travar a inscrição imobiliária + gravar a condicionante EV no produto.

**Architecture:** Campos aditivos em `viability_requests` (pergunta do requerente) e `viability_decisions` (confirmação do analista); um `VirtualOfficeInscriptionLock` (tabela) criado no deferimento da sede. Gatilho como um ramo explícito em `FluxoExpressoService::decidirSobLock` (o mecanismo `RiskTrigger` existe mas está desconectado do caminho real). Flag na ficha via nova coluna em `analysis_records`, propagada à decisão em `AnaliseTecnicaDecisionService::registrarDecisao`. `is_virtual_office` (categoria existente) permanece e passa a ser DERIVADA dos novos flags.

**Tech Stack:** Laravel 11 (PHP 8.4), PostgreSQL, PHPUnit (`php artisan test`, SQLite :memory:), Inertia/React.

**Escopo — FORA (planos irmãos):** abrigado + Lista EV de CNAEs + bloqueio de cadastro (M2); `DesvincularInscricaoService` / mudança de endereço / SEFAZ / notificar abrigados (M3, parte depende do desfecho spec-2); validade Definitivo/Pré-operacional (desfecho spec-2). Ref: `docs/superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md` RN-EV-01..04, RN-EV-07.

> **Decisão de dados (EV-3):** novos flags separam sede (`*_hq`) de abrigado (M2 `*_tenant`). O `is_virtual_office` de hoje é a CATEGORIA (dirige o filtro "Sede de Escritório" e relatórios via `ProcessoQueryService`); no deferimento da sede ele passa a ser setado a partir de `is_virtual_office_hq` (categoria derivada), sem quebrar T01/relatórios.

---

### Task 1: Migration — pergunta da sede + flag da decisão

**Files:**
- Create: `database/migrations/2026_07_16_100000_add_virtual_office_hq_to_viability.php`
- Modify: `app/Models/ViabilityRequest.php` (fillable), `app/Models/ViabilityDecision.php` (fillable)
- Test: `tests/Feature/EscritorioVirtual/SedeSchemaTest.php`

- [ ] **Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escritório virtual — SEDE (reunião SEDUR 2026-07-16). Pergunta ao requerente
 * "será sede de escritório virtual?" na solicitação; confirmação do analista no
 * produto/decisão. Abrigado (*_tenant) vem no M2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->boolean('wants_virtual_office_hq')->default(false)->after('is_virtual_office');
        });
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->boolean('is_virtual_office_hq')->default(false)->after('consolidated_result');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', fn (Blueprint $t) => $t->dropColumn('wants_virtual_office_hq'));
        Schema::table('viability_decisions', fn (Blueprint $t) => $t->dropColumn('is_virtual_office_hq'));
    }
};
```

- [ ] **Step 2: Test** `tests/Feature/EscritorioVirtual/SedeSchemaTest.php`

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedeSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_wants_virtual_office_hq_e_fillable_e_bool(): void
    {
        $request = ViabilityRequest::factory()->create(['wants_virtual_office_hq' => true]);
        $this->assertTrue($request->fresh()->wants_virtual_office_hq);
    }
}
```

- [ ] **Step 3: Run** `php artisan test tests/Feature/EscritorioVirtual/SedeSchemaTest.php` → FAIL (column/fillable missing).

- [ ] **Step 4:** Add `'wants_virtual_office_hq'` to `ViabilityRequest` `#[Fillable([...])]` and cast `'wants_virtual_office_hq' => 'boolean'`. Add `'is_virtual_office_hq'` to `ViabilityDecision` `#[Fillable([...])]` and cast `'boolean'`.

- [ ] **Step 5: Run** → PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_16_100000_add_virtual_office_hq_to_viability.php app/Models/ViabilityRequest.php app/Models/ViabilityDecision.php tests/Feature/EscritorioVirtual/SedeSchemaTest.php
git commit -m "feat(ev): pergunta sede (wants_virtual_office_hq) + flag da decisão (is_virtual_office_hq)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Tabela + model `VirtualOfficeInscriptionLock`

**Files:**
- Create: `database/migrations/2026_07_16_100100_create_virtual_office_inscription_locks.php`
- Create: `app/Models/VirtualOfficeInscriptionLock.php`
- Test: `tests/Feature/EscritorioVirtual/InscriptionLockTest.php`

- [ ] **Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava de inscrição imobiliária pela SEDE ativa (RN-EV-03). Enquanto houver um
 * lock ativo para uma inscrição, ela está vinculada a uma sede de escritório
 * virtual. O M3 (desvinculação) desativa o lock; o M2 (abrigado) o consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_office_inscription_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('property_registration');
            $table->foreignId('sede_viability_request_id')->constrained('viability_requests')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamp('locked_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['property_registration', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_office_inscription_locks');
    }
};
```

- [ ] **Step 2: Test** `tests/Feature/EscritorioVirtual/InscriptionLockTest.php`

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_ativo_por_inscricao(): void
    {
        $sede = ViabilityRequest::factory()->create();
        VirtualOfficeInscriptionLock::create([
            'property_registration' => '123.456.789',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        $this->assertTrue(VirtualOfficeInscriptionLock::ativoPara('123.456.789'));
        $this->assertFalse(VirtualOfficeInscriptionLock::ativoPara('000.000.000'));
    }
}
```

- [ ] **Step 3: Run** → FAIL (model/method missing).

- [ ] **Step 4: Model** `app/Models/VirtualOfficeInscriptionLock.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trava de inscrição imobiliária pela sede de escritório virtual ativa
 * (RN-EV-03). `ativoPara()` responde se uma inscrição já está vinculada a uma
 * sede — usado pelo M2 (abrigado) e pela desvinculação (M3).
 */
#[Fillable(['property_registration', 'sede_viability_request_id', 'active', 'locked_at', 'released_at'])]
class VirtualOfficeInscriptionLock extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'locked_at' => 'datetime', 'released_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class, 'sede_viability_request_id');
    }

    public static function ativoPara(string $propertyRegistration): bool
    {
        return static::query()
            ->where('property_registration', $propertyRegistration)
            ->where('active', true)
            ->exists();
    }
}
```

- [ ] **Step 5: Run** → PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_16_100100_create_virtual_office_inscription_locks.php app/Models/VirtualOfficeInscriptionLock.php tests/Feature/EscritorioVirtual/InscriptionLockTest.php
git commit -m "feat(ev): tabela/model VirtualOfficeInscriptionLock (trava de inscrição da sede)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Parâmetro do CNAE gatilho + texto da condicionante

**Files:**
- Modify: `config/sile.php` (bloco `analise`), `database/seeders/ParameterSeeder.php`
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php` + `tests/Feature/Seeders/DatabaseSeederTest.php` (contagem 89→91)

- [ ] **Step 1:** In `config/sile.php`, no bloco `'analise' => [...]`, adicione:

```php
        'escritorio_virtual' => [
            'cnae_gatilho_sede' => '8211-3/00',
            'condicionante_sede' => 'A viabilidade é DEFERIDA na condição de prestação de serviços de escritório virtual, nos termos da legislação vigente.',
        ],
```

- [ ] **Step 2:** In `database/seeders/ParameterSeeder.php`, junto do grupo `analise`, adicione dois parâmetros:

```php
            'analise.escritorio_virtual.cnae_gatilho_sede' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => '8211-3/00',
                'validation_rules' => ['required', 'string', 'max:12'],
                'description' => 'CNAE que dispara a análise de sede de escritório virtual (Serviços combinados de escritório e apoio administrativo)',
            ],
            'analise.escritorio_virtual.condicionante_sede' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => 'A viabilidade é DEFERIDA na condição de prestação de serviços de escritório virtual, nos termos da legislação vigente.',
                'validation_rules' => ['required', 'string', 'max:2000'],
                'description' => 'Texto da condicionante gravada no produto da sede de escritório virtual',
            ],
```

- [ ] **Step 3:** Bump the seeder-count assertions from `89` to `91` in both `tests/Feature/Seeders/ParameterSeederTest.php` (2 occurrences) and `tests/Feature/Seeders/DatabaseSeederTest.php` (2 occurrences), and update the count comment in DatabaseSeederTest.

- [ ] **Step 4: Run** `php artisan test tests/Feature/Seeders` → PASS.

- [ ] **Step 5: Commit**

```bash
git add config/sile.php database/seeders/ParameterSeeder.php tests/Feature/Seeders/ParameterSeederTest.php tests/Feature/Seeders/DatabaseSeederTest.php
git commit -m "feat(ev): parâmetros do gatilho sede (CNAE 8211-3/00) e texto da condicionante

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Capturar `wants_virtual_office_hq` no passo do imóvel (portal)

**Files:**
- Modify: `app/Http/Requests/Portal/UpdateSolicitacaoImovelRequest.php` (rules)
- Modify: `app/Http/Controllers/Portal/SolicitacaoImovelController.php` (`update`, ~linha 66)
- Test: `tests/Feature/EscritorioVirtual/SedePerguntaPortalTest.php`

- [ ] **Step 1: Test** — mirror an existing imóvel-step test (procure `tests/Feature/*Imovel*` / `Solicitacao*` para o setup real: usuário do portal, solicitação rascunho own). Assert que ao enviar `wants_virtual_office_hq=true` no update do imóvel, persiste na solicitação.

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedePerguntaPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_imovel_persiste_wants_virtual_office_hq(): void
    {
        // AJUSTE o setup ao padrão real do passo do imóvel (ver um teste existente
        // de SolicitacaoImovel): usuário requerente + solicitação rascunho dele +
        // payload mínimo válido do imóvel + wants_virtual_office_hq=true.
        $requester = User::factory()->create();
        $solicitacao = ViabilityRequest::factory()->create([
            'requester_user_id' => $requester->id,
            'status' => 'rascunho',
        ]);

        $this->actingAs($requester)
            ->patch("/portal/solicitacoes/{$solicitacao->id}/imovel", [
                // + campos obrigatórios reais do UpdateSolicitacaoImovelRequest
                'property_registration' => '123.456.789',
                'address_street' => 'Rua X',
                'address_number' => '100',
                'address_neighborhood' => 'Centro',
                'is_virtual_office' => false,
                'wants_virtual_office_hq' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($solicitacao->fresh()->wants_virtual_office_hq);
    }
}
```

> Nota: confirme a ROTA e os campos obrigatórios reais em `UpdateSolicitacaoImovelRequest` e no teste existente do passo do imóvel; ajuste o payload para passar a validação (o objetivo do teste é só o novo campo).

- [ ] **Step 2: Run** → FAIL (campo não persiste / não validado).

- [ ] **Step 3:** Em `UpdateSolicitacaoImovelRequest::rules()`, adicione `'wants_virtual_office_hq' => ['sometimes', 'boolean']` (junto de `is_virtual_office`). Em `SolicitacaoImovelController::update`, no array do `$solicitacao->update([...])` (linha ~66), adicione `'wants_virtual_office_hq' => $request->boolean('wants_virtual_office_hq')`.

- [ ] **Step 4: Run** → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Portal/UpdateSolicitacaoImovelRequest.php app/Http/Controllers/Portal/SolicitacaoImovelController.php tests/Feature/EscritorioVirtual/SedePerguntaPortalTest.php
git commit -m "feat(ev): captura da pergunta 'será sede?' no passo do imóvel (portal)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Gatilho da sede → análise (`FluxoExpressoService`)

**Files:**
- Create: `app/Services/Expresso/SedeEscritorioVirtualGatilho.php` (regra isolada, testável)
- Modify: `app/Services/Expresso/FluxoExpressoService.php:114-116` (novo ramo antes de `emitir`)
- Test: `tests/Feature/EscritorioVirtual/GatilhoSedeTest.php`

- [ ] **Step 1:** READ `FluxoExpressoService::decidirSobLock` (linhas ~83-117). O ramo novo entra após a checagem de `Pendente` (linha ~114) e antes de `return $this->emitir(...)` (linha ~116).

- [ ] **Step 2: Regra isolada** `app/Services/Expresso/SedeEscritorioVirtualGatilho.php`:

```php
<?php

namespace App\Services\Expresso;

use App\Models\ViabilityRequest;
use App\Support\Settings;

/**
 * Gatilho de SEDE de escritório virtual (RN-EV-01): processo com o CNAE gatilho
 * (default 8211-3/00) E o requerente respondeu "será sede? = Sim" NÃO conclui no
 * expresso — vai para análise humana. CNAE gatilho é parametrizável.
 */
class SedeEscritorioVirtualGatilho
{
    public function aplica(ViabilityRequest $request): bool
    {
        if (! $request->wants_virtual_office_hq) {
            return false;
        }

        $cnaeGatilho = $this->normalizar((string) Settings::get(
            'analise.escritorio_virtual.cnae_gatilho_sede',
            config('sile.analise.escritorio_virtual.cnae_gatilho_sede', '8211-3/00'),
        ));

        return $request->cnaes->contains(fn ($cnae): bool => $this->normalizar($cnae->code) === $cnaeGatilho);
    }

    /** Só dígitos, para comparar 8211-3/00 == 82113 00 == 8211300. */
    private function normalizar(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }
}
```

- [ ] **Step 3: Test** `tests/Feature/EscritorioVirtual/GatilhoSedeTest.php` — unit-ish sobre a regra:

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatilhoSedeTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplica_quando_cnae_8211_e_quer_ser_sede(): void
    {
        $cnae = Cnae::factory()->create(['code' => '8211-3/00']);
        $req = ViabilityRequest::factory()->create(['wants_virtual_office_hq' => true]);
        $req->cnaes()->attach($cnae, ['is_primary' => true]);

        $this->assertTrue(app(SedeEscritorioVirtualGatilho::class)->aplica($req->fresh()));
    }

    public function test_nao_aplica_sem_a_flag_mesmo_com_cnae(): void
    {
        $cnae = Cnae::factory()->create(['code' => '8211-3/00']);
        $req = ViabilityRequest::factory()->create(['wants_virtual_office_hq' => false]);
        $req->cnaes()->attach($cnae, ['is_primary' => true]);

        $this->assertFalse(app(SedeEscritorioVirtualGatilho::class)->aplica($req->fresh()));
    }
}
```

> Confirme o factory de `Cnae` (campo `code`); ajuste se o factory exigir outros campos.

- [ ] **Step 4: Run** → FAIL (classe não existe). Implemente (Step 2) → PASS.

- [ ] **Step 5:** Hook no `FluxoExpressoService`. Injete `SedeEscritorioVirtualGatilho $gatilhoSede` no construtor. Em `decidirSobLock`, antes do `return $this->emitir($request, $resolved, $actor);`:

```php
        if ($this->gatilhoSede->aplica($request)) {
            return $this->encaminharAnalise($request, 'gatilho: sede de escritório virtual', $actor, $resolved);
        }
```

- [ ] **Step 6: Test de integração** — adicione ao `GatilhoSedeTest` (ou um Feature test do fluxo) um caso que dirige `FluxoExpressoService::decide` com o setup de um processo elegível ao expresso MAIS o gatilho, e assert que termina em `em_analise` (não emite decisão). Espelhe o setup de `tests/Feature/Analise/EncaminhamentoAnaliseTest.php`.

- [ ] **Step 7: Run** `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Expresso` → PASS (sem regressão no expresso).

- [ ] **Step 8: Commit**

```bash
git add app/Services/Expresso/SedeEscritorioVirtualGatilho.php app/Services/Expresso/FluxoExpressoService.php tests/Feature/EscritorioVirtual/GatilhoSedeTest.php
git commit -m "feat(ev): gatilho sede (CNAE 8211-3/00 + quer ser sede) encaminha à análise

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Flag "Sede de Escritório Virtual" na ficha de análise

**Files:**
- Create: `database/migrations/2026_07_16_100200_add_is_virtual_office_hq_to_analysis_records.php`
- Modify: `app/Models/AnalysisRecord.php` (fillable/cast), `app/Http/Requests/Gestao/AnalysisRecordRequest.php` (rule), `app/Http/Controllers/Gestao/AnalysisRecordController.php` (autosave inclui o campo)
- Modify: `app/Http/Resources/AnalysisRecordResource.php` (expor) + `resources/js/pages/gestao/ficha-analise/show.tsx` (checkbox)
- Test: `tests/Feature/EscritorioVirtual/FichaSedeFlagTest.php`

- [ ] **Step 1: Migration** — `analysis_records.is_virtual_office_hq` boolean nullable (nullable: fichas antigas não têm o dado).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_records', fn (Blueprint $t) => $t->boolean('is_virtual_office_hq')->nullable()->after('parking'));
    }

    public function down(): void
    {
        Schema::table('analysis_records', fn (Blueprint $t) => $t->dropColumn('is_virtual_office_hq'));
    }
};
```

- [ ] **Step 2: Test** `tests/Feature/EscritorioVirtual/FichaSedeFlagTest.php` — autosave grava o flag e o resource o expõe. Espelhe `tests/Feature/Analise/AnalysisRecordAutosaveTest.php` para o setup (analista, processo em_analise, payload da ficha).

```php
    public function test_autosave_grava_sede_flag(): void
    {
        // setup: analista com analisar-processos + processo em análise (ver AnalysisRecordAutosaveTest)
        // PATCH/POST autosave com is_virtual_office_hq=true → recarrega e assert true no record + no resource.
        $this->markTestIncomplete('preencher com o setup real do autosave da ficha');
    }
```

> Substitua o `markTestIncomplete` pelo teste real copiando o setup de `AnalysisRecordAutosaveTest`. (O executor DEVE escrever o teste completo antes de implementar — não deixar incompleto.)

- [ ] **Step 3:** `AnalysisRecord`: adicione `'is_virtual_office_hq'` ao fillable + cast `'boolean'`. `AnalysisRecordRequest::rules()`: `'is_virtual_office_hq' => ['sometimes', 'nullable', 'boolean']`. `AnalysisRecordController` autosave: inclua o campo no que é persistido (siga como os outros campos escalares da ficha são salvos). `AnalysisRecordResource`: exponha `'is_virtual_office_hq'`.

- [ ] **Step 4:** UI `resources/js/pages/gestao/ficha-analise/show.tsx`: um checkbox "Sede de Escritório Virtual" que lê/escreve `ficha.is_virtual_office_hq` pelo mesmo mecanismo de autosave dos demais campos. `npx tsc --noEmit` + `npm run build` verdes (não commitar public/build).

- [ ] **Step 5: Run** `php artisan test tests/Feature/EscritorioVirtual/FichaSedeFlagTest.php` → PASS; tsc/build ok.

- [ ] **Step 6: Commit** (liste os arquivos; não `git add -A`; não commitar public/build).

```bash
git add database/migrations/2026_07_16_100200_add_is_virtual_office_hq_to_analysis_records.php app/Models/AnalysisRecord.php app/Http/Requests/Gestao/AnalysisRecordRequest.php app/Http/Controllers/Gestao/AnalysisRecordController.php app/Http/Resources/AnalysisRecordResource.php resources/js/pages/gestao/ficha-analise/show.tsx tests/Feature/EscritorioVirtual/FichaSedeFlagTest.php
git commit -m "feat(ev): flag 'Sede de Escritório Virtual' na ficha de análise

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Deferimento da sede — propagar flag, travar inscrição, gravar condicionante

**Files:**
- Modify: `app/Services/Analise/AnaliseTecnicaDecisionService.php` (`registrarDecisao`, ~linha 117-145)
- Test: `tests/Feature/EscritorioVirtual/DeferirSedeTest.php`

- [ ] **Step 1:** READ `AnaliseTecnicaDecisionService::decide` (linha 60) + `registrarDecisao` (117) para ver como a `ViabilityDecision` é criada e como `consolidated_result`/condicionantes são resolvidos (`temCondicionantes`, ~189-217).

- [ ] **Step 2: Test** `tests/Feature/EscritorioVirtual/DeferirSedeTest.php`:

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
// + imports do setup real de deferimento (AnalysisRecord finalizada, analista)
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeferirSedeTest extends TestCase
{
    use RefreshDatabase;

    // Helper: cria processo em_analise com CNAE 8211-3/00, inscrição, e uma
    // AnalysisRecord finalizada com is_virtual_office_hq=$sede e per_cnae deferida.
    // (copie o setup de tests/Feature/Analise/AnaliseTecnicaDecisionTest.php)

    public function test_deferir_com_sede_sim_trava_inscricao_e_grava_flag(): void
    {
        $this->markTestIncomplete('preencher com o setup real de decisão da AnaliseTecnicaDecisionService');
        // Arrange: processo em_analise, CNAE 8211-3/00, property_registration '123',
        //          ficha finalizada is_virtual_office_hq=true, per_cnae deferida.
        // Act: app(AnaliseTecnicaDecisionService::class)->decide($record, $analista);
        // Assert:
        //   $decision->is_virtual_office_hq === true
        //   VirtualOfficeInscriptionLock::ativoPara('123') === true
        //   $request->fresh()->is_virtual_office === true (categoria derivada)
        //   condicionante EV presente (consolidated_result permitido_com_condicoes OU texto)
    }

    public function test_deferir_com_sede_nao_nao_trava(): void
    {
        $this->markTestIncomplete('idem, com is_virtual_office_hq=false → sem lock, sem flag');
    }
}
```

> O executor DEVE substituir os `markTestIncomplete` por testes reais (setup copiado de `AnaliseTecnicaDecisionTest`) ANTES de implementar. RN-EV-03 exige: trava SÓ com CNAE 8211-3/00 **e** flag sede=Sim.

- [ ] **Step 3:** Em `registrarDecisao` (ou no fluxo de `decide` que monta a `ViabilityDecision`), quando a ficha tem `is_virtual_office_hq === true`:
  - setar `is_virtual_office_hq => true` na `ViabilityDecision`;
  - se o processo contém o CNAE gatilho (default 8211-3/00, via `SedeEscritorioVirtualGatilho` ou uma checagem equivalente) → criar `VirtualOfficeInscriptionLock` ativa para `$request->property_registration` + `sede_viability_request_id`;
  - marcar `$request->is_virtual_office = true` (categoria derivada — via forceFill/update, seguindo como o serviço já escreve no request);
  - anexar a condicionante EV (texto do parâmetro `analise.escritorio_virtual.condicionante_sede`) ao conjunto de condicionantes que já produz `consolidated_result = permitido_com_condicoes` (RN-EV-04);
  - auditar `('analise','ev-sede-deferida', ...)`.
  Quando `is_virtual_office_hq !== true`: nada disso (RN-EV-03: flag Não ⇒ sem trava).

- [ ] **Step 4: Run** `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Analise` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analise/AnaliseTecnicaDecisionService.php tests/Feature/EscritorioVirtual/DeferirSedeTest.php
git commit -m "feat(ev): deferir sede trava inscrição + grava flag/condicionante EV (RN-EV-03/04)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Regressão + verificação

- [ ] `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Analise tests/Feature/Expresso tests/Feature/Seeders tests/Unit` → tudo verde (skips postgis OK).
- [ ] `npx tsc --noEmit` → 0; `npm run build` → ok.

---

## Self-Review (feito ao escrever)

**Cobertura (RN-EV do M1):** RN-EV-01 gatilho (T4,T5); RN-EV-02 flag na ficha (T6) + propagação (T7); RN-EV-03 trava só com CNAE+flag (T2,T7); RN-EV-04 condicionante (T3,T7); RN-EV-07 parametrização (T3). `is_virtual_office` derivada (T7).

**Fora do M1 (planos irmãos):** RN-EV-05 abrigado + Lista EV (M2 — precisa da decisão fonte da lista: nova tabela SEDUR-endpoint vs reusar `SanitaryRiskClassification.autorizado_escritorio_virtual`); RN-EV-06 desvinculação/SEFAZ/notificar abrigados (M3, parte bloqueada no desfecho spec-2 e Fase 13); validade do abrigado (desfecho spec-2).

**Riscos/notas de execução:** (a) T6/T7 têm `markTestIncomplete` como ANDAIME — o executor DEVE escrever o teste real copiando o setup de `AnalysisRecordAutosaveTest`/`AnaliseTecnicaDecisionTest` antes de implementar (não deixar incompleto). (b) Confirmar rota/campos do passo do imóvel (T4) e o factory de `Cnae` (T5). (c) `routes` não é tocado no M1. (d) A trava/condicionante devem casar com a forma real como `AnaliseTecnicaDecisionService` escreve na decisão/request — ler antes (T7 Step 1). (e) Seeder count 89→91 (T3).
