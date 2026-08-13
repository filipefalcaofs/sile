# Ficha de análise — paridade com o legado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fechar a paridade de conteúdo da ficha de análise (`gestao/ficha-analise/show.tsx`) com o legado SAPS: botões, Motivo de Análise, Tramitação (tabela + impressão), bloco Confirmações do imóvel e 2 acréscimos no card de CNAE — tudo com dado real ou pendência explícita, nunca fachada.

**Architecture:** 2 colunas novas em `analysis_records` (autosave, mesmo padrão de `conditions`/`parking`), 2 chaves de contrato `null` em `PreAnaliseService::perCnae()`, 2 props Inertia novas/estendidas no `AnalysisRecordController` (`localizacao.is_public_area`, `tramitacao`), e as seções/campos correspondentes no componente React da ficha.

**Tech Stack:** Laravel 13 + PHPUnit (backend), Inertia v3 + React 19 + Tailwind 4 (frontend), sem libs novas.

## Global Constraints

- Idioma: UI/mensagens/comentários em pt-BR; código (variáveis, classes, métodos) em inglês.
- `vendor/bin/pint --dirty --format agent` após qualquer alteração PHP, antes de comitar.
- TDD: teste falhando primeiro, depois implementação mínima, depois rodar verde.
- Nenhum valor de negócio inventado: `codigo_louos`/`codigo_tll`/resposta de "desenvolvida no local" ficam `null`/"Pendente" — nunca um valor de exemplo do print.
- Commits em português, Conventional Commits (`feat:`, `test:`, `fix:`, `refactor:`), minúsculas, sem ponto final.
- Migration: quando modificar coluna existente, incluir todos os atributos já definidos (não se aplica aqui — são colunas novas).
- Spec de referência: `docs/superpowers/specs/2026-07-24-ficha-analise-paridade-legado-design.md`.

---

## Task 1: Migration + Model — `analysis_reasons` e `address_confirmed`

**Files:**
- Create: `database/migrations/2026_07_24_120000_add_analysis_reasons_and_address_confirmed_to_analysis_records.php`
- Modify: `app/Models/AnalysisRecord.php`
- Test: `tests/Unit/Analise/AnalysisRecordCastsTest.php`

**Interfaces:**
- Produces: colunas `analysis_reasons` (`json`, nullable, cast `array`) e `address_confirmed` (`boolean`, nullable) em `analysis_records`, disponíveis via `AnalysisRecord::$fillable`/`casts()` para as tasks 2 e 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Analise;

use App\Models\AnalysisRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisRecordCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_reasons_e_address_confirmed_sao_persistidos_e_castados(): void
    {
        $ficha = AnalysisRecord::factory()->create([
            'analysis_reasons' => ['Área zoneamento Semi expresso', 'Tipo Espaço - Casa'],
            'address_confirmed' => false,
        ]);

        $ficha->refresh();

        $this->assertSame(['Área zoneamento Semi expresso', 'Tipo Espaço - Casa'], $ficha->analysis_reasons);
        $this->assertFalse($ficha->address_confirmed);
    }

    public function test_analysis_reasons_e_address_confirmed_aceitam_null(): void
    {
        $ficha = AnalysisRecord::factory()->create([
            'analysis_reasons' => null,
            'address_confirmed' => null,
        ]);

        $ficha->refresh();

        $this->assertNull($ficha->analysis_reasons);
        $this->assertNull($ficha->address_confirmed);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Analise/AnalysisRecordCastsTest.php`
Expected: FAIL — `SQLSTATE... column "analysis_reasons" of relation "analysis_records" does not exist` (ou erro de mass assignment, pois a coluna ainda não existe).

- [ ] **Step 3: Write minimal implementation**

Create `database/migrations/2026_07_24_120000_add_analysis_reasons_and_address_confirmed_to_analysis_records.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paridade com o legado SAPS (HU-135, spec 2026-07-24): "Motivo de Análise"
 * (lista de texto livre do analista) e "Endereço correto?" (confirmação do
 * analista, sem HU formal — interpretação de baixo risco registrada na spec).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_records', function (Blueprint $table): void {
            $table->json('analysis_reasons')->nullable()->after('parecer');
            $table->boolean('address_confirmed')->nullable()->after('analysis_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_records', function (Blueprint $table): void {
            $table->dropColumn(['analysis_reasons', 'address_confirmed']);
        });
    }
};
```

Modify `app/Models/AnalysisRecord.php` — adicionar as duas colunas ao `#[Fillable]` e ao `casts()`:

```php
#[Fillable([
    'viability_request_id',
    'revision',
    'status',
    'analyst_user_id',
    'engine_snapshot',
    'engine_rules_versions',
    'engine_available',
    'per_cnae',
    'conditions',
    'parking',
    'parecer',
    'is_virtual_office_hq',
    'analysis_reasons',
    'address_confirmed',
    'finalized_at',
])]
class AnalysisRecord extends Model
{
    /** @use HasFactory<AnalysisRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisRecordStatus::class,
            'engine_snapshot' => 'array',
            'engine_rules_versions' => 'array',
            'engine_available' => 'boolean',
            'per_cnae' => 'array',
            'conditions' => 'array',
            'parking' => 'array',
            'is_virtual_office_hq' => 'boolean',
            'analysis_reasons' => 'array',
            'address_confirmed' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Analise/AnalysisRecordCastsTest.php`
