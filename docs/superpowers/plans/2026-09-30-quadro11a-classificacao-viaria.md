# Quadro 11A no motor — classificação viária real

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fazer o Quadro 11A decidir de verdade no motor, alimentando a classe viária da LOUOS a partir da camada oficial do GeoServer SEDUR.

**Architecture:** Um cliente WFS novo consulta `louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` por `CODLOG` e devolve a classe bruta. Um normalizador converte a grafia do GIS (`VC-I`, `VC - II`) na grafia da lei (`VC I`, `VC II`). O `TerritoryService` acrescenta o resultado como `CLASSE_VIA_LOUOS` nas propriedades da via — chave que `LouosEnquadramentoService` já lê hoje, então o motor não muda.

**Tech Stack:** Laravel 13, PHPUnit, `Illuminate\Support\Facades\Http` (com `Http::fake` nos testes), PostgreSQL/SQLite.

## Global Constraints

- TDD estrito: teste falhando antes de qualquer código de produção.
- Sem fachada: nenhum caminho pode produzir classe inventada; ausência do dado é `indisponivel` com motivo.
- Toda consulta externa é auditada (RN-002) via `App\Support\Audit\AuditService`.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Textos de motivo entram em `DecisionTextCatalog::defaults()` e em `DecisionTextSeeder::CHAVES`.
- Constantes técnicas em `config/viabiliza.php` (precedente [02-02]); interruptor de fluxo no catálogo `ApiIntegracaoCatalog`.
- Classes oficiais, grafia exata: `VA I`, `VA II`, `VC I`, `VC II`, `VE`, `VL`, `VP`.
- pt-BR em comentários, mensagens e nomes de teste; código em inglês.

---

### Task 1: Normalizador da classe viária

**Files:**
- Create: `app/Support/Louos/Quadro11aClasseVia.php`
- Test: `tests/Unit/Louos/Quadro11aClasseViaTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: `Quadro11aClasseVia::oficializar(?string $bruta): ?string` e `Quadro11aClasseVia::oficiais(): list<string>`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Louos;

use App\Support\Louos\Quadro11aClasseVia;
use PHPUnit\Framework\TestCase;

class Quadro11aClasseViaTest extends TestCase
{
    public function test_oficiais_sao_as_sete_classes_da_louos(): void
    {
        $this->assertSame(
            ['VA I', 'VA II', 'VC I', 'VC II', 'VE', 'VL', 'VP'],
            Quadro11aClasseVia::oficiais(),
        );
    }

    public function test_grafia_do_gis_vira_grafia_da_lei(): void
    {
        $this->assertSame('VC I', Quadro11aClasseVia::oficializar('VC-I'));
        $this->assertSame('VA II', Quadro11aClasseVia::oficializar('VA-II'));
        $this->assertSame('VC II', Quadro11aClasseVia::oficializar('VC - II'));
        $this->assertSame('VL', Quadro11aClasseVia::oficializar('vl'));
        $this->assertSame('VE', Quadro11aClasseVia::oficializar('  VE  '));
    }

    public function test_classe_fora_da_lei_nao_e_promovida(): void
    {
        $this->assertNull(Quadro11aClasseVia::oficializar('VX-9'));
        $this->assertNull(Quadro11aClasseVia::oficializar(''));
        $this->assertNull(Quadro11aClasseVia::oficializar(null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Louos/Quadro11aClasseViaTest.php`
Expected: FAIL com `Class "App\Support\Louos\Quadro11aClasseVia" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Support\Louos;

/**
 * Grafia oficial das 7 classes de via do Quadro 11A (Lei nº 9.148/2016).
 * O GeoServer publica com hífen e espaçamento irregular (VC-I, VC - II); a
 * lei e o cadastro usam espaço simples (VC I, VC II). Classe fora da lista
 * NÃO é promovida a classe da lei — devolve null e o quadro degrada.
 */
final class Quadro11aClasseVia
{
    /** @var list<string> */
    private const OFICIAIS = ['VA I', 'VA II', 'VC I', 'VC II', 'VE', 'VL', 'VP'];

    /**
     * @return list<string>
     */
    public static function oficiais(): array
    {
        return self::OFICIAIS;
    }

    public static function oficializar(?string $bruta): ?string
    {
        if ($bruta === null) {
            return null;
        }

        $chave = self::chave($bruta);

        if ($chave === '') {
            return null;
        }

        foreach (self::OFICIAIS as $oficial) {
            if (self::chave($oficial) === $chave) {
                return $oficial;
            }
        }

        return null;
    }

    /**
     * Compara sem hífen, sem espaço e sem caixa: "VC - II", "VC-II" e
     * "vc ii" resolvem para a mesma classe.
     */
    private static function chave(string $valor): string
    {
        return strtoupper((string) preg_replace('/[\s\-_]+/', '', trim($valor)));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Louos/Quadro11aClasseViaTest.php`
