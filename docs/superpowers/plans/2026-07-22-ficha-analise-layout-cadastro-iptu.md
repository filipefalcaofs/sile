# Ficha de análise — layout legado + Cadastro IPTU — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganizar o topo da ficha de análise no padrão SAPS (Localização | Polígono + Dados do TVL) e preencher o espaço em branco com o bloco só leitura Cadastro Imobiliário (IPTU), com degradação honesta quando o lookup estiver indisponível.

**Architecture:** Prop Inertia `cadastroImobiliario` montada por um serviço de apresentação que consulta `PropertyRegistryLookup` sem inventar dados. O DTO `PropertyCadastroCampos` tipa os campos da certidão; `PropertyRegistryResult` ganha `?cadastro` opcional. A UI em `gestao/ficha-analise/show` espelha o layout legado; o provider real continua `UnavailablePropertyRegistryLookup` (HU-106 fora do ciclo).

**Tech Stack:** Laravel 13, PHPUnit, Inertia v3 + React 19, Tailwind 4, contrato `App\Services\Realty\PropertyRegistryLookup`.

**Spec:** `docs/superpowers/specs/2026-07-22-ficha-analise-layout-cadastro-iptu-design.md`

## Global Constraints

- Sem fachada: unavailable / não encontrado / sem inscrição ⇒ aviso + campos `null`/`"—"`; nunca inventar certidão.
- Bloco IPTU somente leitura.
- Não bloquear abertura/autosave/finalizar da ficha por falha do Cadastro.
- Auditoria RN-002: evento de consulta com `viability_request_id`, `inscricao`, `status` (sem CPF/valor venal no log).
- TDD estrito; `vendor/bin/pint --dirty --format agent` após PHP.
- UI/mensagens em pt-BR; código em inglês.
- Provider real SEFAZ fora do escopo — só contrato + UI + fakes de teste.

---

## File map

| Arquivo | Responsabilidade |
|---|---|
| `app/Services/Realty/PropertyCadastroCampos.php` | DTO readonly dos campos da certidão IPTU + `toArray()` / `vazios()` |
| `app/Services/Realty/PropertyRegistryResult.php` | Inclui `?PropertyCadastroCampos $cadastro` e espelha em `toArray()` |
| `app/Services/Analise/CadastroImobiliarioFichaService.php` | Monta prop `cadastroImobiliario` a partir do processo + lookup |
| `app/Http/Controllers/Gestao/AnalysisRecordController.php` | Expõe `cadastroImobiliario`, `dadosTvl` e `localizacao` estruturada |
| `resources/js/pages/gestao/ficha-analise/show.tsx` | Layout legado + bloco IPTU |
| `tests/Unit/Realty/PropertyCadastroCamposTest.php` | Contrato do DTO |
| `tests/Feature/Analise/CadastroImobiliarioFichaServiceTest.php` | Três estados + mapeamento |
| `tests/Feature/Analise/FichaCadastroImobiliarioInertiaTest.php` | Props Inertia + auditoria |
| `tests/Feature/Analise/FichaUiSmokeTest.php` | Estender smoke (localizacao estruturada + props novas) |
| `tests/Feature/Viabilidade/PropertyRegistryLookupTest.php` | Ajustar fake/`toArray` se shape mudar |

---

### Task 1: DTO `PropertyCadastroCampos` + extensão de `PropertyRegistryResult`

**Files:**
- Create: `app/Services/Realty/PropertyCadastroCampos.php`
- Modify: `app/Services/Realty/PropertyRegistryResult.php`
- Test: `tests/Unit/Realty/PropertyCadastroCamposTest.php`
- Modify: `tests/Feature/Viabilidade/PropertyRegistryLookupTest.php` (asserção `toArray` inclui `cadastro`)

**Interfaces:**
- Consumes: nada novo
- Produces: `PropertyCadastroCampos::vazios(): self`, `toArray(): array`, `PropertyRegistryResult(..., ?PropertyCadastroCampos $cadastro = null)`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit\Realty;

