# CNAE — tela única (CRUD + risco municipal + condicionantes) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Substituir as três telas hoje separadas (`/gestao/cnaes` com modal, `/gestao/risco`, `/gestao/risco/condicionantes`) por uma única ficha de CNAE (`criar`/`editar`) que reúne dados do CNAE, classificação de risco municipal e perguntas de condicionante sanitária.

**Architecture:** `CnaeController` ganha `create`/`edit` (páginas cheias) e passa a fazer upsert direto (sem quatro olhos) em `RiskClassification` ao salvar; três novas sub-rotas do próprio `CnaeController` cobrem o CRUD de `RiskCondicionante` escopado a um CNAE. `RiscoController`, `RiscoMaintenanceService` e `PublishRiscoVersionRequest` são removidos — o import oficial em lote (`RiscoMunicipalImportService`) não muda.

**Tech Stack:** Laravel 11 (Pest para testes), Inertia.js + React 19 + TypeScript, Tailwind.

**Spec:** [docs/superpowers/specs/2026-08-13-cnae-tela-unica-design.md](../specs/2026-08-13-cnae-tela-unica-design.md)

## Global Constraints

- Guard das rotas de gestão é `gestao` (`auth:gestao`); permissões via `permission:` middleware (Spatie). Toda a ficha de CNAE (dados + risco + perguntas) passa a usar só `consultar-cnaes`/`manter-cnaes` — `consultar-risco`/`manter-risco` são removidas.
- `code` do CNAE é sempre normalizado para 7 dígitos no banco; exibido formatado (`DDDD-D/SS`) via `Cnae::formatted_code`. Nunca editável após criação.
- Toda mutação em `Cnae` já é auditada automaticamente via `HasAuditoria` — não adicionar auditoria manual.
- Testes usam `LazilyRefreshDatabase` e `RolesAndPermissionsSeeder`; rodar com `php artisan test --filter=<Classe>`.
- Frontend: `npm run typecheck` deve passar sem erros antes de cada commit que toque `.tsx`.

---

## File Structure

**Backend — criados/modificados:**
- `database/migrations/2026_08_13_120000_add_regras_risco_to_cnaes_table.php` — novo.
- `app/Models/Cnae.php` — modificado (novos campos fillable/cast).
- `database/factories/CnaeFactory.php` — modificado (defaults dos novos campos).
- `app/Http/Requests/Gestao/StoreCnaeRequest.php` — modificado (valida risco + flags).
- `app/Http/Requests/Gestao/UpdateCnaeRequest.php` — modificado (idem).
- `app/Http/Controllers/Gestao/CnaeController.php` — modificado (create/edit/store/update + CRUD de condicionante aninhado).
- `routes/gestao.php` — modificado (novas rotas de CNAE, remove rotas de `/gestao/risco`).
- `database/seeders/RolesAndPermissionsSeeder.php` — modificado (remove `consultar-risco`/`manter-risco`).
- `database/seeders/DemonstracaoClienteSeeder.php` — modificado (idem).

**Backend — removidos:**
- `app/Http/Controllers/Gestao/RiscoController.php`
- `app/Http/Controllers/Gestao/RiscoCondicionanteController.php`
- `app/Services/Risco/RiscoMaintenanceService.php`
- `app/Http/Requests/Gestao/PublishRiscoVersionRequest.php`
- `tests/Feature/Risco/RiscoConsultaTest.php`
- `tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php`

**Frontend — criados:**
- `resources/js/components/cnae/risco-municipal-fields.tsx` — seção de risco compartilhada entre criar/editar.
- `resources/js/pages/gestao/cnaes/criar.tsx`
- `resources/js/pages/gestao/cnaes/editar.tsx`

**Frontend — modificados:**
- `resources/js/pages/gestao/cnaes/index.tsx` — remove modais, navega para páginas cheias.
- `resources/js/layouts/gestao-layout.tsx` — remove itens de menu "Classificação de risco" e "Condicionantes".

**Frontend — removidos:**
- `resources/js/pages/gestao/risco/index.tsx`
- `resources/js/pages/gestao/risco/condicionantes.tsx`

**Mantidos sem alteração:** `app/Http/Requests/Gestao/StoreRiscoCondicionanteRequest.php` e `UpdateRiscoCondicionanteRequest.php` (reaproveitados pelo `CnaeController`), `RuleVersion`, `RuleVersionService`, `RiscoMunicipalImportService`, `RiscoSanitarioImportService` e todos os testes de `tests/Unit/Risco` e os demais de `tests/Feature/Risco` não listados acima.

---

### Task 1: Migration + model + factory dos novos campos em `cnaes`

**Files:**
- Create: `database/migrations/2026_08_13_120000_add_regras_risco_to_cnaes_table.php`
- Modify: `app/Models/Cnae.php`
- Modify: `database/factories/CnaeFactory.php`
- Test: `tests/Feature/Cnae/CnaeCrudTest.php` (só o teste do Passo 1 — o arquivo inteiro é reescrito na Task 11)

**Interfaces:**
- Produces: colunas booleanas `exige_rt`, `exige_rt_se_alto`, `exige_fator_multiplicador`, `exige_detalhamento_multiplicador` em `cnaes`, todas `default(false)`, expostas no `Cnae` model (cast `boolean`, fillable).

- [ ] **Step 1: Escrever teste que falha (coluna ainda não existe)**

Criar um teste isolado temporário para guiar a migration — adicione ao final de `tests/Feature/Cnae/CnaeCrudTest.php` (será substituído por completo na Task 11, então não precisa caprichar no nome):

```php
    public function test_cnae_tem_colunas_de_regra_de_risco(): void
    {
        $cnae = Cnae::factory()->create([
            'exige_rt' => true,
            'exige_rt_se_alto' => false,
            'exige_fator_multiplicador' => true,
            'exige_detalhamento_multiplicador' => false,
        ]);

        $this->assertDatabaseHas('cnaes', [
            'id' => $cnae->id,
            'exige_rt' => true,
            'exige_fator_multiplicador' => true,
        ]);
    }
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=test_cnae_tem_colunas_de_regra_de_risco`
Expected: FAIL — `SQLSTATE[HY000]: ... no such column: exige_rt` (ou erro equivalente do driver de teste).

- [ ] **Step 3: Criar a migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags de regra por CNAE (HU-047 RN-010, tela única): mesmas colunas do
     * projeto de referência (sls-sms) — exigência de responsável técnico
     * (sempre ou só em alto risco) e fator multiplicador (com ou sem
     * detalhamento). Ficam direto em `cnaes` porque não são dado versionado
     * como a classificação de risco (RiskClassification): mudam junto com o
     * cadastro do CNAE, sem quatro olhos.
     */
    public function up(): void
    {
        Schema::table('cnaes', function (Blueprint $table) {
            $table->boolean('exige_rt')->default(false)->after('active');
            $table->boolean('exige_rt_se_alto')->default(false)->after('exige_rt');
            $table->boolean('exige_fator_multiplicador')->default(false)->after('exige_rt_se_alto');
            $table->boolean('exige_detalhamento_multiplicador')->default(false)->after('exige_fator_multiplicador');
        });
    }

    public function down(): void
    {
        Schema::table('cnaes', function (Blueprint $table) {
            $table->dropColumn([
                'exige_rt',
                'exige_rt_se_alto',
                'exige_fator_multiplicador',
                'exige_detalhamento_multiplicador',
            ]);
        });
    }
};
```

- [ ] **Step 4: Atualizar o model `Cnae`**

Em `app/Models/Cnae.php`, troque o atributo `#[Fillable]` e o método `casts()`:

```php
#[Fillable(['code', 'description', 'section_code', 'section_description', 'division_code', 'division_description', 'group_code', 'group_description', 'class_code', 'class_description', 'active', 'exige_rt', 'exige_rt_se_alto', 'exige_fator_multiplicador', 'exige_detalhamento_multiplicador'])]
class Cnae extends Model
{
    use HasAuditoria;

    /** @use HasFactory<CnaeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'exige_rt' => 'boolean',
            'exige_rt_se_alto' => 'boolean',
            'exige_fator_multiplicador' => 'boolean',
            'exige_detalhamento_multiplicador' => 'boolean',
        ];
    }
```

- [ ] **Step 5: Atualizar a factory**

Em `database/factories/CnaeFactory.php`, adicione ao array retornado por `definition()`, logo após `'active' => true,`:

```php
            'active' => true,
            'exige_rt' => false,
            'exige_rt_se_alto' => false,
            'exige_fator_multiplicador' => false,
            'exige_detalhamento_multiplicador' => false,
```

- [ ] **Step 6: Rodar migrations de teste e o teste do Step 1**

Run: `php artisan test --filter=test_cnae_tem_colunas_de_regra_de_risco`
Expected: PASS

- [ ] **Step 7: Remover o teste temporário do Step 1**

