# Renovação de Viabilidade de Localização — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o módulo de Renovação de Viabilidade de Localização — o cidadão renova um TVL provisório pelo portal (wizard de 4 passos, mobile-first), o processo vai para análise semi-expressa e, no deferimento, o analista define o tipo (provisória/definitiva) e a nova data de validade, com envio à SEFAZ auditado.

**Architecture:** Uma renovação é uma `ViabilityRequest` com `service_type_id` = `renovacao-tvl`, `origin` = `portal` e `renews_viability_request_id` apontando para a solicitação de origem. Reusa a fila/ficha/decisão semi-expressa existentes (`AnalysisCategory::SemiExpresso`, `AnaliseTecnicaDecisionService`), adicionando `tvl_kind` e `tvl_valid_until` em `viability_decisions`. Declarações de responsabilidade vêm de um catálogo parametrizável versionado. O envio à SEFAZ usa o gateway já existente (hoje `Unavailable`, auditado — Fase 13).

**Tech Stack:** Laravel 13, Inertia v3, React 19, Tailwind 4, PHPUnit 12 (feature tests), spatie/activitylog (auditoria).

**Spec:** `docs/superpowers/specs/2026-09-28-renovacao-viabilidade-design.md`

## Global Constraints

- TDD estrito (Red-Green-Refactor): nenhum código de produção sem teste falhando antes.
- UI, mensagens, commits e documentação em pt-BR; código (variáveis, classes, métodos) em inglês.
- Conventional commits em português, minúsculas, sem ponto final.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Testes: `php artisan test --compact --filter=<NomeDoTeste>`; factories; `LazilyRefreshDatabase` (SQLite `:memory:`).
- Sem fachada: integração SEFAZ/DAM indisponível fica auditada como `bloqueado`, nunca simulada.
- Parâmetro novo só `renovacao.intervalo_meses` (default 3) — gestor muda sem deploy.
- Não criar grupo novo de navegação; item novo (se houver) só em `resources/js/navigation/gestao-nav.ts` e `assertGestaoNavHealth()` verde.
- Form Request para validação; `$request->validated()`, nunca `$request->all()`.
- Casts no método `casts()` do model; return type hints em todos os métodos.

---

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/..._add_renewal_to_viability_requests.php` | `renews_viability_request_id` (FK nullable) |
| `database/migrations/..._add_tvl_validity_to_viability_decisions.php` | `tvl_kind`, `tvl_valid_until` |
| `app/Enums/TvlKind.php` | enum provisoria/definitiva |
| `app/Models/ViabilityRequest.php` | relação `renews` + `isRenewal()` |
| `app/Models/ViabilityDecision.php` | fillable/casts dos novos campos |
| `app/Services/Renovacao/RenovacaoElegibilidadeService.php` | regras de elegibilidade |
| `app/Services/Renovacao/RenovacaoProtocoloService.php` | cria + protocola a renovação |
| `app/Models/ServiceDeclaration.php` + migration + seeder | catálogo de declarações |
| `app/Models/ServiceDeclarationAcceptance.php` + migration | aceite versionado |
| `app/Http/Controllers/Portal/RenovacaoController.php` | wizard do cidadão |
| `app/Http/Requests/Portal/ProtocolarRenovacaoRequest.php` | validação do protocolo |
| `resources/js/pages/portal/renovacao/wizard.tsx` | wizard 4 passos |
| `resources/js/components/renovacao/etapa-*.tsx` | passos do wizard |
| Gestão: ficha simplificada + decisão com tipo/validade | reuso de ficha-analise |
| `database/seeders/ParameterSeeder.php` | `renovacao.intervalo_meses` |

---

### Task 1: Enum TvlKind + campos na decisão

**Files:**
- Create: `app/Enums/TvlKind.php`
- Create: `database/migrations/2026_09_28_140000_add_tvl_validity_to_viability_decisions.php`
- Modify: `app/Models/ViabilityDecision.php` (fillable + casts)
- Test: `tests/Feature/Renovacao/TvlKindDecisionTest.php`

**Interfaces:**
- Produces: `TvlKind::Provisoria`/`TvlKind::Definitiva` (string backed, values `provisoria`/`definitiva`); colunas `viability_decisions.tvl_kind` (string nullable), `tvl_valid_until` (date nullable).

- [ ] **Step 1: Teste que falha**

```php
<?php