use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryResult;
use PHPUnit\Framework\TestCase;

class PropertyCadastroCamposTest extends TestCase
{
    public function test_vazios_produz_todos_os_campos_null(): void
    {
        $campos = PropertyCadastroCampos::vazios()->toArray();

        $this->assertNull($campos['inscricao']);
        $this->assertNull($campos['endereco']);
        $this->assertNull($campos['numero_metrico']);
        $this->assertNull($campos['loteamento']);
        $this->assertNull($campos['quadra']);
        $this->assertNull($campos['lote']);
        $this->assertNull($campos['conjunto_edificio']);
        $this->assertNull($campos['bloco']);
        $this->assertNull($campos['sub_unidade']);
        $this->assertNull($campos['numero_sub_unidade']);
        $this->assertNull($campos['bairro']);
        $this->assertNull($campos['cep']);
        $this->assertNull($campos['area_construida_m2']);
        $this->assertNull($campos['tipo_imovel']);
        $this->assertNull($campos['data_lancamento']);
        $this->assertNull($campos['situacao_cadastral']);
        $this->assertNull($campos['contribuinte']);
        $this->assertNull($campos['cpf_cnpj']);
        $this->assertNull($campos['numero_porta']);
        $this->assertNull($campos['area_terreno_m2']);
        $this->assertNull($campos['valor_venal_iptu']);
        $this->assertNull($campos['logradouro_tributario']);
        $this->assertNull($campos['situacao_fiscal']);
        $this->assertNull($campos['data_emissao_certidao']);
    }

