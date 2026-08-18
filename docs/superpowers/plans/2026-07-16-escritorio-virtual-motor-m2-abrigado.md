# Escritório virtual — Motor M2 (ABRIGADO + Lista EV) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modelar o ABRIGADO de escritório virtual: uma Lista EV versionada de CNAEs permitidos (fonte oficial: endpoint SEDUR, importada por snapshot), bloqueio no cadastro quando o CNAE do abrigado não está na lista, detecção do abrigado pela inscrição travada por uma sede ativa, e o produto do abrigado referenciando o nº TVL da sede.

**Architecture:** Nova tabela versionada `virtual_office_activity_cnaes` (chave `rule_version_id`+`cnae_code`, espelha `risk_sanitary_classifications`) com domínio `RuleDomain::AtividadesEscritorioVirtual`, importada por `EscritorioVirtualCnaeImportService` (snapshot CSV local; endpoint SEDUR = origem de verdade documentada). O abrigado é DERIVADO: uma solicitação cuja `property_registration` tem `VirtualOfficeInscriptionLock` ativo (sede) — o service type "Atividades em Escritórios Virtuais" é só rótulo (catálogo não-autoritativo). Bloqueio de CNAE no passo de atividades do portal. Produto grava `virtual_office_hq_tvl_number` (TVL da sede) + `is_virtual_office_tenant`.

**Tech Stack:** Laravel 11 (PHP 8.4), PostgreSQL, PHPUnit (SQLite :memory:), Inertia/React.

**Escopo — FORA:** validade do abrigado = validade da sede (BLOQUEADO no desfecho spec-2 — não há campo `validade`); desvinculação/mudança de endereço/notificar abrigados (M3); SEFAZ (Fase 13). Ref: spec motor RN-EV-05, RN-EV-07.

> **Decisões (2026-07-16):** (1) Lista EV = **nova tabela** alimentada pelo endpoint SEDUR (não reusar o `autorizado_escritorio_virtual` da VISA — autoridade diferente). (2) Abrigado = inscrição com **sede lock ativo** (não o service type). (3) "End. Virtual - TVL Nº" = `tvl_product_number` da decisão da sede. (4) Formato do payload do endpoint SEDUR ainda desconhecido → **importar de snapshot CSV local** (`database/data/escritorio-virtual/atividades-permitidas.csv`); fetch live do endpoint é follow-up quando o formato/homologação vier.

---

### Task 1: Lista EV — domínio de regra + tabela versionada + model

**Files:**
- Modify: `app/Enums/RuleDomain.php` (novo case + label + isSensitive)
- Create: `database/migrations/2026_07_16_110000_create_virtual_office_activity_cnaes.php`
- Create: `app/Models/VirtualOfficeActivityCnae.php`
- Test: `tests/Feature/EscritorioVirtual/ListaEvSchemaTest.php`

- [ ] **Step 1:** READ `app/Enums/RuleDomain.php` (cases + `label()` + `isSensitive()`) e `app/Models/SanitaryRiskClassification.php` + sua migration `2026_06_14_014717_create_risk_sanitary_classifications_table.php` para espelhar exatamente o shape versionado.

- [ ] **Step 2:** Add to `RuleDomain`: case `AtividadesEscritorioVirtual = 'atividades_escritorio_virtual'`; arm in `label()` → `'Atividades permitidas em escritório virtual'`; in `isSensitive()` return the same default as the other reference lists (não sensível — seguir o padrão dos domínios de classificação; confirme o default do enum).

- [ ] **Step 3: Migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista oficial de CNAEs permitidos para ABRIGADO de escritório virtual
 * (reunião SEDUR 2026-07-16, RN-EV-05/07). Fonte: endpoint SEDUR
 * AtividadesPermitidasEmEscritorioVirtual.php, importada por snapshot
 * versionado. Espelha risk_sanitary_classifications (chave rule_version+cnae).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae_code'); // dígitos, ex.: '8211300'
            $table->string('cnae_description')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'cnae_code']);
            $table->index('cnae_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_office_activity_cnaes');
    }
};
```

- [ ] **Step 4: Test** `tests/Feature/EscritorioVirtual/ListaEvSchemaTest.php` — cria uma `RuleVersion` vigente do novo domínio + uma linha, e testa um helper `VirtualOfficeActivityCnae::permitido(string $cnaeCode): bool` que consulta a versão vigente e normaliza dígitos:

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\Rules\RuleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaEvSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_permitido_consulta_a_versao_vigente_normalizando_digitos(): void
    {
        $version = app(RuleVersionService::class)->openDraft(RuleDomain::AtividadesEscritorioVirtual, 'ev-2026-07');
        app(RuleVersionService::class)->publish($version);
        VirtualOfficeActivityCnae::create([
            'rule_version_id' => RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first()->id,
            'cnae_code' => '6204000',
            'cnae_description' => 'Consultoria em TI',
        ]);

        $this->assertTrue(VirtualOfficeActivityCnae::permitido('6204-0/00'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('4712-1/00'));
    }
}
```