namespace Tests\Feature\Renovacao;

use App\Enums\DecisionOutcome;
use App\Enums\TvlKind;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TvlKindDecisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_decisao_grava_tipo_e_validade_da_renovacao(): void
    {
        $request = ViabilityRequest::factory()->create();

        $decision = ViabilityDecision::create([
            'viability_request_id' => $request->id,
            'flow' => 'analise_tecnica',
            'outcome' => DecisionOutcome::Deferida,
            'consolidated_result' => 'permitido',
            'tvl_product_number' => 'TVL-2026-000001',
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2027-09-28',
            'per_cnae' => [],
            'decided_at' => now(),
        ]);

        $this->assertSame(TvlKind::Provisoria, $decision->fresh()->tvl_kind);
        $this->assertSame('2027-09-28', $decision->fresh()->tvl_valid_until->toDateString());
    }
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `php artisan test --compact --filter=TvlKindDecisionTest`
Expected: FAIL — `TvlKind` não existe / coluna `tvl_kind` inexistente.

- [ ] **Step 3: Implementar enum + migration + model**

```php
<?php

namespace App\Enums;

enum TvlKind: string
{
    case Provisoria = 'provisoria';
    case Definitiva = 'definitiva';

    public function label(): string
    {
        return match ($this) {
            self::Provisoria => 'Provisória',
            self::Definitiva => 'Definitiva',
        };
    }
}
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->string('tvl_kind')->nullable()->after('tvl_product_number');
            $table->date('tvl_valid_until')->nullable()->after('tvl_kind');
        });
    }

    public function down(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->dropColumn(['tvl_kind', 'tvl_valid_until']);
        });
    }
};
```

Em `ViabilityDecision`: adicionar `'tvl_kind'` e `'tvl_valid_until'` ao `#[Fillable([...])]` e aos casts:

```php
'tvl_kind' => TvlKind::class,
'tvl_valid_until' => 'date',
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `php artisan test --compact --filter=TvlKindDecisionTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/TvlKind.php app/Models/ViabilityDecision.php database/migrations tests/Feature/Renovacao/TvlKindDecisionTest.php
git commit -m "feat: adiciona tipo e validade do TVL na decisão"
```

---

### Task 2: Vínculo da renovação com a solicitação de origem

**Files:**
- Create: `database/migrations/2026_09_28_140100_add_renews_to_viability_requests.php`
- Modify: `app/Models/ViabilityRequest.php` (fillable, relação `renews`, `isRenewal()`)
- Test: `tests/Feature/Renovacao/RenovacaoVinculoTest.php`

**Interfaces:**
- Consumes: `ViabilityRequest` (Task existente).
- Produces: `ViabilityRequest::renews()` (BelongsTo nullable), `ViabilityRequest::isRenewal(): bool`, coluna `renews_viability_request_id`.

- [ ] **Step 1: Teste que falha**

```php
<?php

namespace Tests\Feature\Renovacao;

use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoVinculoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_renovacao_aponta_para_a_solicitacao_de_origem(): void
    {
        $origem = ViabilityRequest::factory()->create();
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);

        $renovacao = ViabilityRequest::factory()->create([
            'service_type_id' => $tipo->id,
            'renews_viability_request_id' => $origem->id,
        ]);

        $this->assertTrue($renovacao->isRenewal());
        $this->assertSame($origem->id, $renovacao->renews->id);
        $this->assertFalse($origem->isRenewal());
    }
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `php artisan test --compact --filter=RenovacaoVinculoTest`
Expected: FAIL — coluna/método inexistentes.