    public function test_property_registry_result_inclui_cadastro_no_to_array(): void
    {
        $cadastro = new PropertyCadastroCampos(
            inscricao: '379387-7',
            endereco: 'Rua Martiniano Bonfim',
            numero_metrico: '224',
            loteamento: null,
            quadra: '0181',
            lote: '0043',
            conjunto_edificio: null,
            bloco: null,
            sub_unidade: 'GL - Galpão',
            numero_sub_unidade: null,
            bairro: 'CABULA',
            cep: null,
            area_construida_m2: '0,00',
            tipo_imovel: 'Residencial Horizontal',
            data_lancamento: '01/01/1986',
            situacao_cadastral: 'Ativo',
            contribuinte: 'ANTONIO COSTA NETO',
            cpf_cnpj: '035.385.595-20',
            numero_porta: '000224',
            area_terreno_m2: '704,00',
            valor_venal_iptu: 'R$ 769.120,00',
            logradouro_tributario: '3704 - Rua Martiniano Bonfim',
            situacao_fiscal: 'Contribuinte',
            data_emissao_certidao: '01/12/2023 10:22:28',
        );

        $result = new PropertyRegistryResult(
            latitude: -12.97,
            longitude: -38.50,
            inscricao: '379387-7',
            source: 'fake',
            raw: [],
            cadastro: $cadastro,
        );

        $this->assertSame('379387-7', $result->toArray()['cadastro']['inscricao']);
        $this->assertSame('0181', $result->toArray()['cadastro']['quadra']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Realty/PropertyCadastroCamposTest.php`

Expected: FAIL — class `PropertyCadastroCampos` not found.

- [ ] **Step 3: Implement DTO + extend result**

`app/Services/Realty/PropertyCadastroCampos.php`:

```php
<?php

namespace App\Services\Realty;

/** Campos tipados da certidão / Cadastro Imobiliário (IPTU) — só leitura na ficha. */
final readonly class PropertyCadastroCampos
{
    public function __construct(
        public ?string $inscricao,
        public ?string $endereco,
        public ?string $numero_metrico,
        public ?string $loteamento,
        public ?string $quadra,
        public ?string $lote,
        public ?string $conjunto_edificio,
        public ?string $bloco,
        public ?string $sub_unidade,
        public ?string $numero_sub_unidade,
        public ?string $bairro,
        public ?string $cep,
        public ?string $area_construida_m2,
        public ?string $tipo_imovel,
        public ?string $data_lancamento,
        public ?string $situacao_cadastral,
        public ?string $contribuinte,
        public ?string $cpf_cnpj,
        public ?string $numero_porta,
        public ?string $area_terreno_m2,
        public ?string $valor_venal_iptu,
        public ?string $logradouro_tributario,
        public ?string $situacao_fiscal,
        public ?string $data_emissao_certidao,
    ) {}

    public static function vazios(): self
    {
        return new self(
            inscricao: null,
            endereco: null,
            numero_metrico: null,
            loteamento: null,
            quadra: null,
            lote: null,
            conjunto_edificio: null,
            bloco: null,
            sub_unidade: null,
            numero_sub_unidade: null,
            bairro: null,
            cep: null,
            area_construida_m2: null,
            tipo_imovel: null,
            data_lancamento: null,
            situacao_cadastral: null,
            contribuinte: null,
            cpf_cnpj: null,
            numero_porta: null,
            area_terreno_m2: null,
            valor_venal_iptu: null,
            logradouro_tributario: null,
            situacao_fiscal: null,
            data_emissao_certidao: null,
        );
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'inscricao' => $this->inscricao,
            'endereco' => $this->endereco,
            'numero_metrico' => $this->numero_metrico,
            'loteamento' => $this->loteamento,
            'quadra' => $this->quadra,
            'lote' => $this->lote,
            'conjunto_edificio' => $this->conjunto_edificio,
            'bloco' => $this->bloco,
            'sub_unidade' => $this->sub_unidade,
            'numero_sub_unidade' => $this->numero_sub_unidade,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'area_construida_m2' => $this->area_construida_m2,
            'tipo_imovel' => $this->tipo_imovel,
            'data_lancamento' => $this->data_lancamento,
            'situacao_cadastral' => $this->situacao_cadastral,
            'contribuinte' => $this->contribuinte,
            'cpf_cnpj' => $this->cpf_cnpj,
            'numero_porta' => $this->numero_porta,
            'area_terreno_m2' => $this->area_terreno_m2,
            'valor_venal_iptu' => $this->valor_venal_iptu,
            'logradouro_tributario' => $this->logradouro_tributario,
            'situacao_fiscal' => $this->situacao_fiscal,
            'data_emissao_certidao' => $this->data_emissao_certidao,
        ];
    }
}
```

Em `PropertyRegistryResult`, adicionar parâmetro opcional e chave `cadastro` em `toArray()`:

```php
public function __construct(
    public float $latitude,
    public float $longitude,
    public string $inscricao,
    public ?string $source = null,
    public array $raw = [],
    public ?PropertyCadastroCampos $cadastro = null,
) {}

public function toArray(): array
{
    return [
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'inscricao' => $this->inscricao,
        'source' => $this->source,
        'raw' => $this->raw,
        'cadastro' => $this->cadastro?->toArray(),
    ];
}
```

Atualizar `PropertyRegistryLookupTest::test_contrato_resolve_inscricao_em_coordenada_com_provider_disponivel` para esperar `'cadastro' => null` no `toArray()`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Realty/PropertyCadastroCamposTest.php tests/Feature/Viabilidade/PropertyRegistryLookupTest.php`

Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Realty/PropertyCadastroCampos.php app/Services/Realty/PropertyRegistryResult.php tests/Unit/Realty/PropertyCadastroCamposTest.php tests/Feature/Viabilidade/PropertyRegistryLookupTest.php
git commit -m "feat: tipa campos do cadastro imobiliário no PropertyRegistryResult"
```

---

### Task 2: `CadastroImobiliarioFichaService` (três estados)

**Files:**
- Create: `app/Services/Analise/CadastroImobiliarioFichaService.php`
- Test: `tests/Feature/Analise/CadastroImobiliarioFichaServiceTest.php`

**Interfaces:**
- Consumes: `PropertyRegistryLookup::resolve`, `PropertyCadastroCampos`, `PropertyRegistryUnavailableException`, `PropertyNotFoundException`
- Produces: `CadastroImobiliarioFichaService::para(ViabilityRequest $request): array` com shape:

```php
[
  'status' => 'disponivel'|'indisponivel'|'nao_encontrado'|'sem_inscricao',
  'mensagem' => ?string,
  'inscricao' => ?string,
  'campos' => array, // PropertyCadastroCampos::toArray()
  'consultado_em' => ?string, // ISO8601 ou null
  'source' => ?string,
]
```

- [ ] **Step 1: Write the failing feature tests**

```php
<?php

namespace Tests\Feature\Analise;

use App\Models\ViabilityRequest;
use App\Services\Analise\CadastroImobiliarioFichaService;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadastroImobiliarioFichaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_inscricao_nao_chama_lookup(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new \RuntimeException('lookup não deveria ser chamado');
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => null]);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('sem_inscricao', $payload['status']);
        $this->assertStringContainsString('não informada', (string) $payload['mensagem']);
        $this->assertNull($payload['campos']['quadra']);
    }

    public function test_lookup_indisponivel_degrada_com_aviso(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyRegistryUnavailableException($inscricao);
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '379387-7']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('indisponivel', $payload['status']);
        $this->assertStringContainsString('indisponível', (string) $payload['mensagem']);
        $this->assertSame('379387-7', $payload['inscricao']);
        $this->assertNull($payload['campos']['contribuinte']);
    }

    public function test_lookup_nao_encontrado(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyNotFoundException($inscricao);
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '000']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('nao_encontrado', $payload['status']);
    }

    public function test_lookup_disponivel_mapeia_campos_do_cadastro(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'fake-cadastro',
                    raw: [],
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Martiniano Bonfim',
                        numero_metrico: '224',
                        loteamento: null,
                        quadra: '0181',
                        lote: '0043',
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: 'GL - Galpão',
                        numero_sub_unidade: null,
                        bairro: 'CABULA',
                        cep: null,
                        area_construida_m2: '0,00',
                        tipo_imovel: 'Residencial Horizontal',
                        data_lancamento: '01/01/1986',
                        situacao_cadastral: 'Ativo',
                        contribuinte: 'ANTONIO COSTA NETO',
                        cpf_cnpj: '035.385.595-20',
                        numero_porta: '000224',
                        area_terreno_m2: '704,00',
                        valor_venal_iptu: 'R$ 769.120,00',
                        logradouro_tributario: '3704 - Rua Martiniano Bonfim',
                        situacao_fiscal: 'Contribuinte',
                        data_emissao_certidao: '01/12/2023 10:22:28',
                    ),
                );
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '379387-7']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('disponivel', $payload['status']);
        $this->assertSame('0181', $payload['campos']['quadra']);
        $this->assertSame('fake-cadastro', $payload['source']);
        $this->assertNotNull($payload['consultado_em']);
    }
}
```

- [ ] **Step 2: Run to verify RED**

Run: `php artisan test --compact tests/Feature/Analise/CadastroImobiliarioFichaServiceTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement service**