Expected: PASS, 3 testes.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Louos/Quadro11aClasseVia.php tests/Unit/Louos/Quadro11aClasseViaTest.php
git commit -m "feat: normaliza a classe viária da LOUOS para a grafia da lei"
```

---

### Task 2: Textos de motivo da classificação viária

**Files:**
- Modify: `app/Services/Decisao/DecisionTextCatalog.php` (bloco `defaults()`, junto de `louos.motivo.via_sem_atributo`)
- Modify: `database/seeders/DecisionTextSeeder.php` (mapa de chaves)
- Test: `tests/Feature/Louos/Quadro11aClasseViaTextosTest.php`

**Interfaces:**
- Consumes: `DecisionTextCatalog::get(string $key): string`.
- Produces: as chaves `louos.motivo.via_sem_codlog`, `louos.motivo.via_classe_desligada`, `louos.motivo.via_classe_indisponivel` e `louos.motivo.via_classe_desconhecida`, usadas nas Tasks 3 e 4.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Louos;

use App\Services\Decisao\DecisionTextCatalog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class Quadro11aClasseViaTextosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_motivos_da_classificacao_viaria_existem_no_catalogo(): void
    {
        $catalogo = app(DecisionTextCatalog::class);

        foreach ([
            'louos.motivo.via_sem_codlog',
            'louos.motivo.via_classe_desligada',
            'louos.motivo.via_classe_indisponivel',
            'louos.motivo.via_classe_desconhecida',
        ] as $chave) {
            $this->assertNotSame('', trim($catalogo->get($chave)), "Texto ausente: {$chave}");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Louos/Quadro11aClasseViaTextosTest.php`
Expected: FAIL com `InvalidArgumentException` na primeira chave desconhecida.

- [ ] **Step 3: Write minimal implementation**

Em `app/Services/Decisao/DecisionTextCatalog.php`, logo depois da linha
`'louos.motivo.via_sem_atributo' => ...`, acrescentar:

```php
            'louos.motivo.via_sem_codlog' => 'Via identificada sem o código do logradouro (CODLOG) para consultar a classificação viária da LOUOS',
            'louos.motivo.via_classe_desligada' => 'Consulta da classificação viária no GeoServer SEDUR desligada',
            'louos.motivo.via_classe_indisponivel' => 'Classificação viária indisponível no GeoServer SEDUR',
            'louos.motivo.via_classe_desconhecida' => 'Classificação viária fora das classes do Quadro 11A da Lei nº 9.148/2016',
```

Em `database/seeders/DecisionTextSeeder.php`, no mesmo mapa de descrições:

```php
        'louos.motivo.via_sem_codlog' => 'Motivo quando a via não tem CODLOG para consultar a classificação viária.',
        'louos.motivo.via_classe_desligada' => 'Motivo quando o interruptor da classificação viária está desligado.',
        'louos.motivo.via_classe_indisponivel' => 'Motivo quando o GeoServer não respondeu a classificação viária.',
        'louos.motivo.via_classe_desconhecida' => 'Motivo quando a classificação viária não é uma das 7 classes do Quadro 11A.',
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Louos/Quadro11aClasseViaTextosTest.php tests/Feature/Decisao/DecisionTextCrudTest.php`
Expected: PASS nos dois arquivos.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Decisao/DecisionTextCatalog.php database/seeders/DecisionTextSeeder.php tests/Feature/Louos/Quadro11aClasseViaTextosTest.php
git commit -m "feat: textos de degradação da classificação viária"
```

---

### Task 3: Cliente WFS da classificação viária

**Files:**
- Create: `app/Services/Geo/ClassificacaoViariaHit.php`
- Create: `app/Services/Geo/GeoServerClassificacaoViariaClient.php`
- Modify: `config/viabiliza.php` (bloco `integrations.geoserver`, após `type_names`)
- Test: `tests/Feature/Geo/GeoServerClassificacaoViariaClientTest.php`

**Interfaces:**
- Consumes: `Quadro11aClasseVia::oficializar()` (Task 1); as chaves de texto da Task 2 NÃO são usadas aqui — o cliente devolve o motivo cru e quem traduz é a Task 4.
- Produces:
  - `ClassificacaoViariaHit` com `public string $status` (`identificado`, `nao_encontrado`, `indisponivel`), `public ?string $classe` (já oficializada), `public ?string $classeBruta`, `public ?string $motivo`.
  - `GeoServerClassificacaoViariaClient::consultar(string $codLog): ClassificacaoViariaHit`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoServerClassificacaoViariaClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_via_classe'],
            [
                'group' => 'features',
                'type' => 'boolean',
                'value' => '1',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta da classificação viária no GeoServer.',
            ],
        );

        cache()->forget('viabiliza.parameters.features.geoserver_via_classe');
    }

    private function colecao(?string $classe): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $classe === null ? [] : [[
                'type' => 'Feature',
                'properties' => ['CODLOG' => 822, 'LOGRADOURO' => 'Avenida Joana Angélica', 'CLASSIFICACAO_VIARIA' => $classe],
            ]],
        ];
    }

    public function test_classe_do_geoserver_volta_na_grafia_da_lei(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VC-I'), 200)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertSame(ClassificacaoViariaHit::IDENTIFICADO, $hit->status);
        $this->assertSame('VC I', $hit->classe);
        $this->assertSame('VC-I', $hit->classeBruta);
    }

    public function test_codlog_vai_no_filtro_cql_e_a_consulta_e_memoizada(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VA-II'), 200)]);

        $client = app(GeoServerClassificacaoViariaClient::class);
        $client->consultar('5502');
        $client->consultar('5502');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'CODLOG=5502')
            && str_contains($request->url(), 'louos_classificacao_viaria%3AVM_L_CLASSIF_VIARIA'));
    }

    public function test_classe_fora_das_sete_oficiais_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VX-9'), 200)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('999');

        $this->assertSame(ClassificacaoViariaHit::INDISPONIVEL, $hit->status);
        $this->assertNull($hit->classe);
        $this->assertSame('VX-9', $hit->classeBruta);
    }

    public function test_codlog_sem_linha_na_camada_e_nao_encontrado(): void
    {
        Http::fake(['*' => Http::response($this->colecao(null), 200)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('123');

        $this->assertSame(ClassificacaoViariaHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->classe);
    }

    public function test_falha_http_e_indisponivel_sem_inventar_classe(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertSame(ClassificacaoViariaHit::INDISPONIVEL, $hit->status);
        $this->assertNull($hit->classe);
        $this->assertNotNull($hit->motivo);
    }

    public function test_interruptor_desligado_nao_chama_o_geoserver(): void
    {
        Parameter::query()->where('key', 'features.geoserver_via_classe')->update(['value' => '0']);
        cache()->forget('viabiliza.parameters.features.geoserver_via_classe');
        Http::fake();

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertSame(ClassificacaoViariaHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_consulta_identificada_e_auditada(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VC-I'), 200)]);

        app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'classificacao-viaria',
            'result' => 'identificado',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Geo/GeoServerClassificacaoViariaClientTest.php`
Expected: FAIL com `Class "App\Services\Geo\ClassificacaoViariaHit" not found`.

- [ ] **Step 3: Write minimal implementation**

`app/Services/Geo/ClassificacaoViariaHit.php`:

```php
<?php

namespace App\Services\Geo;

/**
 * Resultado da consulta da classificação viária LOUOS por CODLOG no GeoServer
 * SEDUR. Sem fachada: `classe` só vem preenchida quando o valor publicado é
 * uma das 7 classes do Quadro 11A; qualquer outro caso carrega o motivo.
 */
final readonly class ClassificacaoViariaHit
{
    public const IDENTIFICADO = 'identificado';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const INDISPONIVEL = 'indisponivel';

    public const DESLIGADO = 'desligado';

    public function __construct(
        public string $status,
        public ?string $classe,
        public ?string $classeBruta,
        public ?string $motivo,
    ) {}
}
```

`app/Services/Geo/GeoServerClassificacaoViariaClient.php`:

```php
<?php

namespace App\Services\Geo;

use App\Support\Audit\AuditService;
use App\Support\Louos\Quadro11aClasseVia;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Classe viária da LOUOS (Quadro 11A) por CODLOG, na camada oficial
 * `louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` do GeoServer SEDUR.
 *
 * O casamento é por CODLOG, não espacial: a camada é de LINHAS e um
 * INTERSECTS de ponto sobre linha praticamente nunca casa. O CODLOG já vem
 * resolvido pela via mais próxima ou pelo logradouro tributário da inscrição.
 *
 * Sem fachada: toggle desligado, GeoServer fora, CODLOG sem linha ou classe
 * fora das 7 oficiais NUNCA viram classe inventada — o motor degrada.
 */
class GeoServerClassificacaoViariaClient
{
    /** @var array<string, ClassificacaoViariaHit> */
    private array $memo = [];

    public function __construct(private AuditService $audit) {}

    public function consultar(string $codLog): ClassificacaoViariaHit
    {
        $codLog = trim($codLog);

        return $this->memo[$codLog] ??= $this->consultarNoGeoServer($codLog);
    }

    private function consultarNoGeoServer(string $codLog): ClassificacaoViariaHit
    {
        if (! Settings::enabled('geoserver_via_classe')) {
            return new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::DESLIGADO,
                classe: null,
                classeBruta: null,
                motivo: 'Consulta da classificação viária no GeoServer SEDUR desligada.',
            );
        }

        if ($codLog === '') {
            return new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'CODLOG ausente para consultar a classificação viária.',
            );
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($codLog));
        } catch (Throwable $e) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage(),
            ));
        }

        if (! $response->successful()) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'GeoServer SEDUR respondeu HTTP '.$response->status().' na classificação viária.',
            ));
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'Resposta fora do contrato WFS na classificação viária.',
            ));
        }

        $features = $payload['features'] ?? [];

        if (! is_array($features) || $features === []) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::NAO_ENCONTRADO,
                classe: null,
                classeBruta: null,
                motivo: null,
            ));
        }

        $atributo = (string) config('viabiliza.integrations.geoserver.classificacao_viaria.atributo', 'CLASSIFICACAO_VIARIA');
        $properties = is_array($features[0]['properties'] ?? null) ? $features[0]['properties'] : [];
        $bruta = $properties[$atributo] ?? null;
        $bruta = is_string($bruta) && trim($bruta) !== '' ? trim($bruta) : null;
        $classe = Quadro11aClasseVia::oficializar($bruta);

        if ($classe === null) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: $bruta,
                motivo: 'Classificação viária publicada fora das classes do Quadro 11A.',
            ));
        }

        return $this->auditar($codLog, new ClassificacaoViariaHit(
            status: ClassificacaoViariaHit::IDENTIFICADO,
            classe: $classe,
            classeBruta: $bruta,
            motivo: null,
        ));
    }

    private function auditar(string $codLog, ClassificacaoViariaHit $hit): ClassificacaoViariaHit
    {
        $this->audit->log(
            logName: 'territorio',
            event: 'classificacao-viaria',
            description: "Classificação viária do logradouro {$codLog}",
            properties: [
                'cod_log' => $codLog,
                'classe_bruta' => $hit->classeBruta,
                'classe' => $hit->classe,
            ],
            result: $hit->status,
            rulesVersion: 'lei-9148-2016-quadro11a',
        );

        return $hit;
    }

    private function url(string $codLog): string
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('viabiliza.integrations.geoserver.base_url'),
        ), '/');

        $typeName = (string) config(
            'viabiliza.integrations.geoserver.classificacao_viaria.type_name',
            'louos_classificacao_viaria:VM_L_CLASSIF_VIARIA',
        );

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => 1,
            'CQL_FILTER' => sprintf('CODLOG=%s', $codLog),
        ]);
    }
}
```

Em `config/viabiliza.php`, dentro de `integrations.geoserver`, logo depois do array `type_names`:

```php
            // Classificação viária da LOUOS (Quadro 11A). Constante TÉCNICA
            // (precedente [02-02]): a SEDUR ainda confirma se a classe é o
            // CLASSIFICACAO_VIARIA desta camada ou o HIERARQUIA do logradouro
            // — trocar a fonte é config, não deploy de código.
            'classificacao_viaria' => [
                'type_name' => 'louos_classificacao_viaria:VM_L_CLASSIF_VIARIA',
                'atributo' => 'CLASSIFICACAO_VIARIA',
            ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Geo/GeoServerClassificacaoViariaClientTest.php`
Expected: PASS, 7 testes.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/ClassificacaoViariaHit.php app/Services/Geo/GeoServerClassificacaoViariaClient.php config/viabiliza.php tests/Feature/Geo/GeoServerClassificacaoViariaClientTest.php
git commit -m "feat: consulta a classificação viária da LOUOS por CODLOG no GeoServer"
```

---

### Task 4: Interruptor no catálogo de integrações

**Files:**
- Modify: `app/Support/ApiIntegracaoCatalog.php:215` (aba GeoServer, lista `campos`)
- Modify: `database/seeders/ParameterSeeder.php` (catálogo de parâmetros)
- Test: `tests/Feature/Geo/GeoServerViaClasseToggleTest.php`

**Interfaces:**
- Consumes: `Settings::enabled('geoserver_via_classe')`, já lido pelo cliente da Task 3.
- Produces: o parâmetro `features.geoserver_via_classe` no catálogo, com default ligado.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Support\ApiIntegracaoCatalog;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GeoServerViaClasseToggleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_interruptor_esta_no_catalogo_de_parametros(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertDatabaseHas('parameters', [
            'key' => 'features.geoserver_via_classe',
            'group' => 'features',
            'type' => 'boolean',
        ]);
    }

    public function test_interruptor_aparece_na_aba_geoserver(): void
    {
        $aba = ApiIntegracaoCatalog::find('geoserver');

        $chaves = array_column($aba['campos'], 'key');

        $this->assertContains('features.geoserver_via_classe', $chaves);
    }

    public function test_interruptor_nasce_ligado(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.geoserver_via_classe')->sole();

        $this->assertSame('1', $parametro->default_value);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Geo/GeoServerViaClasseToggleTest.php`