Apague o método `test_cnae_tem_colunas_de_regra_de_risco` de `CnaeCrudTest.php` — ele cumpriu o papel de guiar a migration; a cobertura definitiva vem na Task 11.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_13_120000_add_regras_risco_to_cnaes_table.php app/Models/Cnae.php database/factories/CnaeFactory.php
git commit -m "feat(cnae): adiciona colunas de RT e fator multiplicador em cnaes"
```

---

### Task 2: Validação de `risco_municipal` e das novas flags nos FormRequests de CNAE

**Files:**
- Modify: `app/Http/Requests/Gestao/StoreCnaeRequest.php`
- Modify: `app/Http/Requests/Gestao/UpdateCnaeRequest.php`

**Interfaces:**
- Consumes: `App\Enums\RiscoMunicipal` (já existe, `RiscoMunicipal::cases()`/`Rule::enum`).
- Produces: `$request->validated()` de ambos os requests passa a incluir `risco_municipal` (string do enum) e as 4 flags booleanas — consumido pela Task 3 (`CnaeController`).

- [ ] **Step 1: Reescrever `StoreCnaeRequest`**

```php
<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RiscoMunicipal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCnaeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-cnaes da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aceita o código no formato oficial DDDD-D/SS ou já em dígitos:
     * a normalização acontece antes da validação.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => preg_replace('/\D/', '', (string) $this->input('code'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'regex:/^\d{7}$/', 'unique:cnaes,code'],
            'description' => ['required', 'string', 'max:255'],
            'section_code' => ['required', 'string', 'max:1'],
            'section_description' => ['required', 'string', 'max:255'],
            'division_code' => ['required', 'string', 'max:2'],
            'division_description' => ['required', 'string', 'max:255'],
            'group_code' => ['required', 'string', 'max:5'],
            'group_description' => ['required', 'string', 'max:255'],
            'class_code' => ['required', 'string', 'max:7'],
            'class_description' => ['required', 'string', 'max:255'],
            'risco_municipal' => ['required', Rule::enum(RiscoMunicipal::class)],
            'exige_rt' => ['required', 'boolean'],
            'exige_rt_se_alto' => ['required', 'boolean'],
            'exige_fator_multiplicador' => ['required', 'boolean'],
            'exige_detalhamento_multiplicador' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'description' => 'denominação',
            'section_code' => 'código da seção',
            'section_description' => 'descrição da seção',
            'division_code' => 'código da divisão',
            'division_description' => 'descrição da divisão',
            'group_code' => 'código do grupo',
            'group_description' => 'descrição do grupo',
            'class_code' => 'código da classe',
            'class_description' => 'descrição da classe',
            'risco_municipal' => 'grau de risco',
            'exige_rt' => 'exige responsável técnico',
            'exige_rt_se_alto' => 'exige RT apenas se alto risco',
            'exige_fator_multiplicador' => 'possui fator multiplicador',
            'exige_detalhamento_multiplicador' => 'exige detalhamento do multiplicador',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'O código deve ter 7 dígitos no padrão DDDD-D/SS.',
        ];
    }
}
```

- [ ] **Step 2: Reescrever `UpdateCnaeRequest`**

```php
<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RiscoMunicipal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCnaeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-cnaes da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Código e hierarquia vêm da fonte oficial (import) ou do cadastro manual
     * completo e são imutáveis na edição (padrão CPF da Fase 1: valor enviado
     * é ignorado). Denominação, situação, grau de risco e as flags de
     * RT/fator multiplicador são editáveis na ficha única do CNAE.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'risco_municipal' => ['required', Rule::enum(RiscoMunicipal::class)],
            'exige_rt' => ['required', 'boolean'],
            'exige_rt_se_alto' => ['required', 'boolean'],
            'exige_fator_multiplicador' => ['required', 'boolean'],
            'exige_detalhamento_multiplicador' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'description' => 'denominação',
            'active' => 'situação',
            'risco_municipal' => 'grau de risco',
            'exige_rt' => 'exige responsável técnico',
            'exige_rt_se_alto' => 'exige RT apenas se alto risco',
            'exige_fator_multiplicador' => 'possui fator multiplicador',
            'exige_detalhamento_multiplicador' => 'exige detalhamento do multiplicador',
        ];
    }
}
```

- [ ] **Step 3: Commit**

Sem teste próprio neste passo — a Task 3 (controller) e a Task 11 (reescrita de `CnaeCrudTest`) exercitam esta validação de ponta a ponta.

```bash
git add app/Http/Requests/Gestao/StoreCnaeRequest.php app/Http/Requests/Gestao/UpdateCnaeRequest.php
git commit -m "feat(cnae): valida grau de risco e flags de RT/multiplicador no request"
```

---

### Task 3: `CnaeController` — páginas cheias + upsert direto de `RiskClassification`

**Files:**
- Modify: `app/Http/Controllers/Gestao/CnaeController.php`
- Test: `tests/Feature/Cnae/CnaeCrudTest.php` (passos temporários — reescrito por completo na Task 11)

**Interfaces:**
- Consumes: `StoreCnaeRequest`/`UpdateCnaeRequest` (Task 2), `RuleVersion::vigente()` (`app/Models/RuleVersion.php`, já existe), `RiskClassification` (já existe).
- Produces: `CnaeController::create()`/`edit(Cnae $cnae)` renderizando `gestao/cnaes/criar` / `gestao/cnaes/editar` (consumido pela Task 7/8); `store()`/`update()` fazendo upsert de `RiskClassification` sem quatro olhos.

- [ ] **Step 1: Escrever teste que falha (upsert de risco municipal ao criar)**

Adicione ao final de `tests/Feature/Cnae/CnaeCrudTest.php` (arquivo será reescrito na Task 11, mas o teste precisa passar agora):

```php
    public function test_criar_cnae_grava_classificacao_de_risco_municipal_direto(): void
    {
        RuleVersion::factory()->create();

        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', [
                ...$this->validPayload(),
                'risco_municipal' => 'alto',
                'exige_rt' => true,
                'exige_rt_se_alto' => false,
                'exige_fator_multiplicador' => false,
                'exige_detalhamento_multiplicador' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cnae = Cnae::where('code', '9900800')->firstOrFail();

        $this->assertTrue($cnae->exige_rt);
        $this->assertDatabaseHas('risk_classifications', [
            'cnae_code' => '9900800',
            'risco_municipal' => 'alto',
        ]);
    }
```

Adicione os `use` necessários no topo do arquivo (serão consolidados na Task 11): `use App\Models\RuleVersion;`.

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=test_criar_cnae_grava_classificacao_de_risco_municipal_direto`
Expected: FAIL — `assertSessionHasNoErrors` falha porque `StoreCnaeRequest` já exige `risco_municipal`/flags (Task 2) mas o controller ainda não sabe lidar com o campo extra (`Cnae::create($validated)` vai estourar `QueryException` por coluna `risco_municipal` inexistente em `cnaes`, já que o validated ainda contém a chave).

- [ ] **Step 3: Reescrever `CnaeController`**

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RiscoMunicipal;
use App\Enums\RiscoSanitario;
use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreCnaeRequest;
use App\Http\Requests\Gestao\StoreRiscoCondicionanteRequest;
use App\Http\Requests\Gestao\UpdateCnaeRequest;
use App\Http\Requests\Gestao\UpdateRiscoCondicionanteRequest;
use App\Models\Cnae;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\CnaesReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Ficha única do CNAE (06-08bis): cadastro do CNAE, classificação de risco
 * municipal (Decreto 32.636/2020) e perguntas de condicionante sanitária
 * (VISA) numa só tela — substitui /gestao/risco e /gestao/risco/condicionantes.
 * O grau de risco municipal é salvo direto (updateOrCreate na linha vigente,
 * sem quatro olhos): a publicação em lote com quatro olhos continua existindo
 * só no import oficial da planilha (RiscoMunicipalImportService), não nesta tela.
 */