```php
<?php

namespace App\Services\Analise;

use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryUnavailableException;

class CadastroImobiliarioFichaService
{
    public function __construct(private PropertyRegistryLookup $lookup) {}

    /**
     * @return array{
     *   status: string,
     *   mensagem: string|null,
     *   inscricao: string|null,
     *   campos: array<string, string|null>,
     *   consultado_em: string|null,
     *   source: string|null
     * }
     */
    public function para(ViabilityRequest $request): array
    {
        $inscricao = trim((string) ($request->property_registration ?? ''));

        if ($inscricao === '') {
            return $this->payload(
                status: 'sem_inscricao',
                mensagem: 'Inscrição imobiliária não informada no processo.',
                inscricao: null,
            );
        }

        try {
            $result = $this->lookup->resolve($inscricao);
        } catch (PropertyRegistryUnavailableException) {
            return $this->payload(
                status: 'indisponivel',
                mensagem: 'Cadastro Imobiliário indisponível — pendente SEDUR/SEFAZ.',
                inscricao: $inscricao,
            );
        } catch (PropertyNotFoundException) {
            return $this->payload(
                status: 'nao_encontrado',
                mensagem: 'Inscrição não encontrada no Cadastro Imobiliário.',
                inscricao: $inscricao,
            );
        }

        $campos = $result->cadastro ?? PropertyCadastroCampos::vazios();

        // Se o provider devolveu coordenada sem campos tipados, ainda é "disponivel"
        // geograficamente, mas a certidão fica vazia (honesto — não inventa IPTU).
        return [
            'status' => 'disponivel',
            'mensagem' => null,
            'inscricao' => $inscricao,
            'campos' => $campos->toArray(),
            'consultado_em' => now()->toIso8601String(),
            'source' => $result->source,
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   mensagem: string|null,
     *   inscricao: string|null,
     *   campos: array<string, string|null>,
     *   consultado_em: null,
     *   source: null
     * }
     */
    private function payload(string $status, string $mensagem, ?string $inscricao): array
    {
        return [
            'status' => $status,
            'mensagem' => $mensagem,
            'inscricao' => $inscricao,
            'campos' => PropertyCadastroCampos::vazios()->toArray(),
            'consultado_em' => null,
            'source' => null,
        ];
    }
}
```