Expected: PASS (2 testes)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_24_120000_add_analysis_reasons_and_address_confirmed_to_analysis_records.php app/Models/AnalysisRecord.php tests/Unit/Analise/AnalysisRecordCastsTest.php
git commit -m "feat: adiciona analysis_reasons e address_confirmed à ficha"
```

---

## Task 2: Resource + Request + Service — expor, validar e persistir os 2 campos novos no autosave

**Files:**
- Modify: `app/Http/Resources/AnalysisRecordResource.php`
- Modify: `app/Http/Requests/Gestao/AnalysisRecordRequest.php`
- Modify: `app/Services/Analise/AnalysisRecordService.php`
- Test: `tests/Feature/Analise/AnalysisRecordAutosaveTest.php`

**Interfaces:**
- Consumes: `AnalysisRecord::$analysis_reasons` (`array|null`), `AnalysisRecord::$address_confirmed` (`bool|null`) da Task 1; `AnalysisRecordService::autosave(AnalysisRecord $record, array $data): AnalysisRecord` (já existe, ponto único de persistência do autosave).
- Produces: payload Inertia `ficha.analysis_reasons` (`string[]`, default `[]`) e `ficha.address_confirmed` (`bool|null`); autosave aceita e PERSISTE `analysis_reasons` (`array` de `string`) e `address_confirmed` (`boolean|null`) — consumido pela Task 5 (frontend).

- [ ] **Step 1: Write the failing test**

Adicionar ao final de `tests/Feature/Analise/AnalysisRecordAutosaveTest.php` (dentro da classe existente, mesmos helpers `analista()`/`fichaRascunho()` já presentes no arquivo):

```php
    public function test_autosave_persiste_motivos_de_analise_e_endereco_correto(): void
    {
        $ficha = $this->fichaRascunho();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", [
                'analysis_reasons' => ['Área zoneamento Semi expresso', 'Áreas - parcelamento'],
                'address_confirmed' => false,
            ])
            ->assertOk();

        $ficha->refresh();

        $this->assertSame(['Área zoneamento Semi expresso', 'Áreas - parcelamento'], $ficha->analysis_reasons);
        $this->assertFalse($ficha->address_confirmed);
    }

    public function test_show_expoe_analysis_reasons_vazio_e_address_confirmed_nulo_por_padrao(): void
    {
        $ficha = $this->fichaRascunho();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$ficha->viability_request_id}/ficha")
            ->assertOk();

        $page = $response->viewData('page');

        $this->assertSame([], $page['props']['ficha']['analysis_reasons']);
        $this->assertNull($page['props']['ficha']['address_confirmed']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_autosave_persiste_motivos_de_analise_e_endereco_correto`
Expected: FAIL — 422 (campo não está nas regras de validação, então o valor não é persistido) ou `analysis_reasons` ausente na resposta/prop.

- [ ] **Step 3: Write minimal implementation**

Modify `app/Http/Resources/AnalysisRecordResource.php` — adicionar ao array retornado, após `is_virtual_office_hq`:

```php
            'is_virtual_office_hq' => $this->is_virtual_office_hq,
            'analysis_reasons' => $this->analysis_reasons ?? [],
            'address_confirmed' => $this->address_confirmed,
            'analyst' => $this->analyst?->name,
```

Modify `app/Http/Requests/Gestao/AnalysisRecordRequest.php` — adicionar às `rules()`:

```php
        return [
            'per_cnae' => ['sometimes', 'array'],
            'per_cnae.*.cnae' => ['required', 'string'],
            'per_cnae.*.status_escolhido' => ['required', Rule::in($statusEscolhido)],
            'per_cnae.*.justificativa' => ['nullable', 'string'],
            'per_cnae.*.condicionantes' => ['sometimes', 'array'],
            'conditions' => ['sometimes', 'array'],
            'parking' => ['sometimes', 'array'],
            'parecer' => ['sometimes', 'nullable', 'string'],
            'is_virtual_office_hq' => ['sometimes', 'nullable', 'boolean'],
            'analysis_reasons' => ['sometimes', 'array'],
            'analysis_reasons.*' => ['string'],
            'address_confirmed' => ['sometimes', 'nullable', 'boolean'],
        ];
```

Modify `app/Services/Analise/AnalysisRecordService.php`, método `autosave()` — adicionar a persistência dos 2 campos (sem isso, a validação passa mas o valor nunca chega ao banco):

```php
        if (array_key_exists('is_virtual_office_hq', $data)) {
            $record->is_virtual_office_hq = $data['is_virtual_office_hq'];
        }

        if (array_key_exists('analysis_reasons', $data) && is_array($data['analysis_reasons'])) {
            $record->analysis_reasons = array_values($data['analysis_reasons']);
        }

        if (array_key_exists('address_confirmed', $data)) {
            $record->address_confirmed = $data['address_confirmed'];
        }

        $record->save();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Analise/AnalysisRecordAutosaveTest.php`
Expected: PASS (todos os testes do arquivo, incluindo os 2 novos)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Resources/AnalysisRecordResource.php app/Http/Requests/Gestao/AnalysisRecordRequest.php app/Services/Analise/AnalysisRecordService.php tests/Feature/Analise/AnalysisRecordAutosaveTest.php
git commit -m "feat: expõe, valida e persiste motivos de análise e endereço confirmado no autosave"
```

---

## Task 3: `PreAnaliseService` — contrato explícito `codigo_louos`/`codigo_tll`

**Files:**
- Modify: `app/Services/Analise/PreAnaliseService.php`
- Test: `tests/Feature/Analise/PreAnaliseServiceTest.php` (arquivo já existe — adicionar o método de teste a ele, reusando os helpers privados já presentes na classe: `emAnaliseComCnaes()`, `fakeBairroComZona()`, `classificarMunicipal()`, `seedQuadro7()`, `seedQuadro10()`, `service()`)

**Interfaces:**
- Produces: cada item de `AnalysisRecord::per_cnae` (revisão 1) passa a ter as chaves `codigo_louos` e `codigo_tll`, sempre `null` até a tabela oficial da SEDUR ser entregue — consumido pela Task 6 (frontend, estado de pendência).

- [ ] **Step 1: Write the failing test**

Adicionar o método abaixo à classe `PreAnaliseServiceTest` já existente em `tests/Feature/Analise/PreAnaliseServiceTest.php` (reaproveitando exatamente o fixture do teste `test_cria_revisao_1_pre_preenchida_pelo_motor_real` já presente no arquivo, que resolve com sucesso e popula `per_cnae`):

```php
    public function test_per_cnae_inclui_codigo_louos_e_codigo_tll_como_pendencia_explicita(): void
    {
        // Spec 2026-07-24: contrato explícito, nunca um valor inventado — o
        // código LOUOS/TLL estruturado depende de tabela oficial que a SEDUR
        // ainda não entregou (docs/ANALISE-HUs-REUNIAO-SEDUR.md:241).
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['8888881']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertNotEmpty($record->per_cnae);
        $this->assertArrayHasKey('codigo_louos', $record->per_cnae[0]);
        $this->assertArrayHasKey('codigo_tll', $record->per_cnae[0]);
        $this->assertNull($record->per_cnae[0]['codigo_louos']);
        $this->assertNull($record->per_cnae[0]['codigo_tll']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_per_cnae_inclui_codigo_louos_e_codigo_tll_como_pendencia_explicita`
Expected: FAIL — `Failed asserting that an array has the key 'codigo_louos'` (a chave ainda não existe no item).

- [ ] **Step 3: Write minimal implementation**

Modify `app/Services/Analise/PreAnaliseService.php`, método `perCnae()`:

```php
    private function perCnae(ResolvedViability $resolved): array
    {
        return array_map(fn (array $item): array => [
            'cnae' => $item['cnae'],
            'cnae_formatado' => $item['cnae_formatado'],
            'is_primary' => $item['is_primary'],
            'tendencia' => $item['tendencia'],
            'tendencia_label' => $item['tendencia_label'],
            'status_sugerido' => $this->statusSugerido((string) $item['tendencia']),
            'fluxo' => $item['fluxo'],
            'fundamentacao' => $item['consulta']->fundamentacao(),
            // Paridade com o legado (spec 2026-07-24): código LOUOS/TLL
            // estruturado não é entregue pela SEDUR ainda (bloqueio externo
            // real, docs/ANALISE-HUs-REUNIAO-SEDUR.md:241) — contrato explícito
            // null, nunca um valor de exemplo do print.
            'codigo_louos' => null,
            'codigo_tll' => null,
        ], $resolved->por_cnae);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Analise/PreAnaliseServiceTest.php`
Expected: PASS (todos os testes do arquivo, incluindo o novo — os testes de degradação FA-01 continuam com `per_cnae` null, sem quebrar)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/PreAnaliseService.php tests/Feature/Analise/PreAnaliseServiceTest.php
git commit -m "feat: documenta codigo_louos e codigo_tll como pendencia explicita no per_cnae"
```

---

## Task 4: Controller — `is_public_area` na localização + prop `tramitacao`

**Files:**
- Modify: `app/Http/Controllers/Gestao/AnalysisRecordController.php`
- Test: `tests/Feature/Analise/FichaUiSmokeTest.php`

**Interfaces:**
- Consumes: `ViabilityRequest::$is_public_area` (já existe), `ViabilityRequest::analysisStatusTransitions()` (já existe, relação `HasMany` com `AnalysisStatusTransition`, que tem `belongsTo actor` e `to_status`/`from_status` cast `AnalysisStatus`), `ViabilityRequest::sector()` (já existe).
- Produces: prop Inertia `localizacao.is_public_area` (`bool|null`) e prop Inertia `tramitacao` (`list<array{data: string, setor: string|null, usuario: string|null, status: string, reason: string|null}>`) — consumidos pela Task 6/7 (frontend).

- [ ] **Step 1: Write the failing test**

Adicionar ao final de `tests/Feature/Analise/FichaUiSmokeTest.php` (dentro da classe, usando o helper `analista()` já existente no arquivo):

```php
    public function test_ficha_expoe_is_public_area_e_tramitacao(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000200',
            'protocoled_at' => now(),
            'is_public_area' => true,
        ]);

        $analista = $this->analista();

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        $processo->analysisStatusTransitions()->create([
            'from_status' => null,
            'to_status' => 'para_distribuir',
            'reason' => null,
            'actor_user_id' => null,
        ]);
        $processo->analysisStatusTransitions()->create([
            'from_status' => 'para_distribuir',
            'to_status' => 'analisar',
            'reason' => null,
            'actor_user_id' => $analista->id,
        ]);

        $response = $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();

        $page = $response->viewData('page');

        $this->assertTrue($page['props']['localizacao']['is_public_area']);
        $this->assertCount(2, $page['props']['tramitacao']);
        $this->assertSame('Para distribuir', $page['props']['tramitacao'][0]['status']);
        $this->assertNull($page['props']['tramitacao'][0]['usuario']);
        $this->assertSame('Analisar', $page['props']['tramitacao'][1]['status']);
        $this->assertSame($analista->name, $page['props']['tramitacao'][1]['usuario']);
    }

    public function test_ficha_sem_transicoes_operacionais_entrega_tramitacao_vazia(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000201',
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();

        $this->assertSame([], $response->viewData('page')['props']['tramitacao']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_ficha_expoe_is_public_area_e_tramitacao`
Expected: FAIL — `Undefined array key "is_public_area"` (ou `"tramitacao"`), pois nenhuma das duas props existe ainda.

- [ ] **Step 3: Write minimal implementation**

Modify `app/Http/Controllers/Gestao/AnalysisRecordController.php`:

1. No método `localizacaoDoImovel()`, adicionar `is_public_area` ao array retornado:

```php
        return [
            'poligono' => $request->property_polygon_geojson,
            'endereco' => $endereco,
            'cod_log' => null,
            'logradouro' => $logradouro !== '' ? $logradouro : null,
            'numero_metrico' => $numeroMetrico !== '' ? $numeroMetrico : null,
            'bairro' => $bairro !== '' ? $bairro : null,
            'cep' => $cep !== '' ? $cep : null,
            'ponto_referencia' => $pontoReferencia !== '' ? $pontoReferencia : null,
            'zona' => null,
            'via' => null,
            'is_public_area' => $request->is_public_area,
        ];
```

2. Adicionar o novo método privado `tramitacao()`, próximo de `localizacaoDoImovel`/`dadosTvl`:

```php
    /**
     * Tramitação da ficha (paridade com o legado, spec 2026-07-24): uma linha
     * por transição do eixo operacional (AnalysisStatusStateMachine), com o
     * setor ATUAL do processo — o modelo não versiona setor por transição, uma
     * mudança de setor no meio do fluxo não é retroativa nas linhas antigas
     * (limitação documentada na spec, não bug).
     *
     * @return list<array{data: string, setor: string|null, usuario: string|null, status: string, reason: string|null}>
     */
    private function tramitacao(ViabilityRequest $request): array
    {
        $request->loadMissing('sector');

        return $request->analysisStatusTransitions()
            ->with('actor')
            ->orderBy('id')
            ->get()
            ->map(fn ($transicao): array => [
                'data' => $transicao->created_at->toIso8601String(),
                'setor' => $request->sector?->name,
                'usuario' => $transicao->actor?->name,
                'status' => $transicao->to_status->label(),
                'reason' => $transicao->reason,
            ])
            ->all();
    }
```

3. No método `show()`, adicionar a prop `tramitacao` junto às demais:

```php
        return Inertia::render('gestao/ficha-analise/show', [
            'ficha' => (new AnalysisRecordResource($record))->resolve(),
            'processo' => [
                'id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
                'status' => $viabilityRequest->status->value,
                'status_label' => $viabilityRequest->status->label(),
            ],
            'localizacao' => $this->localizacaoDoImovel($viabilityRequest),
            'dadosTvl' => $this->dadosTvl($viabilityRequest, $record),
            'cadastroImobiliario' => $cadastro,
            'tramitacao' => $this->tramitacao($viabilityRequest),
            'escritorioVirtual' => $this->escritorioVirtual($viabilityRequest, $record),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php`
Expected: PASS (todos os testes do arquivo, incluindo os 2 novos)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/AnalysisRecordController.php tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "feat: expõe is_public_area e tramitação na ficha de análise"
```

---

## Task 5: Frontend — tipos, estado e payload do autosave (sem UI ainda)

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`

**Interfaces:**
- Consumes: props Inertia `ficha.analysis_reasons`, `ficha.address_confirmed`, `localizacao.is_public_area`, `tramitacao` (Tasks 2 e 4); `perCnae[i].codigo_louos`/`codigo_tll` (Task 3).
- Produces: estado React `analysisReasons: string[]`, `addressConfirmed: boolean | null`, funções `adicionarMotivoAnalise(texto: string)` e `removerMotivoAnalise(indice: number)` — consumidos pelas Tasks 6, 7 e 8 (UI).

Esta task só ajusta tipos/estado/payload; a UI visual entra nas Tasks 6-8. Não há teste PHPUnit aqui (é TypeScript de página Inertia, já coberto pelos smoke tests de props do backend nas Tasks 2/4); a verificação é a compilação TypeScript.

- [ ] **Step 1: Atualizar interfaces**

Em `Localizacao` (linha ~78), adicionar o campo:

```typescript
interface Localizacao {
    poligono: GeoJsonObject | null;
    endereco: string | null;
    cod_log: string | null;
    logradouro: string | null;
    numero_metrico: string | null;
    bairro: string | null;
    cep: string | null;
    ponto_referencia: string | null;
    zona: string | null;
    via: string | null;
    is_public_area: boolean | null;
}
```

Em `PerCnae` (linha ~23), adicionar:

```typescript
interface PerCnae {
    cnae: string;
    cnae_formatado?: string | null;
    is_primary?: boolean;
    tendencia?: string | null;
    tendencia_label?: string | null;
    status_sugerido?: string | null;
    status_escolhido?: string | null;
    fluxo?: string | null;
    grupo_uso?: string | null;
    valor_tll?: number | string | null;
    codigo_louos?: string | null;
    codigo_tll?: string | null;
    gatilhos?: string[] | null;
    fundamentacao?: string[] | null;
    condicionantes?: string[] | null;
    justificativa?: string | null;
}
```

Em `Ficha` (linha ~46), adicionar:

```typescript
interface Ficha {
    id: number;
    viability_request_id: number;
    revision: number;
    status: string;
    status_label: string;
    editavel: boolean;
    engine_available: boolean;
    per_cnae: PerCnae[];
    conditions: string[];
    parking: Parking;
    parecer: string | null;
    is_virtual_office_hq: boolean | null;
    analysis_reasons: string[];
    address_confirmed: boolean | null;
    analyst: string | null;
    finalized_at: string | null;
    updated_at: string | null;
}
```

Nova interface, próxima de `PrecedenteImovel` (linha ~112):

```typescript
interface TramitacaoItem {
    data: string;
    setor: string | null;
    usuario: string | null;
    status: string;
    reason: string | null;
}
```

Em `FichaAnaliseShowProps` (linha ~191), adicionar `tramitacao`:

```typescript
interface FichaAnaliseShowProps {
    ficha: Ficha;
    processo: Processo;
    localizacao?: Localizacao;
    dadosTvl: DadosTvl;
    cadastroImobiliario: CadastroImobiliario;
    tramitacao: TramitacaoItem[];
    escritorioVirtual: EscritorioVirtual;
    textosPadrao: TextoPadrao[];
    autosaveDebounceMs: number;
    sugestoesIa?: SugestaoIa[];
}
```

- [ ] **Step 2: Atualizar assinatura do componente e estado**

Na desestruturação de props (linha ~506):

```typescript
export default function FichaAnaliseShow({
    ficha,
    processo,
    localizacao,
    dadosTvl,
    cadastroImobiliario,
    tramitacao,
    escritorioVirtual,
    textosPadrao,
    autosaveDebounceMs,
    sugestoesIa,
}: FichaAnaliseShowProps) {
```

Após a linha `const [novaCondicao, setNovaCondicao] = useState('');` (linha ~528), adicionar:

```typescript
    const [analysisReasons, setAnalysisReasons] = useState<string[]>(() => ficha.analysis_reasons ?? []);
    const [addressConfirmed, setAddressConfirmed] = useState<boolean | null>(() => ficha.address_confirmed ?? null);
    const [novoMotivoAnalise, setNovoMotivoAnalise] = useState('');
```

- [ ] **Step 3: Atualizar o tipo e o payload do autosave**

No `useHttp` de autosave (linha ~531), adicionar os 2 campos ao tipo genérico e ao valor inicial:

```typescript
    const autosave = useHttp<{
        per_cnae: Array<{ cnae: string; status_escolhido: string; justificativa: string | null; condicionantes: string[] }>;
        conditions: string[];
        parking: Parking;
        parecer: string | null;
        is_virtual_office_hq: boolean;
        analysis_reasons: string[];
        address_confirmed: boolean | null;
    }>({
        per_cnae: [],
        conditions: [],
        parking: {},
        parecer: null,
        is_virtual_office_hq: false,
        analysis_reasons: [],
        address_confirmed: null,
    });
```

Em `construirPayload` (linha ~580), adicionar os 2 campos ao objeto retornado e às dependências do `useCallback`:

```typescript
    const construirPayload = useCallback(
        () => ({
            per_cnae: perCnae.map((item) => ({
                cnae: item.cnae,
                status_escolhido: (item.status_escolhido ?? item.status_sugerido ?? 'analise') as string,
                justificativa: item.justificativa ?? null,
                condicionantes: item.condicionantes ?? [],
            })),
            conditions,
            parking,
            parecer: parecer.trim() === '' ? null : parecer,
            is_virtual_office_hq: sedeEscritorioVirtual,
            analysis_reasons: analysisReasons,
            address_confirmed: addressConfirmed,
        }),
        [perCnae, conditions, parking, parecer, sedeEscritorioVirtual, analysisReasons, addressConfirmed],
    );
```

No `useEffect` do autosave (linha ~617, array de dependências em `~633`), adicionar `analysisReasons` e `addressConfirmed`:

```typescript
        const timer = window.setTimeout(() => salvarRascunho(), autosaveDebounceMs);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [perCnae, conditions, parecer, parking, sedeEscritorioVirtual, analysisReasons, addressConfirmed]);
```

- [ ] **Step 4: Adicionar as funções de manipulação da lista**

Próximo de `removerCondicao` (linha ~662), adicionar:

```typescript
    function adicionarMotivoAnalise(texto: string) {
        const limpo = texto.trim();

        if (limpo === '') {
            return;
        }

        setAnalysisReasons((atual) => [...atual, limpo]);
    }

    function removerMotivoAnalise(indice: number) {
        setAnalysisReasons((atual) => atual.filter((_, i) => i !== indice));
    }
```

- [ ] **Step 4: Run type check to verify it compiles**

Run: `npm run build`
Expected: build sem erros de TypeScript (os novos campos/estado ainda não são consumidos por nenhuma UI, mas devem tipar corretamente — variáveis não usadas como `tramitacao`, `analysisReasons` etc. ainda serão consumidas nas próximas tasks; se o linter reclamar de "declared but never read" antes da Task 6, isso é esperado e temporário — resolvido na Task 6/7).

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "feat: tipos e estado da ficha para motivos, endereco e tramitacao"
```

---

## Task 6: Frontend — botões renomeados + bloco "Confirmações do imóvel" + acréscimos no CNAE

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`

**Interfaces:**
- Consumes: `localizacao.is_public_area`, `addressConfirmed`/`setAddressConfirmed` (Task 5), `perCnae[i].codigo_louos`/`codigo_tll` (Task 3/5), `editavel` (já existente).

- [ ] **Step 1: Renomear os botões**

Em torno da linha 1476-1480 (`Card` "Ações"):

```typescript
                                            <Button onClick={salvarRascunho} variant="outline" size="sm">
                                                Salvar Ficha
                                            </Button>
                                            <Button onClick={() => setShowFinalizar(true)} size="sm">
                                                Finalizar Ficha
                                            </Button>
```

- [ ] **Step 2: Adicionar o card "Confirmações do imóvel"**

Entre o `Card` "Dados do TVL" (que termina em `</Card>` na linha ~1048) e a abertura do `<div className="grid gap-6 lg:grid-cols-3">` (linha ~1050), inserir:

```tsx
                <Card>
                    <CardHeader
                        title="Confirmações do imóvel"
                        description="Paridade com a ficha do legado (SAPS) — confirmações de nível de processo."
                    />
                    <CardContent>
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <DescItem label="Atividade está estabelecida em área pública?">
                                {localizacao?.is_public_area === null || localizacao?.is_public_area === undefined
                                    ? '—'
                                    : localizacao.is_public_area
                                      ? 'Sim'
                                      : 'Não'}
                            </DescItem>
                            <div>
                                <Label className="mb-1.5">Endereço correto?</Label>
                                <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Endereço correto?">
                                    {[
                                        { valor: true, label: 'Sim' },
                                        { valor: false, label: 'Não' },
                                    ].map((opcao) => {
                                        const ativo = addressConfirmed === opcao.valor;

                                        return (
                                            <button
                                                key={opcao.label}
                                                type="button"
                                                role="radio"
                                                aria-checked={ativo}
                                                disabled={!editavel}
                                                onClick={() => setAddressConfirmed(opcao.valor)}
                                                className={`rounded-lg px-3 py-1.5 text-theme-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ${
                                                    ativo
                                                        ? 'bg-brand-500 text-white'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'
                                                }`}
                                            >
                                                {opcao.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </dl>
                        <p className="mt-3 text-theme-xs text-gray-400 dark:text-gray-500">
                            Área pública é informada pelo requerente na solicitação; "Endereço correto?" é uma confirmação do
                            analista.
                        </p>
                    </CardContent>
                </Card>

```

- [ ] **Step 3: Adicionar Pergunta/Resposta e Código LOUOS/TLL no card de CNAE**

Dentro do `<li>` de cada CNAE, após o bloco `{(item.fundamentacao?.length ?? 0) > 0 && (...)}` (linha ~1167) e antes do `<div className="mt-3 grid gap-3 sm:grid-cols-2">` (linha ~1169), inserir:

```tsx
                                                    <div className="mt-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                                        <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                                            Pergunta: A atividade será desenvolvida no local?
                                                        </p>
                                                        <p className="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">
                                                            Resposta: Pendente — requerente ainda não respondeu esta pergunta no
                                                            formulário de solicitação.
                                                        </p>
                                                    </div>

```

E substituir o bloco `<div className="mt-3 grid gap-3 sm:grid-cols-2">` existente (que hoje só tem "Valor TLL" e "Fluxo") para incluir Código LOUOS e Código TLL:

```tsx
                                                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                        <DescItem label="Código LOUOS">
                                                            {item.codigo_louos ?? (
                                                                <span className="text-gray-400 dark:text-gray-500">
                                                                    Pendente — tabela oficial SEDUR não entregue
                                                                </span>
                                                            )}
                                                        </DescItem>
                                                        <DescItem label="Código TLL">
                                                            {item.codigo_tll ?? (
                                                                <span className="text-gray-400 dark:text-gray-500">
                                                                    Pendente — tabela oficial SEDUR não entregue
                                                                </span>
                                                            )}
                                                        </DescItem>
                                                        <DescItem label="Valor TLL">
                                                            {item.valor_tll != null && item.valor_tll !== '' ? (
                                                                <span>{String(item.valor_tll)}</span>
                                                            ) : (
                                                                <span className="text-gray-400 dark:text-gray-500">
                                                                    Pendente (tabela de taxas/DAM)
                                                                </span>
                                                            )}
                                                        </DescItem>
                                                        <DescItem label="Fluxo">
                                                            {item.fluxo ?? '—'}
                                                        </DescItem>
                                                    </div>
```

- [ ] **Step 4: Run build to verify it compiles**

Run: `npm run build`
Expected: build sem erros.

- [ ] **Step 5: Estender o smoke test do backend para cobrir a UI (verificação indireta via props)**

Como a ficha já expõe `localizacao.is_public_area` e `perCnae[i].codigo_louos`/`codigo_tll` (Tasks 3/4), nenhum teste PHPUnit novo é necessário aqui — a Task 7 adiciona o smoke test de página que cobre a presença visual.

Run: `php artisan test --compact tests/Feature/Analise/`
Expected: PASS (nenhuma regressão nos testes já existentes da pasta).

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "feat: renomeia botoes e adiciona confirmacoes do imovel e codigos louos/tll pendentes"
```

---

## Task 7: Frontend — seção "Motivo de Análise"

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`
- Test: `tests/Feature/Analise/FichaUiSmokeTest.php`

**Interfaces:**
- Consumes: `analysisReasons`, `adicionarMotivoAnalise`, `removerMotivoAnalise`, `novoMotivoAnalise`/`setNovoMotivoAnalise` (Task 5), `editavel`.

- [ ] **Step 1: Write the failing test**

Adicionar a `tests/Feature/Analise/FichaUiSmokeTest.php`:

```php
    public function test_ficha_expoe_analysis_reasons_preenchidos_para_a_secao_motivo_de_analise(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000202',
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'analysis_reasons' => ['Área zoneamento Semi expresso', 'Tipo Espaço - Casa'],
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();

        $this->assertSame(
            ['Área zoneamento Semi expresso', 'Tipo Espaço - Casa'],
            $response->viewData('page')['props']['ficha']['analysis_reasons'],
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_ficha_expoe_analysis_reasons_preenchidos_para_a_secao_motivo_de_analise`
Expected: já deve passar (o backend das Tasks 1-2 já expõe `analysis_reasons`) — isso confirma que a base de dados está pronta antes de acrescentar a UI. Se passar de primeira, siga para o Step 3 sem alterações de backend.

- [ ] **Step 3: Write minimal implementation (UI)**

No JSX, após o `Card` "Parecer técnico" (que termina em `</Card>` na linha ~1451) e antes de `</div>` (fim da coluna principal, linha ~1452), inserir:

```tsx
                        {/* Motivo de Análise (paridade com o legado, spec 2026-07-24) */}
                        <Card>
                            <CardHeader
                                title="Motivo de Análise"
                                description="Anotações do analista sobre o que exigiu análise humana neste processo."
                            />
                            <CardContent>
                                {analysisReasons.length > 0 ? (
                                    <ul className="space-y-2">
                                        {analysisReasons.map((motivo, indice) => (
                                            <li
                                                key={`${motivo}-${indice}`}
                                                className="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                                            >
                                                <span className="text-theme-sm text-gray-700 dark:text-gray-300">{motivo}</span>
                                                {editavel && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removerMotivoAnalise(indice)}
                                                        aria-label={`Remover motivo ${indice + 1}`}
                                                        className="shrink-0 text-error-500 transition hover:text-error-600"
                                                    >
                                                        <TrashIcon className="size-4.5" />
                                                    </button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Nenhum motivo registrado.
                                    </p>
                                )}

                                {editavel && (
                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <div className="flex-1">
                                            <Label htmlFor="novo-motivo-analise">Adicionar motivo</Label>
                                            <Input
                                                id="novo-motivo-analise"
                                                type="text"
                                                value={novoMotivoAnalise}
                                                placeholder="Descreva o motivo…"
                                                onChange={(event) => setNovoMotivoAnalise(event.target.value)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        event.preventDefault();
                                                        adicionarMotivoAnalise(novoMotivoAnalise);
                                                        setNovoMotivoAnalise('');
                                                    }
                                                }}
                                            />
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                adicionarMotivoAnalise(novoMotivoAnalise);
                                                setNovoMotivoAnalise('');
                                            }}
                                        >
                                            Adicionar
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php`
Expected: PASS

Run: `npm run build`
Expected: build sem erros.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "feat: adiciona secao motivo de analise na ficha"
```

---

## Task 8: Frontend — seção "Tramitação" + "Imprimir Extrato"

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`
- Test: `tests/Feature/Analise/FichaUiSmokeTest.php`

**Interfaces:**
- Consumes: `tramitacao: TramitacaoItem[]` (prop, Tasks 4/5), `formatarDataHora` (helper já existente na linha ~245), `ChevronDownIcon` (já importado), `EmptyState` (já importado).

- [ ] **Step 1: Write the failing test**

Adicionar a `tests/Feature/Analise/FichaUiSmokeTest.php` (a prop `tramitacao` já foi coberta na Task 4; este teste garante que ela chega íntegra até a página com múltiplos itens e ordenação):

```php
    public function test_ficha_expoe_tramitacao_ordenada_cronologicamente(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000203',
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        $processo->analysisStatusTransitions()->create([
            'from_status' => null,
            'to_status' => 'para_distribuir',
            'reason' => null,
            'actor_user_id' => null,
        ]);
        $processo->analysisStatusTransitions()->create([
            'from_status' => 'para_distribuir',
            'to_status' => 'encaminhado',
            'reason' => 'Encaminhado ao setor competente.',
            'actor_user_id' => null,
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();

        $tramitacao = $response->viewData('page')['props']['tramitacao'];

        $this->assertCount(2, $tramitacao);
        $this->assertSame('Para distribuir', $tramitacao[0]['status']);
        $this->assertSame('Encaminhado para', $tramitacao[1]['status']);
        $this->assertSame('Encaminhado ao setor competente.', $tramitacao[1]['reason']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_ficha_expoe_tramitacao_ordenada_cronologicamente`
Expected: já deve passar (backend da Task 4 já cobre isso) — confirma a base antes da UI. Prossiga para a UI mesmo assim.

- [ ] **Step 3: Write minimal implementation (UI)**

Adicionar a função de impressão próxima de `formatarDataHora` (linha ~253), antes do componente principal:

```typescript
/**
 * Extrato de tramitação real (não é PDF novo nem endpoint novo — spec
 * 2026-07-24 D7): monta uma janela de impressão do navegador a partir dos
 * mesmos dados já renderizados na tabela.
 */
function imprimirExtratoTramitacao(protocolo: string | null, itens: TramitacaoItem[]) {
    const janela = window.open('', '_blank', 'width=800,height=600');

    if (!janela) {
        return;
    }

    const linhas = itens
        .map(
            (item) => `
        <tr>
            <td>${formatarDataHora(item.data)}</td>
            <td>${item.setor ?? '—'}</td>
            <td>${item.usuario ?? '—'}</td>
            <td>${item.status}</td>
        </tr>`,
        )
        .join('');

    janela.document.write(`
        <html>
            <head>
                <title>Extrato de tramitação — ${protocolo ?? ''}</title>
                <style>
                    body { font-family: sans-serif; padding: 24px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 13px; }
                    th { background: #f3f4f6; }
                </style>
            </head>
            <body>
                <h3>Extrato de tramitação — ${protocolo ?? ''}</h3>
                <table>
                    <thead><tr><th>Data</th><th>Setor</th><th>Usuário</th><th>Status</th></tr></thead>
                    <tbody>${linhas}</tbody>
                </table>
            </body>
        </html>
    `);
    janela.document.close();
    janela.focus();
    janela.print();
}
```

No JSX, após o novo `Card` "Motivo de Análise" (Task 7) e antes de `</div>` (fim da coluna principal), inserir:

```tsx
                        {/* Tramitação (paridade com o legado, spec 2026-07-24) */}
                        <Card>
                            <CardHeader
                                title="Tramitação"
                                description="Histórico do eixo operacional da análise."
                                actions={
                                    <Button
                                        size="xs"
                                        variant="outline"
                                        onClick={() => imprimirExtratoTramitacao(processo.protocol_number, tramitacao)}
                                    >
                                        Imprimir Extrato da Tramitação
                                    </Button>
                                }
                            />
                            <CardContent>
                                {tramitacao.length === 0 ? (
                                    <EmptyState
                                        title="Sem tramitação registrada"
                                        description="O histórico do eixo operacional aparece aqui conforme o processo tramita."
                                    />
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-theme-sm">
                                            <thead>
                                                <tr className="border-b border-gray-200 text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:border-gray-800 dark:text-gray-500">
                                                    <th className="py-2 pr-4">Data</th>
                                                    <th className="py-2 pr-4">Setor</th>
                                                    <th className="py-2 pr-4">Usuário</th>
                                                    <th className="py-2 pr-4">Status</th>
                                                    <th className="py-2" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {tramitacao.map((item, indice) => (
                                                    <TramitacaoLinha key={`${item.data}-${indice}`} item={item} />
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
```

Adicionar o componente `TramitacaoLinha` próximo de `StatusEscolhido` (linha ~501, após o fechamento da função):

```tsx
/** Linha expansível da tabela de Tramitação — revela o `reason`, quando houver. */
function TramitacaoLinha({ item }: { item: TramitacaoItem }) {
    const [aberto, setAberto] = useState(false);

    return (
        <>
            <tr className="border-b border-gray-100 last:border-0 dark:border-gray-800">
                <td className="py-2 pr-4 text-gray-500 dark:text-gray-400">{formatarDataHora(item.data)}</td>
                <td className="py-2 pr-4">{item.setor ?? '—'}</td>
                <td className="py-2 pr-4">{item.usuario ?? '—'}</td>
                <td className="py-2 pr-4 font-medium text-gray-800 dark:text-white/90">{item.status}</td>
                <td className="py-2 text-right">
                    {item.reason && (
                        <button
                            type="button"
                            onClick={() => setAberto((atual) => !atual)}
                            aria-label={aberto ? 'Ocultar motivo' : 'Ver motivo'}
                            aria-expanded={aberto}
                            className="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            <ChevronDownIcon className={`size-4 transition-transform ${aberto ? 'rotate-180' : ''}`} />
                        </button>
                    )}
                </td>
            </tr>
            {aberto && item.reason && (
                <tr>
                    <td colSpan={5} className="pb-3 text-theme-xs text-gray-500 dark:text-gray-400">
                        {item.reason}
                    </td>
                </tr>
            )}
        </>
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php`
Expected: PASS

Run: `npm run build`
Expected: build sem erros de TypeScript.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "feat: adiciona secao tramitacao com impressao de extrato na ficha"
```

---

## Task 9: Verificação final — suíte completa + `STATE.md`/`ROADMAP.md`

**Files:**
- Modify: `.planning/STATE.md` (registrar as 2 pendências externas — ver seção "Fora deste ciclo" da spec)
- Modify: `docs/superpowers/specs/2026-07-24-ficha-analise-paridade-legado-design.md` (atualizar `Status:` para "Implementado")

**Interfaces:**
- Nenhuma nova — task de fechamento.

- [ ] **Step 1: Rodar a suíte completa**

Run: `php artisan test --compact`
Expected: PASS em todos os testes (nenhuma regressão nas Fases 1-15 já existentes).

- [ ] **Step 2: Rodar o build de produção do frontend**

Run: `npm run build`
Expected: build sem erros.

- [ ] **Step 3: Rodar o Pint em todo o diff da feature**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sem alterações pendentes (ou já corrigidas automaticamente).

- [ ] **Step 4: Registrar as pendências externas em `.planning/STATE.md`**

Adicionar (na seção de pendências/bloqueios do arquivo, seguindo o formato já usado nas entradas existentes — abrir o arquivo primeiro e replicar o padrão de cabeçalho e bullets):

```markdown
- **Ficha de análise — pergunta "desenvolvida no local?" (HU-064/065):** sem captura no wizard do cidadão hoje; a ficha exibe "Pendente" honesto (spec 2026-07-24). Bloqueio: nova pergunta no formulário de solicitação, fora do escopo da ficha.
- **Ficha de análise — código LOUOS + código/valor TLL (HU-015/HU-038):** tabela oficial não entregue pela SEDUR (docs/ANALISE-HUs-REUNIAO-SEDUR.md:241). A ficha exibe "Pendente — tabela oficial SEDUR não entregue" (spec 2026-07-24). Bloqueio externo real — aguarda entrega da SEDUR.
```

- [ ] **Step 5: Atualizar o status da spec**

Em `docs/superpowers/specs/2026-07-24-ficha-analise-paridade-legado-design.md`, trocar:

```markdown
**Status:** Aprovado — aguardando plano de implementação
```

por:

```markdown
**Status:** Implementado
```

- [ ] **Step 6: Commit**

```bash
git add .planning/STATE.md docs/superpowers/specs/2026-07-24-ficha-analise-paridade-legado-design.md
git commit -m "docs: registra pendencias externas e marca spec da ficha como implementada"
```