- [ ] **Step 3: Implementar migration + model**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->foreignId('renews_viability_request_id')
                ->nullable()
                ->after('service_type_id')
                ->constrained('viability_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('renews_viability_request_id');
        });
    }
};
```

Em `ViabilityRequest`: adicionar `'renews_viability_request_id'` ao fillable e:

```php
/**
 * Solicitação de origem que esta renovação prorroga (só em renovação de TVL).
 *
 * @return BelongsTo<ViabilityRequest, $this>
 */
public function renews(): BelongsTo
{
    return $this->belongsTo(ViabilityRequest::class, 'renews_viability_request_id');
}

public function isRenewal(): bool
{
    return $this->renews_viability_request_id !== null;
}
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `php artisan test --compact --filter=RenovacaoVinculoTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/ViabilityRequest.php database/migrations tests/Feature/Renovacao/RenovacaoVinculoTest.php
git commit -m "feat: vincula renovação à solicitação de origem"
```

---

### Task 3: Parâmetro de intervalo entre renovações

**Files:**
- Modify: `database/seeders/ParameterSeeder.php` (adicionar `renovacao.intervalo_meses`)
- Test: `tests/Feature/Renovacao/RenovacaoParametroTest.php`

**Interfaces:**
- Produces: parâmetro `renovacao.intervalo_meses` (int, default 3) lido via o mecanismo de parâmetros existente.

- [ ] **Step 1: Ler o padrão de parâmetro existente**

Inspecionar `database/seeders/ParameterSeeder.php` e o service de leitura de parâmetros para seguir a convenção (chave, tipo, descrição, valor padrão, validação). Não inventar formato novo.

- [ ] **Step 2: Teste que falha**

```php
public function test_intervalo_padrao_entre_renovacoes_e_tres_meses(): void
{
    $this->seed(\Database\Seeders\ParameterSeeder::class);

    $this->assertSame(3, (int) parametro('renovacao.intervalo_meses'));
}
```

(Ajustar `parametro(...)` ao helper/service real do projeto.)

- [ ] **Step 3: Registrar o parâmetro no seeder** (tipo inteiro, faixa 1–60, descrição em pt-BR, default 3)

- [ ] **Step 4: Rodar e confirmar passa**

Run: `php artisan test --compact --filter=RenovacaoParametroTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ParameterSeeder.php tests/Feature/Renovacao/RenovacaoParametroTest.php
git commit -m "feat: parametriza intervalo mínimo entre renovações de TVL"
```

---

### Task 4: Serviço de elegibilidade

**Files:**
- Create: `app/Services/Renovacao/RenovacaoElegibilidadeService.php`
- Create: `app/Services/Renovacao/RenovacaoElegibilidade.php` (resultado: elegivel + motivo)
- Test: `tests/Feature/Renovacao/RenovacaoElegibilidadeTest.php`

**Interfaces:**
- Consumes: `ViabilityRequest` (status, decision.tvl_kind, renews), parâmetro `renovacao.intervalo_meses` (Task 3).
- Produces: `RenovacaoElegibilidadeService::avaliar(ViabilityRequest $origem): RenovacaoElegibilidade` com `->elegivel: bool` e `->motivo: ?string`.

- [ ] **Step 1: Teste que falha — cada bloqueio + elegível**

```php
public function test_bloqueia_quando_ja_ha_renovacao_em_analise(): void
{
    $origem = $this->tvlProvisorioElegivel();
    $this->renovacaoEmAnalise($origem);

    $r = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

    $this->assertFalse($r->elegivel);
    $this->assertSame(
        'Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise.',
        $r->motivo,
    );
}
```

Cobrir também: sem decisão (sem TVL), TVL definitiva, renovada há menos de 3 meses, e o caso elegível.

- [ ] **Step 2: Rodar e confirmar falha**