- [ ] **Step 4: Run GREEN**

Run: `php artisan test --compact tests/Feature/Analise/CadastroImobiliarioFichaServiceTest.php`

Expected: PASS (4 testes)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/CadastroImobiliarioFichaService.php tests/Feature/Analise/CadastroImobiliarioFichaServiceTest.php
git commit -m "feat: monta prop cadastro imobiliário da ficha com degradação honesta"
```

---

### Task 3: Controller — `localizacao` estruturada, `dadosTvl`, `cadastroImobiliario` + auditoria

**Files:**
- Modify: `app/Http/Controllers/Gestao/AnalysisRecordController.php`
- Create: `tests/Feature/Analise/FichaCadastroImobiliarioInertiaTest.php`
- Modify: `tests/Feature/Analise/FichaUiSmokeTest.php`

**Interfaces:**
- Consumes: `CadastroImobiliarioFichaService::para`
- Produces: props Inertia `cadastroImobiliario`, `dadosTvl`, `localizacao` expandida

Shape `localizacao` (compatível com smoke atual — manter `endereco` e `poligono`):

```php
[
  'poligono' => ?array,
  'endereco' => ?string, // legado do smoke
  'cod_log' => null, // modelo ainda não tem — null honesto
  'logradouro' => ?string,
  'numero_metrico' => ?string,
  'bairro' => ?string,
  'cep' => ?string,
  'ponto_referencia' => ?string,
  'zona' => null, // pendente SEDUR
  'via' => null,
]
```

Shape `dadosTvl`:

```php
[
  'razao_social' => ?string, // company.legal_name
  'sede_escritorio_virtual' => bool, // record.is_virtual_office_hq || request flags
  'porte' => ?string, // company.size
  'tipo_imovel' => null, // sem coluna — null
  'categoria_empresa' => ?string, // company.legal_nature ou null
  'torre_bloco_ala' => null,
  'complemento' => ?string, // address_complement
]
```

- [ ] **Step 1: Write failing Inertia tests**

Arquivo `tests/Feature/Analise/FichaCadastroImobiliarioInertiaTest.php`:

- Com binding unavailable (default): `cadastroImobiliario.status === 'indisponivel'` quando há inscrição; `has('dadosTvl')`; `localizacao.logradouro` preenchido.
- Sem inscrição: `status === 'sem_inscricao'`.
- Com fake disponivel: `status === 'disponivel'` e `campos.quadra === '0181'`.
- Assert activity log name `analise` event `ficha-cadastro-consulta` com properties sem `cpf_cnpj` / `valor_venal_iptu`.

Estender `FichaUiSmokeTest::test_ficha_renderiza...` com:

```php
->has('cadastroImobiliario')
->has('dadosTvl')
->where('localizacao.logradouro', 'Rua das Flores')
```

- [ ] **Step 2: Run RED**

Run: `php artisan test --compact --filter=FichaCadastroImobiliarioInertiaTest`

Expected: FAIL (props ausentes).

- [ ] **Step 3: Wire controller**

No construtor, injetar `CadastroImobiliarioFichaService`.

No `show`, após montar a ficha:

```php
$cadastro = $this->cadastroImobiliario->para($viabilityRequest);