> Confirme a assinatura de `RuleVersionService::openDraft`/`publish` (pode exigir autor/quatro-olhos se o domínio for sensível — por isso o Step 2 deve marcá-lo NÃO sensível). Ajuste o test setup ao contrato real.

- [ ] **Step 5:** Run → FAIL. Create `app/Models/VirtualOfficeActivityCnae.php` (fillable `rule_version_id`/`cnae_code`/`cnae_description`; `ruleVersion()` BelongsTo; static `permitido(string $code): bool` que normaliza dígitos e checa `whereHas('ruleVersion', vigente do domínio)`). Run → PASS.

- [ ] **Step 6: Commit** (`git add` os 4 arquivos; nunca `-A`).

```bash
git commit -m "feat(ev): tabela versionada da Lista EV (CNAEs permitidos p/ abrigado)"
```

---

### Task 2: Import do snapshot da Lista EV + seed

**Files:**
- Create: `database/data/escritorio-virtual/atividades-permitidas.csv` (snapshot inicial — cnae_code,cnae_description)
- Create: `app/Services/EscritorioVirtual/EscritorioVirtualCnaeImportService.php`
- Create: `database/seeders/EscritorioVirtualCnaeSeeder.php` (+ registrar no DatabaseSeeder se os outros de referência forem)
- Test: `tests/Feature/EscritorioVirtual/ListaEvImportTest.php`

- [ ] **Step 1:** READ `app/Services/Risco/RiscoSanitarioImportService.php` (o `import(RuleVersion, $csvPath)` idempotente com `upsert`) e `database/seeders/RiscoSanitarioSeeder.php` (openDraft→publish→import→audit). Espelhar.

- [ ] **Step 2:** Snapshot CSV mínimo `database/data/escritorio-virtual/atividades-permitidas.csv` — cabeçalho `cnae_code,cnae_description` + algumas linhas reais de atividades de escritório (ex.: 8211-3/00, 6204-0/00, 6920-6/01…). NOTA no topo do seeder: fonte oficial = endpoint SEDUR `AtividadesPermitidasEmEscritorioVirtual.php`; este CSV é o snapshot até o fetch live (formato do endpoint a confirmar).

- [ ] **Step 3: Test** `ListaEvImportTest`: roda o seeder → assert que a versão vigente do domínio existe + as linhas do CSV foram importadas + `permitido()` responde certo + idempotência (rodar 2x não duplica). Mirror `tests/Feature/Risco/*Sanitario*`/seeder tests.

- [ ] **Step 4:** Run → FAIL. Implement the import service (upsert keyed `rule_version_id`+`cnae_code`, normaliza dígitos) + seeder (openDraft `RuleDomain::AtividadesEscritorioVirtual` → publish → import CSV → `AuditService::log('escritorio-virtual','importacao-lista-ev',...)`). Run → PASS.

- [ ] **Step 5:** Se o seeder entrar no `DatabaseSeeder`, atualizar `tests/Feature/Seeders/DatabaseSeederTest.php` (contagens que mudarem). Run seeders → PASS.

- [ ] **Step 6: Commit.**

```bash
git commit -m "feat(ev): import versionado da Lista EV (snapshot do endpoint SEDUR) + seed"
```

---

### Task 3: `VirtualOfficeInscriptionLock::sedeAtiva()` (resolver da sede)

**Files:**
- Modify: `app/Models/VirtualOfficeInscriptionLock.php`
- Test: `tests/Feature/EscritorioVirtual/SedeAtivaResolverTest.php`

- [ ] **Step 1: Test** — cria uma sede (ViabilityRequest deferida + decision com tvl) + lock ativo; assert `VirtualOfficeInscriptionLock::sedeAtiva('123')` retorna o lock com `->sede->decision->tvl_product_number` acessível; e `null` para inscrição sem lock.

```php
    public function test_sede_ativa_retorna_lock_com_sede_e_decisao(): void
    {
        $sede = ViabilityRequest::factory()->create(['property_registration' => '123']);
        // decision da sede com tvl (crie via factory de ViabilityDecision, outcome deferida, tvl_product_number)
        // + lock ativo
        // ...
        $lock = VirtualOfficeInscriptionLock::sedeAtiva('123');
        $this->assertNotNull($lock);
        $this->assertSame($sede->id, $lock->sede->id);
        $this->assertNull(VirtualOfficeInscriptionLock::sedeAtiva('999'));
    }
```