class CnaeController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos via request —
     * whitelists técnicas de proteção; o padrão de página é parâmetro.
     */
    private const SORTABLE_COLUMNS = ['code', 'description'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * Listagem com busca por código (prefixo, dígitos) ou denominação
     * (HU-011 CA-01), filtro de situação, ordenação e itens por página
     * server-driven (Fase 2.4). Paginação parametrizada — nenhum valor
     * de negócio hardcoded.
     */
    public function index(Request $request): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta o conjunto filtrado da tela pelo
        // contrato único — sem rota nova, sem reimplementar export.
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(CnaesReportSource::class),
                ReportFilters::fromArray($request->only(['search', 'active', 'sort', 'direction'])),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'code';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $active = $request->string('active')->toString();

        $cnaes = Cnae::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('code', 'like', "{$digits}%");
                    }

                    // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                    $inner->orWhereLike('description', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Cnae $cnae) => [
                'id' => $cnae->id,
                'code' => $cnae->code,
                'formatted_code' => $cnae->formatted_code,
                'description' => $cnae->description,
                'active' => $cnae->active,
                'class_code' => $cnae->class_code,
            ]);

        return Inertia::render('gestao/cnaes/index', [
            'cnaes' => $cnaes,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'active' => in_array($active, ['0', '1'], true) ? $active : '',
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('gestao/cnaes/criar', [
            'niveisMunicipais' => $this->niveisMunicipais(),
        ]);
    }

    /**
     * Ficha única do CNAE: dados próprios + classificação de risco municipal
     * vigente + perguntas de condicionante sanitária vigentes.
     */
    public function edit(Cnae $cnae): Response
    {
        $municipal = $this->versaoMunicipalVigente();
        $sanitaria = $this->versaoSanitariaVigente();

        $classificacao = $municipal === null ? null : RiskClassification::query()
            ->where('rule_version_id', $municipal->id)
            ->where('cnae_code', $cnae->code)
            ->first();

        $condicionantes = $sanitaria === null ? collect() : RiskCondicionante::query()
            ->where('rule_version_id', $sanitaria->id)
            ->where('cnae_code', $cnae->code)
            ->orderBy('id')
            ->get();

        return Inertia::render('gestao/cnaes/editar', [
            'cnae' => [
                'id' => $cnae->id,
                'code' => $cnae->code,
                'formatted_code' => $cnae->formatted_code,
                'description' => $cnae->description,
                'active' => $cnae->active,
                'exige_rt' => $cnae->exige_rt,
                'exige_rt_se_alto' => $cnae->exige_rt_se_alto,
                'exige_fator_multiplicador' => $cnae->exige_fator_multiplicador,
                'exige_detalhamento_multiplicador' => $cnae->exige_detalhamento_multiplicador,
                'risco_municipal' => $classificacao?->risco_municipal->value,
            ],
            'condicionantes' => $condicionantes->map(fn (RiskCondicionante $condicionante) => [
                'id' => $condicionante->id,
                'pergunta' => $condicionante->pergunta,
                'regra_reclassificacao' => $condicionante->regra_reclassificacao,
                'texto_parecer' => $condicionante->texto_parecer,
            ])->values(),
            'niveisMunicipais' => $this->niveisMunicipais(),
            'niveisReclassificacao' => $this->niveisReclassificacao(),
            'semVersaoMunicipal' => $municipal === null,
            'semVersaoSanitaria' => $sanitaria === null,
        ]);
    }

    public function store(StoreCnaeRequest $request): RedirectResponse
    {
        $municipal = $this->versaoMunicipalVigente();

        if ($municipal === null) {
            return back()->withInput()->with(
                'error',
                'Não há versão vigente de risco municipal para registrar a classificação.',
            );
        }

        $validated = $request->validated();
        $riscoMunicipal = $validated['risco_municipal'];
        unset($validated['risco_municipal']);

        $cnae = Cnae::create($validated);

        RiskClassification::updateOrCreate(
            ['rule_version_id' => $municipal->id, 'cnae_code' => $cnae->code],
            ['risco_municipal' => $riscoMunicipal],
        );

        return redirect()->route('gestao.cnaes.index')->with('status', 'CNAE cadastrado com sucesso.');
    }

    public function update(UpdateCnaeRequest $request, Cnae $cnae): RedirectResponse
    {
        $municipal = $this->versaoMunicipalVigente();

        if ($municipal === null) {
            return back()->with(
                'error',
                'Não há versão vigente de risco municipal para registrar a classificação.',
            );
        }

        $validated = $request->validated();
        $riscoMunicipal = $validated['risco_municipal'];
        unset($validated['risco_municipal']);

        $cnae->update($validated);

        RiskClassification::updateOrCreate(
            ['rule_version_id' => $municipal->id, 'cnae_code' => $cnae->code],
            ['risco_municipal' => $riscoMunicipal],
        );

        return back()->with('status', 'CNAE atualizado com sucesso.');
    }

    /**
     * Exclusão física bloqueada quando o CNAE está vinculado a empresas
     * (Fase 3): verificação amigável na aplicação + restrictOnDelete como
     * defesa no banco (company_cnae.cnae_id).
     */
    public function destroy(Cnae $cnae): RedirectResponse
    {
        if ($cnae->companies()->exists()) {
            return back()->with('error', 'CNAE vinculado a empresas não pode ser excluído.');
        }

        $cnae->delete();

        return back()->with('status', 'CNAE excluído.');
    }

    /**
     * Cadastro de pergunta de condicionante sanitária diretamente na ficha do
     * CNAE — reaproveita o request do antigo RiscoCondicionanteController,
     * ignorando qualquer cnae_code enviado: o vínculo vem sempre da rota.
     */
    public function storeCondicionante(StoreRiscoCondicionanteRequest $request, Cnae $cnae): RedirectResponse
    {
        $versao = $this->versaoSanitariaVigente();

        if ($versao === null) {
            return back()->with('error', 'Não há versão vigente de risco sanitário para vincular a condicionante.');
        }

        RiskCondicionante::create([
            'rule_version_id' => $versao->id,
            'cnae_code' => $cnae->code,
            ...$request->safe()->except('cnae_code'),
        ]);

        return back()->with('status', 'Pergunta de classificação de risco cadastrada com sucesso.');
    }

    public function updateCondicionante(
        UpdateRiscoCondicionanteRequest $request,
        Cnae $cnae,
        RiskCondicionante $condicionante,
    ): RedirectResponse {
        if ($condicionante->cnae_code !== $cnae->code) {
            abort(404);
        }

        $condicionante->update($request->safe()->except('cnae_code'));

        return back()->with('status', 'Pergunta atualizada com sucesso.');
    }

    public function destroyCondicionante(Cnae $cnae, RiskCondicionante $condicionante): RedirectResponse
    {
        if ($condicionante->cnae_code !== $cnae->code) {
            abort(404);
        }

        $condicionante->delete();

        return back()->with('status', 'Pergunta removida.');
    }

    private function versaoMunicipalVigente(): ?RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first();
    }

    private function versaoSanitariaVigente(): ?RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function niveisMunicipais(): array
    {
        return array_map(
            fn (RiscoMunicipal $nivel) => ['value' => $nivel->value, 'label' => $nivel->label()],
            RiscoMunicipal::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function niveisReclassificacao(): array
    {
        return array_map(
            fn (RiscoSanitario $nivel) => ['value' => $nivel->value, 'label' => $nivel->label()],
            RiscoSanitario::cases(),
        );
    }
}
```

- [ ] **Step 4: Rodar o teste do Step 1 e confirmar que passa**

Run: `php artisan test --filter=test_criar_cnae_grava_classificacao_de_risco_municipal_direto`
Expected: PASS

- [ ] **Step 5: Rodar a suíte inteira de CNAE para checar regressão**

Run: `php artisan test --filter=CnaeCrudTest`
Expected: as demais funções de `validPayload()`/testes antigos agora FALHAM (esperado — eles não enviam `risco_municipal`/flags nem seedam `RuleVersion`; serão corrigidos por completo na Task 11). Confirme que a única causa de falha é ausência desses dados, não um erro de sintaxe/500 inesperado.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Gestao/CnaeController.php tests/Feature/Cnae/CnaeCrudTest.php
git commit -m "feat(cnae): ficha única com risco municipal direto e CRUD de condicionantes"
```

---

### Task 4: Rotas — nova ficha de CNAE, remoção de `/gestao/risco`

**Files:**
- Modify: `routes/gestao.php`

**Interfaces:**
- Consumes: `CnaeController@create/@edit/@storeCondicionante/@updateCondicionante/@destroyCondicionante` (Task 3).
- Produces: rotas nomeadas `gestao.cnaes.create`, `gestao.cnaes.edit`, `gestao.cnaes.condicionantes.store/update/destroy` — consumidas pelas Tasks 7-9 (frontend).

- [ ] **Step 1: Remover os `use` dos controllers de risco removidos**

Em `routes/gestao.php`, remova as duas linhas (a Task 6 apaga os arquivos correspondentes):

```php
use App\Http\Controllers\Gestao\RiscoCondicionanteController;
use App\Http\Controllers\Gestao\RiscoController;
```

- [ ] **Step 2: Substituir o bloco de rotas de CNAE e remover o bloco de `/gestao/risco`**

Troque:

```php
        // Consulta granular separada da manutenção (HU-011 CA-04)
        Route::middleware('permission:consultar-cnaes')->group(function () {
            Route::get('cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
        });

        Route::middleware('permission:manter-cnaes')->group(function () {
            Route::post('cnaes', [CnaeController::class, 'store'])->name('cnaes.store');
            Route::put('cnaes/{cnae}', [CnaeController::class, 'update'])->name('cnaes.update');
            Route::delete('cnaes/{cnae}', [CnaeController::class, 'destroy'])->name('cnaes.destroy');
        });
```

por:

```php
        // Ficha única do CNAE: dados + classificação de risco municipal +
        // perguntas de condicionante sanitária, tudo na mesma tela. Consulta
        // granular separada da manutenção (HU-011 CA-04).
        Route::middleware('permission:consultar-cnaes')->group(function () {
            Route::get('cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
            Route::get('cnaes/{cnae}/editar', [CnaeController::class, 'edit'])->name('cnaes.edit');
        });

        Route::middleware('permission:manter-cnaes')->group(function () {
            Route::get('cnaes/criar', [CnaeController::class, 'create'])->name('cnaes.create');
            Route::post('cnaes', [CnaeController::class, 'store'])->name('cnaes.store');
            Route::put('cnaes/{cnae}', [CnaeController::class, 'update'])->name('cnaes.update');
            Route::delete('cnaes/{cnae}', [CnaeController::class, 'destroy'])->name('cnaes.destroy');
            Route::post('cnaes/{cnae}/condicionantes', [CnaeController::class, 'storeCondicionante'])->name('cnaes.condicionantes.store');
            Route::put('cnaes/{cnae}/condicionantes/{condicionante}', [CnaeController::class, 'updateCondicionante'])->name('cnaes.condicionantes.update');
            Route::delete('cnaes/{cnae}/condicionantes/{condicionante}', [CnaeController::class, 'destroyCondicionante'])->name('cnaes.condicionantes.destroy');
        });
```

E remova por completo o bloco (comentário incluído):

```php
        // Classificação de risco (HU-019/HU-020/HU-052/HU-053): a consulta da
        // tabela vigente (analista/gestor/admin) é separada da manutenção
        // versionada e do CRUD de condicionantes (admin). Gate cross-guard via
        // permission: (PADRÃO 04-03). Atualizar publica NOVA versão (4-olhos),
        // nunca edição destrutiva da vigente.
        Route::middleware('permission:consultar-risco')->prefix('risco')->name('risco.')->group(function () {
            Route::get('/', [RiscoController::class, 'index'])->name('index');
            Route::get('condicionantes', [RiscoCondicionanteController::class, 'index'])->name('condicionantes.index');
        });

        Route::middleware('permission:manter-risco')->prefix('risco')->name('risco.')->group(function () {
            Route::put('publicar', [RiscoController::class, 'publish'])->name('publicar');
            Route::post('condicionantes', [RiscoCondicionanteController::class, 'store'])->name('condicionantes.store');
            Route::put('condicionantes/{condicionante}', [RiscoCondicionanteController::class, 'update'])->name('condicionantes.update');
            Route::delete('condicionantes/{condicionante}', [RiscoCondicionanteController::class, 'destroy'])->name('condicionantes.destroy');
        });
```

- [ ] **Step 3: Verificar que as rotas resolvem**

Run: `php artisan route:list --name=gestao.cnaes`
Expected: lista mostra `gestao.cnaes.index`, `.create`, `.store`, `.edit`, `.update`, `.destroy`, `.condicionantes.store`, `.condicionantes.update`, `.condicionantes.destroy` — sem nenhuma linha `gestao.risco.*`.

- [ ] **Step 4: Commit**

```bash
git add routes/gestao.php
git commit -m "feat(cnae): rotas da ficha única, remove rotas de /gestao/risco"
```

---

### Task 5: Remover controllers/serviço/request de risco em lote + testes órfãos

**Files:**
- Delete: `app/Http/Controllers/Gestao/RiscoController.php`
- Delete: `app/Http/Controllers/Gestao/RiscoCondicionanteController.php`
- Delete: `app/Services/Risco/RiscoMaintenanceService.php`
- Delete: `app/Http/Requests/Gestao/PublishRiscoVersionRequest.php`
- Delete: `tests/Feature/Risco/RiscoConsultaTest.php`
- Delete: `tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php`

**Interfaces:**
- Consumes: nada (a Task 4 já removeu toda referência de rota a essas classes).

- [ ] **Step 1: Confirmar que nada mais referencia essas classes**

Run: `grep -rn "RiscoController\|RiscoCondicionanteController\|RiscoMaintenanceService\|PublishRiscoVersionRequest" app routes resources --include="*.php" --include="*.tsx"`
Expected: nenhum resultado (a Task 4 já limpou `routes/gestao.php`; o `CnaeController` da Task 3 não os importa).

- [ ] **Step 2: Apagar os arquivos**

```bash
git rm app/Http/Controllers/Gestao/RiscoController.php
git rm app/Http/Controllers/Gestao/RiscoCondicionanteController.php
git rm app/Services/Risco/RiscoMaintenanceService.php
git rm app/Http/Requests/Gestao/PublishRiscoVersionRequest.php
git rm tests/Feature/Risco/RiscoConsultaTest.php
git rm tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php
```

- [ ] **Step 3: Rodar a suíte de risco restante para confirmar que nada mais quebrou**

Run: `php artisan test --filter=Risco`
Expected: PASS em todos os testes restantes (`RiscoClassificarCommandTest`, `RiscoGoldenCaseTest`, `RiscoSanitarioSeederTest`, `RiscoSeedDistributionTest`, `RiscoMunicipalSeederTest`, `RiscoClassificationServiceTest`, `RiscoMunicipalImportServiceTest`, `RiscoSanitarioImportServiceTest`, `RiscoDtoTest`) — nenhum deles depende das classes removidas.

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor(cnae): remove publicação em lote de risco municipal e CRUD de condicionantes standalone"
```

---

### Task 6: Seeders de permissão — remover `consultar-risco`/`manter-risco`

**Files:**
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `database/seeders/DemonstracaoClienteSeeder.php`
- Modify: `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php`

**Interfaces:**
- Produces: nenhuma role passa a ter `consultar-risco`/`manter-risco`; a permissão nem é mais criada — a ficha de CNAE (Task 3/4) já usa só `consultar-cnaes`/`manter-cnaes`.

- [ ] **Step 1: Escrever teste que falha (permissão órfã bloquearia o seeder)**

Adicione a `tests/Feature/Cnae/CnaeCrudTest.php` (será consolidado na Task 11):

```php
    public function test_permissoes_de_risco_nao_existem_mais(): void
    {
        $this->assertDatabaseMissing('permissions', ['name' => 'consultar-risco']);
        $this->assertDatabaseMissing('permissions', ['name' => 'manter-risco']);
    }
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=test_permissoes_de_risco_nao_existem_mais`
Expected: FAIL — `RolesAndPermissionsSeeder` ainda cria `consultar-risco`/`manter-risco`.

- [ ] **Step 3: Editar `RolesAndPermissionsSeeder`**

Em `database/seeders/RolesAndPermissionsSeeder.php`, remova as duas linhas do array `$permissions`:

```php
            'consultar-risco',
            'manter-risco',
```

E remova `'consultar-risco'` das listas de `givePermissionTo` de `analista` e `gestor`, e `'consultar-risco'`/`'manter-risco'` da lista de `administrador` (3 ocorrências de `consultar-risco` + 1 de `manter-risco` a mais, além das do array `$permissions`).

- [ ] **Step 4: Editar `DemonstracaoClienteSeeder`**

Em `database/seeders/DemonstracaoClienteSeeder.php`, remova as 3 ocorrências de `'consultar-risco',` dentro de `PERFIS_VALIDACAO` (`validacao-fase-07`, `validacao-fase-10`, `validacao-fase-completa`).

- [ ] **Step 5: Rodar o teste do Step 1 e confirmar que passa**

Run: `php artisan test --filter=test_permissoes_de_risco_nao_existem_mais`
Expected: PASS

- [ ] **Step 6: Corrigir `RolesAndPermissionsSeederTest.php` (asserções diretas de `consultar-risco`/`manter-risco`)**

Este arquivo tem asserções específicas que vão quebrar: `Role::hasPermissionTo('manter-risco')` lança `PermissionDoesNotExist` (não retorna `false`) quando a permissão não existe mais, e o teste de idempotência conta o total de permissions.

Em `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php`:

1. No método `test_seeder_cria_papeis_e_permissoes_da_fase`, remova as duas linhas do array `$permissions`:

```php
            'consultar-risco',
            'manter-risco',
```

2. Apague o método inteiro `test_papeis_recebem_permissoes_de_risco` (cobre exatamente a atribuição de `consultar-risco`/`manter-risco` por papel, que deixa de existir):

```php
    public function test_papeis_recebem_permissoes_de_risco(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['consultar-risco', 'manter-risco'] as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consulta da tabela de risco: analista, gestor e administrador (espelha CNAEs).
        foreach (['administrador', 'analista', 'gestor'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('consultar-risco'),
            );
        }

        // Manutenção (publicação versionada e condicionantes): só o administrador.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-risco'));
        $this->assertFalse(Role::findByName('analista', 'web')->hasPermissionTo('manter-risco'));
        $this->assertFalse(Role::findByName('gestor', 'web')->hasPermissionTo('manter-risco'));
        $this->assertFalse(Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-risco'));
    }
```

3. Em `test_seeder_e_idempotente`, troque a contagem total de permissões (32 → 30: `RolesAndPermissionsSeeder` cria 32 permissões hoje, das quais 2 são `consultar-risco`/`manter-risco`):

```php
        $this->assertSame(4, Role::query()->count());
        $this->assertSame(30, Permission::query()->count());
```

- [ ] **Step 7: Rodar a suíte inteira do arquivo corrigido**

Run: `php artisan test --filter=RolesAndPermissionsSeederTest`
Expected: PASS em todos os métodos restantes.

- [ ] **Step 8: Rodar a suíte de seeders/permissões para checar regressão**

Run: `php artisan test --filter=DemonstracaoClienteSeeder`
Expected: PASS (se não existir teste dedicado, rode `php artisan test --filter=Seeder` e confirme que nenhum teste espera `consultar-risco`/`manter-risco`).

- [ ] **Step 9: Commit**

```bash
git add database/seeders/RolesAndPermissionsSeeder.php database/seeders/DemonstracaoClienteSeeder.php tests/Feature/Authorization/RolesAndPermissionsSeederTest.php tests/Feature/Cnae/CnaeCrudTest.php
git commit -m "refactor(cnae): remove permissoes consultar-risco e manter-risco"
```

---

### Task 7: Frontend — seção de risco compartilhada (`RiscoMunicipalFields`)

**Files:**
- Create: `resources/js/components/cnae/risco-municipal-fields.tsx`

**Interfaces:**
- Produces: `export default function RiscoMunicipalFields(props: RiscoMunicipalFieldsProps): JSX.Element` — usado pelas Tasks 8 e 9 (`criar.tsx` e `editar.tsx`).

- [ ] **Step 1: Criar o componente**

```tsx
import Label from '@/components/form/label';
import Select from '@/components/form/select';

interface NivelOption {
    value: string;
    label: string;
}

interface RiscoMunicipalFieldsProps {
    niveisMunicipais: NivelOption[];
    riscoMunicipal: string;
    onRiscoMunicipalChange: (value: string) => void;
    exigeRt: string;
    onExigeRtChange: (value: string) => void;
    exigeRtSeAlto: string;
    onExigeRtSeAltoChange: (value: string) => void;
    exigeFatorMultiplicador: string;
    onExigeFatorMultiplicadorChange: (value: string) => void;
    exigeDetalhamentoMultiplicador: string;
    onExigeDetalhamentoMultiplicadorChange: (value: string) => void;
    errors: Partial<
        Record<
            | 'risco_municipal'
            | 'exige_rt'
            | 'exige_rt_se_alto'
            | 'exige_fator_multiplicador'
            | 'exige_detalhamento_multiplicador',
            string
        >
    >;
}

const SIM_NAO_OPTIONS = [
    { value: '1', label: 'Sim' },
    { value: '0', label: 'Não' },
];

/**
 * Seção "Classificação de risco" da ficha do CNAE: grau de risco municipal
 * (Decreto 32.636/2020) e as flags de RT/fator multiplicador (HU-047
 * RN-010). Compartilhada entre criar e editar — os mesmos 5 campos.
 */
export default function RiscoMunicipalFields({
    niveisMunicipais,
    riscoMunicipal,
    onRiscoMunicipalChange,
    exigeRt,
    onExigeRtChange,
    exigeRtSeAlto,
    onExigeRtSeAltoChange,
    exigeFatorMultiplicador,
    onExigeFatorMultiplicadorChange,
    exigeDetalhamentoMultiplicador,
    onExigeDetalhamentoMultiplicadorChange,
    errors,
}: RiscoMunicipalFieldsProps) {
    return (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <Label htmlFor="risco-municipal" required>
                    Grau de risco
                </Label>
                <Select
                    id="risco-municipal"
                    name="risco_municipal"
                    value={riscoMunicipal}
                    onChange={onRiscoMunicipalChange}
                    options={niveisMunicipais}
                />
                {errors.risco_municipal && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.risco_municipal}</p>
                )}
            </div>
            <div>
                <Label htmlFor="exige-rt" required>
                    Exige responsável técnico?
                </Label>
                <Select id="exige-rt" name="exige_rt" value={exigeRt} onChange={onExigeRtChange} options={SIM_NAO_OPTIONS} />
                {errors.exige_rt && <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_rt}</p>}
            </div>
            <div>
                <Label htmlFor="exige-rt-se-alto" required>
                    Exige RT apenas se alto risco?
                </Label>
                <Select
                    id="exige-rt-se-alto"
                    name="exige_rt_se_alto"
                    value={exigeRtSeAlto}
                    onChange={onExigeRtSeAltoChange}
                    options={SIM_NAO_OPTIONS}
                />
                <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                    O Responsável Técnico será exigido apenas quando o estabelecimento for classificado como Alto Risco.
                </p>
                {errors.exige_rt_se_alto && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_rt_se_alto}</p>
                )}
            </div>
            <div>
                <Label htmlFor="exige-fator-multiplicador" required>
                    Possui fator multiplicador?
                </Label>
                <Select
                    id="exige-fator-multiplicador"
                    name="exige_fator_multiplicador"
                    value={exigeFatorMultiplicador}
                    onChange={onExigeFatorMultiplicadorChange}
                    options={SIM_NAO_OPTIONS}
                />
                {errors.exige_fator_multiplicador && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_fator_multiplicador}</p>
                )}
            </div>
            <div>
                <Label htmlFor="exige-detalhamento-multiplicador" required>
                    Exige detalhamento do multiplicador?
                </Label>
                <Select
                    id="exige-detalhamento-multiplicador"
                    name="exige_detalhamento_multiplicador"
                    value={exigeDetalhamentoMultiplicador}
                    onChange={onExigeDetalhamentoMultiplicadorChange}
                    options={SIM_NAO_OPTIONS}
                />
                {errors.exige_detalhamento_multiplicador && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_detalhamento_multiplicador}</p>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Typecheck**

