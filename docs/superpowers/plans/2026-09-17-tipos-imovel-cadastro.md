# Cadastro de Tipos de Imóvel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** substituir o catálogo hardcoded `TipoImovelCatalog::sedur200826()` por um cadastro administrável (`property_types` + `property_type_aliases`) com CRUD auditado, sem mudar nenhum comportamento de roteamento para os valores atuais.

**Architecture:** o catálogo vira dado de banco lido por `TipoImovelCatalog::vigente()` com cache invalidado na escrita (mesmo padrão `Settings`: cache → banco → fallback embutido só quando o banco está inalcançável). A assinatura pública do catálogo (`codigoQueDirige`, `codigoRamoComum`) não muda; os três consumidores só trocam o named constructor. CRUD server-driven espelhando `ViabilityServiceTypeController`, atrás de permissão nova `manter-tipos-imovel`.

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, Inertia v3 + React 19, spatie/permission + spatie/activitylog.

**Origem:** Fase 1 do backlog `docs/superpowers/plans/2026-09-17-parametrizacao-matrizes-auditoria.md` (auditoria de parametrização 2026-09-17, achado ALTÍSSIMO #1).

## Global Constraints

- TDD estrito: nenhum código de produção sem teste falhando antes; rodar o teste e confirmar o RED pelo motivo certo.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Models de domínio usam `HasAuditoria` (RN-002) e `#[Fillable([...])]` + `casts()` no método, padrão `ViabilityServiceType`.
- Seeder idempotente (`updateOrCreate`/`firstOrCreate`) e ADITIVO — re-seed em produção não remove nada.
- Permissão nova é aditiva no `RolesAndPermissionsSeeder` (`firstOrCreate` + `givePermissionTo`, nunca `sync`).
- UI, mensagens, commits em pt-BR; código em inglês; conventional commits.
- **Decisão de nomenclatura (registrada):** models `PropertyType`/`PropertyTypeAlias`, tabelas `property_types`/`property_type_aliases`. A auditoria sugeriu `tipos_imovel`; mudamos porque `App\Models\TipoImovel` colidiria com o value object `App\Services\Risco\TipoImovel` e o precedente de cadastros (`RiskTrigger`, `ViabilityServiceType`) é inglês. O vocabulário `TipoImovel*` permanece nos services.
- **Não parametrizar:** `TipoImovel::normalize` (algoritmo) permanece em código.

---

### Task 1: Tabelas, models, factories e seeder

**Files:**
- Create: `database/migrations/2026_09_17_150000_create_property_types_table.php`
- Create: `database/migrations/2026_09_17_150001_create_property_type_aliases_table.php`
- Create: `app/Models/PropertyType.php`
- Create: `app/Models/PropertyTypeAlias.php`
- Create: `database/factories/PropertyTypeFactory.php`
- Create: `database/factories/PropertyTypeAliasFactory.php`
- Create: `database/seeders/PropertyTypeSeeder.php`
- Test: `tests/Feature/Risco/PropertyTypeSeederTest.php`

**Interfaces:**
- Produces: `PropertyType` (`code`, `label`, `drives_rule`, `active`) com `aliases(): HasMany`, `scopeActive()`, const `CACHE_KEY`; `PropertyTypeAlias` (`property_type_id`, `alias`) com normalização automática do alias na gravação; `PropertyTypeSeeder` com os 5 tipos atuais.

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test Risco/PropertyTypeSeederTest --no-interaction
```

```php
<?php

namespace Tests\Feature\Risco;

use App\Models\PropertyType;
use App\Services\Risco\TipoImovel;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O seed replica fielmente o catálogo SEDUR 2026-08-26 hoje embutido em
 * TipoImovelCatalog::sedur200826() — migração sem mudança de comportamento.
 */
class PropertyTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_cria_os_cinco_tipos_com_as_flags_corretas(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $dirigem = PropertyType::query()->where('drives_rule', true)->pluck('code')->sort()->values()->all();
        $comum = PropertyType::query()->where('drives_rule', false)->pluck('code')->sort()->values()->all();

        $this->assertSame(['container', 'edificacao_residencial', 'galpao'], $dirigem);
        $this->assertSame(['edificacao_comercial', 'sala'], $comum);
        $this->assertSame(5, PropertyType::query()->where('active', true)->count());
    }

    public function test_aliases_sao_normalizados_na_gravacao(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $galpao = PropertyType::query()->where('code', 'galpao')->firstOrFail();

        $this->assertTrue($galpao->aliases()->where('alias', 'galpao')->exists());
        // Alias acentuado gravado via model sai normalizado (regra do REGIN).
        $galpao->aliases()->create(['alias' => 'GALPÃO INDUSTRIAL']);
        $this->assertTrue($galpao->aliases()->where('alias', 'galpao industrial')->exists());
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(PropertyTypeSeeder::class);

        $this->assertSame(5, PropertyType::query()->count());
        $this->assertSame(5, \App\Models\PropertyTypeAlias::query()->count());
    }

    public function test_alias_e_unico_entre_tipos(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $sala = PropertyType::query()->where('code', 'sala')->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);
        $sala->aliases()->create(['alias' => 'galpao']);
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Risco/PropertyTypeSeederTest.php`
Expected: FAIL — `App\Models\PropertyType` não existe.

- [ ] **Step 3: Criar migrations, models, factories e seeder**

```bash
php artisan make:model PropertyType --factory --no-interaction
php artisan make:model PropertyTypeAlias --factory --no-interaction
php artisan make:migration create_property_type_aliases_table --no-interaction
php artisan make:seeder PropertyTypeSeeder --no-interaction
```

Migration `create_property_types_table` (gerada pelo make:model — renomear o timestamp para `2026_09_17_150000`):

```php
public function up(): void
{
    Schema::create('property_types', function (Blueprint $table) {
        $table->id();
        $table->string('code', 50)->unique();
        $table->string('label');
        $table->boolean('drives_rule')->default(false);
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
}
```

Migration `create_property_type_aliases_table`:

```php
public function up(): void
{
    Schema::create('property_type_aliases', function (Blueprint $table) {
        $table->id();
        $table->foreignId('property_type_id')->constrained()->cascadeOnDelete();
        $table->string('alias')->unique(); // normalizado; um alias pertence a UM tipo
        $table->timestamps();
    });
}
```

`app/Models/PropertyType.php`:

```php
<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\PropertyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Tipo de imóvel reconhecido do REGIN (SEDUR 2026-08-26) — dado administrável.
 * drives_rule=true injeta o gatilho dados_do_processo e derruba o processo do
 * expresso para análise; por isso a edição é auditada (HasAuditoria, RN-002)
 * e a desativação degrada para "desconhecido" (vai à análise), nunca para
 * decisão automática.
 */
#[Fillable(['code', 'label', 'drives_rule', 'active'])]
class PropertyType extends Model
{
    use HasAuditoria;

    /** @use HasFactory<PropertyTypeFactory> */
    use HasFactory;

    public const CACHE_KEY = 'sile.property_types.catalogo';

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drives_rule' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PropertyTypeAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(PropertyTypeAlias::class);
    }

    /**
     * @param  Builder<PropertyType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
```

`app/Models/PropertyTypeAlias.php`:

```php
<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Services\Risco\TipoImovel;
use Database\Factories\PropertyTypeAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Grafia alternativa (normalizada) que o REGIN pode enviar para um tipo de
 * imóvel. A normalização é a mesma do motor (TipoImovel::normalize) aplicada
 * na gravação — o match nunca depende de acento/caixa.
 */
#[Fillable(['property_type_id', 'alias'])]
class PropertyTypeAlias extends Model
{
    use HasAuditoria;

    /** @use HasFactory<PropertyTypeAliasFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(PropertyType::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return BelongsTo<PropertyType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    protected function alias(): Attribute
    {
        return Attribute::make(set: fn (string $value) => TipoImovel::normalize($value));
    }
}
```

`database/factories/PropertyTypeFactory.php`:

```php
class PropertyTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'tipo_'.fake()->unique()->numerify('####'),
            'label' => ucfirst(fake()->words(2, true)),
            'drives_rule' => false,
            'active' => true,
        ];
    }

    public function drivesRule(): static
    {
        return $this->state(fn () => ['drives_rule' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
```

`database/factories/PropertyTypeAliasFactory.php`:

```php
class PropertyTypeAliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_type_id' => PropertyType::factory(),
            'alias' => fake()->unique()->words(2, true),
        ];
    }
}
```

`database/seeders/PropertyTypeSeeder.php` (conteúdo = exatamente o `sedur200826()` atual):

```php
<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;

/**
 * Carga inicial = catálogo SEDUR 2026-08-26 (hoje TipoImovelCatalog::sedur200826()).
 * Idempotente e aditivo: updateOrCreate por code, firstOrCreate por alias.
 */
class PropertyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $itens = [
            ['code' => 'galpao', 'label' => 'Galpão', 'drives_rule' => true, 'aliases' => ['galpao']],
            ['code' => 'container', 'label' => 'Container', 'drives_rule' => true, 'aliases' => ['container']],
            ['code' => 'edificacao_residencial', 'label' => 'Edificação residencial', 'drives_rule' => true, 'aliases' => ['edificacao residencial']],
            ['code' => 'edificacao_comercial', 'label' => 'Edificação comercial', 'drives_rule' => false, 'aliases' => ['edificacao comercial']],
            ['code' => 'sala', 'label' => 'Sala', 'drives_rule' => false, 'aliases' => ['sala']],
        ];

        foreach ($itens as $item) {
            $tipo = PropertyType::updateOrCreate(
                ['code' => $item['code']],
                ['label' => $item['label'], 'drives_rule' => $item['drives_rule'], 'active' => true],
            );

            foreach ($item['aliases'] as $alias) {
                $tipo->aliases()->firstOrCreate(['alias' => $alias]);
            }
        }
    }
}
```

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Risco/PropertyTypeSeederTest.php`
Expected: PASS (4 testes).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*property_type* app/Models/PropertyType*.php database/factories/PropertyType*.php database/seeders/PropertyTypeSeeder.php tests/Feature/Risco/PropertyTypeSeederTest.php
git commit -m "feat: cria cadastro de tipos de imóvel (tabelas, models e seed)"
```

---

### Task 2: `TipoImovelCatalog::vigente()` lendo do banco + troca dos call sites

**Files:**
- Modify: `app/Services/Risco/TipoImovelCatalog.php`
- Modify: `app/Services/Solicitacao/SolicitacaoViabilityResolver.php:113`
- Modify: `app/Services/Regin/ReginProtocoloSimulacaoService.php:198`
- Modify: `app/Services/Regin/ReginTipoImovelApplier.php:17`
- Test: `tests/Feature/Risco/TipoImovelCatalogVigenteTest.php`

**Interfaces:**
- Consumes: `PropertyType`/`PropertyTypeAlias` da Task 1; `PropertyType::CACHE_KEY`.
- Produces: `TipoImovelCatalog::vigente(): self` — banco com cache; fallback `sedur200826()` SOMENTE quando o banco está inalcançável (QueryException, padrão `Settings::get`). Banco alcançável e vazio retorna catálogo vazio (tudo vira `Desconhecido` → análise: degradação honesta, nunca decisão automática).
- Não muda: `codigoQueDirige()`, `codigoRamoComum()`, `match()` — a lógica de match (aliases + código + código com espaços) é preservada, o que garante não-regressão.

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test Risco/TipoImovelCatalogVigenteTest --no-interaction
```

```php
<?php

namespace Tests\Feature\Risco;

use App\Enums\TipoImovelReconhecimento;
use App\Models\PropertyType;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O catálogo vigente vem do banco (cadastro administrável). O seed oficial
 * resolve EXATAMENTE como o sedur200826() embutido — prova de não-regressão
 * da migração código → banco.
 */
class TipoImovelCatalogVigenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_semeado_resolve_como_o_embutido(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $catalogo = TipoImovelCatalog::vigente();

        foreach (['GALPÃO', 'Container', 'Edificação Residencial'] as $raw) {
            $this->assertSame(
                TipoImovelReconhecimento::DirigeRegra,
                TipoImovel::fromRegin($raw, $catalogo)->reconhecimento,
                $raw,
            );
        }

        $comum = TipoImovel::fromRegin('Edificação Comercial', $catalogo);
        $this->assertSame(TipoImovelReconhecimento::RamoComum, $comum->reconhecimento);
        $this->assertSame('edificacao_comercial', $comum->normalized);

        $desconhecido = TipoImovel::fromRegin('Loja de shopping (grafia nova)', $catalogo);
        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $desconhecido->reconhecimento);
    }

    public function test_escrita_no_cadastro_invalida_o_cache(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $antes = TipoImovel::fromRegin('Galpão logístico', TipoImovelCatalog::vigente());
        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $antes->reconhecimento);

        PropertyType::query()->where('code', 'galpao')->firstOrFail()
            ->aliases()->create(['alias' => 'Galpão logístico']);

        $depois = TipoImovel::fromRegin('Galpão logístico', TipoImovelCatalog::vigente());
        $this->assertSame(TipoImovelReconhecimento::DirigeRegra, $depois->reconhecimento);
    }

    public function test_tipo_inativo_sai_do_reconhecimento_e_degrada_para_analise(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        PropertyType::query()->where('code', 'galpao')->firstOrFail()->update(['active' => false]);

        $tipo = TipoImovel::fromRegin('GALPÃO', TipoImovelCatalog::vigente());
        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $tipo->reconhecimento);
        $this->assertFalse($tipo->permiteDecisaoAutomatica());
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Risco/TipoImovelCatalogVigenteTest.php`
Expected: FAIL — `TipoImovelCatalog::vigente()` não existe.

- [ ] **Step 3: Implementar `vigente()` e trocar os 3 call sites**

Em `app/Services/Risco/TipoImovelCatalog.php`, acrescentar (imports: `App\Models\PropertyType`, `Illuminate\Database\QueryException`, `Illuminate\Support\Facades\Cache`):

```php
    /**
     * Catálogo vigente: banco (cadastro administrável) com cache invalidado
     * na escrita dos models — efeito sem deploy, padrão Settings. O fallback
     * sedur200826() só vale com o banco INALCANÇÁVEL (build Docker, CI, testes
     * Unit); banco alcançável e vazio degrada tudo para análise (honesto).
     */
    public static function vigente(): self
    {
        try {
            return Cache::remember(
                PropertyType::CACHE_KEY,
                (int) config('sile.parameters.cache_ttl', 300),
                function () {
                    $dirigemRegra = [];
                    $ramoComum = [];

                    foreach (PropertyType::query()->active()->with('aliases')->get() as $tipo) {
                        $mapa = $tipo->drives_rule ? 'dirigemRegra' : 'ramoComum';
                        ${$mapa}[$tipo->code] = $tipo->aliases->pluck('alias')->all();
                    }

                    return new self(dirigemRegra: $dirigemRegra, ramoComum: $ramoComum);
                },
            );
        } catch (QueryException|\Exception) {
            return self::sedur200826();
        }
    }
```

Atualizar o docblock da classe e de `sedur200826()`: o catálogo oficial agora é o banco; `sedur200826()` é o fallback de emergência e fixture de testes unitários.

Trocar nos 3 consumidores `TipoImovelCatalog::sedur200826()` → `TipoImovelCatalog::vigente()`:
- `app/Services/Solicitacao/SolicitacaoViabilityResolver.php:113`
- `app/Services/Regin/ReginProtocoloSimulacaoService.php:198`
- `app/Services/Regin/ReginTipoImovelApplier.php:17`

- [ ] **Step 4: Rodar e confirmar o GREEN + caçar testes que agora batem no banco**

Run: `php artisan test --compact tests/Feature/Risco/TipoImovelCatalogVigenteTest.php`
Expected: PASS (3 testes).

Run: `php artisan test --compact`
Expected: PASS. Testes de feature que exercitam os 3 consumidores end-to-end (ex.: `tests/Feature/Solicitacao/TipoImovelReginTest.php`, `tests/Feature/Risco/ReginProtocoloSimulacaoTest.php`) agora leem o banco: nos que falharem por catálogo vazio, adicionar `$this->seed(PropertyTypeSeeder::class);` no `setUp()` (ou no teste) — NUNCA afrouxar o assert. Testes que usam `sedur200826()` direto como fixture in-memory (`tests/Unit/Risco/TipoImovelTest.php`, `RiscoDtoTest`) não mudam.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Risco/TipoImovelCatalog.php app/Services/Solicitacao/SolicitacaoViabilityResolver.php app/Services/Regin/ReginProtocoloSimulacaoService.php app/Services/Regin/ReginTipoImovelApplier.php tests/
git commit -m "feat: lê catálogo de tipos de imóvel do banco com cache"
```

---

### Task 3: Permissão `manter-tipos-imovel` + CRUD backend

**Files:**
- Modify: `app/Support/PermissionCatalog.php` (após `manter-tipos-servico`)
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` (array `$permissions` + `givePermissionTo` de admin e gestor)
- Modify: `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` (contagem 30→31 e listas esperadas)
- Create: `app/Http/Controllers/Gestao/PropertyTypeController.php`
- Create: `app/Http/Requests/Gestao/StorePropertyTypeRequest.php`
- Create: `app/Http/Requests/Gestao/UpdatePropertyTypeRequest.php`
- Modify: `routes/gestao.php` (após o bloco `tipos-servico`, linha ~337)
- Test: `tests/Feature/Risco/PropertyTypeCrudTest.php`

**Interfaces:**
- Consumes: models da Task 1.
- Produces: rotas `gestao.tipos-imovel.{index,store,update,ativacao.update}` sob `permission:manter-tipos-imovel`; payload com `aliases` (array de strings, normalizadas no model); `code` único no store e IMUTÁVEL no update (padrão CPF/CNAE).

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test Risco/PropertyTypeCrudTest --no-interaction
```

Espelhar `tests/Feature/Solicitacao/ViabilityServiceTypeCrudTest.php`. Casos mínimos:

```php
    public function test_lista_exige_permissao(): void
    {
        $analista = User::factory()->create();
        $analista->assignRole('analista'); // acessa a gestão, mas NÃO tem manter-tipos-imovel

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/tipos-imovel')
            ->assertForbidden();
    }

    public function test_cria_tipo_com_aliases_normalizados(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manter-tipos-imovel');

        $this->actingAs($admin, 'gestao')->post('/gestao/tipos-imovel', [
            'code' => 'galpao_logistico',
            'label' => 'Galpão logístico',
            'drives_rule' => true,
            'active' => true,
            'aliases' => ['GALPÃO LOGÍSTICO', 'galpao  logistico'],
        ])->assertRedirect();

        $tipo = PropertyType::query()->where('code', 'galpao_logistico')->firstOrFail();
        $this->assertTrue($tipo->drives_rule);
        // normalizado + distinct: as duas grafias viram UM alias
        $this->assertSame(['galpao logistico'], $tipo->aliases->pluck('alias')->all());
    }

    public function test_code_e_imutavel_na_edicao(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manter-tipos-imovel');
        $tipo = PropertyType::factory()->create(['code' => 'galpao']);

        $this->actingAs($admin, 'gestao')->put("/gestao/tipos-imovel/{$tipo->id}", [
            'code' => 'tentativa_de_troca',
            'label' => 'Galpão atualizado',
            'drives_rule' => true,
            'active' => true,
            'aliases' => [],
        ])->assertRedirect();

        $this->assertSame('galpao', $tipo->fresh()->code);
        $this->assertSame('Galpão atualizado', $tipo->fresh()->label);
    }

    public function test_alias_duplicado_entre_tipos_e_rejeitado(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manter-tipos-imovel');
        PropertyType::factory()->has(\App\Models\PropertyTypeAlias::factory()->state(['alias' => 'galpao']), 'aliases')->create();

        $this->actingAs($admin, 'gestao')->post('/gestao/tipos-imovel', [
            'code' => 'outro_tipo',
            'label' => 'Outro tipo',
            'drives_rule' => false,
            'active' => true,
            'aliases' => ['GALPÃO'],
        ])->assertSessionHasErrors('aliases.0');
    }

    public function test_toggle_desativa_sem_excluir_e_audita(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manter-tipos-imovel');
        $tipo = PropertyType::factory()->create(['active' => true]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/tipos-imovel/{$tipo->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($tipo->fresh()->active);
        $this->assertDatabaseHas('activity_log', ['subject_type' => PropertyType::class, 'subject_id' => $tipo->id]);
    }
```

Nota de setup: o CRUD fica sob o grupo `auth:gestao` — seguir exatamente o setup de autenticação/guard do `ViabilityServiceTypeCrudTest` (inclusive seed de roles se ele o fizer).

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Risco/PropertyTypeCrudTest.php`
Expected: FAIL — rota `/gestao/tipos-imovel` não existe (404/403 genérico).

- [ ] **Step 3: Implementar permissão, rotas, requests e controller**

`app/Support/PermissionCatalog.php` — adicionar após `manter-tipos-servico`:

```php
            [
                'name' => 'manter-tipos-imovel',
                'label' => 'Cadastrar tipos de imóvel',
                'description' => 'Cria e edita os tipos de imóvel que dirigem a regra de roteamento do expresso.',
                'group' => 'Atendimento',
            ],
```

`RolesAndPermissionsSeeder.php`: adicionar `'manter-tipos-imovel'` ao array `$permissions` e aos `givePermissionTo` dos papéis que hoje recebem `manter-tipos-servico` (admin e gestor). Ajustar `RolesAndPermissionsSeederTest` (contagem 30→31; admin e gestor têm a permissão; analista NÃO).

`routes/gestao.php` — após o bloco `tipos-servico`:

```php
        // Tipos de imóvel reconhecidos do REGIN: drives_rule derruba o processo
        // do expresso para análise — CRUD auditado atrás de permissão própria.
        Route::middleware('permission:manter-tipos-imovel')->prefix('tipos-imovel')->name('tipos-imovel.')->group(function () {
            Route::get('/', [PropertyTypeController::class, 'index'])->name('index');
            Route::post('/', [PropertyTypeController::class, 'store'])->name('store');
            Route::put('{propertyType}', [PropertyTypeController::class, 'update'])->name('update');
            Route::put('{propertyType}/ativacao', [PropertyTypeController::class, 'toggleActivation'])->name('ativacao.update');
        });
```

`StorePropertyTypeRequest`:

```php
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'aliases' => array_values(array_filter(array_map(
                fn ($a) => trim((string) $a),
                (array) $this->input('aliases', []),
            ))),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:property_types,code'],
            'label' => ['required', 'string', 'max:255'],
            'drives_rule' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'aliases' => ['present', 'array'],
            // distinct pós-normalização é garantido pelo unique do banco; aqui
            // barra grafia já usada por OUTRO tipo com mensagem clara.
            'aliases.*' => ['string', 'max:255', new \App\Rules\PropertyTypeAliasAvailable],
        ];
    }
```

`App\Rules\PropertyTypeAliasAvailable` (`php artisan make:rule PropertyTypeAliasAvailable --no-interaction`): normaliza o valor com `TipoImovel::normalize()` e falha se já existe `property_type_aliases.alias` igual (no update, ignorando os aliases do próprio tipo — receber `?int $ignoreTypeId` no construtor; o UpdateRequest passa o id da rota).

`UpdatePropertyTypeRequest`: mesmas regras SEM `code` (imutável) e com `PropertyTypeAliasAvailable($this->route('propertyType')->id)`.

`PropertyTypeController` — espelho de `ViabilityServiceTypeController` com estas diferenças exatas:
- `SORTABLE_COLUMNS = ['code', 'label']`; listagem com `with('aliases')` e `through()` expondo `id`, `code`, `label`, `drives_rule`, `active`, `aliases` (lista de strings).
- `store`/`update` envolvem tipo + sync de aliases numa `DB::transaction`: apagar aliases ausentes do payload, `firstOrCreate` os presentes.
- `toggleActivation` igual ao de referência (nunca exclui).
- `Inertia::render('gestao/tipos-imovel/index', ...)`.

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Risco/PropertyTypeCrudTest.php tests/Feature/Authorization/RolesAndPermissionsSeederTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/PermissionCatalog.php database/seeders/RolesAndPermissionsSeeder.php app/Http/Controllers/Gestao/PropertyTypeController.php app/Http/Requests/Gestao/*PropertyType*.php app/Rules/PropertyTypeAliasAvailable.php routes/gestao.php tests/
git commit -m "feat: adiciona CRUD de tipos de imóvel na gestão"
```

---

### Task 4: Tela `gestao/tipos-imovel` + item de menu

**Files:**
- Create: `resources/js/pages/gestao/tipos-imovel/index.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx` (grupo "Regras do licenciamento", após "Tipos de serviço", linha ~101)

**Interfaces:**
- Consumes: rotas e props da Task 3 (`propertyTypes` paginado, `filters`, `perPageOptions`).
- Produces: tela de manutenção no padrão de listagem da gestão.

- [ ] **Step 1: Criar a página espelhando `resources/js/pages/gestao/tipos-servico/index.tsx`**

Mesma estrutura (PageHeader, Card com ação primária "Novo tipo de imóvel", DataTable, TableToolbar com busca, Pagination, Modal de criar/editar com `<Form>`, confirm-dialog no toggle). Diferenças:
- Colunas: `code`, `label`, **Dirige regra** (Badge "Dirige regra" / "Ramo comum"), **Aliases** (chips/badges secundários), situação.
- Campos do modal: `code` (desabilitado na edição), `label`, `drives_rule` (switch/checkbox), `active`, `aliases` (textarea "uma grafia por linha" → split client-side em array antes do submit).
- **Confirmação explícita ao marcar `drives_rule`**: texto "Processos com este tipo de imóvel passam a ir para análise técnica (gatilho dados_do_processo). Confirmar?" — impacto de roteamento (grupo G da auditoria).
- Gate de UI: `auth.permissions.includes('manter-tipos-imovel')` (mesmo padrão da página de referência, linha 203).

- [ ] **Step 2: Adicionar o item de menu**

Em `gestao-layout.tsx`, grupo "Regras do licenciamento", após "Tipos de serviço":

```tsx
                {
                    name: 'Tipos de imóvel',
                    href: '/gestao/tipos-imovel',
                    icon: <TagIcon />,
                    visible: auth.permissions.includes('manter-tipos-imovel'),
                },
```

- [ ] **Step 3: Verificar o build**

Run: `npm run build`
Expected: build sem erros de tipo/compilação. (Deploy exige `public/build` commitado — ver regra docker-publish; commitar `public/build` junto se o fluxo de release estiver em curso, caso contrário deixar para o release.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/gestao/tipos-imovel/index.tsx resources/js/layouts/gestao-layout.tsx
git commit -m "feat: adiciona tela de manutenção de tipos de imóvel"
```

---

### Task 5: Verificação final

- [ ] **Step 1: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sem pendências de estilo nos arquivos tocados.

- [ ] **Step 2: Suíte completa**

Run: `php artisan test --compact`
Expected: PASS — suíte inteira verde, incluindo os golden cases LOUOS e os testes REGIN.

- [ ] **Step 3: Prova do critério de pronto (sem deploy)**

Com a aplicação rodando, pela interface: criar "Galpão logístico" com `drives_rule=true` → `TipoImovel::fromRegin('Galpão logístico', TipoImovelCatalog::vigente())` resolve `DirigeRegra` imediatamente (já coberto pelo teste `test_escrita_no_cadastro_invalida_o_cache` da Task 2 — não criar script de verificação adicional).

- [ ] **Step 4: Atualizar o backlog**

Marcar a Fase 1 como concluída em `docs/superpowers/plans/2026-09-17-parametrizacao-matrizes-auditoria.md` e registrar a entrega no `.planning/STATE.md` (padrão do projeto).

---

## Self-Review

- **Cobertura da spec (Fase 1 do backlog):** 1.1 migrations/models → Task 1; 1.2 seed fiel → Task 1 (teste prova igualdade com o catálogo embutido); 1.3 catálogo do banco + cache + call sites → Task 2; 1.4 CRUD auditado + confirmação de `drives_rule` → Tasks 3 e 4; 1.5 golden tests → Task 2 (`test_catalogo_semeado_resolve_como_o_embutido`) + suíte existente verde na Task 5.
- **Placeholders:** o setup exato de autenticação do CRUD test referencia o teste irmão (deliberado — copiar 60 linhas de setup criaria divergência; o teste irmão é a fonte).
- **Consistência de tipos:** `PropertyType::CACHE_KEY` usado por models (Task 1) e catálogo (Task 2); `aliases` como array de strings no payload (Tasks 3-4); nomes de rota `gestao.tipos-imovel.*` iguais em controller, testes e TSX.