> Escreva o setup real (ViabilityDecision factory com tvl_product_number). NÃO deixar incompleto.

- [ ] **Step 2:** Run → FAIL. Add:

```php
    public static function sedeAtiva(string $propertyRegistration): ?self
    {
        return static::query()
            ->where('property_registration', $propertyRegistration)
            ->where('active', true)
            ->with('sede.decision')
            ->latest('id')
            ->first();
    }
```

- [ ] **Step 3:** Run → PASS. **Commit.**

```bash
git commit -m "feat(ev): VirtualOfficeInscriptionLock::sedeAtiva (resolve sede + TVL da inscrição)"
```

---

### Task 4: Bloqueio de CNAE do abrigado no passo de atividades

**Files:**
- Modify: `app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php` (`after()` hook + mensagem)
- Modify: `config/sile.php` + `database/seeders/ParameterSeeder.php` (texto parametrizável da mensagem de bloqueio) + count tests
- Test: `tests/Feature/EscritorioVirtual/AbrigadoCnaeBlockTest.php`

- [ ] **Step 1:** READ `UpdateSolicitacaoAtividadesRequest.php` (`rules()` + `after()` closures + `messages()`) e como o route model `solicitacao` é resolvido.

- [ ] **Step 2: Param** `analise.escritorio_virtual.mensagem_bloqueio_abrigado` (config + seeder + bump seeder counts) — texto: "A atividade informada não está na lista de atividades permitidas para escritório virtual nesta inscrição.".

- [ ] **Step 3: Test** `AbrigadoCnaeBlockTest`: (a) inscrição COM sede ativa + CNAE fora da Lista EV → o PUT do passo de atividades falha validação (`assertSessionHasErrors`) e não sincroniza; (b) CNAE dentro da lista → passa; (c) inscrição SEM sede ativa → sem bloqueio (não é abrigado); (d) property_registration null → sem bloqueio. Base o setup no teste existente do passo de atividades (grep `tests/` por `Atividade`), + `VirtualOfficeInscriptionLock` ativo + linhas na Lista EV vigente.

- [ ] **Step 4:** Run → FAIL. In `UpdateSolicitacaoAtividadesRequest::after()`, add a closure: resolve `$solicitacao = $this->route('solicitacao')`; if `$solicitacao->property_registration` não-vazio E `VirtualOfficeInscriptionLock::ativoPara($solicitacao->property_registration)` → cada CNAE submetido (principal + complementares, resolvidos a `Cnae.code`) deve satisfazer `VirtualOfficeActivityCnae::permitido($code)`, senão `$validator->errors()->add('principal_cnae_id', <mensagem do param>)`. Run → PASS.

- [ ] **Step 5:** `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Solicitacao tests/Feature/Seeders` → sem regressão. **Commit.**

```bash
git commit -m "feat(ev): bloqueio de CNAE do abrigado fora da Lista EV no cadastro (RN-EV-05/CA-04)"
```

---

### Task 5: Campos do produto abrigado + gravação nos 2 fluxos de decisão

**Files:**
- Create: `database/migrations/2026_07_16_110100_add_tenant_fields_to_viability_decisions.php`
- Modify: `app/Models/ViabilityDecision.php` (fillable/casts)
- Modify: `app/Services/Expresso/FluxoExpressoService.php` (`emitir`, ~linhas 276-289)
- Modify: `app/Services/Analise/AnaliseTecnicaDecisionService.php` (`registrarDecisao`, ~linhas 146-160)
- Create: `app/Services/EscritorioVirtual/AbrigadoResolver.php` (regra isolada: dado um request, é abrigado? qual TVL da sede?)
- Test: `tests/Feature/EscritorioVirtual/ProdutoAbrigadoTest.php`

- [ ] **Step 1: Migration** — `viability_decisions`: `is_virtual_office_tenant` (bool default false) + `virtual_office_hq_tvl_number` (string nullable), após `is_virtual_office_hq`. Add to `ViabilityDecision` fillable + cast bool.

- [ ] **Step 2: `AbrigadoResolver`** — método `resolve(ViabilityRequest $request): ?array` que, se `property_registration` não-vazio E existe sede ativa (`VirtualOfficeInscriptionLock::sedeAtiva`) E os CNAEs do request ⊆ Lista EV, retorna `['hq_tvl_number' => $lock->sede->decision?->tvl_product_number]`; senão null. (Regra isolada e testável, reusada pelos 2 fluxos.)