Expected: FAIL — o parâmetro não existe e a chave não está na aba.

- [ ] **Step 3: Write minimal implementation**

Em `app/Support/ApiIntegracaoCatalog.php`, no array `campos` da aba `geoserver`, depois do interruptor `territorio`:

```php
                self::interruptor('via_classe', 'features.geoserver_via_classe', 'Classificação viária habilitada'),
```

Em `database/seeders/ParameterSeeder.php`, junto dos demais `features.geoserver_*`:

```php
            'features.geoserver_via_classe' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta a classificação viária da LOUOS no GeoServer SEDUR (insumo do Quadro 11A). Desligado, as condições pela via ficam indisponíveis',
            ],
```

Nota: `DatabaseSeederTest` valida a CONTAGEM de parâmetros. Ao acrescentar
este, incrementar em 1 as duas asserções de contagem nesse teste.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Geo/GeoServerViaClasseToggleTest.php tests/Feature/Seeders/DatabaseSeederTest.php`
Expected: PASS nos dois arquivos.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/ApiIntegracaoCatalog.php database/seeders/ParameterSeeder.php tests/Feature/Geo/GeoServerViaClasseToggleTest.php tests/Feature/Seeders/DatabaseSeederTest.php
git commit -m "feat: interruptor da classificação viária no catálogo de integrações"
```

---

### Task 5: TerritoryService entrega a classe na via

**Files:**
- Modify: `app/Services/Geo/TerritoryService.php:28-32` (construtor) e `:97-110` (`identifyNearest`)
- Test: `tests/Unit/Geo/TerritoryServiceClasseViaTest.php`

**Interfaces:**
- Consumes: `GeoServerClassificacaoViariaClient::consultar(string $codLog): ClassificacaoViariaHit` (Task 3).
- Produces: a chave `CLASSE_VIA_LOUOS` dentro de `via['propriedades']`, e `via['classe_via_motivo']` quando a classe não veio. `LouosEnquadramentoService::classeViaLouos` já lê a primeira.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\TerritoryService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class TerritoryServiceClasseViaTest extends TestCase
{
    // resolveLayer consulta geo_layers: sem camada vigente de via o serviço
    // curto-circuita em indisponivel e a classe nunca é consultada.
    use LazilyRefreshDatabase;

    private function servico(ClassificacaoViariaHit $hit): TerritoryService
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Via,
            'version' => 'via-2026',
            'feature_count' => 1,
        ]);

        $fake = new FakeSpatialRepository;
        $fake->setNearest(GeoLayerType::Via, [
            'id' => 1,
            'properties' => ['CODLOG' => 822, 'NOME_LOGRADOURO' => 'Avenida Joana Angélica'],
            'distancia_m' => 12.0,
        ]);

        $client = Mockery::mock(GeoServerClassificacaoViariaClient::class);
        $client->shouldReceive('consultar')->with('822')->andReturn($hit);

        return new TerritoryService($fake, app(AuditService::class), null, $client);
    }

    public function test_classe_viaria_entra_nas_propriedades_da_via(): void
    {
        $resultado = $this->servico(new ClassificacaoViariaHit(
            ClassificacaoViariaHit::IDENTIFICADO, 'VC I', 'VC-I', null,
        ))->identify(-12.98, -38.51);

        $this->assertSame('VC I', $resultado->via['propriedades']['CLASSE_VIA_LOUOS']);
    }

    public function test_sem_classe_a_via_carrega_o_motivo_e_nao_inventa_atributo(): void
    {
        $resultado = $this->servico(new ClassificacaoViariaHit(
            ClassificacaoViariaHit::INDISPONIVEL, null, null, 'GeoServer SEDUR indisponível.',
        ))->identify(-12.98, -38.51);

        $this->assertArrayNotHasKey('CLASSE_VIA_LOUOS', $resultado->via['propriedades']);
        $this->assertSame('GeoServer SEDUR indisponível.', $resultado->via['classe_via_motivo']);
    }
}
```

Conferir a forma exata de `setNearest` em `tests/Support/Geo/FakeSpatialRepository.php`
antes de escrever: o retorno precisa das mesmas chaves que `nearestFeature`
entrega (`properties` e `distancia_m`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Geo/TerritoryServiceClasseViaTest.php`
Expected: FAIL — o construtor não aceita o 4º parâmetro.

- [ ] **Step 3: Write minimal implementation**

No construtor de `app/Services/Geo/TerritoryService.php`:

```php
    public function __construct(
        private SpatialRepository $spatial,
        private AuditService $audit,
        private ?GeoServerWfsZonaClient $geoserver = null,
        private ?GeoServerClassificacaoViariaClient $classificacaoViaria = null,
    ) {}
```