Run: `npm run typecheck`
Expected: sem erros novos (o componente ainda não é importado por ninguém, então só valida a sintaxe/tipos dele mesmo).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/cnae/risco-municipal-fields.tsx
git commit -m "feat(cnae): componente compartilhado da secao de risco municipal"
```

---

### Task 8: Frontend — página `gestao/cnaes/criar`

**Files:**
- Create: `resources/js/pages/gestao/cnaes/criar.tsx`

**Interfaces:**
- Consumes: `RiscoMunicipalFields` (Task 7), rota `POST /gestao/cnaes` (Task 4), prop `niveisMunicipais` vinda de `CnaeController@create` (Task 3).
- Produces: componente Inertia `gestao/cnaes/criar` — a Task 11 asserta que `CnaeController@create` renderiza este componente.

- [ ] **Step 1: Criar a página**

```tsx
import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import RiscoMunicipalFields from '@/components/cnae/risco-municipal-fields';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { ArrowRightIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface NivelOption {
    value: string;
    label: string;
}

interface CnaesCriarProps {
    niveisMunicipais: NivelOption[];
}

export default function CnaesCriar({ niveisMunicipais }: CnaesCriarProps) {
    const [riscoMunicipal, setRiscoMunicipal] = useState('');
    const [exigeRt, setExigeRt] = useState('0');
    const [exigeRtSeAlto, setExigeRtSeAlto] = useState('0');
    const [exigeFatorMultiplicador, setExigeFatorMultiplicador] = useState('0');
    const [exigeDetalhamentoMultiplicador, setExigeDetalhamentoMultiplicador] = useState('0');

    return (
        <>
            <Head title="Cadastrar CNAE" />
            <PageHeader
                title="Cadastrar CNAE"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'CNAEs', href: '/gestao/cnaes' },
                ]}
                actions={
                    <Link
                        href="/gestao/cnaes"
                        className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                    >
                        <ArrowRightIcon className="size-4 rotate-180" />
                        Voltar
                    </Link>
                }
            />

            <Form action="/gestao/cnaes" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader
                                title="Dados do CNAE"
                                description="Informe o código da subclasse, a denominação e a hierarquia oficial (IBGE/CONCLA)."
                            />
                            <CardContent>
                                <div className="flex flex-col gap-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="code" required>
                                                Código (DDDD-D/SS)
                                            </Label>
                                            <Input
                                                id="code"
                                                type="text"
                                                name="code"
                                                required
                                                placeholder="0000-0/00"
                                                error={!!errors.code}
                                                hint={errors.code}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="description" required>
                                                Denominação
                                            </Label>
                                            <Input
                                                id="description"
                                                type="text"
                                                name="description"
                                                required
                                                error={!!errors.description}
                                                hint={errors.description}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="section-code" required>
                                                Seção (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-16 shrink-0">
                                                    <Input
                                                        id="section-code"
                                                        type="text"
                                                        name="section_code"
                                                        required
                                                        maxLength={1}
                                                        placeholder="A"
                                                        error={!!errors.section_code}
                                                        hint={errors.section_code}
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <Input
                                                        type="text"
                                                        name="section_description"
                                                        required
                                                        aria-label="Descrição da seção"
                                                        error={!!errors.section_description}
                                                        hint={errors.section_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="division-code" required>
                                                Divisão (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-16 shrink-0">
                                                    <Input
                                                        id="division-code"
                                                        type="text"
                                                        name="division_code"
                                                        required
                                                        maxLength={2}
                                                        placeholder="01"
                                                        error={!!errors.division_code}
                                                        hint={errors.division_code}
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <Input
                                                        type="text"
                                                        name="division_description"
                                                        required
                                                        aria-label="Descrição da divisão"
                                                        error={!!errors.division_description}
                                                        hint={errors.division_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="group-code" required>
                                                Grupo (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-20 shrink-0">
                                                    <Input
                                                        id="group-code"
                                                        type="text"
                                                        name="group_code"
                                                        required
                                                        maxLength={5}
                                                        placeholder="01.1"
                                                        error={!!errors.group_code}
                                                        hint={errors.group_code}
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <Input
                                                        type="text"
                                                        name="group_description"
                                                        required
                                                        aria-label="Descrição do grupo"
                                                        error={!!errors.group_description}
                                                        hint={errors.group_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="class-code" required>
                                                Classe (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-24 shrink-0">
                                                    <Input
                                                        id="class-code"
                                                        type="text"
                                                        name="class_code"
                                                        required
                                                        maxLength={7}
                                                        placeholder="01.11-3"
                                                        error={!!errors.class_code}
                                                        hint={errors.class_code}
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <Input
                                                        type="text"
                                                        name="class_description"
                                                        required
                                                        aria-label="Descrição da classe"
                                                        error={!!errors.class_description}
                                                        hint={errors.class_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader
                                title="Classificação de risco"
                                description="Decreto nº 32.636/2020 e regras de responsável técnico/fator multiplicador (parametrizável por CNAE)."
                            />
                            <CardContent>
                                <RiscoMunicipalFields
                                    niveisMunicipais={niveisMunicipais}
                                    riscoMunicipal={riscoMunicipal}
                                    onRiscoMunicipalChange={setRiscoMunicipal}
                                    exigeRt={exigeRt}
                                    onExigeRtChange={setExigeRt}
                                    exigeRtSeAlto={exigeRtSeAlto}
                                    onExigeRtSeAltoChange={setExigeRtSeAlto}
                                    exigeFatorMultiplicador={exigeFatorMultiplicador}
                                    onExigeFatorMultiplicadorChange={setExigeFatorMultiplicador}
                                    exigeDetalhamentoMultiplicador={exigeDetalhamentoMultiplicador}
                                    onExigeDetalhamentoMultiplicadorChange={setExigeDetalhamentoMultiplicador}
                                    errors={errors}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-end gap-3">
                            <Link
                                href="/gestao/cnaes"
                                className="inline-flex h-10 items-center rounded-lg px-4 text-theme-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                            >
                                Cancelar
                            </Link>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

CnaesCriar.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
```

- [ ] **Step 2: Typecheck**

Run: `npm run typecheck`
Expected: sem erros.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/gestao/cnaes/criar.tsx
git commit -m "feat(cnae): pagina cheia de cadastro de CNAE com classificacao de risco"
```

---

### Task 9: Frontend — página `gestao/cnaes/editar` (dados + risco + perguntas)

**Files:**
- Create: `resources/js/pages/gestao/cnaes/editar.tsx`

**Interfaces:**
- Consumes: `RiscoMunicipalFields` (Task 7), rotas `PUT /gestao/cnaes/{id}`, `POST/PUT/DELETE /gestao/cnaes/{id}/condicionantes[/{id}]` (Task 4), props de `CnaeController@edit` (Task 3): `cnae`, `condicionantes`, `niveisMunicipais`, `niveisReclassificacao`, `semVersaoMunicipal`, `semVersaoSanitaria`.
- Produces: componente Inertia `gestao/cnaes/editar` — a Task 11 asserta que `CnaeController@edit` renderiza este componente com essas props.

- [ ] **Step 1: Criar a página**

```tsx
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import RiscoMunicipalFields from '@/components/cnae/risco-municipal-fields';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { ArrowRightIcon, InfoIcon, PencilIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

const textareaClassName =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

interface NivelOption {
    value: string;
    label: string;
}

interface CnaeEditData {
    id: number;
    code: string;
    formatted_code: string;
    description: string;
    active: boolean;
    exige_rt: boolean;
    exige_rt_se_alto: boolean;
    exige_fator_multiplicador: boolean;
    exige_detalhamento_multiplicador: boolean;
    risco_municipal: string | null;
}

interface RegraReclassificacao {
    resposta_gatilho: boolean;
    reclassifica_para: string | null;
    fundamento?: string | null;
}

interface CondicionanteItem {
    id: number;
    pergunta: string;
    regra_reclassificacao: RegraReclassificacao | null;
    texto_parecer: string | null;
}

interface CnaesEditarProps {
    cnae: CnaeEditData;
    condicionantes: CondicionanteItem[];
    niveisMunicipais: NivelOption[];
    niveisReclassificacao: NivelOption[];
    semVersaoMunicipal: boolean;
    semVersaoSanitaria: boolean;
}

function AvisoSemVersao({ mensagem }: { mensagem: string }) {
    return (
        <div className="mb-5 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
            <InfoIcon className="size-5 shrink-0 fill-current text-warning-500" />
            <p className="text-theme-sm text-gray-600 dark:text-gray-300">{mensagem}</p>
        </div>
    );
}

function CondicionanteRow({
    item,
    niveisReclassificacao,
    canMaintain,
    onEdit,
    onDelete,
}: {
    item: CondicionanteItem;
    niveisReclassificacao: NivelOption[];
    canMaintain: boolean;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const regra = item.regra_reclassificacao;
    const gatilho = regra?.resposta_gatilho ? 'Sim' : 'Não';
    const alvoLabel =
        regra?.reclassifica_para == null
            ? 'Análise técnica'
            : (niveisReclassificacao.find((nivel) => nivel.value === regra.reclassifica_para)?.label ??
              regra.reclassifica_para);

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1.5">
                <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{item.pergunta}</p>
                <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                    Resposta <strong>{gatilho}</strong> reclassifica para{' '}
                    <Badge size="sm" color={alvoLabel === 'Análise técnica' ? 'light' : 'warning'}>
                        {alvoLabel}
                    </Badge>
                </p>
                {item.texto_parecer && (
                    <p className="text-theme-xs text-gray-400 dark:text-gray-500">Parecer: {item.texto_parecer}</p>
                )}
            </div>

            {canMaintain && (
                <div className="flex shrink-0 gap-2">
                    <TableAction tone="brand" icon={<PencilIcon className="size-4.5" />} label="Editar" onClick={onEdit} />
                    <TableAction tone="error" icon={<TrashIcon className="size-4.5" />} label="Excluir" onClick={onDelete} />
                </div>
            )}
        </div>
    );
}

function CondicionanteModal({
    cnaeId,
    niveisReclassificacao,
    condicionante,
    onClose,
}: {
    cnaeId: number;
    niveisReclassificacao: NivelOption[];
    condicionante: CondicionanteItem | null;
    onClose: () => void;
}) {
    const isEdit = condicionante !== null;

    const { data, setData, post, put, processing, errors, reset, transform } = useForm<{
        pergunta: string;
        resposta_gatilho: string;
        reclassifica_para: string;
        fundamento: string;
        texto_parecer: string;
    }>({
        pergunta: condicionante?.pergunta ?? '',
        resposta_gatilho: condicionante?.regra_reclassificacao?.resposta_gatilho === false ? '0' : '1',
        reclassifica_para: condicionante?.regra_reclassificacao?.reclassifica_para ?? '',
        fundamento: condicionante?.regra_reclassificacao?.fundamento ?? '',
        texto_parecer: condicionante?.texto_parecer ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((current) => ({
            pergunta: current.pergunta,
            regra_reclassificacao: {
                resposta_gatilho: current.resposta_gatilho === '1',
                reclassifica_para: current.reclassifica_para === '' ? null : current.reclassifica_para,
                fundamento: current.fundamento === '' ? null : current.fundamento,
            },
            texto_parecer: current.texto_parecer === '' ? null : current.texto_parecer,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (isEdit && condicionante) {
            put(`/gestao/cnaes/${cnaeId}/condicionantes/${condicionante.id}`, options);
        } else {
            post(`/gestao/cnaes/${cnaeId}/condicionantes`, options);
        }
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {isEdit ? 'Editar pergunta' : 'Adicionar pergunta'}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A resposta do requerente a esta pergunta pode reclassificar o risco sanitário do CNAE.
            </p>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <div>
                    <Label htmlFor="pergunta-texto" required>
                        Texto da pergunta
                    </Label>
                    <textarea
                        id="pergunta-texto"
                        rows={2}
                        maxLength={1000}
                        className={textareaClassName}
                        value={data.pergunta}
                        onChange={(event) => setData('pergunta', event.target.value)}
                    />
                    {errors.pergunta && <p className="mt-1.5 text-theme-xs text-error-500">{errors.pergunta}</p>}
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="resposta-gatilho" required>
                            Resposta que reclassifica
                        </Label>
                        <Select
                            id="resposta-gatilho"
                            value={data.resposta_gatilho}
                            onChange={(value) => setData('resposta_gatilho', value)}
                            options={[
                                { value: '1', label: 'Sim' },
                                { value: '0', label: 'Não' },
                            ]}
                        />
                    </div>
                    <div>
                        <Label htmlFor="reclassifica-para">Nível resultante</Label>
                        <Select
                            id="reclassifica-para"
                            value={data.reclassifica_para}
                            onChange={(value) => setData('reclassifica_para', value)}
                            placeholder="Análise técnica (sem reclassificação automática)"
                            options={niveisReclassificacao}
                        />
                        {errors['regra_reclassificacao.reclassifica_para'] && (
                            <p className="mt-1.5 text-theme-xs text-error-500">
                                {errors['regra_reclassificacao.reclassifica_para']}
                            </p>
                        )}
                    </div>
                </div>

                <div>
                    <Label htmlFor="fundamento">Fundamento (aparece no parecer)</Label>
                    <textarea
                        id="fundamento"
                        rows={2}
                        maxLength={2000}
                        className={textareaClassName}
                        value={data.fundamento}
                        onChange={(event) => setData('fundamento', event.target.value)}
                    />
                </div>

                <div>
                    <Label htmlFor="texto-parecer">Texto do parecer</Label>
                    <textarea
                        id="texto-parecer"
                        rows={2}
                        maxLength={2000}
                        className={textareaClassName}
                        value={data.texto_parecer}
                        onChange={(event) => setData('texto_parecer', event.target.value)}
                    />
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" type="submit" disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function CnaesEditar({
    cnae,
    condicionantes,
    niveisMunicipais,
    niveisReclassificacao,
    semVersaoMunicipal,
    semVersaoSanitaria,
}: CnaesEditarProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-cnaes');

    const [active, setActive] = useState(cnae.active ? '1' : '0');
    const [riscoMunicipal, setRiscoMunicipal] = useState(cnae.risco_municipal ?? '');
    const [exigeRt, setExigeRt] = useState(cnae.exige_rt ? '1' : '0');
    const [exigeRtSeAlto, setExigeRtSeAlto] = useState(cnae.exige_rt_se_alto ? '1' : '0');
    const [exigeFatorMultiplicador, setExigeFatorMultiplicador] = useState(cnae.exige_fator_multiplicador ? '1' : '0');
    const [exigeDetalhamentoMultiplicador, setExigeDetalhamentoMultiplicador] = useState(
        cnae.exige_detalhamento_multiplicador ? '1' : '0',
    );

    const [showCondicionanteModal, setShowCondicionanteModal] = useState(false);
    const [editingCondicionante, setEditingCondicionante] = useState<CondicionanteItem | null>(null);
    const [deletingCondicionante, setDeletingCondicionante] = useState<CondicionanteItem | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    function confirmDelete() {
        if (!deletingCondicionante) {
            return;
        }

        router.delete(`/gestao/cnaes/${cnae.id}/condicionantes/${deletingCondicionante.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => setDeleteProcessing(false),
            onSuccess: () => setDeletingCondicionante(null),
        });
    }

    return (
        <>
            <Head title={`Editar CNAE ${cnae.formatted_code}`} />
            <PageHeader
                title="Editar CNAE"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'CNAEs', href: '/gestao/cnaes' },
                ]}
                actions={
                    <Link
                        href="/gestao/cnaes"
                        className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                    >
                        <ArrowRightIcon className="size-4 rotate-180" />
                        Voltar
                    </Link>
                }
            />

            <div className="space-y-6">
                <Form action={`/gestao/cnaes/${cnae.id}`} method="put">
                    {({ errors, processing }) => (
                        <div className="space-y-6">
                            <Card>
                                <CardHeader title="Dados do CNAE" description="Atualize os dados do CNAE." />
                                <CardContent>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="edit-code">Código CNAE</Label>
                                            <Input id="edit-code" type="text" value={cnae.formatted_code} disabled />
                                        </div>
                                        <div>
                                            <Label htmlFor="edit-active" required>
                                                Situação
                                            </Label>
                                            <Select
                                                id="edit-active"
                                                name="active"
                                                value={active}
                                                onChange={setActive}
                                                options={[
                                                    { value: '1', label: 'Ativo' },
                                                    { value: '0', label: 'Inativo' },
                                                ]}
                                            />
                                            {errors.active && (
                                                <p className="mt-1.5 text-theme-xs text-error-500">{errors.active}</p>
                                            )}
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label htmlFor="edit-description" required>
                                                Denominação
                                            </Label>
                                            <Input
                                                id="edit-description"
                                                type="text"
                                                name="description"
                                                defaultValue={cnae.description}
                                                required
                                                error={!!errors.description}
                                                hint={errors.description}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader
                                    title="Classificação de risco"
                                    description="Decreto nº 32.636/2020 e regras de responsável técnico/fator multiplicador."
                                />
                                <CardContent>
                                    {semVersaoMunicipal && (
                                        <AvisoSemVersao mensagem="Não há versão vigente de risco municipal — o grau de risco não poderá ser salvo até que uma versão seja carregada." />
                                    )}
                                    <RiscoMunicipalFields
                                        niveisMunicipais={niveisMunicipais}
                                        riscoMunicipal={riscoMunicipal}
                                        onRiscoMunicipalChange={setRiscoMunicipal}
                                        exigeRt={exigeRt}
                                        onExigeRtChange={setExigeRt}
                                        exigeRtSeAlto={exigeRtSeAlto}
                                        onExigeRtSeAltoChange={setExigeRtSeAlto}
                                        exigeFatorMultiplicador={exigeFatorMultiplicador}
                                        onExigeFatorMultiplicadorChange={setExigeFatorMultiplicador}
                                        exigeDetalhamentoMultiplicador={exigeDetalhamentoMultiplicador}
                                        onExigeDetalhamentoMultiplicadorChange={setExigeDetalhamentoMultiplicador}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Salvando...' : 'Salvar'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                <Card>
                    <CardHeader
                        title="Perguntas de classificação de risco"
                        description={`${condicionantes.length} pergunta(s) — risco sanitário (VISA)`}
                        actions={
                            canMaintain ? (
                                <Button size="sm" variant="outline" onClick={() => setShowCondicionanteModal(true)}>
                                    Adicionar pergunta
                                </Button>
                            ) : undefined
                        }
                    />
                    <CardContent>
                        {semVersaoSanitaria && (
                            <AvisoSemVersao mensagem="Não há versão vigente de risco sanitário — novas perguntas não poderão ser cadastradas até que uma versão seja carregada." />
                        )}

                        {condicionantes.length === 0 ? (
                            <EmptyState
                                title="Nenhuma pergunta cadastrada"
                                description="Cadastre a primeira pergunta de classificação de risco sanitário deste CNAE."
                            />
                        ) : (
                            <div className="flex flex-col gap-3">
                                {condicionantes.map((item) => (
                                    <CondicionanteRow
                                        key={item.id}
                                        item={item}
                                        niveisReclassificacao={niveisReclassificacao}
                                        canMaintain={canMaintain}
                                        onEdit={() => setEditingCondicionante(item)}
                                        onDelete={() => setDeletingCondicionante(item)}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {canMaintain && showCondicionanteModal && (
                <CondicionanteModal
                    cnaeId={cnae.id}
                    niveisReclassificacao={niveisReclassificacao}
                    condicionante={null}
                    onClose={() => setShowCondicionanteModal(false)}
                />
            )}

            {canMaintain && editingCondicionante && (
                <CondicionanteModal
                    cnaeId={cnae.id}
                    niveisReclassificacao={niveisReclassificacao}
                    condicionante={editingCondicionante}
                    onClose={() => setEditingCondicionante(null)}
                />
            )}

            {deletingCondicionante && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setDeletingCondicionante(null)}
                    onConfirm={confirmDelete}
                    title="Excluir pergunta"
                    description={`Confirma a exclusão da pergunta "${deletingCondicionante.pergunta}"? Esta ação não pode ser desfeita.`}
                    confirmLabel="Excluir"
                    processing={deleteProcessing}
                />
            )}
        </>
    );
}

CnaesEditar.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
```

- [ ] **Step 2: Typecheck**

Run: `npm run typecheck`
Expected: sem erros.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/gestao/cnaes/editar.tsx
git commit -m "feat(cnae): ficha de edicao com risco municipal e perguntas de condicionante"
```

---

### Task 10: Frontend — `cnaes/index.tsx` navega para as páginas cheias

**Files:**
- Modify: `resources/js/pages/gestao/cnaes/index.tsx`

**Interfaces:**
- Consumes: rotas `/gestao/cnaes/criar` e `/gestao/cnaes/{id}/editar` (Task 4/8/9).

- [ ] **Step 1: Reescrever o arquivo**

Substitua o conteúdo inteiro de `resources/js/pages/gestao/cnaes/index.tsx` por:

```tsx
import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import { PencilIcon, PowerIcon, TrashIcon } from '@/components/icons';
import Select from '@/components/form/select';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef, SortDirection } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface CnaeItem {
    id: number;
    code: string;
    formatted_code: string;
    description: string;
    active: boolean;
    class_code: string;
}

interface CnaesIndexProps {
    cnaes: {
        data: CnaeItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        sort: string;
        direction: SortDirection;
        per_page: number;
        active: string;
    };
    perPageOptions: number[];
}

type PendingAction = { type: 'delete' | 'toggle'; cnae: CnaeItem };

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

export default function CnaesIndex({ cnaes, filters, perPageOptions }: CnaesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-cnaes');

    const table = useServerTable({
        url: '/gestao/cnaes',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<CnaeItem>[] = [
        {
            id: 'code',
            header: 'Código',
            sortable: true,
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (cnae) => cnae.formatted_code,
        },
        {
            id: 'description',
            header: 'Denominação',
            sortable: true,
            cell: (cnae) => cnae.description,
        },
        {
            id: 'class_code',
            header: 'Classe',
            cellClassName: 'whitespace-nowrap',
            cell: (cnae) => cnae.class_code,
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (cnae) => <SituationBadge active={cnae.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (cnae) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  href={`/gestao/cnaes/${cnae.id}/editar`}
                              />
                              <TableAction
                                  tone={cnae.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={cnae.active ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingAction({ type: 'toggle', cnae })}
                              />
                              <TableAction
                                  tone="error"
                                  icon={<TrashIcon className="size-4.5" />}
                                  label="Excluir"
                                  onClick={() => setPendingAction({ type: 'delete', cnae })}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<CnaeItem>,
              ]
            : []),
    ];

    function executePendingAction() {
        if (!pendingAction) {
            return;
        }

        const { type, cnae } = pendingAction;
        const options = {
            preserveScroll: true,
            onStart: () => setActionProcessing(true),
            onFinish: () => setActionProcessing(false),
            onSuccess: () => setPendingAction(null),
        };

        if (type === 'delete') {
            router.delete(`/gestao/cnaes/${cnae.id}`, options);
        } else {
            router.put(
                `/gestao/cnaes/${cnae.id}`,
                { description: cnae.description, active: cnae.active ? '0' : '1' },
                options,
            );
        }
    }

    const confirmContent = pendingAction
        ? pendingAction.type === 'delete'
            ? {
                  variant: 'danger' as const,
                  title: 'Excluir CNAE',
                  description: `Confirma a exclusão do CNAE ${pendingAction.cnae.formatted_code} — ${pendingAction.cnae.description}? Esta ação não pode ser desfeita.`,
                  confirmLabel: 'Excluir',
              }
            : pendingAction.cnae.active
              ? {
                    variant: 'warning' as const,
                    title: 'Desativar CNAE',
                    description: `Confirma a desativação do CNAE ${pendingAction.cnae.formatted_code} — ${pendingAction.cnae.description}?`,
                    confirmLabel: 'Desativar',
                }
              : {
                    variant: 'info' as const,
                    title: 'Reativar CNAE',
                    description: `Confirma a reativação do CNAE ${pendingAction.cnae.formatted_code} — ${pendingAction.cnae.description}?`,
                    confirmLabel: 'Reativar',
                }
        : null;

    return (
        <>
            <Head title="CNAEs" />
            <PageHeader title="CNAEs" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="CNAEs cadastrados"
                    description="Estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA)"
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => router.visit('/gestao/cnaes/criar')}>
                                Cadastrar CNAE
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar por código ou denominação...',
                                label: 'Buscar CNAEs',
                            }}
                            filters={
                                <div className="w-40">
                                    <label htmlFor="filter-active" className="sr-only">
                                        Filtrar por situação
                                    </label>
                                    <Select
                                        id="filter-active"
                                        value={table.filters.active}
                                        onChange={(value) => table.setFilter('active', value)}
                                        placeholder="Situação"
                                        options={[
                                            { value: '1', label: 'Ativos' },
                                            { value: '0', label: 'Inativos' },
                                        ]}
                                    />
                                </div>
                            }
                            actions={
                                <PerPageSelect
                                    value={table.perPage}
                                    options={perPageOptions}
                                    onChange={table.setPerPage}
                                />
                            }
                        />

                        {table.filters.active !== '' && (
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge size="sm" color="light">
                                    Situação: {table.filters.active === '1' ? 'Ativos' : 'Inativos'}
                                </Badge>
                                <button
                                    type="button"
                                    onClick={() => table.setFilter('active', '')}
                                    className="text-theme-xs font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                >
                                    Limpar filtro
                                </button>
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={cnaes.data}
                            rowKey={(cnae) => cnae.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum CNAE cadastrado'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                            : 'Cadastre o primeiro CNAE para montar a base de atividades.'
                                    }
                                    action={
                                        !filtering && canMaintain ? (
                                            <Button size="sm" onClick={() => router.visit('/gestao/cnaes/criar')}>
                                                Cadastrar CNAE
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={cnaes.links}
                            meta={{ from: cnaes.from, to: cnaes.to, total: cnaes.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {confirmContent && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingAction(null)}
                    onConfirm={executePendingAction}
                    title={confirmContent.title}
                    description={confirmContent.description}
                    confirmLabel={confirmContent.confirmLabel}
                    variant={confirmContent.variant}
                    processing={actionProcessing}
                />
            )}
        </>
    );
}

CnaesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
```

- [ ] **Step 2: Typecheck**

Run: `npm run typecheck`
Expected: sem erros.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/gestao/cnaes/index.tsx
git commit -m "refactor(cnae): lista navega para as paginas cheias em vez de modal"
```

---

### Task 11: Remover páginas/menu de `/gestao/risco` e reescrever `CnaeCrudTest`

**Files:**
- Delete: `resources/js/pages/gestao/risco/index.tsx`
- Delete: `resources/js/pages/gestao/risco/condicionantes.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx`
- Modify: `tests/Feature/Cnae/CnaeCrudTest.php` (reescrita completa — consolida os testes temporários das Tasks 1/3/6 e adiciona os novos cenários)
- Create: `tests/Feature/Cnae/CnaeCondicionanteMaintenanceTest.php`

**Interfaces:**
- Consumes: tudo das Tasks 1-10.

- [ ] **Step 1: Apagar as páginas de risco**

```bash
git rm resources/js/pages/gestao/risco/index.tsx
git rm resources/js/pages/gestao/risco/condicionantes.tsx
```

- [ ] **Step 2: Remover os itens de menu**

Em `resources/js/layouts/gestao-layout.tsx`, remova os dois objetos do grupo "Regras do licenciamento":

```tsx
                {
                    name: 'Classificação de risco',
                    href: '/gestao/risco',
                    icon: <ShieldIcon />,
                    visible: auth.permissions.includes('consultar-risco'),
                },
                {
                    name: 'Condicionantes',
                    href: '/gestao/risco/condicionantes',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('manter-risco'),
                },
```

`ShieldIcon` e `ListIcon` continuam usados em outros itens do mesmo arquivo — não remova os imports.

- [ ] **Step 3: Typecheck do frontend**

Run: `npm run typecheck`
Expected: sem erros (nenhum arquivo restante importa as páginas apagadas).

- [ ] **Step 4: Reescrever `tests/Feature/Cnae/CnaeCrudTest.php` por completo**

```php
<?php

namespace Tests\Feature\Cnae;

use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CnaeCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Grau de risco municipal é salvo direto na versão vigente — sem uma
        // versão do domínio, store()/update() bloqueiam com flash.error.
        RuleVersion::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'code' => '9900-8/00',
            'description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'section_code' => 'U',
            'section_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'division_code' => '99',
            'division_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'group_code' => '99.0',
            'group_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'class_code' => '99.00-8',
            'class_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'risco_municipal' => 'baixo_a',
            'exige_rt' => false,
            'exige_rt_se_alto' => false,
            'exige_fator_multiplicador' => false,
            'exige_detalhamento_multiplicador' => false,
        ];
    }

    public function test_administrador_lista_cnaes_paginados(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->count(3)->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/index')
                ->has('cnaes.data', 3)
                ->has('cnaes.data.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'code', 'formatted_code', 'description', 'active'])
                    ->etc()));
    }

    public function test_busca_filtra_por_codigo_ou_denominacao(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes e similares']);
        Cnae::factory()->create(['code' => '0111301', 'description' => 'Cultivo de arroz']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes?search=restaurante')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 1)
                ->where('cnaes.data.0.code', '5611201'));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes?search=0111-3')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 1)
                ->where('cnaes.data.0.code', '0111301'));
    }

    public function test_analista_consulta_mas_nao_mantem(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk();

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_cidadao_nao_acessa_cnaes(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'gestao')
            ->get('/gestao/cnaes')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $cidadao->id,
        ]);
    }

    public function test_pagina_de_criacao_carrega_niveis_municipais(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes/criar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/criar')
                ->has('niveisMunicipais', 3));
    }

    public function test_cria_cnae_manual_normalizando_codigo(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('cnaes', [
            'code' => '9900800',
            'description' => 'Organismos internacionais e outras instituições extraterritoriais',
        ]);
    }

    public function test_criar_cnae_grava_classificacao_de_risco_municipal_direto(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', [
                ...$this->validPayload(),
                'risco_municipal' => 'alto',
                'exige_rt' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cnae = Cnae::where('code', '9900800')->firstOrFail();

        $this->assertTrue($cnae->exige_rt);
        $this->assertDatabaseHas('risk_classifications', [
            'cnae_code' => '9900800',
            'risco_municipal' => 'alto',
        ]);
    }

    public function test_codigo_duplicado_e_malformado_bloqueados(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->create(['code' => '9900800']);

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertSessionHasErrors('code');

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', [...$this->validPayload(), 'code' => '123'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Cnae::count());
    }

    public function test_pagina_de_edicao_carrega_classificacao_e_perguntas_vigentes(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $municipal = RuleVersion::vigente(\App\Enums\RuleDomain::RiscoMunicipal)->firstOrFail();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '0111301',
            'risco_municipal' => 'alto',
        ]);

        $sanitaria = RuleVersion::factory()->create(['domain' => \App\Enums\RuleDomain::RiscoSanitario]);
        \App\Models\RiskCondicionante::factory()->create([
            'rule_version_id' => $sanitaria->id,
            'cnae_code' => '0111301',
            'pergunta' => 'O produto é artesanal?',
        ]);

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/cnaes/{$cnae->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/editar')
                ->where('cnae.risco_municipal', 'alto')
                ->has('condicionantes', 1)
                ->where('condicionantes.0.pergunta', 'O produto é artesanal?'));
    }

    public function test_edicao_atualiza_dados_mas_nunca_o_codigo(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'code' => '9999999',
                'description' => 'Denominação ajustada',
                'active' => true,
                'risco_municipal' => 'baixo_a',
                'exige_rt' => false,
                'exige_rt_se_alto' => false,
                'exige_fator_multiplicador' => false,
                'exige_detalhamento_multiplicador' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cnae->refresh();

        $this->assertSame('0111301', $cnae->code);
        $this->assertSame('Denominação ajustada', $cnae->description);
    }

    public function test_edicao_atualiza_grau_de_risco_sem_pedir_quatro_olhos(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'description' => $cnae->description,
                'active' => true,
                'risco_municipal' => 'alto',
                'exige_rt' => true,
                'exige_rt_se_alto' => false,
                'exige_fator_multiplicador' => false,
                'exige_detalhamento_multiplicador' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('risk_classifications', [
            'cnae_code' => '0111301',
            'risco_municipal' => 'alto',
        ]);
        $this->assertTrue($cnae->refresh()->exige_rt);
    }

    public function test_desativacao_logica_preserva_o_registro(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'description' => $cnae->description,
                'active' => false,
                'risco_municipal' => 'baixo_a',
                'exige_rt' => false,
                'exige_rt_se_alto' => false,
                'exige_fator_multiplicador' => false,
                'exige_detalhamento_multiplicador' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cnaes', ['id' => $cnae->id]);
        $this->assertFalse($cnae->refresh()->active);
    }

    public function test_mudancas_sao_auditadas_com_attribute_changes(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'description' => 'Denominação auditável',
                'active' => true,
                'risco_municipal' => 'baixo_a',
                'exige_rt' => false,
                'exige_rt_se_alto' => false,
                'exige_fator_multiplicador' => false,
                'exige_detalhamento_multiplicador' => false,
            ])
            ->assertRedirect();

        $activity = Activity::where('event', 'updated')
            ->where('subject_type', Cnae::class)
            ->where('subject_id', $cnae->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de atualização do CNAE');
        $this->assertArrayHasKey('description', $activity->attribute_changes['attributes'] ?? []);
    }

    public function test_exclusao_de_cnae_e_auditada(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/cnaes/{$cnae->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('cnaes', ['id' => $cnae->id]);

        $activity = Activity::where('event', 'deleted')
            ->where('subject_type', Cnae::class)
            ->where('subject_id', $cnae->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de exclusão do CNAE');
    }

    public function test_exclusao_de_cnae_vinculado_a_empresa_e_bloqueada(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $company = Company::factory()->create();
        $cnae = Cnae::factory()->create();
        $company->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/cnaes/{$cnae->id}")
            ->assertRedirect()
            ->assertSessionHas('error', 'CNAE vinculado a empresas não pode ser excluído.');

        $this->assertDatabaseHas('cnaes', ['id' => $cnae->id]);

        $activity = Activity::where('event', 'deleted')
            ->where('subject_type', Cnae::class)
            ->where('subject_id', $cnae->id)
            ->first();

        $this->assertNull($activity, 'Não deveria haver activity de exclusão para CNAE vinculado');
    }

    public function test_permissoes_de_risco_nao_existem_mais(): void
    {
        $this->assertDatabaseMissing('permissions', ['name' => 'consultar-risco']);
        $this->assertDatabaseMissing('permissions', ['name' => 'manter-risco']);
    }

    public function test_rotas_de_risco_standalone_nao_existem_mais(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')->get('/gestao/risco')->assertNotFound();
        $this->actingAs($admin, 'gestao')->get('/gestao/risco/condicionantes')->assertNotFound();
    }
}
```

- [ ] **Step 5: Rodar a suíte reescrita**

Run: `php artisan test --filter=CnaeCrudTest`
Expected: PASS em todos os métodos.

- [ ] **Step 6: Criar `tests/Feature/Cnae/CnaeCondicionanteMaintenanceTest.php`**

```php
<?php

namespace Tests\Feature\Cnae;

use App\Enums\RuleDomain;
use App\Models\Cnae;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CnaeCondicionanteMaintenanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_administrador_cria_pergunta_de_condicionante_no_cnae(): void
    {
        RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($this->admin(), 'gestao')
            ->post("/gestao/cnaes/{$cnae->id}/condicionantes", [
                'pergunta' => 'O produto é artesanal?',
                'regra_reclassificacao' => [
                    'resposta_gatilho' => false,
                    'reclassifica_para' => 'alto',
                    'fundamento' => 'Produto não artesanal eleva o risco.',
                ],
                'texto_parecer' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('risk_condicionantes', [
            'cnae_code' => '0111301',
            'pergunta' => 'O produto é artesanal?',
        ]);
    }

    public function test_condicionante_ignora_cnae_code_enviado_e_usa_o_da_rota(): void
    {
        RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create(['code' => '0111301']);
        Cnae::factory()->create(['code' => '9999999']);

        $this->actingAs($this->admin(), 'gestao')
            ->post("/gestao/cnaes/{$cnae->id}/condicionantes", [
                'cnae_code' => '9999999',
                'pergunta' => 'Pergunta qualquer?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('risk_condicionantes', [
            'cnae_code' => '0111301',
            'pergunta' => 'Pergunta qualquer?',
        ]);
    }

    public function test_administrador_edita_e_remove_pergunta(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create(['code' => '0111301']);
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '0111301',
            'pergunta' => 'Pergunta original?',
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}/condicionantes/{$condicionante->id}", [
                'pergunta' => 'Pergunta editada?',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Pergunta editada?', $condicionante->refresh()->pergunta);

        $this->actingAs($this->admin(), 'gestao')
            ->delete("/gestao/cnaes/{$cnae->id}/condicionantes/{$condicionante->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('risk_condicionantes', ['id' => $condicionante->id]);
    }

    public function test_edicao_de_pergunta_de_outro_cnae_e_bloqueada(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnaeA = Cnae::factory()->create(['code' => '0111301']);
        $cnaeB = Cnae::factory()->create(['code' => '9999999']);
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '9999999',
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->put("/gestao/cnaes/{$cnaeA->id}/condicionantes/{$condicionante->id}", ['pergunta' => 'Tentativa?'])
            ->assertNotFound();

        $this->assertSame('9999999', $condicionante->refresh()->cnae_code);
        $this->assertNotNull($cnaeB);
    }

    public function test_analista_nao_mantem_condicionantes(): void
    {
        RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/cnaes/{$cnae->id}/condicionantes", ['pergunta' => 'Pergunta?'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 7: Rodar a nova suíte**

Run: `php artisan test --filter=CnaeCondicionanteMaintenanceTest`
Expected: PASS em todos os métodos.

- [ ] **Step 8: Commit**

```bash
git add tests/Feature/Cnae/CnaeCrudTest.php tests/Feature/Cnae/CnaeCondicionanteMaintenanceTest.php resources/js/layouts/gestao-layout.tsx
git rm resources/js/pages/gestao/risco/index.tsx resources/js/pages/gestao/risco/condicionantes.tsx 2>/dev/null || true
git commit -m "test(cnae): cobre ficha unica de CNAE, risco direto e CRUD de condicionantes; remove telas de /gestao/risco"
```

---

### Task 12: Verificação final

**Files:** nenhum (só validação).

- [ ] **Step 1: Rodar a suíte de backend inteira**

Run: `php artisan test`
Expected: PASS em todos os testes (nenhuma regressão fora do escopo desta mudança).

- [ ] **Step 2: Typecheck e lint do frontend**

Run: `npm run typecheck`
Expected: sem erros.

- [ ] **Step 3: Build de produção do frontend**

Run: `npm run build`
Expected: build conclui sem erros (confirma que `criar.tsx`/`editar.tsx`/`index.tsx` resolvem todos os imports).

- [ ] **Step 4: Smoke manual no navegador**

Suba o ambiente local (`php artisan serve` + `npm run dev`, ou o preview configurado no projeto), logue como administrador em `/gestao/login` e percorra:
1. `/gestao/cnaes` → "Cadastrar CNAE" → preencher e salvar → cai na listagem com o novo CNAE.
2. Abrir "Editar" de um CNAE → mudar grau de risco → Salvar → recarregar a página e confirmar que o grau persiste.
3. Na mesma ficha, "Adicionar pergunta" → salvar → editar a pergunta → excluir a pergunta.
4. Confirmar que `/gestao/risco` e `/gestao/risco/condicionantes` retornam 404 e que o menu lateral não lista mais "Classificação de risco"/"Condicionantes".

Reporte qualquer divergência antes de considerar a tarefa concluída — este passo não é automatizável pelos testes acima (que cobrem contrato HTTP/Inertia, não renderização real).

- [ ] **Step 5: Commit final (se houver ajustes do smoke test)**

Só commitar se o Step 4 revelar algum ajuste necessário; caso contrário, este task não gera commit novo.