- [ ] **Step 3: Test** `ProdutoAbrigadoTest`: sede deferida (com TVL) + lock ativo; um abrigado na mesma inscrição com CNAE da lista é decidido (expresso) → `decision->is_virtual_office_tenant === true` e `decision->virtual_office_hq_tvl_number === <TVL da sede>`. E um processo comum (sem sede na inscrição) → tenant false, hq_tvl null. Espelhe o setup de decisão do expresso (`tests/Feature/Expresso/*`) e da análise.

- [ ] **Step 4:** Run → FAIL. In `FluxoExpressoService::emitir()` and `AnaliseTecnicaDecisionService::registrarDecisao()`, ao criar a `ViabilityDecision`, consultar `AbrigadoResolver` e setar `is_virtual_office_tenant` + `virtual_office_hq_tvl_number` quando abrigado. (Inject `AbrigadoResolver`.) Run → PASS.

- [ ] **Step 5:** `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Expresso tests/Feature/Analise` → sem regressão. **Commit** (hunk-stage se `AnaliseTecnicaDecisionService`/`FluxoExpressoService` tiverem WIP alheio).

```bash
git commit -m "feat(ev): produto do abrigado referencia TVL da sede (is_virtual_office_tenant + hq_tvl)"
```

---

### Task 6: Gate do expresso para o abrigado (sede precisa estar ativa)

**Files:**
- Modify: `app/Services/Expresso/FluxoExpressoService.php` (`decidirSobLock`, após o branch do gatilho sede ~linha 119)
- Test: `tests/Feature/EscritorioVirtual/AbrigadoExpressoGateTest.php`

- [ ] **Step 1:** READ `decidirSobLock` (branch order). RN-EV-05: abrigado pode ser expresso SE a sede já estiver ativa/deferida no local. Hoje um abrigado cai direto em `emitir()`. Decisão de design: se a inscrição tem CNAE da Lista EV mas NÃO tem sede ativa → não é abrigado válido → encaminhar à análise (não deferir expresso às cegas). Se tem sede ativa → segue expresso (o produto ganha a ref no Task 5).

- [ ] **Step 2: Test** `AbrigadoExpressoGateTest`: (a) inscrição SEM sede ativa + CNAE de escritório → NÃO defere expresso, vai a `em_analise`; (b) inscrição COM sede ativa → segue expresso (defere) com a ref do abrigado. Base no setup expresso-elegível de `EncaminhamentoAnaliseTest`.

> Confirme a regra exata com o resultado do Task 4 (o bloqueio de cadastro já impede CNAE fora da lista; este gate trata do caso "CNAE de escritório sem sede na inscrição"). Se a SEDUR quiser permitir expresso sem sede, ajustar — `[OPEN]` a validar.

- [ ] **Step 3:** Run → FAIL. Add the abrigado branch in `decidirSobLock` (parallel to `gatilhoSede->aplica()`), usando `AbrigadoResolver`/`VirtualOfficeInscriptionLock`. Run → PASS.

- [ ] **Step 4:** `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Expresso` → sem regressão. **Commit.**

```bash
git commit -m "feat(ev): gate do expresso do abrigado (exige sede ativa na inscrição)"
```

---

### Task 7: Regressão + verificação

- [ ] `php artisan test tests/Feature/EscritorioVirtual tests/Feature/Analise tests/Feature/Expresso tests/Feature/Solicitacao tests/Feature/Seeders tests/Unit` → verde (postgis skip OK).
- [ ] `npx tsc --noEmit` → 0; `npm run build` → ok (se algum TSX mudou; M2 é majoritariamente backend).

---

## Self-Review

**Cobertura (RN-EV-05):** Lista EV versionada + import (T1,T2); abrigado = sede lock (T3, AbrigadoResolver T5); bloqueio CNAE fora da lista (T4/CA-04); produto com TVL da sede (T5/CA-05 parcial); expresso do abrigado só com sede ativa (T6). RN-EV-07 parametrização (T2,T4).

**Fora/bloqueado:** validade do abrigado = validade da sede (desfecho spec-2, sem campo `validade`); N abrigados por sede é natural (sem limite); desvinculação (M3).

**Notas de execução:** (a) formato do endpoint SEDUR desconhecido → import de CSV snapshot; fetch live é follow-up. (b) service type "Atividades em Escritórios Virtuais" é só rótulo (seed opcional), NÃO gate. (c) T5/T6 tocam FluxoExpressoService/AnaliseTecnicaDecisionService que têm WIP alheio → hunk-stage. (d) confirmar contrato de `RuleVersionService::openDraft/publish` (sensível vs não) e o setup real dos testes de decisão. (e) `[OPEN]` T6: permitir expresso de "CNAE de escritório sem sede" ou mandar à análise — assumido análise; validar com SEDUR.