Trocar o corpo de `identifyNearest`:

```php
    private function identifyNearest(GeoLayerType $type, float $lng, float $lat, int $maxMeters, ?CarbonInterface $date): array
    {
        $layer = $this->resolveLayer($type, $date);

        if ($this->isBlocked($layer)) {
            return $this->dimIndisponivel($this->motivoFor($type), $layer?->version) + ['distancia_m' => null];
        }

        $feature = $this->spatial->nearestFeature($layer, $lng, $lat, $maxMeters);

        if ($feature === null) {
            return $this->dimNaoEncontrado($layer->version) + ['distancia_m' => null];
        }

        $dim = $this->dimIdentificado($layer->version, $feature['properties'], ['distancia_m' => $feature['distancia_m']]);

        return $type === GeoLayerType::Via ? $this->comClasseViaria($dim) : $dim;
    }

    /**
     * Acrescenta a classe viária da LOUOS (Quadro 11A) às propriedades da via,
     * consultada por CODLOG na camada oficial. Sem classe, a via carrega o
     * MOTIVO e o atributo NÃO é criado — o enquadramento degrada honesto em
     * vez de receber uma classe inventada.
     *
     * @param  array<string, mixed>  $dim
     * @return array<string, mixed>
     */
    private function comClasseViaria(array $dim): array
    {
        if ($this->classificacaoViaria === null) {
            return $dim + ['classe_via_motivo' => 'Consulta da classificação viária não configurada.'];
        }

        $codLog = $dim['propriedades']['CODLOG'] ?? null;
        $codLog = is_string($codLog) || is_numeric($codLog) ? trim((string) $codLog) : '';

        if ($codLog === '') {
            return $dim + ['classe_via_motivo' => 'Via identificada sem CODLOG.'];
        }

        $hit = $this->classificacaoViaria->consultar($codLog);

        if ($hit->classe === null) {
            return $dim + ['classe_via_motivo' => $hit->motivo ?? 'Classificação viária não encontrada.'];
        }

        $dim['propriedades']['CLASSE_VIA_LOUOS'] = $hit->classe;

        return $dim + ['classe_via_motivo' => null];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Geo/TerritoryServiceClasseViaTest.php tests/Unit/Geo/TerritoryServiceTest.php`
Expected: PASS nos dois arquivos (o construtor novo é opcional, os testes antigos seguem válidos).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/TerritoryService.php tests/Unit/Geo/TerritoryServiceClasseViaTest.php
git commit -m "feat: via identificada carrega a classe viária da LOUOS"
```

---

### Task 6: Motivo específico no Quadro 11A

**Files:**
- Modify: `app/Services/Louos/LouosEnquadramentoService.php:1030-1038` (ramo sem classe)
- Test: `tests/Feature/Louos/Quadro11aDegradacaoTest.php`

**Interfaces:**
- Consumes: `via['classe_via_motivo']` (Task 5) e as chaves de texto da Task 2.
- Produces: `quadro11a['motivo']` específico por causa, em vez do genérico "pendente SEDUR".

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Louos;

use App\Services\Louos\EnquadramentoInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Espelha o apoio de tests/Feature/Louos/LouosEnquadramentoTerritorioTest.php
 * (territorioComVia/viaIdentificada/service) — copiar os helpers de lá.
 */
class Quadro11aDegradacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_via_sem_classe_usa_o_motivo_da_causa(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');

        $via = $this->viaIdentificada(['NOME_LOGRADOURO' => 'Rua Sem Classe', 'CODLOG' => 999]);
        $via['classe_via_motivo'] = 'GeoServer SEDUR indisponível.';

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComVia($via), []);

        $result = $this->service()->enquadrar($input);

        $this->assertSame('indisponivel', $result->quadro11a['status']);
        $this->assertSame('GeoServer SEDUR indisponível.', $result->quadro11a['motivo']);
        $this->assertNull($result->quadro11a['classe_via']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Louos/Quadro11aDegradacaoTest.php`
Expected: FAIL — o motivo é o genérico `via_sem_atributo`.

- [ ] **Step 3: Write minimal implementation**

Em `LouosEnquadramentoService::enquadrarCondicoesVia`, trocar o ramo sem classe:

```php
        $classeVia = $this->classeViaLouos($via);

        if ($classeVia === null) {
            // A causa exata vem do TerritoryService (toggle desligado, CODLOG
            // ausente, GeoServer fora, classe fora do Quadro 11A). Sem causa
            // registrada, cai no motivo genérico do catálogo.
            $motivo = $via['classe_via_motivo'] ?? null;

            return $this->dimIndisponivel(
                is_string($motivo) && $motivo !== '' ? $motivo : $this->textos->get('louos.motivo.via_sem_atributo'),
                null,
                ['classe_via' => null, 'condicoes' => [], 'base_legal' => null],
            );
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Louos/Quadro11aDegradacaoTest.php tests/Feature/Louos/LouosEnquadramentoTerritorioTest.php`
Expected: PASS nos dois arquivos.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Louos/LouosEnquadramentoService.php tests/Feature/Louos/Quadro11aDegradacaoTest.php
git commit -m "feat: Quadro 11A informa a causa real da classe viária ausente"
```

---

### Task 7: Golden cases do Quadro 11A ponta a ponta

**Files:**
- Test: `tests/Feature/Louos/Quadro11aGoldenCaseTest.php`

**Interfaces:**
- Consumes: tudo das Tasks 1 a 6, mais o CSV oficial `database/data/louos/oficial/quadro11a-condicoes-via.csv` carregado por `LouosQuadro11Seeder`.
- Produces: nada — é a rede de proteção do comportamento.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Louos;

use App\Enums\ResultadoViabilidade;
use App\Services\Louos\EnquadramentoInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Golden cases do Quadro 11A com a matriz OFICIAL carregada: a classe viária
 * real muda o veredito consolidado. Copiar os helpers de apoio
 * (territorioComVia/viaIdentificada/service/enquadramentoFaixa) de
 * tests/Feature/Louos/LouosEnquadramentoTerritorioTest.php.
 */
class Quadro11aGoldenCaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function resultadoPara(string $classe, string $condicao): array
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro11Condicao(
            \App\Enums\RuleDomain::LouosQuadro11a,
            'lei-9148-2016-quadro11a',
            $classe,
            'nR1',
            [$condicao],
        );

        $via = $this->viaIdentificada([
            'NOME_LOGRADOURO' => 'Avenida Joana Angélica',
            'CODLOG' => 822,
            'CLASSE_VIA_LOUOS' => $classe,
        ]);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComVia($via), []);

        return (array) $this->service()->enquadrar($input)->quadro11a;
    }

    public function test_classe_com_nao_no_quadro_veda_o_uso(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro11Condicao(\App\Enums\RuleDomain::LouosQuadro11a, 'lei-9148-2016-quadro11a', 'VC I', 'nR1', ['Não']);

        $via = $this->viaIdentificada(['CODLOG' => 822, 'CLASSE_VIA_LOUOS' => 'VC I']);
        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComVia($via), []);

        $result = $this->service()->enquadrar($input);

        $this->assertSame('identificado', $result->quadro11a['status']);
        $this->assertSame('VC I', $result->quadro11a['classe_via']);
        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $result->consolidado['resultado']);
    }

    public function test_classe_com_analise_da_cnlu_deixa_pendente(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro11Condicao(\App\Enums\RuleDomain::LouosQuadro11a, 'lei-9148-2016-quadro11a', 'VA II', 'nR1', ['R']);

        $via = $this->viaIdentificada(['CODLOG' => 5502, 'CLASSE_VIA_LOUOS' => 'VA II']);
        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComVia($via), []);

        $result = $this->service()->enquadrar($input);

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->consolidado['resultado']);
    }

    public function test_classe_com_sim_nao_veda_e_registra_a_versao(): void
    {
        $resultado = $this->resultadoPara('VL', 'Sim');

        $this->assertSame('identificado', $resultado['status']);
        $this->assertSame('VL', $resultado['classe_via']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Louos/Quadro11aGoldenCaseTest.php`
Expected: FAIL enquanto os helpers não forem copiados; depois disso, PASS se as Tasks 1-6 estiverem corretas. Se falhar por veredito, a cadeia tem bug — investigar antes de seguir.

- [ ] **Step 3: Write minimal implementation**

Nenhum código de produção novo. Copiar para o teste os helpers privados
`service()`, `enquadramentoFaixa()`, `quadro11Condicao()`, `territorioComVia()`
e `viaIdentificada()` de `tests/Feature/Louos/LouosEnquadramentoTerritorioTest.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Louos/Quadro11aGoldenCaseTest.php`
Expected: PASS, 3 testes.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Louos/Quadro11aGoldenCaseTest.php
git commit -m "test: golden cases do Quadro 11A com a classe viária real"
```

---

### Task 8: Comando de verificação contra o GeoServer real

**Files:**
- Create: `app/Console/Commands/LouosClasseViaCommand.php`
- Test: `tests/Feature/Louos/LouosClasseViaCommandTest.php`

**Interfaces:**
- Consumes: `GeoServerClassificacaoViariaClient::consultar()` (Task 3).
- Produces: `php artisan louos:classe-via {codlog}` — evidência de homologação da integração real.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Louos;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LouosClasseViaCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_comando_mostra_a_classe_oficial_do_codlog(): void
    {
        Http::fake(['*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['CODLOG' => 822, 'LOGRADOURO' => 'Avenida Joana Angélica', 'CLASSIFICACAO_VIARIA' => 'VC-I'],
            ]],
        ], 200)]);

        $this->artisan('louos:classe-via', ['codlog' => '822'])
            ->expectsOutputToContain('VC I')
            ->assertExitCode(0);
    }

    public function test_comando_falha_quando_o_geoserver_nao_responde(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $this->artisan('louos:classe-via', ['codlog' => '822'])
            ->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Louos/LouosClasseViaCommandTest.php`