Run: `php artisan test --compact --filter=RenovacaoElegibilidadeTest`
Expected: FAIL — classe inexistente.

- [ ] **Step 3: Implementar o serviço** com as 4 regras do documento (sem vínculo/decisão, definitiva, <intervalo, já em análise), na ordem, retornando o primeiro motivo.

- [ ] **Step 4: Rodar e confirmar passa**

Run: `php artisan test --compact --filter=RenovacaoElegibilidadeTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Renovacao tests/Feature/Renovacao/RenovacaoElegibilidadeTest.php
git commit -m "feat: avalia elegibilidade à renovação de TVL"
```

---

### Task 5: Catálogo de declarações + aceite versionado

**Files:**
- Create: `app/Models/ServiceDeclaration.php` + migration
- Create: `app/Models/ServiceDeclarationAcceptance.php` + migration
- Create: `database/seeders/ServiceDeclarationSeeder.php` (declarações de `renovacao-tvl`)
- Test: `tests/Feature/Renovacao/ServiceDeclarationTest.php`

**Interfaces:**
- Produces: `ServiceDeclaration::vigentesPara(serviceTypeId): Collection`; `ServiceDeclarationAcceptance` registra `service_declaration_id`, `viability_request_id`, `user_id`, `version`, `accepted_at`.

- [ ] **Step 1: Teste que falha** — lista declarações vigentes do tipo e grava aceite com a versão exata.

- [ ] **Step 2: Rodar e confirmar falha** — Expected: FAIL (tabelas inexistentes).

- [ ] **Step 3: Implementar models + migrations + seeder** (campos: `service_type_id`, `body`, `version`, `required`, `valid_from`, `valid_to`; aceite com FK e `version`).

- [ ] **Step 4: Rodar e confirmar passa** — Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models database/migrations database/seeders tests/Feature/Renovacao/ServiceDeclarationTest.php
git commit -m "feat: catalogo parametrizavel de declarações por tipo de serviço"
```

---

### Task 6: Serviço de protocolo da renovação

**Files:**
- Create: `app/Services/Renovacao/RenovacaoProtocoloService.php`
- Test: `tests/Feature/Renovacao/RenovacaoProtocoloServiceTest.php`

**Interfaces:**
- Consumes: `RenovacaoElegibilidadeService` (Task 4), `ProtocolarSolicitacaoService` ou `ViabilityRequestStateMachine` + `ProtocolNumberGenerator`, `ServiceDeclarationAcceptance` (Task 5).
- Produces: `RenovacaoProtocoloService::protocolar(ViabilityRequest $origem, User $ator, array $contato, array $declaracoesAceitas): ViabilityRequest` — cria a renovação (cópia somente-leitura de endereço/CNAEs da origem, `renews_viability_request_id`, categoria semi-expresso), grava aceites e protocola.

- [ ] **Step 1: Teste que falha** — protocolar cria renovação semi-expresso vinculada, sem alterar endereço/atividades, exigindo todas as declarações obrigatórias.

- [ ] **Step 2: Rodar e confirmar falha** — Expected: FAIL.

- [ ] **Step 3: Implementar** — recusar se não elegível; recusar se faltar declaração obrigatória; criar + protocolar + gravar aceites em transação; auditar.

- [ ] **Step 4: Rodar e confirmar passa** — Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Renovacao tests/Feature/Renovacao/RenovacaoProtocoloServiceTest.php
git commit -m "feat: protocola renovação de TVL como semi-expresso"
```

---

### Task 7: Portal — rotas, controller e wizard (mobile-first)

**Files:**
- Create: `app/Http/Controllers/Portal/RenovacaoController.php`
- Create: `app/Http/Requests/Portal/ProtocolarRenovacaoRequest.php`
- Modify: `routes/portal.php`
- Create: `resources/js/pages/portal/renovacao/wizard.tsx`
- Create: `resources/js/components/renovacao/etapa-{confirmar-tvl,contato,declaracoes,revisar}.tsx`
- Test: `tests/Feature/Renovacao/RenovacaoPortalTest.php`