$this->audit->log(
    logName: 'analise',
    event: 'ficha-cadastro-consulta',
    description: "Consulta ao Cadastro Imobiliário na ficha do processo #{$viabilityRequest->id}",
    properties: [
        'viability_request_id' => $viabilityRequest->id,
        'inscricao' => $cadastro['inscricao'],
        'status' => $cadastro['status'],
    ],
    subject: $viabilityRequest,
);

return Inertia::render('gestao/ficha-analise/show', [
    // ...existente...
    'localizacao' => $this->localizacaoDoImovel($viabilityRequest),
    'dadosTvl' => $this->dadosTvl($viabilityRequest, $record),
    'cadastroImobiliario' => $cadastro,
]);
```

Expandir `localizacaoDoImovel` e adicionar `dadosTvl` privado (loadMissing `company`).

- [ ] **Step 4: Run GREEN**

Run: `php artisan test --compact --filter='FichaCadastroImobiliarioInertiaTest|FichaUiSmokeTest'`

Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/AnalysisRecordController.php tests/Feature/Analise/FichaCadastroImobiliarioInertiaTest.php tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "feat: expõe cadastro IPTU e dados do TVL na ficha de análise"
```

---

### Task 4: UI — layout legado + bloco Cadastro IPTU

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`

**Interfaces:**
- Consumes: props `localizacao` (estruturada), `dadosTvl`, `cadastroImobiliario` do Task 3
- Produces: topo visual Localização | Polígono + faixa Dados do TVL + bloco IPTU no branco

- [ ] **Step 1: Estender smoke/tipos (já parcialmente no Task 3) — assert textual opcional**

Se houver teste de conteúdo HTML/Inertia props suficientes no Task 3, nesta task foque na UI. Adicione no Inertia test (se ainda não):

```php
->where('cadastroImobiliario.mensagem', fn ($m) => is_string($m) || $m === null)
```

- [ ] **Step 2: Atualizar tipos TypeScript no `show.tsx`**

```tsx
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
}

interface DadosTvl {
    razao_social: string | null;
    sede_escritorio_virtual: boolean;
    porte: string | null;
    tipo_imovel: string | null;
    categoria_empresa: string | null;
    torre_bloco_ala: string | null;
    complemento: string | null;
}