Expected: FAIL com `The command "louos:classe-via" does not exist.`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Console\Commands;

use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use Illuminate\Console\Command;

/**
 * Consulta a classe viária da LOUOS de um CODLOG no GeoServer SEDUR.
 * Evidência de homologação da integração real (Quadro 11A) — roda em host
 * com rota ao GeoServer.
 */
class LouosClasseViaCommand extends Command
{
    protected $signature = 'louos:classe-via {codlog : Código do logradouro (CODLOG)}';

    protected $description = 'Mostra a classe viária da LOUOS de um logradouro, consultada no GeoServer SEDUR';

    public function handle(GeoServerClassificacaoViariaClient $client): int
    {
        $codLog = (string) $this->argument('codlog');
        $hit = $client->consultar($codLog);

        $this->line("CODLOG: {$codLog}");
        $this->line('Status: '.$hit->status);
        $this->line('Classe publicada: '.($hit->classeBruta ?? '—'));
        $this->line('Classe da lei: '.($hit->classe ?? '—'));

        if ($hit->status === ClassificacaoViariaHit::IDENTIFICADO) {
            return self::SUCCESS;
        }

        $this->error($hit->motivo ?? 'Classificação viária não identificada.');

        return self::FAILURE;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Louos/LouosClasseViaCommandTest.php`
Expected: PASS, 2 testes.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/LouosClasseViaCommand.php tests/Feature/Louos/LouosClasseViaCommandTest.php
git commit -m "feat: comando de verificação da classe viária no GeoServer"
```

---

### Task 9: Documentação e estado do projeto

**Files:**
- Modify: `docs/integracoes/geoserver-sedur.md:149-166` (seção 5.3)
- Modify: `.planning/STATE.md` (bloco de dados de produção)

**Interfaces:**
- Consumes: nada.
- Produces: nada — fecha a pendência documental.

- [ ] **Step 1: Atualizar a seção 5.3 do guia do GeoServer**

Substituir o parágrafo final de pendência por:

```markdown
**Em uso pelo motor.** `CLASSIFICACAO_VIARIA` alimenta o Quadro 11A: o
`GeoServerClassificacaoViariaClient` consulta por `CQL_FILTER=CODLOG=<n>`
(casamento por código, não espacial — a camada é de linhas) e
`Quadro11aClasseVia` normaliza a grafia (`VC-I` → `VC I`). Interruptor:
`features.geoserver_via_classe`. Fonte configurável em
`viabiliza.integrations.geoserver.classificacao_viaria`.

Verificação: `php artisan louos:classe-via 822` → `VC I`.

Pendência restante: confirmar com a SEDUR se a classe do Quadro 11A é o
`CLASSIFICACAO_VIARIA` desta camada ou o `HIERARQUIA` do logradouro. Trocar a
fonte é mudança de config.
```

- [ ] **Step 2: Registrar no STATE**

Acrescentar ao bloco de dados de produção:

```markdown
- **Quadro 11A ligado (2026-09-30):** a classe viária vem de
  `louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` por CODLOG. Antes disso o
  quadro nunca aplicava (o motor lia `CLASSE_VIA_LOUOS`, atributo que nenhuma
  camada publica). Pendência SEDUR: `CLASSIFICACAO_VIARIA` × `HIERARQUIA`.
```

- [ ] **Step 3: Rodar a suíte completa**

Run: `php artisan test --compact`
Expected: sem falhas novas. `EnvironmentAccessTest::test_servidor_autentica_pelo_login_interno` já falha antes desta mudança.

- [ ] **Step 4: Commit**

```bash
git add docs/integracoes/geoserver-sedur.md .planning/STATE.md
git commit -m "docs: registra a classificação viária como fonte do Quadro 11A"
```

---

## Verificação final (evidência fresca)

- [ ] `php artisan test --compact` sem falhas novas.
- [ ] Em host com rota ao GeoServer: `php artisan louos:classe-via 822` devolve `VC I`; `louos:classe-via 5502` devolve `VA II`; `louos:classe-via 896` devolve `VC II`.
- [ ] Um processo real com imóvel na Avenida Joana Angélica mostra `quadro11a.classe_via = 'VC I'` no snapshot da decisão, com o status `identificado`.
- [ ] Desligar `features.geoserver_via_classe` e confirmar que a ficha mostra o motivo, sem veredito pela via.