**Interfaces:**
- Consumes: `RenovacaoElegibilidadeService`, `RenovacaoProtocoloService`, `ServiceDeclaration::vigentesPara`.
- Produces: rotas `portal/renovacao/{origem}/nova` (GET wizard), `portal/renovacao/{origem}` (POST protocolar); lista de TVLs elegíveis no painel.

- [ ] **Step 1: Teste que falha** — GET do wizard só com TVL elegível (403/redirect com motivo quando bloqueado); POST exige declarações e protocola.

- [ ] **Step 2: Rodar e confirmar falha** — Expected: FAIL.

- [ ] **Step 3: Implementar controller + Form Request + rotas.**

- [ ] **Step 4: Implementar wizard React (4 passos, mobile-first, acessível).**

- [ ] **Step 5: Rodar testes + build** — `php artisan test --compact --filter=RenovacaoPortalTest` e `npm run build`. Expected: PASS + build ok.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/portal.php resources/js tests/Feature/Renovacao/RenovacaoPortalTest.php public/build
git commit -m "feat: wizard de renovação de viabilidade no portal"
```

---

### Task 8: Gestão — ficha simplificada e decisão com tipo/validade

**Files:**
- Modify: `app/Services/Analise/AnaliseTecnicaDecisionService.php` (aceitar `tvl_kind`/`tvl_valid_until` na renovação)
- Modify: ficha de análise (condicionantes + campos tipo/validade no deferimento de renovação)
- Test: `tests/Feature/Renovacao/RenovacaoDecisaoTest.php`

**Interfaces:**
- Consumes: `TvlKind` (Task 1), `ViabilityRequest::isRenewal()` (Task 2).
- Produces: no deferimento de renovação, grava `tvl_kind` + `tvl_valid_until`; indeferimento exige fundamentação; dispara envio SEFAZ (gateway existente).

- [ ] **Step 1: Teste que falha** — deferir renovação exige tipo + validade e os grava; indeferir exige fundamentação; condicionante não atendido → indeferido.

- [ ] **Step 2: Rodar e confirmar falha** — Expected: FAIL.

- [ ] **Step 3: Implementar** — ramo de renovação na decisão; validação dos campos; gravação; auditoria; envio SEFAZ via gateway (audita `bloqueado` hoje).

- [ ] **Step 4: Rodar e confirmar passa** — Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services app/Http resources/js tests/Feature/Renovacao/RenovacaoDecisaoTest.php
git commit -m "feat: decisão da renovação define tipo e nova validade do TVL"
```

---

## Self-Review

- **Spec coverage:** elegibilidade (Task 4), wizard portal (Task 7), análise semi-expressa + tipo/validade (Task 8), envio SEFAZ auditado (Task 8), declarações parametrizáveis (Task 5), parâmetro (Task 3), modelo de dados (Tasks 1–2), regra "não altera endereço/atividades" (Tasks 6–7). Coberto.
- **Placeholder scan:** Tasks 3, 4, 5, 6, 7, 8 têm um passo "ler padrão existente / implementar" sem código completo — intencional: dependem de convenções que o executor deve confirmar no código (helper de parâmetro, padrão da ficha). O executor DEVE ler os arquivos irmãos antes de codar e NÃO inventar formato novo.
- **Type consistency:** `TvlKind`, `renews_viability_request_id`, `renews()`, `isRenewal()`, `RenovacaoElegibilidadeService::avaliar`, `RenovacaoProtocoloService::protocolar`, `ServiceDeclaration::vigentesPara` consistentes entre tasks.

## Notas de execução

- Confirmar o helper/service de leitura de parâmetro real antes da Task 3.
- Confirmar o padrão da ficha de análise (condicionantes) antes da Task 8.
- SEFAZ/DAM: gateway `Unavailable` hoje — registrar pendência Fase 13, nunca simular.