interface CadastroImobiliario {
    status: 'disponivel' | 'indisponivel' | 'nao_encontrado' | 'sem_inscricao';
    mensagem: string | null;
    inscricao: string | null;
    campos: Record<string, string | null>;
    consultado_em: string | null;
    source: string | null;
}
```

Incluir nas props do page component.

- [ ] **Step 3: Implementar componentes de seção no mesmo arquivo (ou extrair helpers locais)**

Ordem no JSX (antes do grid atual de enquadramento CNAE / coluna lateral):

1. Card grade 2 colunas:
   - **Localização:** campos readonly em grid (CodLog, Logradouro, Nº Métrico, Bairro, CEP, Ponto de Referência) + abaixo/ao lado o **CadastroImobiliarioPanel** (campos marcados com `bg-warning-50` / `ring-warning-200` + `aria`-rótulo “destacado”; demais campos; banner se `status !== 'disponivel'`).
   - **Polígono:** reutilizar `MapaSection` atual; Zona/Via; texto pendência SEDUR se null.

2. Card **Dados do TVL** full width com os campos do legado (valores `dadosTvl`; “—” se null; sede EV: Sim/Não a partir do bool — se a ficha já edita sede EV, manter o controle existente neste bloco ou logo abaixo, sem duplicar lógica conflitante).

3. Remover o card lateral antigo que só mostrava mapa+endereço (evitar duplicar mapa). Manter precedentes/ações na coluna lateral ou abaixo conforme o restante da página.

Helper de exibição:

```tsx
function valorOuTraco(v: string | null | undefined): string {
    return v && v.trim() !== '' ? v : '—';
}
```

Lista de chaves marcadas (destaque):

```tsx
const CAMPOS_IPTU_MARCADOS = [
  'inscricao', 'endereco', 'numero_metrico', 'loteamento', 'quadra', 'lote',
  'conjunto_edificio', 'bloco', 'sub_unidade', 'numero_sub_unidade',
  'bairro', 'cep', 'area_construida_m2', 'tipo_imovel', 'data_lancamento',
  'situacao_cadastral',
] as const;
```

Labels pt-BR para cada chave (mapa estático no arquivo).

- [ ] **Step 4: Verificar manualmente / smoke**

Run: `php artisan test --compact --filter='FichaUiSmokeTest|FichaCadastroImobiliarioInertiaTest|CadastroImobiliarioFichaServiceTest|PropertyCadastroCamposTest'`

Expected: PASS

Se TypeScript local: `npx tsc --noEmit` (ou o script do projeto) — sem erros novos na página.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "feat: alinha ficha ao layout legado e exibe cadastro IPTU"
```

---

### Task 5: Verificação final + atualizar status da spec

**Files:**
- Modify: `docs/superpowers/specs/2026-07-22-ficha-analise-layout-cadastro-iptu-design.md` (status → implementado)

- [ ] **Step 1: Rodar suíte relacionada**

```bash
php artisan test --compact --filter='PropertyCadastroCamposTest|PropertyRegistryLookupTest|CadastroImobiliarioFichaServiceTest|FichaCadastroImobiliarioInertiaTest|FichaUiSmokeTest|FichaEvPainelTest|FichaSedeFlagTest'
```

Expected: todos PASS.

- [ ] **Step 2: Atualizar status da spec**

Trocar linha Status para: `Implementado (plano 2026-07-22-ficha-analise-layout-cadastro-iptu)`.

- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/specs/2026-07-22-ficha-analise-layout-cadastro-iptu-design.md
git commit -m "docs: marca spec ficha IPTU como implementada"
```

---

## Spec coverage checklist

| Spec | Task |
|---|---|
| Layout Localização \| Polígono + Dados TVL | Task 4 |
| Bloco IPTU no branco (campos 4.1 + 4.2) | Task 4 |
| Prop `cadastroImobiliario` + statuses | Tasks 2–3 |
| Extensão DTO/contrato | Task 1 |
| Degradação unavailable / sem inscrição / não encontrado | Task 2 |
| Auditoria sem dado sensível no log | Task 3 |
| CA-01…CA-06 | Tasks 3–4 |
| Provider real fora | (nenhuma task cria provider SEFAZ) |

## Self-review notes

- Sem placeholders TBD.
- Nomes alinhados: `CadastroImobiliarioFichaService::para`, statuses snake pt no valor (`sem_inscricao`).
- `localizacao.endereco` preservado para não quebrar smoke antigo.
- `tipo_imovel` / `cod_log` / `torre` podem ser null no modelo atual — UI mostra “—” (honesto).
