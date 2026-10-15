# Polígono do endereçamento e zona pela maior porção — Plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: usar superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para executar este plano tarefa a tarefa. Os passos usam checkbox (`- [ ]`).

**Objetivo:** gravar no processo o polígono do endereçamento SEDUR (`enderecamento:VM_ENDERECAMENTO`) e, com ele, decidir a zona urbanística pela maior porção do terreno (art. 18, § 2º, da Lei 9.148/2016), com degradação para o ponto, empate e divergência encaminhados à análise.

**Arquitetura:** um cliente WFS novo busca o polígono por `CODLOG` + número (+ complemento) e um serviço o grava no protocolo. Um resolvedor (interface + implementação GeoServer/PostGIS) traz as zonas candidatas por `BBOX`, calcula `ST_Area(ST_Intersection(...)::geography)` no PostGIS da aplicação e devolve um `ZonaMaiorPorcao`. O `ZonaProcessoService` materializa `zona_codigo`/`zona_fonte` e audita; o motor (resolver de viabilidade), o inventário de camadas e o fluxo expresso leem esse resultado. A UI mostra o segundo polígono e a fonte/porções da zona.

**Stack:** Laravel 13 / PHP 8.5, PostgreSQL + PostGIS (produção), SQLite na suíte, PHPUnit 12, Inertia v3 + React 19, Leaflet (react-leaflet), Vitest.

**Spec:** `docs/superpowers/specs/2026-10-02-poligono-enderecamento-zona-design.md`

## Restrições globais

- Coluna nova `address_polygon_geojson` (+ `address_polygon_consulted_at`) em `viability_requests`, **fora do `fillable`**; `property_polygon_geojson` não muda.
- Coluna nova `zona_fonte` com os valores `ponto` e `enderecamento_maior_porcao`.
- Interruptor `features.geoserver_enderecamento`, booleano, default ligado, mesmo padrão de `geoserver_zona`/`geoserver_territorio`/`geoserver_via_classe`. **Sem parâmetro de limiar percentual.**
- Empate: diferença de área ≤ **0,01 m²** (constante técnica, não parâmetro).
- Camada de zona falhando → `indisponivel`; fora do PostgreSQL → `indisponivel` sem consultar o GeoServer.
- Geometria linear (`LineString`/`MultiLineString`) não disputa a maior porção.
- Nenhuma geometria de zona é persistida.
- Precedência no expresso: RT-01 → empate → divergência endereçamento × desenho → RN-005 existente → demais regras territoriais → fluxo atual.
- Sem desenho de polígono a partir de ponto + área. Portal do cidadão não mostra porções.
- PHP: chaves sempre, tipos de retorno explícitos, PHPDoc com array shapes, `vendor/bin/pint --dirty --format agent` antes de cada commit com PHP.
- Commits em português, sem coautoria nem menção a ferramenta/IA.
- Rodar só os testes da tarefa a cada passo (`php artisan test --compact --filter=...`); a suíte inteira só na Tarefa 14, depois de perguntar ao usuário.
- `TestCase` desliga integrações HTTP por padrão: testes de cliente ligam o toggle e usam `Http::fake`.

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/2026_10_02_120000_add_address_polygon_and_zona_fonte_to_viability_requests_table.php` (criar) | colunas novas |
| `app/Enums/ZonaFonte.php` (criar) | `ponto` / `enderecamento_maior_porcao` |
| `app/Models/ViabilityRequest.php` (alterar) | casts das colunas novas |
| `config/viabiliza.php`, `database/seeders/ParameterSeeder.php`, `app/Support/ApiIntegracaoCatalog.php`, `tests/TestCase.php` (alterar) | interruptor e `type_name` do endereçamento |
| `app/Services/Geo/EnderecamentoHit.php` (criar) | resultado da consulta do endereçamento |
| `app/Services/Geo/GeoServerEnderecamentoClient.php` (criar) | WFS do endereçamento, desambiguação, memo |
| `app/Services/Geo/PoligonoEnderecamentoService.php` (criar) | grava o polígono no processo e audita |
| `app/Services/Realty/LocalizacaoInscricao.php`, `app/Services/Realty/LocalizacaoInscricaoService.php` (alterar) | a certidão (número métrico, bloco) viaja junto da localização da inscrição |
| `app/Services/Geo/ZonaMaiorPorcao.php` (criar) | resultado do cálculo por área |
| `app/Services/Geo/ZonaMaiorPorcaoResolver.php` (criar) | interface do resolvedor |
| `app/Services/Geo/GeoServerZonaMaiorPorcaoResolver.php` (criar) | BBOX no GeoServer + interseção no PostGIS, memo |
| `app/Services/Geo/GeoServerWfsZonaClient.php` (alterar) | `codigoZona` vira `public static` (reuso) |
| `app/Services/Geo/ZonaProcessoService.php` (criar) | materializa `zona_codigo`/`zona_fonte`, audita, texto do empate |
| `app/Services/Solicitacao/ProtocolarSolicitacaoService.php` (alterar) | encadeia polígono + zona no protocolo |
| `app/Services/Solicitacao/SolicitacaoViabilityResolver.php` (alterar) | motor usa a zona por área |
| `app/Services/Geo/CamadasIncidentesService.php` (alterar) | `zona.fonte`, `zona.porcoes`, `zona.maior_porcao` |
| `app/Services/Geo/LocalizacaoDivergenciaService.php` (alterar) | `avaliarEnderecamento()` |
| `app/Enums/TipoGatilho.php`, `app/Services/Expresso/FluxoExpressoService.php` (alterar) | empate e divergência no expresso |
| `app/Services/Decisao/DecisionTextCatalog.php`, `database/seeders/DecisionTextSeeder.php` (alterar) | dois textos novos |
| `app/Http/Controllers/Gestao/AnalysisRecordController.php`, `app/Http/Controllers/Gestao/ProcessoController.php` (alterar) | props `poligono_enderecamento` e `zona.fonte` |
| `resources/js/components/geo/map-imovel.tsx` (alterar) | estilo por camada |
| `resources/js/components/geo/geometria.ts` (criar) + teste | centro de Polygon/MultiPolygon |
| `resources/js/components/geo/camadas-imovel-zona.ts` (criar) + teste | texto da linha da zona |
| `resources/js/components/geo/camadas-imovel.tsx`, `resources/js/pages/gestao/ficha-analise/show.tsx`, `resources/js/pages/gestao/processos/show.tsx` (alterar) | UI |
| `app/Services/Expresso/FluxoExpressoService.php` (alterar, Tarefa 12) | fonte da zona em `rules_versions` da decisão |
| `.planning/STATE.md`, `docs/integracoes/geoserver-sedur.md` (alterar, Tarefa 13) | decisão registrada e inventário do GeoServer atualizado |
| `tests/Support/Geo/FakeZonaMaiorPorcaoResolver.php` (criar) | fake do resolvedor para a suíte SQLite |

---

### Tarefa 1: Colunas do processo e enum `ZonaFonte`

**Arquivos:**
- Criar: `database/migrations/2026_10_02_120000_add_address_polygon_and_zona_fonte_to_viability_requests_table.php`
- Criar: `app/Enums/ZonaFonte.php`
- Alterar: `app/Models/ViabilityRequest.php` (método `casts()`, ~linha 102; docblock da classe ~linha 36)
- Teste: `tests/Feature/Geo/EnderecamentoSchemaTest.php`

**Interfaces:**
- Produz: `ViabilityRequest::$address_polygon_geojson` (`?array`), `$address_polygon_consulted_at` (`?Carbon`), `$zona_fonte` (`?ZonaFonte`); `enum ZonaFonte: string { Ponto = 'ponto'; EnderecamentoMaiorPorcao = 'enderecamento_maior_porcao'; label(): string }`.

- [ ] **Passo 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Geo;

use App\Enums\ZonaFonte;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnderecamentoSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_processo_tem_as_colunas_do_poligono_do_enderecamento_e_da_fonte_da_zona(): void
    {
        $this->assertTrue(Schema::hasColumns('viability_requests', [
            'address_polygon_geojson',
            'address_polygon_consulted_at',
            'zona_fonte',
        ]));
    }

    public function test_colunas_sao_do_sistema_e_ficam_fora_do_fillable(): void
    {
        $fillable = (new ViabilityRequest)->getFillable();

        $this->assertNotContains('address_polygon_geojson', $fillable);
        $this->assertNotContains('address_polygon_consulted_at', $fillable);
        $this->assertNotContains('zona_fonte', $fillable);
    }

    public function test_casts_devolvem_array_data_e_enum(): void
    {
        $request = ViabilityRequest::factory()->create();
        $poligono = ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]];

        $request->forceFill([
            'address_polygon_geojson' => $poligono,
            'address_polygon_consulted_at' => now(),
            'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
        ])->save();

        $request = $request->fresh();

        $this->assertSame($poligono, $request->address_polygon_geojson);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
        $this->assertSame('Maior porção do lote', ZonaFonte::EnderecamentoMaiorPorcao->label());
        $this->assertSame('Ponto da inscrição', ZonaFonte::Ponto->label());
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/EnderecamentoSchemaTest.php`
Esperado: FAIL — colunas ausentes / classe `App\Enums\ZonaFonte` inexistente.

- [ ] **Passo 3: Criar a migration**

Run: `php artisan make:migration add_address_polygon_and_zona_fonte_to_viability_requests_table --table=viability_requests --no-interaction` e renomear o arquivo para `2026_10_02_120000_...` se o timestamp gerado ficar antes de `2026_09_26_190000`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polígono do endereçamento licenciado (GeoServer SEDUR,
     * enderecamento:VM_ENDERECAMENTO) e a regra que decidiu a zona do
     * processo. Escritos pelo sistema no protocolo, nunca pelo requerente.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->json('address_polygon_geojson')->nullable()->after('property_polygon_geojson');
            $table->timestamp('address_polygon_consulted_at')->nullable()->after('address_polygon_geojson');
            $table->string('zona_fonte', 40)->nullable()->after('zona_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn(['address_polygon_geojson', 'address_polygon_consulted_at', 'zona_fonte']);
        });
    }
};
```

- [ ] **Passo 4: Criar o enum**

```php
<?php

namespace App\Enums;

/**
 * Regra que decidiu a zona urbanística do processo: o ponto da inscrição ou a
 * maior porção do polígono do endereçamento (art. 18, § 2º, da Lei 9.148/2016).
 */
enum ZonaFonte: string
{
    case Ponto = 'ponto';
    case EnderecamentoMaiorPorcao = 'enderecamento_maior_porcao';

    public function label(): string
    {
        return match ($this) {
            self::Ponto => 'Ponto da inscrição',
            self::EnderecamentoMaiorPorcao => 'Maior porção do lote',
        };
    }
}
```

- [ ] **Passo 5: Casts no model**

Em `ViabilityRequest::casts()`, ao lado de `'property_polygon_geojson' => 'array'`:

```php
'address_polygon_geojson' => 'array',
'address_polygon_consulted_at' => 'datetime',
'zona_fonte' => ZonaFonte::class,
```

Importar `App\Enums\ZonaFonte`. No docblock da classe, na frase do território materializado, acrescentar `zona_fonte` e `address_polygon_geojson` à lista de colunas escritas pelo sistema.

- [ ] **Passo 6: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/EnderecamentoSchemaTest.php`
Esperado: PASS (3 testes).

- [ ] **Passo 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_02_120000_add_address_polygon_and_zona_fonte_to_viability_requests_table.php app/Enums/ZonaFonte.php app/Models/ViabilityRequest.php tests/Feature/Geo/EnderecamentoSchemaTest.php
git commit -m "feat: guarda o polígono do endereçamento e a fonte da zona no processo"
```

---

### Tarefa 2: Interruptor `features.geoserver_enderecamento`

**Arquivos:**
- Alterar: `config/viabiliza.php` (bloco `features`, ~linha 42; bloco `integrations.geoserver`, ~linha 325)
- Alterar: `database/seeders/ParameterSeeder.php` (ao lado de `features.geoserver_via_classe`, ~linha 308)
- Alterar: `app/Support/ApiIntegracaoCatalog.php` (aba `geoserver`, ~linha 217)
- Alterar: `tests/TestCase.php` (~linha 47)
- Alterar: `tests/Feature/Seeders/DatabaseSeederTest.php` (linhas 104 e 401), `tests/Feature/Seeders/ParameterSeederTest.php` (linhas 18 e 838): 105 → 106
- Teste: `tests/Feature/Geo/GeoServerEnderecamentoToggleTest.php`

**Interfaces:**
- Produz: `Settings::enabled('geoserver_enderecamento')`; `config('viabiliza.integrations.geoserver.enderecamento.type_name')` = `'enderecamento:VM_ENDERECAMENTO'`.

- [ ] **Passo 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Support\ApiIntegracaoCatalog;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GeoServerEnderecamentoToggleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_interruptor_esta_no_catalogo_de_parametros_e_nasce_ligado(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.geoserver_enderecamento')->sole();

        $this->assertSame('features', $parametro->group);
        $this->assertSame('boolean', $parametro->type);
        $this->assertSame('1', $parametro->default_value);
    }

    public function test_interruptor_aparece_na_aba_geoserver(): void
    {
        $chaves = array_column(ApiIntegracaoCatalog::find('geoserver')['campos'], 'key');

        $this->assertContains('features.geoserver_enderecamento', $chaves);
    }

    public function test_camada_do_enderecamento_esta_configurada(): void
    {
        $this->assertSame('enderecamento:VM_ENDERECAMENTO', config('viabiliza.integrations.geoserver.enderecamento.type_name'));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/GeoServerEnderecamentoToggleTest.php`
Esperado: FAIL (parâmetro inexistente).

- [ ] **Passo 3: Implementar**

`config/viabiliza.php`, em `features`, depois de `'geoserver_via_classe' => true,`:

```php
// Polígono do endereçamento SEDUR (enderecamento:VM_ENDERECAMENTO) e zona
// pela maior porção do lote. Desligado, a zona segue pelo ponto.
'geoserver_enderecamento' => true,
```

Em `integrations.geoserver`, ao lado de `classificacao_viaria`:

```php
'enderecamento' => [
    'type_name' => 'enderecamento:VM_ENDERECAMENTO',
],
```

`ParameterSeeder`, depois de `features.geoserver_via_classe`:

```php
'features.geoserver_enderecamento' => [
    'group' => 'features',
    'type' => 'boolean',
    'default_value' => '1',
    'validation_rules' => ['required', 'boolean'],
    'description' => 'Consulta o polígono do endereçamento no GeoServer SEDUR e decide a zona pela maior porção do lote (art. 18, § 2º, da Lei 9.148/2016). Desligado, a zona segue pelo ponto da inscrição',
],
```

`ApiIntegracaoCatalog::geoserver()`, depois do interruptor `via_classe`:

```php
self::interruptor('enderecamento', 'features.geoserver_enderecamento', 'Polígono do endereçamento habilitado'),
```

`tests/TestCase.php`, logo abaixo de `config(['viabiliza.features.geoserver_zona' => false]);`:

```php
config(['viabiliza.features.geoserver_enderecamento' => false]);
```

Contagens de parâmetros: trocar `105` por `106` nas quatro asserções citadas.

- [ ] **Passo 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/GeoServerEnderecamentoToggleTest.php tests/Feature/Seeders/ParameterSeederTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Gestao/ApiIntegracaoControllerTest.php`
Esperado: PASS. Se `ApiIntegracaoControllerTest` ou `ParameterSeederTest` listarem as chaves da aba GeoServer por igualdade, acrescentar a chave nova na lista esperada.

- [ ] **Passo 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/viabiliza.php database/seeders/ParameterSeeder.php app/Support/ApiIntegracaoCatalog.php tests/TestCase.php tests/Feature/Geo/GeoServerEnderecamentoToggleTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/ParameterSeederTest.php tests/Feature/Gestao/ApiIntegracaoControllerTest.php
git commit -m "feat: interruptor do polígono do endereçamento no GeoServer"
```

---

### Tarefa 3: Cliente WFS do endereçamento

**Arquivos:**
- Criar: `app/Services/Geo/EnderecamentoHit.php`
- Criar: `app/Services/Geo/GeoServerEnderecamentoClient.php`
- Alterar: `app/Providers/AppServiceProvider.php` (~linha 98, junto do `scoped(GeoServerWfsCamadaClient::class)`)
- Teste: `tests/Feature/Geo/GeoServerEnderecamentoClientTest.php`

**Interfaces:**
- Produz:
  - `final readonly class EnderecamentoHit { ENCONTRADO='encontrado'; AMBIGUO='ambiguo'; NAO_ENCONTRADO='nao_encontrado'; INDISPONIVEL='indisponivel'; DESLIGADO='desligado'; __construct(string $status, ?array $geometria = null, ?array $propriedades = null, int $candidatas = 0, ?string $motivo = null); encontrado(): bool }`
  - `GeoServerEnderecamentoClient::consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit` (memo por chave; escopo `scoped`).

Regras (spec §2 e decisões 2 e 12):
- Uma requisição por chave: `CQL_FILTER=CODLOG=<n> AND NUMERACAO_METRICA=<n>`, `maxFeatures=50`, `srsName=EPSG:4326`, GeoJSON.
- `CODLOG` e número precisam ser só dígitos (são `xsd:decimal`); senão `nao_encontrado` com motivo, sem HTTP.
- Só `Polygon`/`MultiPolygon` contam.
- Com complemento, filtra localmente por `COMPLEMENTO` (trim, maiúsculas). Se nenhuma casar, segue com todas.
- Geometrias idênticas contam como uma (`json_encode` da geometria).
- 0 distintas → `nao_encontrado`; 1 → `encontrado`; >1 → `ambiguo` com `candidatas`.

- [ ] **Passo 1: Escrever os testes que falham**

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\GeoServerEnderecamentoClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoServerEnderecamentoClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ligar(true);
    }

    private function ligar(bool $ligado): void
    {
        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_enderecamento'],
            [
                'group' => 'features',
                'type' => 'boolean',
                'value' => $ligado ? '1' : '0',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Polígono do endereçamento.',
            ],
        );

        cache()->forget('viabiliza.parameters.features.geoserver_enderecamento');
    }

    /**
     * Quadrado de 0,0005° com canto em ($lng, $lat).
     *
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function quadrado(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.0005, $lat], [$lng + 0.0005, $lat + 0.0005], [$lng, $lat + 0.0005], [$lng, $lat],
        ]]];
    }

    /**
     * @param  list<array{complemento: ?string, geometria: array<string, mixed>}>  $feicoes
     */
    private function responder(array $feicoes): void
    {
        Http::fake(['*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => array_map(fn (array $f): array => [
                'type' => 'Feature',
                'geometry' => $f['geometria'],
                'properties' => ['CODLOG' => 24574, 'NUMERACAO_METRICA' => 70, 'COMPLEMENTO' => $f['complemento'], 'ALVARA' => 'A-1'],
            ], $feicoes),
        ])]);
    }

    public function test_uma_feicao_devolve_o_poligono(): void
    {
        $this->responder([['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)]]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
        $this->assertSame('A-1', $hit->propriedades['ALVARA']);
    }

    public function test_tres_geometrias_distintas_sem_complemento_e_ambiguo(): void
    {
        $this->responder([
            ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6, -12.98)],
            ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::AMBIGUO, $hit->status);
        $this->assertSame(3, $hit->candidatas);
        $this->assertNull($hit->geometria);
    }

    public function test_complemento_desempata(): void
    {
        $this->responder([
            ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6, -12.98)],
            ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70', ' bl-3 ');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
    }

    public function test_geometrias_identicas_contam_como_uma(): void
    {
        $this->responder([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('4923', '7416');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
    }

    public function test_sem_feicao_e_nao_encontrado(): void
    {
        $this->responder([]);

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, app(GeoServerEnderecamentoClient::class)->consultar('1', '2')->status);
    }

    public function test_falha_http_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response('URL Bloqueada', 500)]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertStringContainsString('500', (string) $hit->motivo);
    }

    public function test_desligado_nao_consulta(): void
    {
        $this->ligar(false);
        Http::fake();

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_numero_nao_numerico_nao_consulta(): void
    {
        Http::fake();

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', 'S/N');

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_filtro_cql_usa_codlog_e_numero_e_a_consulta_e_memoizada(): void
    {
        $this->responder([['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)]]);
        $cliente = app(GeoServerEnderecamentoClient::class);

        $cliente->consultar('024574', '70');
        $cliente->consultar('024574', '70');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['typeName'] === 'enderecamento:VM_ENDERECAMENTO'
                && $query['CQL_FILTER'] === 'CODLOG=24574 AND NUMERACAO_METRICA=70'
                && $query['srsName'] === 'EPSG:4326';
        });
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/GeoServerEnderecamentoClientTest.php`
Esperado: FAIL — classe inexistente.

- [ ] **Passo 3: Implementar `EnderecamentoHit`**

```php
<?php

namespace App\Services\Geo;

/**
 * Resultado da consulta ao endereçamento SEDUR por CODLOG + número métrico.
 */
final readonly class EnderecamentoHit
{
    public const ENCONTRADO = 'encontrado';

    public const AMBIGUO = 'ambiguo';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const INDISPONIVEL = 'indisponivel';

    public const DESLIGADO = 'desligado';

    /**
     * @param  array<string, mixed>|null  $geometria  GeoJSON Polygon/MultiPolygon em EPSG:4326
     * @param  array<string, mixed>|null  $propriedades
     */
    public function __construct(
        public string $status,
        public ?array $geometria = null,
        public ?array $propriedades = null,
        public int $candidatas = 0,
        public ?string $motivo = null,
    ) {}

    public function encontrado(): bool
    {
        return $this->status === self::ENCONTRADO;
    }
}
```

- [ ] **Passo 4: Implementar o cliente**

```php
<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Polígono do endereço licenciado na camada enderecamento:VM_ENDERECAMENTO do
 * GeoServer SEDUR, chaveado por CODLOG + NUMERACAO_METRICA. A mesma chave pode
 * ter várias feições: o complemento desempata e geometrias idênticas
 * (subunidades) contam como uma. Sobrando mais de uma, o resultado é ambíguo e
 * nenhuma é escolhida.
 */
class GeoServerEnderecamentoClient
{
    /** @var array<string, EnderecamentoHit> */
    private array $memo = [];

    public function consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit
    {
        $codLog = ltrim(trim($codLog), '0');
        $numero = trim($numero);
        $complemento = mb_strtoupper(trim((string) $complemento));
        $chave = implode('|', [$codLog, $numero, $complemento]);

        return $this->memo[$chave] ??= $this->consultarNoGeoServer($codLog, $numero, $complemento);
    }

    private function consultarNoGeoServer(string $codLog, string $numero, string $complemento): EnderecamentoHit
    {
        if (! Settings::enabled('geoserver_enderecamento')) {
            return new EnderecamentoHit(EnderecamentoHit::DESLIGADO, motivo: 'Consulta do endereçamento no GeoServer SEDUR desligada.');
        }

        if (! ctype_digit($codLog) || ! ctype_digit($numero)) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO, motivo: 'CODLOG ou número métrico não numérico.');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($codLog, $numero));
        } catch (Throwable $e) {
            return new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'GeoServer SEDUR respondeu HTTP '.$response->status().' no endereçamento.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'Resposta fora do contrato WFS no endereçamento.');
        }

        return $this->escolher($this->poligonais($payload['features'] ?? []), $complemento);
    }

    /**
     * @return list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>
     */
    private function poligonais(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $poligonais = [];

        foreach ($features as $feature) {
            $geometria = $feature['geometry'] ?? null;

            if (is_array($geometria) && in_array($geometria['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                $poligonais[] = [
                    'geometry' => $geometria,
                    'properties' => is_array($feature['properties'] ?? null) ? $feature['properties'] : [],
                ];
            }
        }

        return $poligonais;
    }

    /**
     * @param  list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>  $feicoes
     */
    private function escolher(array $feicoes, string $complemento): EnderecamentoHit
    {
        if ($complemento !== '') {
            $casam = array_values(array_filter(
                $feicoes,
                fn (array $f): bool => mb_strtoupper(trim((string) ($f['properties']['COMPLEMENTO'] ?? ''))) === $complemento,
            ));

            if ($casam !== []) {
                $feicoes = $casam;
            }
        }

        $distintas = [];

        foreach ($feicoes as $feicao) {
            $distintas[(string) json_encode($feicao['geometry'])] ??= $feicao;
        }

        if ($distintas === []) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
        }

        if (count($distintas) > 1) {
            return new EnderecamentoHit(
                EnderecamentoHit::AMBIGUO,
                candidatas: count($distintas),
                motivo: count($distintas).' polígonos distintos para o mesmo CODLOG e número, sem complemento que desempate.',
            );
        }

        $unica = array_values($distintas)[0];

        return new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, geometria: $unica['geometry'], propriedades: $unica['properties'], candidatas: 1);
    }

    private function url(string $codLog, string $numero): string
    {
        $base = rtrim((string) Settings::get('integrations.geoserver.base_url', config('viabiliza.integrations.geoserver.base_url')), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => (string) config('viabiliza.integrations.geoserver.enderecamento.type_name', 'enderecamento:VM_ENDERECAMENTO'),
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => 50,
            'CQL_FILTER' => sprintf('CODLOG=%s AND NUMERACAO_METRICA=%s', $codLog, $numero),
        ]);
    }
}
```

Em `AppServiceProvider::register()`, junto do cliente de camadas: `$this->app->scoped(GeoServerEnderecamentoClient::class);` (importar a classe).

- [ ] **Passo 5: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/GeoServerEnderecamentoClientTest.php`
Esperado: PASS (9 testes).

- [ ] **Passo 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/EnderecamentoHit.php app/Services/Geo/GeoServerEnderecamentoClient.php app/Providers/AppServiceProvider.php tests/Feature/Geo/GeoServerEnderecamentoClientTest.php
git commit -m "feat: consulta o polígono do endereçamento no GeoServer SEDUR"
```

---

### Tarefa 4: Gravar o polígono do endereçamento no protocolo

**Arquivos:**
- Criar: `app/Services/Geo/PoligonoEnderecamentoService.php`
- Alterar: `app/Services/Realty/LocalizacaoInscricao.php` (carregar a certidão)
- Alterar: `app/Services/Realty/LocalizacaoInscricaoService.php` (preencher a certidão nos três retornos de `consultar()`)
- Alterar: `app/Services/Solicitacao/ProtocolarSolicitacaoService.php` (construtor ~linha 39; método `protocol()` ~linha 81)
- Teste: `tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php` (CA-PE-01 a CA-PE-06)

**Interfaces:**
- Consome: `GeoServerEnderecamentoClient::consultar()` e `EnderecamentoHit` (Tarefa 3); `LocalizacaoInscricaoService::localizar()` (memoizado por execução).
- Produz: `PoligonoEnderecamentoService::aplicar(ViabilityRequest $request): bool` — true quando gravou. Auditoria `logName: 'geo'`, `event: 'enderecamento-poligono'`, `result` = status do hit ou `sem_chave`, propriedades `viability_request_id`, `cod_log`, `numero_metrico`, `complemento`, `status`, `candidatas`, `motivo`.
- Produz: `LocalizacaoInscricao::$cadastro` (`?PropertyCadastroCampos`) — a certidão do Cadastro Imobiliário disponível para quem já consome a localização da inscrição.

**A chave da consulta é a certidão, não o número da Junta.**

`NUMERACAO_METRICA`, no endereçamento, é o **número métrico**. O `address_number` do processo vem de `RES_NUME` do RUC da JUCEB, que é o **número de porta** declarado pelo requerente. Medição de 2026-10-02 na base cadastral: em 8 de 15 registros com os dois campos preenchidos, porta e métrico divergem (CODLOG 629: porta 54, métrico 357; CODLOG 3925: porta 58, métrico 393). Consultar `NUMERACAO_METRICA` com o número de porta erra o imóvel ou, no pior caso, acerta o lote de **outro** imóvel da mesma via — e decidiria a zona sobre o polígono errado, sem sinal de erro.

A certidão da SEFAZ que o processo já consulta (e que fornece o `CodigoLogradouroTributario` usado no `cod_log`) traz `NumeroMetrico` e `NumeroPorta` como campos distintos, além de `Bloco`, `SubUnidade` e `NumeroSubUnidade`. A chave sai de lá, do mesmo registro que deu o CODLOG:

| Atributo do endereçamento | Origem |
|---|---|
| `CODLOG` | `cod_log` do processo (já é o `CodigoLogradouroTributario` da certidão) |
| `NUMERACAO_METRICA` | `cadastro->numero_metrico` |
| `COMPLEMENTO` | `cadastro->bloco` e, na falta dele, `address_complement` |

Sem `numero_metrico` na certidão, **não se consulta**: status `sem_chave`, zona pelo ponto. Nunca cair no número de porta como substituto.

- [ ] **Passo 1: Escrever os testes que falham**

Base do teste: copiar o `setUp()`, `rascunhoRegin()` e `fakeCadastro()` de `tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php` (linhas 26–100), que já protocolam um processo REGIN com ponto do cadastro e CODLOG tributário `212`.

Ajuste obrigatório no `fakeCadastro()` copiado: a resposta falsa da SEFAZ precisa trazer o bloco de endereço `TipoEndereco: 'Localização'` com `NumeroMetrico`, espelhando o payload real já usado em `tests/Feature/Realty/SedurSefazPropertyRegistryLookupTest.php` (linhas 190–203). Assinatura `fakeCadastro(string $codLog, ?string $numeroMetrico = '58', ?string $bloco = null)`, para os testes variarem a chave:

```php
'Enderecos' => [[
    'TipoEndereco' => 'Localização',
    'Logradouro' => 'RUA EXEMPLO',
    'NumeroPorta' => '000070',
    'NumeroMetrico' => $numeroMetrico,
    'Bloco' => $bloco,
    'Bairro' => 'GRACA',
    'CEP' => '40150000',
    'Municipio' => 'SALVADOR',
    'UF' => 'BA',
]],
```

Repare que porta e métrico são propositalmente diferentes no fixture (`000070` e `58`): é o que trava a regressão de voltar a usar `address_number` como chave.

Acrescentar:

```php
private function ligarEnderecamento(bool $ligado = true): void
{
    Parameter::query()->updateOrCreate(
        ['key' => 'features.geoserver_enderecamento'],
        ['group' => 'features', 'type' => 'boolean', 'value' => $ligado ? '1' : '0', 'default_value' => '1', 'validation_rules' => ['required', 'boolean'], 'description' => 'Polígono do endereçamento.'],
    );
    cache()->forget('viabiliza.parameters.features.geoserver_enderecamento');
}

/**
 * @return array<string, mixed>
 */
private function quadrado(float $lng): array
{
    return ['type' => 'Polygon', 'coordinates' => [[[$lng, -12.98], [$lng + 0.0005, -12.98], [$lng + 0.0005, -12.9795], [$lng, -12.9795], [$lng, -12.98]]]];
}

/**
 * @param  list<array{complemento: ?string, geometria: array<string, mixed>}>  $feicoes
 */
private function enderecamento(array $feicoes): void
{
    Http::fake(function (Request $request) use ($feicoes) {
        if (! str_contains($request->url(), 'VM_ENDERECAMENTO')) {
            return Http::response(['type' => 'FeatureCollection', 'features' => []]);
        }

        return Http::response(['type' => 'FeatureCollection', 'features' => array_map(fn (array $f): array => [
            'type' => 'Feature',
            'geometry' => $f['geometria'],
            'properties' => ['COMPLEMENTO' => $f['complemento']],
        ], $feicoes)]);
    });
}

private function protocolar(array $atributos = []): ViabilityRequest
{
    $solicitacao = $this->rascunhoRegin(['address_number' => '70', ...$atributos]);
    app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, User::factory()->create());

    return $solicitacao->fresh();
}

private function auditoria(ViabilityRequest $request): ?Activity
{
    return Activity::query()->where('event', 'enderecamento-poligono')->where('subject_id', $request->id)->latest('id')->first();
}
```

Testes:

```php
public function test_ca_pe_01_um_poligono_e_gravado_com_a_data_da_consulta(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento();
    $this->enderecamento([['complemento' => null, 'geometria' => $this->quadrado(-38.5)]]);

    $request = $this->protocolar();

    $this->assertSame($this->quadrado(-38.5), $request->address_polygon_geojson);
    $this->assertNotNull($request->address_polygon_consulted_at);
    $this->assertSame('encontrado', $this->auditoria($request)?->properties['status']);
}

public function test_ca_pe_02_tres_geometrias_sem_complemento_nao_grava_e_audita_ambiguo(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento();
    $this->enderecamento([
        ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5)],
        ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6)],
        ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7)],
    ]);

    $request = $this->protocolar();

    $this->assertNull($request->address_polygon_geojson);
    $this->assertSame('ambiguo', $this->auditoria($request)?->properties['status']);
    $this->assertSame(3, $this->auditoria($request)?->properties['candidatas']);
}

public function test_ca_pe_03_complemento_que_casa_grava_a_feicao_dele(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento();
    $this->enderecamento([
        ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5)],
        ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6)],
        ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7)],
    ]);

    $request = $this->protocolar(['address_complement' => 'BL-25']);

    $this->assertSame($this->quadrado(-38.6), $request->address_polygon_geojson);
}

public function test_ca_pe_04_subunidades_com_o_mesmo_poligono_gravam(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento();
    $this->enderecamento([
        ['complemento' => null, 'geometria' => $this->quadrado(-38.5)],
        ['complemento' => null, 'geometria' => $this->quadrado(-38.5)],
    ]);

    $this->assertSame($this->quadrado(-38.5), $this->protocolar()->address_polygon_geojson);
}

public function test_ca_pe_05_sem_feicao_nao_grava_e_segue_o_fluxo(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento();
    $this->enderecamento([]);

    $request = $this->protocolar();

    $this->assertNull($request->address_polygon_geojson);
    $this->assertNotNull($request->protocol_number);
    $this->assertSame('nao_encontrado', $this->auditoria($request)?->properties['status']);
}

public function test_ca_pe_06_desligado_nao_consulta_e_a_zona_fica_pelo_ponto(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento(false);
    Http::fake();

    $request = $this->protocolar();

    Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'VM_ENDERECAMENTO'));
    $this->assertNull($request->address_polygon_geojson);
    $this->assertSame('ZCN-1', $request->zona_codigo);
    $this->assertSame('desligado', $this->auditoria($request)?->properties['status']);
}

public function test_sem_numero_metrico_na_certidao_nao_consulta_e_audita_sem_chave(): void
{
    $this->fakeCadastro('212', numeroMetrico: null);
    $this->ligarEnderecamento();
    Http::fake();

    $request = $this->protocolar();

    Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'VM_ENDERECAMENTO'));
    $this->assertSame('sem_chave', $this->auditoria($request)?->properties['status']);
}

public function test_a_chave_e_o_numero_metrico_da_certidao_nao_o_numero_de_porta(): void
{
    // Porta 70 no processo, métrico 58 na certidão: a consulta tem de usar 58.
    $this->fakeCadastro('212', numeroMetrico: '58');
    $this->ligarEnderecamento();
    $this->enderecamento([['complemento' => null, 'geometria' => $this->quadrado(-38.5)]]);

    $request = $this->protocolar(['address_number' => '70']);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'VM_ENDERECAMENTO')
        && str_contains(urldecode($r->url()), 'NUMERACAO_METRICA = 58'));
    $this->assertSame('58', $this->auditoria($request)?->properties['numero_metrico']);
}

public function test_bloco_da_certidao_desempata_antes_do_complemento_do_processo(): void
{
    $this->fakeCadastro('212', numeroMetrico: '58', bloco: 'BL-25');
    $this->ligarEnderecamento();
    $this->enderecamento([
        ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5)],
        ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6)],
    ]);

    $request = $this->protocolar(['address_complement' => 'BL-3']);

    $this->assertSame($this->quadrado(-38.6), $request->address_polygon_geojson);
}
```

Imports adicionais: `App\Models\Parameter`, `Illuminate\Http\Client\Request`, `Illuminate\Support\Facades\Http`.

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php`
Esperado: FAIL — polígono não gravado / auditoria ausente.

- [ ] **Passo 3: Implementar o serviço**

```php
<?php

namespace App\Services\Geo;

use App\Models\ViabilityRequest;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Support\Audit\AuditService;

/**
 * Grava no processo o polígono do endereçamento SEDUR, consultado pelo CODLOG
 * do logradouro tributário e pelo NÚMERO MÉTRICO da certidão do Cadastro
 * Imobiliário — nunca pelo número de porta, que é outro dado. Ambíguo, ausente
 * ou indisponível não grava nada e fica na auditoria.
 */
class PoligonoEnderecamentoService
{
    public function __construct(
        private GeoServerEnderecamentoClient $cliente,
        private LocalizacaoInscricaoService $localizacao,
        private AuditService $audit,
    ) {}

    public function aplicar(ViabilityRequest $request): bool
    {
        $codLog = trim((string) $request->cod_log);
        $cadastro = $this->localizacao->localizar($request->property_registration)->cadastro;
        // O número métrico é a chave do endereçamento e sai da certidão, nunca
        // do address_number (número de porta declarado na Junta).
        $numero = trim((string) ($cadastro?->numero_metrico ?? ''));
        $complemento = $this->textoOuNulo($cadastro?->bloco) ?? $request->address_complement;

        if ($codLog === '' || $numero === '' || $numero === '0') {
            $this->auditar($request, 'sem_chave', $codLog, $numero, $complemento, new EnderecamentoHit('sem_chave', motivo: 'Sem CODLOG ou sem número métrico na certidão do Cadastro Imobiliário.'));

            return false;
        }

        $hit = $this->cliente->consultar($codLog, $numero, $complemento);

        $this->auditar($request, $hit->status, $codLog, $numero, $complemento, $hit);

        if (! $hit->encontrado()) {
            return false;
        }

        $request->forceFill([
            'address_polygon_geojson' => $hit->geometria,
            'address_polygon_consulted_at' => now(),
        ])->save();

        return true;
    }

    private function auditar(ViabilityRequest $request, string $status, string $codLog, string $numero, ?string $complemento, EnderecamentoHit $hit): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'enderecamento-poligono',
            description: "Polígono do endereçamento ({$status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'cod_log' => $codLog,
                'numero_metrico' => $numero,
                'complemento' => $complemento,
                'status' => $status,
                'candidatas' => $hit->candidatas,
                'motivo' => $hit->motivo,
            ],
            subject: $request,
            result: $status,
        );
    }

    private function textoOuNulo(?string $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }
}
```

- [ ] **Passo 3b: A certidão passa a viajar na localização da inscrição**

Em `app/Services/Realty/LocalizacaoInscricao.php`, acrescentar a propriedade ao construtor promovido, depois de `$geocode`:

```php
public ?PropertyCadastroCampos $cadastro = null,
```

Em `app/Services/Realty/LocalizacaoInscricaoService.php`, método `consultar()`, passar `cadastro: $resultado->cadastro,` nos **três** retornos de `LocalizacaoInscricao` que já têm `codLogTributario` (localizada com ponto, localizada por geocodificação e sem coordenada). Os retornos de `NAO_ENCONTRADA` e `INDISPONIVEL` continuam sem certidão.

O memo por inscrição já existente no serviço garante uma consulta por execução (CA-ZN-08 vale igual aqui): protocolo, CODLOG tributário e polígono do endereçamento reaproveitam a mesma certidão.

- [ ] **Passo 4: Encadear no protocolo**

No construtor de `ProtocolarSolicitacaoService`, acrescentar `private PoligonoEnderecamentoService $poligonoEnderecamento,` (import `App\Services\Geo\PoligonoEnderecamentoService`). Em `protocol()`, logo após `$this->territorioProcesso->aplicarLogradouroTributario($request);`:

```php
// Com o CODLOG tributário aplicado, o polígono do endereçamento SEDUR é
// consultado por CODLOG + número + complemento.
$this->poligonoEnderecamento->aplicar($request);
```

Atualizar o comentário do bloco anterior para citar o polígono do endereçamento.

- [ ] **Passo 5: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php tests/Feature/Solicitacao/ProtocolarSolicitacaoTest.php`
Esperado: PASS.

- [ ] **Passo 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/PoligonoEnderecamentoService.php app/Services/Solicitacao/ProtocolarSolicitacaoService.php tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php
git commit -m "feat: grava o polígono do endereçamento no protocolo"
```

---

### Tarefa 5: Resolvedor da zona pela maior porção

**Arquivos:**
- Criar: `app/Services/Geo/ZonaMaiorPorcao.php`
- Criar: `app/Services/Geo/ZonaMaiorPorcaoResolver.php`
- Criar: `app/Services/Geo/GeoServerZonaMaiorPorcaoResolver.php`
- Alterar: `app/Services/Geo/GeoServerWfsZonaClient.php` (`codigoZona` privado → `public static`)
- Alterar: `app/Providers/AppServiceProvider.php` (bind scoped da interface)
- Criar: `tests/Support/Geo/FakeZonaMaiorPorcaoResolver.php`
- Teste: `tests/Feature/Geo/ZonaMaiorPorcaoResolverTest.php` (SQLite — CA-ZN-07)
- Teste: `tests/Feature/Geo/ZonaMaiorPorcaoResolverPostgisTest.php` (grupo postgis — CA-ZN-01, 03, 04, 05, 06, 08)

**Interfaces:**
- Produz:
  - `final readonly class ZonaMaiorPorcao { IDENTIFICADO='identificado'; EMPATE='empate'; SEM_INTERSECAO='sem_intersecao'; INDISPONIVEL='indisponivel'; TOLERANCIA_EMPATE_M2 = 0.01; __construct(string $status, ?string $codigo = null, array $porcoes = [], array $empate = [], array $camadas = [], ?float $areaLoteM2 = null, ?string $motivo = null); static indisponivel(string $motivo, array $camadas = []): self; identificado(): bool; toArray(): array }`
    - `porcoes`: `list<array{zona: string, area_m2: float, percentual: float}>`, maior → menor.
    - `empate`: `list<string>` (as duas zonas empatadas).
    - `camadas`: `list<array{type_name: string, geometria: ?string, status: string}>` — `geometria` ∈ `poligonal|linear|null`, `status` ∈ `ok|falha`.
  - `interface ZonaMaiorPorcaoResolver { public function resolver(array $poligono): ZonaMaiorPorcao; }`
  - `GeoServerWfsZonaClient::codigoZona(array $properties, string $typeName): ?string` (estático, público).
  - `Tests\Support\Geo\FakeZonaMaiorPorcaoResolver` com `para(array $poligono, ZonaMaiorPorcao $r): self`, `public int $chamadas`, e `static identificado(string $zona, array $porcoes = []): ZonaMaiorPorcao`, `static empate(string $a, string $b): ZonaMaiorPorcao`.

- [ ] **Passo 0: Confirmar a ordem dos eixos do BBOX em produção**

A spec mediu que o filtro `BBOX` passa pelo WAF, mas não registrou a forma. Antes de codar, confirmar com uma consulta real (substituir pelo BBOX de um lote conhecido em ZPR 3):

```bash
curl -s "https://geoserver.sedur.salvador.ba.gov.br/geoserver/wfs?service=WFS&version=1.1.0&request=GetFeature&typeName=louos_zpr3:VM_L_Z_USO_ZPR_3&outputFormat=application/json&srsName=EPSG:4326&CQL_FILTER=BBOX(GEOMETRY,-38.4560,-12.9900,-38.4550,-12.9890,'EPSG:4326')" | head -c 300
```

Esperado: `FeatureCollection` com `numberReturned` > 0 e coordenadas em `[lng, lat]`. Se vier vazio, testar a ordem `lat,lng` no BBOX e ajustar `filtroBbox()` (Passo 6) para a ordem que retornar feições. Anotar no commit qual ordem valeu.

- [ ] **Passo 1: Teste SQLite (CA-ZN-07) que falha**

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\GeoServerLayer;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZonaMaiorPorcaoResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ca_zn_07_fora_do_postgres_e_indisponivel_sem_consultar_o_geoserver(): void
    {
        config(['viabiliza.features.geoserver_zona' => true, 'viabiliza.features.geoserver_enderecamento' => true]);
        GeoServerLayer::factory()->create();
        Http::fake();

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver([
            'type' => 'Polygon',
            'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]],
        ]);

        $this->assertSame(ZonaMaiorPorcao::INDISPONIVEL, $resultado->status);
        $this->assertStringContainsString('PostGIS', (string) $resultado->motivo);
        Http::assertNothingSent();
    }
}
```

- [ ] **Passo 2: Teste PostGIS que falha**

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\GeoServerLayer;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Maior porção (art. 18, § 2º, da Lei 9.148/2016) calculada no PostGIS:
 * lote de 0,001° × 0,001° em Salvador, dividido por faixas verticais de zona.
 */
#[Group('postgis')]
class ZonaMaiorPorcaoResolverPostgisTest extends PostgisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.features.geoserver_zona' => true, 'viabiliza.features.geoserver_enderecamento' => true]);

        GeoServerLayer::factory()->create(['workspace' => 'louos_zpr3', 'type_name' => 'VM_L_Z_USO_ZPR_3', 'ordem' => 1]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zeis', 'type_name' => 'VM_L_Z_USO_ZEIS', 'ordem' => 2]);
    }

    /**
     * @return array<string, mixed>
     */
    private function retangulo(float $lngMin, float $lngMax, float $latMin = -12.9800, float $latMax = -12.9790): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lngMin, $latMin], [$lngMax, $latMin], [$lngMax, $latMax], [$lngMin, $latMax], [$lngMin, $latMin],
        ]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(): array
    {
        return $this->retangulo(-38.5000, -38.4990);
    }

    /**
     * @param  array<string, array<string, mixed>|int>  $porCamada  geometria da feição, ou status HTTP de falha
     */
    private function geoserver(array $porCamada): void
    {
        Http::fake(function (Request $request) use ($porCamada) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $resposta = $porCamada[$query['typeName'] ?? ''] ?? null;

            if (is_int($resposta)) {
                return Http::response('URL Bloqueada', $resposta);
            }

            return Http::response([
                'type' => 'FeatureCollection',
                'features' => $resposta === null ? [] : [['type' => 'Feature', 'geometry' => $resposta, 'properties' => []]],
            ]);
        });
    }

    public function test_ca_zn_01_78_por_cento_em_zpr3_vence(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.49922, -12.9810, -12.9780),
            'louos_zeis:VM_L_Z_USO_ZEIS' => $this->retangulo(-38.49922, -38.4980, -12.9810, -12.9780),
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::IDENTIFICADO, $resultado->status);
        $this->assertSame('ZPR 3', $resultado->codigo);
        $this->assertSame(['ZPR 3', 'ZEIS'], array_column($resultado->porcoes, 'zona'));
        $this->assertEqualsWithDelta(78.0, $resultado->porcoes[0]['percentual'], 0.2);
        $this->assertEqualsWithDelta(22.0, $resultado->porcoes[1]['percentual'], 0.2);
        $this->assertGreaterThan($resultado->porcoes[1]['area_m2'], $resultado->porcoes[0]['area_m2']);
    }

    public function test_ca_zn_03_uma_camada_falhando_torna_o_resultado_indisponivel(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4980, -12.9810, -12.9780),
            'louos_zeis:VM_L_Z_USO_ZEIS' => 500,
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::INDISPONIVEL, $resultado->status);
        $this->assertStringContainsString('louos_zeis:VM_L_Z_USO_ZEIS', (string) $resultado->motivo);
    }

    public function test_ca_zn_04_areas_iguais_sao_empate(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4995, -12.9810, -12.9780),
            'louos_zeis:VM_L_Z_USO_ZEIS' => $this->retangulo(-38.4995, -38.4980, -12.9810, -12.9780),
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::EMPATE, $resultado->status);
        $this->assertNull($resultado->codigo);
        $this->assertEqualsCanonicalizing(['ZPR 3', 'ZEIS'], $resultado->empate);
    }

    public function test_ca_zn_05_sem_intersecao(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.40, -38.39),
        ]);

        $this->assertSame(ZonaMaiorPorcao::SEM_INTERSECAO, app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote())->status);
    }

    public function test_ca_zn_06_camada_linear_nao_disputa_a_maior_porcao(): void
    {
        GeoServerLayer::factory()->create(['workspace' => 'louos_zona_uso_zclmu', 'type_name' => 'VM_L_Z_USO_ZCLMU', 'ordem' => 3]);
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4980, -12.9810, -12.9780),
            'louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU' => ['type' => 'LineString', 'coordinates' => [[-38.5005, -12.9795], [-38.4985, -12.9795]]],
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::IDENTIFICADO, $resultado->status);
        $this->assertSame(['ZPR 3'], array_column($resultado->porcoes, 'zona'));
        $linear = collect($resultado->camadas)->firstWhere('type_name', 'louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU');
        $this->assertSame('linear', $linear['geometria']);
    }

    public function test_ca_zn_08_uma_consulta_por_poligono_e_por_camada(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4980, -12.9810, -12.9780),
        ]);
        $resolver = app(ZonaMaiorPorcaoResolver::class);

        $resolver->resolver($this->lote());
        $resolver->resolver($this->lote());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with((string) ($query['CQL_FILTER'] ?? ''), 'BBOX(GEOMETRY,');
        });
    }
}
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/ZonaMaiorPorcaoResolverTest.php`
Esperado: FAIL — interface inexistente.
Run: `php artisan test --compact --group=postgis --filter=ZonaMaiorPorcaoResolverPostgisTest`
Esperado: FAIL (ou SKIP se o container PostGIS não estiver de pé — nesse caso subir o container da porta 5433 e rodar `php artisan geo:preparar-banco-de-testes` antes de seguir).

- [ ] **Passo 4: Tornar `codigoZona` reutilizável**

Em `GeoServerWfsZonaClient`: trocar `private function codigoZona(array $properties, string $typeName): ?string` por `public static function codigoZona(array $properties, string $typeName): ?string`; dentro dela, `$this->normalizarCodigo($valor)` → `self::normalizarCodigo($valor)`; em `extrair()`, `$this->codigoZona(` → `self::codigoZona(`.

Run: `php artisan test --compact tests/Unit/Geo/GeoServerWfsZonaClientTest.php` — Esperado: PASS.

- [ ] **Passo 5: Value object e interface**

```php
<?php

namespace App\Services\Geo;

/**
 * Zona do terreno pela maior porção (art. 18, § 2º, da Lei 9.148/2016): área
 * da interseção do polígono com cada zona candidata, da maior para a menor.
 */
final readonly class ZonaMaiorPorcao
{
    public const IDENTIFICADO = 'identificado';

    public const EMPATE = 'empate';

    public const SEM_INTERSECAO = 'sem_intersecao';

    public const INDISPONIVEL = 'indisponivel';

    /** Diferença de área abaixo da qual duas porções empatam (ruído de ponto flutuante). */
    public const TOLERANCIA_EMPATE_M2 = 0.01;

    /**
     * @param  list<array{zona: string, area_m2: float, percentual: float}>  $porcoes
     * @param  list<string>  $empate
     * @param  list<array{type_name: string, geometria: ?string, status: string}>  $camadas
     */
    public function __construct(
        public string $status,
        public ?string $codigo = null,
        public array $porcoes = [],
        public array $empate = [],
        public array $camadas = [],
        public ?float $areaLoteM2 = null,
        public ?string $motivo = null,
    ) {}

    /**
     * @param  list<array{type_name: string, geometria: ?string, status: string}>  $camadas
     */
    public static function indisponivel(string $motivo, array $camadas = []): self
    {
        return new self(self::INDISPONIVEL, camadas: $camadas, motivo: $motivo);
    }

    public function identificado(): bool
    {
        return $this->status === self::IDENTIFICADO && $this->codigo !== null;
    }

    /**
     * @return array{status: string, codigo: ?string, porcoes: list<array{zona: string, area_m2: float, percentual: float}>, empate: list<string>, camadas: list<array{type_name: string, geometria: ?string, status: string}>, area_lote_m2: ?float, motivo: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'codigo' => $this->codigo,
            'porcoes' => $this->porcoes,
            'empate' => $this->empate,
            'camadas' => $this->camadas,
            'area_lote_m2' => $this->areaLoteM2,
            'motivo' => $this->motivo,
        ];
    }
}
```

```php
<?php

namespace App\Services\Geo;

/**
 * Zona urbanística de um polígono pela maior porção. A implementação real
 * consulta o GeoServer SEDUR e calcula no PostGIS; a suíte SQLite usa um fake.
 */
interface ZonaMaiorPorcaoResolver
{
    /**
     * @param  array<string, mixed>  $poligono  GeoJSON Polygon/MultiPolygon em EPSG:4326
     */
    public function resolver(array $poligono): ZonaMaiorPorcao;
}
```

- [ ] **Passo 6: Implementação GeoServer + PostGIS**

```php
<?php

namespace App\Services\Geo;

use App\Models\GeoServerLayer;
use App\Support\Settings;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Traz a geometria das zonas candidatas por BBOX do polígono em cada camada de
 * zona ativa (o WAF da Prefeitura bloqueia polígono na URL) e calcula a área de
 * cada interseção no PostGIS da aplicação. Uma camada sem resposta torna o
 * resultado indisponível: sem ela a maior porção não é verificável. Camada que
 * responde linha (centralidade linear) não disputa. Nada é persistido.
 */
class GeoServerZonaMaiorPorcaoResolver implements ZonaMaiorPorcaoResolver
{
    /** @var array<string, ZonaMaiorPorcao> */
    private array $memo = [];

    public function resolver(array $poligono): ZonaMaiorPorcao
    {
        return $this->memo[md5((string) json_encode($poligono))] ??= $this->calcular($poligono);
    }

    /**
     * @param  array<string, mixed>  $poligono
     */
    private function calcular(array $poligono): ZonaMaiorPorcao
    {
        if (DB::getDriverName() !== 'pgsql') {
            return ZonaMaiorPorcao::indisponivel('Cálculo da maior porção exige PostGIS.');
        }

        if (! Settings::enabled('geoserver_enderecamento') || ! Settings::enabled('geoserver_zona')) {
            return ZonaMaiorPorcao::indisponivel('Consulta de zona ou do endereçamento no GeoServer SEDUR desligada.');
        }

        $bbox = $this->bbox($poligono);

        if ($bbox === null) {
            return ZonaMaiorPorcao::indisponivel('Polígono sem coordenadas.');
        }

        $typeNames = GeoServerLayer::query()->ativos()->get()
            ->map(fn (GeoServerLayer $camada): string => $camada->nomeCompleto())
            ->all();

        if ($typeNames === []) {
            return ZonaMaiorPorcao::indisponivel('Nenhuma FeatureType de zona configurada no GeoServer.');
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($typeNames, $bbox): void {
                foreach ($typeNames as $typeName) {
                    $pool->as($typeName)
                        ->withHeaders(['User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0')])
                        ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                        ->connectTimeout(3)
                        ->acceptJson()
                        ->get($this->url($typeName, $bbox));
                }
            });
        } catch (Throwable $e) {
            return ZonaMaiorPorcao::indisponivel('GeoServer SEDUR indisponível: '.$e->getMessage());
        }

        $camadas = [];
        $candidatas = [];
        $falhas = [];

        foreach ($typeNames as $typeName) {
            $response = $responses[$typeName] ?? null;
            $payload = $response instanceof Response && $response->successful() ? $response->json() : null;

            if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
                $falhas[] = $typeName;
                $camadas[] = ['type_name' => $typeName, 'geometria' => null, 'status' => 'falha'];

                continue;
            }

            $linear = false;

            foreach (is_array($payload['features'] ?? null) ? $payload['features'] : [] as $feature) {
                $geometria = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;
                $tipo = $geometria['type'] ?? null;

                if (in_array($tipo, ['LineString', 'MultiLineString'], true)) {
                    $linear = true;

                    continue;
                }

                if (! in_array($tipo, ['Polygon', 'MultiPolygon'], true)) {
                    continue;
                }

                $codigo = GeoServerWfsZonaClient::codigoZona(is_array($feature['properties'] ?? null) ? $feature['properties'] : [], $typeName);

                if ($codigo !== null) {
                    $candidatas[] = ['zona' => $codigo, 'geometria' => $geometria];
                }
            }

            $camadas[] = ['type_name' => $typeName, 'geometria' => $linear ? 'linear' : 'poligonal', 'status' => 'ok'];
        }

        if ($falhas !== []) {
            return ZonaMaiorPorcao::indisponivel('Camada de zona sem resposta do GeoServer SEDUR: '.implode(', ', $falhas).'.', $camadas);
        }

        return $this->porcoes($poligono, $candidatas, $camadas);
    }

    /**
     * @param  array<string, mixed>  $poligono
     * @param  list<array{zona: string, geometria: array<string, mixed>}>  $candidatas
     * @param  list<array{type_name: string, geometria: ?string, status: string}>  $camadas
     */
    private function porcoes(array $poligono, array $candidatas, array $camadas): ZonaMaiorPorcao
    {
        $areaLote = $this->area('SELECT ST_Area(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))::geography) AS area', [json_encode($poligono)]);
        $areas = [];

        foreach ($candidatas as $candidata) {
            $area = $this->area(
                'SELECT ST_Area(ST_CollectionExtract(ST_Intersection(
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)),
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
                    ), 3)::geography) AS area',
                [json_encode($poligono), json_encode($candidata['geometria'])],
            );

            if ($area > 0.0) {
                $areas[$candidata['zona']] = ($areas[$candidata['zona']] ?? 0.0) + $area;
            }
        }

        if ($areas === []) {
            return new ZonaMaiorPorcao(ZonaMaiorPorcao::SEM_INTERSECAO, camadas: $camadas, areaLoteM2: round($areaLote, 2));
        }

        arsort($areas);

        $porcoes = [];

        foreach ($areas as $zona => $area) {
            $porcoes[] = [
                'zona' => (string) $zona,
                'area_m2' => round($area, 2),
                'percentual' => $areaLote > 0.0 ? round($area / $areaLote * 100, 1) : 0.0,
            ];
        }

        $valores = array_values($areas);
        $zonas = array_map('strval', array_keys($areas));

        if (count($valores) > 1 && abs($valores[0] - $valores[1]) <= ZonaMaiorPorcao::TOLERANCIA_EMPATE_M2) {
            return new ZonaMaiorPorcao(ZonaMaiorPorcao::EMPATE, porcoes: $porcoes, empate: [$zonas[0], $zonas[1]], camadas: $camadas, areaLoteM2: round($areaLote, 2));
        }

        return new ZonaMaiorPorcao(ZonaMaiorPorcao::IDENTIFICADO, codigo: $zonas[0], porcoes: $porcoes, camadas: $camadas, areaLoteM2: round($areaLote, 2));
    }

    /**
     * @param  list<string|false>  $bindings
     */
    private function area(string $sql, array $bindings): float
    {
        $linha = DB::selectOne($sql, $bindings);

        return $linha !== null && $linha->area !== null ? (float) $linha->area : 0.0;
    }

    /**
     * @param  array<string, mixed>  $poligono
     * @return array{0: float, 1: float, 2: float, 3: float}|null  [lngMin, latMin, lngMax, latMax]
     */
    private function bbox(array $poligono): ?array
    {
        if (! is_array($poligono['coordinates'] ?? null)) {
            return null;
        }

        $pontos = [];

        array_walk_recursive($poligono['coordinates'], function (mixed $valor) use (&$pontos): void {
            $pontos[] = (float) $valor;
        });

        if (count($pontos) < 6) {
            return null;
        }

        $lngs = array_values(array_filter($pontos, fn (int $i): bool => $i % 2 === 0, ARRAY_FILTER_USE_KEY));
        $lats = array_values(array_filter($pontos, fn (int $i): bool => $i % 2 === 1, ARRAY_FILTER_USE_KEY));

        return [min($lngs), min($lats), max($lngs), max($lats)];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function url(string $typeName, array $bbox): string
    {
        $base = rtrim((string) Settings::get('integrations.geoserver.base_url', config('viabiliza.integrations.geoserver.base_url')), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'CQL_FILTER' => $this->filtroBbox($bbox),
        ]);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function filtroBbox(array $bbox): string
    {
        return sprintf("BBOX(GEOMETRY,%.7f,%.7f,%.7f,%.7f,'EPSG:4326')", $bbox[0], $bbox[1], $bbox[2], $bbox[3]);
    }
}
```

Observação: `array_walk_recursive` sobre `coordinates` alterna lng/lat em Polygon e MultiPolygon porque cada posição é `[lng, lat]` (sem altitude no endereçamento). Se aparecer posição com 3 valores, trocar por iteração explícita das posições.

No `AppServiceProvider::register()`: `$this->app->scoped(ZonaMaiorPorcaoResolver::class, GeoServerZonaMaiorPorcaoResolver::class);` (imports).

- [ ] **Passo 7: Fake para a suíte**

```php
<?php

namespace Tests\Support\Geo;

use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;

class FakeZonaMaiorPorcaoResolver implements ZonaMaiorPorcaoResolver
{
    public int $chamadas = 0;

    /** @var array<string, ZonaMaiorPorcao> */
    private array $respostas = [];

    /**
     * @param  array<string, mixed>  $poligono
     */
    public function para(array $poligono, ZonaMaiorPorcao $resultado): self
    {
        $this->respostas[md5((string) json_encode($poligono))] = $resultado;

        return $this;
    }

    public function resolver(array $poligono): ZonaMaiorPorcao
    {
        $this->chamadas++;

        return $this->respostas[md5((string) json_encode($poligono))]
            ?? ZonaMaiorPorcao::indisponivel('Fake sem resposta para o polígono.');
    }

    /**
     * @param  list<array{zona: string, area_m2: float, percentual: float}>  $porcoes
     */
    public static function identificado(string $zona, array $porcoes = []): ZonaMaiorPorcao
    {
        return new ZonaMaiorPorcao(
            ZonaMaiorPorcao::IDENTIFICADO,
            codigo: $zona,
            porcoes: $porcoes !== [] ? $porcoes : [['zona' => $zona, 'area_m2' => 100.0, 'percentual' => 100.0]],
            camadas: [['type_name' => 'louos_teste:VM_TESTE', 'geometria' => 'poligonal', 'status' => 'ok']],
            areaLoteM2: 100.0,
        );
    }

    public static function empate(string $a, string $b): ZonaMaiorPorcao
    {
        return new ZonaMaiorPorcao(
            ZonaMaiorPorcao::EMPATE,
            porcoes: [['zona' => $a, 'area_m2' => 50.0, 'percentual' => 50.0], ['zona' => $b, 'area_m2' => 50.0, 'percentual' => 50.0]],
            empate: [$a, $b],
            areaLoteM2: 100.0,
        );
    }
}
```

- [ ] **Passo 8: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/ZonaMaiorPorcaoResolverTest.php tests/Unit/Geo/GeoServerWfsZonaClientTest.php`
Run: `php artisan test --compact --group=postgis --filter=ZonaMaiorPorcaoResolverPostgisTest`
Esperado: PASS (1 + 6 testes, sem SKIP).

- [ ] **Passo 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/ZonaMaiorPorcao.php app/Services/Geo/ZonaMaiorPorcaoResolver.php app/Services/Geo/GeoServerZonaMaiorPorcaoResolver.php app/Services/Geo/GeoServerWfsZonaClient.php app/Providers/AppServiceProvider.php tests/Support/Geo/FakeZonaMaiorPorcaoResolver.php tests/Feature/Geo/ZonaMaiorPorcaoResolverTest.php tests/Feature/Geo/ZonaMaiorPorcaoResolverPostgisTest.php
git commit -m "feat: calcula a zona pela maior porção do lote no PostGIS"
```

---

### Tarefa 6: Materializar `zona_codigo`/`zona_fonte` e auditar

**Arquivos:**
- Criar: `app/Services/Geo/ZonaProcessoService.php`
- Alterar: `app/Services/Solicitacao/ProtocolarSolicitacaoService.php` (logo após `poligonoEnderecamento->aplicar`)
- Alterar: `app/Services/Decisao/DecisionTextCatalog.php` (~linha 183) e `database/seeders/DecisionTextSeeder.php` (~linha 76): chave `zona.motivo.empate_maior_porcao`
- Teste: `tests/Feature/Geo/ZonaProcessoServiceTest.php`

**Interfaces:**
- Consome: `ZonaMaiorPorcaoResolver`, `ZonaMaiorPorcao` (Tarefa 5), `ZonaFonte` (Tarefa 1).
- Produz:
  - `ZonaProcessoService::resolver(ViabilityRequest $request): ?ZonaMaiorPorcao` — null sem polígono do endereçamento; memo herdado do resolvedor.
  - `ZonaProcessoService::materializar(ViabilityRequest $request): ?ZonaMaiorPorcao` — grava e audita.
  - `ZonaProcessoService::motivoEmpate(ZonaMaiorPorcao $resultado): string`.
  - Auditoria `logName: 'geo'`, `event: 'zona-maior-porcao'`, `rulesVersion: 'lei-9148-2016-art18'`, propriedades `viability_request_id`, `status`, `zona_vencedora`, `porcoes`, `empate`, `camadas`, `area_lote_m2`, `motivo`.

Regras de gravação:
- Sem polígono do endereçamento: se `zona_codigo` existe, `zona_fonte = ponto`; nada auditado.
- `identificado`: `zona_codigo = codigo`, `zona_fonte = enderecamento_maior_porcao`.
- Outro status: se a fonte já for `enderecamento_maior_porcao`, não mexe (a degradação não apaga dado bom); senão `zona_fonte = ponto` quando há `zona_codigo`. Empate nunca grava zona vencedora.

- [ ] **Passo 1: Escrever os testes que falham**

```php
<?php

namespace Tests\Feature\Geo;

use App\Enums\ZonaFonte;
use App\Models\ViabilityRequest;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Geo\ZonaProcessoService;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class ZonaProcessoServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOTE = ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.979], [-38.5, -12.98]]]];

    private FakeZonaMaiorPorcaoResolver $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DecisionTextSeeder::class);
        $this->fake = new FakeZonaMaiorPorcaoResolver;
        $this->app->instance(ZonaMaiorPorcaoResolver::class, $this->fake);
    }

    private function processo(?array $enderecamento = self::LOTE, ?string $zonaPonto = 'ZEIS'): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['address_polygon_geojson' => $enderecamento, 'zona_codigo' => $zonaPonto])->save();

        return $request->fresh();
    }

    public function test_ca_zn_01_maior_porcao_grava_zona_e_fonte(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::identificado('ZPR 3', [
            ['zona' => 'ZPR 3', 'area_m2' => 780.0, 'percentual' => 78.0],
            ['zona' => 'ZEIS', 'area_m2' => 220.0, 'percentual' => 22.0],
        ]));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $request->refresh();
        $this->assertSame('ZPR 3', $request->zona_codigo);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
    }

    public function test_ca_au_01_audita_vencedora_porcoes_e_camadas(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::identificado('ZPR 3'));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $auditoria = Activity::query()->where('event', 'zona-maior-porcao')->where('subject_id', $request->id)->sole();
        $this->assertSame('ZPR 3', $auditoria->properties['zona_vencedora']);
        $this->assertSame('ZPR 3', $auditoria->properties['porcoes'][0]['zona']);
        $this->assertSame('louos_teste:VM_TESTE', $auditoria->properties['camadas'][0]['type_name']);
        $this->assertSame('identificado', $auditoria->properties['status']);
    }

    public function test_ca_zn_03_indisponivel_mantem_a_zona_do_ponto_e_registra_o_motivo(): void
    {
        $this->fake->para(self::LOTE, ZonaMaiorPorcao::indisponivel('Camada de zona sem resposta do GeoServer SEDUR: louos_zeis.'));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $request->refresh();
        $this->assertSame('ZEIS', $request->zona_codigo);
        $this->assertSame(ZonaFonte::Ponto, $request->zona_fonte);
        $auditoria = Activity::query()->where('event', 'zona-maior-porcao')->where('subject_id', $request->id)->sole();
        $this->assertStringContainsString('louos_zeis', $auditoria->properties['motivo']);
    }

    public function test_ca_zn_04_empate_nao_grava_zona_vencedora(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::empate('ZPR 3', 'ZEIS'));
        $request = $this->processo(zonaPonto: null);

        $resultado = app(ZonaProcessoService::class)->materializar($request);

        $this->assertNull($request->fresh()->zona_codigo);
        $motivo = app(ZonaProcessoService::class)->motivoEmpate($resultado);
        $this->assertStringContainsString('ZPR 3', $motivo);
        $this->assertStringContainsString('ZEIS', $motivo);
    }

    public function test_sem_poligono_do_enderecamento_marca_a_fonte_ponto_sem_consultar(): void
    {
        $request = $this->processo(enderecamento: null);

        $this->assertNull(app(ZonaProcessoService::class)->materializar($request));
        $this->assertSame(ZonaFonte::Ponto, $request->fresh()->zona_fonte);
        $this->assertSame(0, $this->fake->chamadas);
    }

    public function test_degradacao_nao_apaga_a_zona_ja_decidida_por_area(): void
    {
        $this->fake->para(self::LOTE, ZonaMaiorPorcao::indisponivel('GeoServer fora.'));
        $request = $this->processo(zonaPonto: 'ZPR 3');
        $request->forceFill(['zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao])->save();

        app(ZonaProcessoService::class)->materializar($request);

        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->fresh()->zona_fonte);
        $this->assertSame('ZPR 3', $request->fresh()->zona_codigo);
    }
}
```

Acrescentar ao final de `tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php` (fluxo completo do protocolo com o fake):

```php
public function test_protocolo_com_poligono_decide_a_zona_pela_maior_porcao(): void
{
    $this->fakeCadastro('212');
    $this->ligarEnderecamento();
    $this->enderecamento([['complemento' => null, 'geometria' => $this->quadrado(-38.5)]]);
    $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)
        ->para($this->quadrado(-38.5), FakeZonaMaiorPorcaoResolver::identificado('ZPR 3')));

    $request = $this->protocolar();

    $this->assertSame('ZPR 3', $request->zona_codigo);
    $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
}
```

E em `test_ca_pe_06_...`, acrescentar `$this->assertSame(ZonaFonte::Ponto, $request->zona_fonte);`.

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/ZonaProcessoServiceTest.php tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php`
Esperado: FAIL — `ZonaProcessoService` inexistente.

- [ ] **Passo 3: Texto do empate**

Em `DecisionTextCatalog::defaults()`, depois de `localizacao.motivo.divergencia_zona`:

```php
'zona.motivo.empate_maior_porcao' => 'Zona urbanística indefinida (art. 18, § 2º, da Lei nº 9.148/2016): o terreno tem porções iguais nas zonas :zona_a e :zona_b — segue para análise técnica.',
```

Em `DecisionTextSeeder` (mapa de descrições), na mesma posição:

```php
'zona.motivo.empate_maior_porcao' => 'Motivo do encaminhamento à análise quando o polígono do endereçamento tem porções iguais em duas zonas (art. 18, § 2º, da LOUOS). Placeholders: :zona_a, :zona_b.',
```

- [ ] **Passo 4: Implementar o serviço**

```php
<?php

namespace App\Services\Geo;

use App\Enums\ZonaFonte;
use App\Models\ViabilityRequest;
use App\Services\Decisao\DecisionTextCatalog;
use App\Support\Audit\AuditService;

/**
 * Zona do processo pela maior porção do polígono do endereçamento (art. 18,
 * § 2º, da Lei 9.148/2016). Sem polígono, ou com o cálculo indisponível, a
 * zona continua a do ponto e `zona_fonte` diz qual regra decidiu. Empate não
 * grava zona vencedora: o fluxo expresso encaminha à análise.
 */
class ZonaProcessoService
{
    public function __construct(
        private ZonaMaiorPorcaoResolver $maiorPorcao,
        private DecisionTextCatalog $textos,
        private AuditService $audit,
    ) {}

    public function resolver(ViabilityRequest $request): ?ZonaMaiorPorcao
    {
        $poligono = $request->address_polygon_geojson;

        return is_array($poligono) && $poligono !== [] ? $this->maiorPorcao->resolver($poligono) : null;
    }

    public function materializar(ViabilityRequest $request): ?ZonaMaiorPorcao
    {
        $resultado = $this->resolver($request);

        if ($resultado === null) {
            $this->marcarPonto($request);

            return null;
        }

        $this->auditar($request, $resultado);

        if ($resultado->identificado()) {
            $request->forceFill([
                'zona_codigo' => $resultado->codigo,
                'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
            ])->save();

            return $resultado;
        }

        if ($request->zona_fonte !== ZonaFonte::EnderecamentoMaiorPorcao) {
            $this->marcarPonto($request);
        }

        return $resultado;
    }

    public function motivoEmpate(ZonaMaiorPorcao $resultado): string
    {
        return $this->textos->render('zona.motivo.empate_maior_porcao', [
            ':zona_a' => $resultado->empate[0] ?? '—',
            ':zona_b' => $resultado->empate[1] ?? '—',
        ]);
    }

    private function marcarPonto(ViabilityRequest $request): void
    {
        if ($request->zona_codigo !== null && $request->zona_fonte !== ZonaFonte::Ponto) {
            $request->forceFill(['zona_fonte' => ZonaFonte::Ponto])->save();
        }
    }

    private function auditar(ViabilityRequest $request, ZonaMaiorPorcao $resultado): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'zona-maior-porcao',
            description: "Zona pela maior porção ({$resultado->status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'status' => $resultado->status,
                'zona_vencedora' => $resultado->codigo,
                'porcoes' => $resultado->porcoes,
                'empate' => $resultado->empate,
                'camadas' => $resultado->camadas,
                'area_lote_m2' => $resultado->areaLoteM2,
                'motivo' => $resultado->motivo,
            ],
            subject: $request,
            result: $resultado->status,
            rulesVersion: 'lei-9148-2016-art18',
        );
    }
}
```

- [ ] **Passo 5: Encadear no protocolo**

`ProtocolarSolicitacaoService`: injetar `private ZonaProcessoService $zonaProcesso,` e, logo após `$this->poligonoEnderecamento->aplicar($request);`:

```php
$this->zonaProcesso->materializar($request);
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/ZonaProcessoServiceTest.php tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php tests/Feature/Seeders/DecisionTextSeederTest.php tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php`
Esperado: PASS.

- [ ] **Passo 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/ZonaProcessoService.php app/Services/Solicitacao/ProtocolarSolicitacaoService.php app/Services/Decisao/DecisionTextCatalog.php database/seeders/DecisionTextSeeder.php tests/Feature/Geo/ZonaProcessoServiceTest.php tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php
git commit -m "feat: grava a zona da maior porção do lote e a fonte da zona no processo"
```

---

### Tarefa 7: Motor LOUOS usa a zona da maior porção

**Arquivos:**
- Alterar: `app/Services/Solicitacao/SolicitacaoViabilityResolver.php` (construtor ~linha 47; `resolve()` ~linhas 57–77)
- Teste: `tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php` (novo teste)

**Interfaces:**
- Consome: `ViabilityRequest::$zona_fonte`, `$zona_codigo` (Tarefas 1 e 6), `TerritoryService::identify()`, `ConsultaViabilidadeService::consultarComTerritorio()`.
- Produz: quando `zona_fonte === EnderecamentoMaiorPorcao`, o Quadro 10/11A recebe a zona gravada (`versao_camada = 'geoserver-wfs-maior-porcao'`); bairro, via, lote e restrições continuam do ponto. Ordem: território do snapshot REGIN → zona por área → ponto → só CNAE.

- [ ] **Passo 1: Escrever o teste que falha**

Em `SolicitacaoViabilityResolverTest`:

```php
public function test_zona_da_maior_porcao_substitui_a_zona_do_ponto_no_quadro_10(): void
{
    // Ponto cai em ZR-1 (proibido); a maior porção do lote é ZR-2 (permitido).
    $this->fakeBairroComZona('ZR-1');
    $this->seedTratamento('4712100', 'nR1', 'nR1-01');
    $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::NaoPermitido);
    $this->seedQuadro10('ZR-2', 'nR1', Quadro10Permissao::Permitido);

    $request = $this->draftComCnaes(['4712100']);
    $request->forceFill(['zona_codigo' => 'ZR-2', 'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao])->save();

    $resolved = $this->resolver()->resolve($request->fresh());

    $this->assertSame(ResultadoViabilidade::Permitido->value, $resolved->consolidado);
    $territorio = $resolved->por_cnae[0]['consulta_array']['territorio'];
    $this->assertSame('ZR-2', $territorio['zona']['nome']);
    $this->assertSame('Comércio', $territorio['bairro']['nome']);
}

public function test_zona_pelo_ponto_continua_quando_a_fonte_e_o_ponto(): void
{
    $this->fakeBairroComZona('ZR-1');
    $this->seedTratamento('4712100', 'nR1', 'nR1-01');
    $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::NaoPermitido);

    $request = $this->draftComCnaes(['4712100']);
    $request->forceFill(['zona_codigo' => 'ZR-1', 'zona_fonte' => ZonaFonte::Ponto])->save();

    $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $this->resolver()->resolve($request->fresh())->consolidado);
}
```

Imports: `App\Enums\ZonaFonte`. Se `Quadro10Permissao::NaoPermitido` tiver outro nome no enum, usar o caso que `test_consolida_pior_caso_nao_permitido_entre_os_cnaes` já usa.

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php --filter=maior_porcao`
Esperado: FAIL — consolidado `nao_permitido` (zona do ponto).

- [ ] **Passo 3: Implementar**

Injetar `private TerritoryService $territory` no construtor (import `App\Services\Geo\TerritoryService`, `App\Services\Geo\TerritoryResult`, `App\Enums\ZonaFonte`). Em `resolve()`, antes do `foreach`:

```php
$territorioPorArea = $ponto !== null ? $this->territorioComZonaDoLote($request, $ponto) : null;
```

E a escolha da consulta:

```php
$territorio = ReginProtocoloSimulacaoService::territorioDoSnapshot(
    is_array($request->simulation_snapshot) ? $request->simulation_snapshot : [],
) ?? $territorioPorArea;

$result = $territorio !== null
    ? $this->consulta->consultarComTerritorio($territorio, $cnae->code, $area, $tipoImovel, $respostas)
    : ($ponto === null
        ? $this->consulta->consultarPorCnae($cnae->code, $area, $tipoImovel, $respostas)
        : $this->consulta->consultarPorPontoConhecido($ponto['lat'], $ponto['lng'], $cnae->code, $area, $tipoImovel, $respostas));
```

Método novo:

```php
/**
 * Com a zona decidida pela maior porção do endereçamento (art. 18, § 2º),
 * o enquadramento recebe essa zona; bairro, via e restrições continuam do
 * ponto do imóvel.
 *
 * @param  array{lat: float, lng: float}  $ponto
 */
private function territorioComZonaDoLote(ViabilityRequest $request, array $ponto): ?TerritoryResult
{
    if ($request->zona_fonte !== ZonaFonte::EnderecamentoMaiorPorcao || blank($request->zona_codigo)) {
        return null;
    }

    $base = $this->territory->identify($ponto['lat'], $ponto['lng']);

    return new TerritoryResult(
        bairro: $base->bairro,
        via: $base->via,
        zona: [
            'status' => 'identificado',
            'nome' => $request->zona_codigo,
            'propriedades' => ['ZONA' => $request->zona_codigo, 'fonte' => ZonaFonte::EnderecamentoMaiorPorcao->value],
            'motivo' => null,
            'versao_camada' => 'geoserver-wfs-maior-porcao',
        ],
        lote: $base->lote,
        restricoes: $base->restricoes,
    );
}
```

Atualizar o docblock da classe: a zona do processo pode vir da maior porção do endereçamento.

- [ ] **Passo 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php`
Esperado: PASS (todos, inclusive os antigos).

- [ ] **Passo 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Solicitacao/SolicitacaoViabilityResolver.php tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php
git commit -m "feat: enquadramento usa a zona da maior porção do lote"
```

---

### Tarefa 8: Inventário de camadas com fonte e porções

**Arquivos:**
- Alterar: `app/Services/Geo/CamadasIncidentesService.php` (construtor, `identificar()`, novo `zona()`)
- Teste: `tests/Feature/Geo/CamadasIncidentesServiceTest.php` (novos testes)

**Interfaces:**
- Consome: `ZonaProcessoService::resolver()` (Tarefa 6).
- Produz em `camadas_incidentes['zona']`: `{status, nome, fonte: 'ponto'|'enderecamento_maior_porcao'|null, porcoes: list<{zona, area_m2, percentual}>, maior_porcao: {status, motivo, empate}|null}`.

- [ ] **Passo 1: Escrever os testes que falham**

Em `CamadasIncidentesServiceTest` (o `setUp` já liga zona `ZCMe 1/01` pelo ponto):

```php
private const LOTE = ['type' => 'Polygon', 'coordinates' => [[[-38.5165, -12.9778], [-38.5160, -12.9778], [-38.5160, -12.9773], [-38.5165, -12.9773], [-38.5165, -12.9778]]]];

public function test_ca_zn_02_porcoes_ficam_no_inventario_da_maior_para_a_menor(): void
{
    $this->wfsComTrecho(null);
    $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)->para(self::LOTE, FakeZonaMaiorPorcaoResolver::identificado('ZPR 3', [
        ['zona' => 'ZPR 3', 'area_m2' => 780.0, 'percentual' => 78.0],
        ['zona' => 'ZEIS', 'area_m2' => 220.0, 'percentual' => 22.0],
    ])));
    $request = $this->processo();
    $request->forceFill(['address_polygon_geojson' => self::LOTE])->save();

    $zona = app(CamadasIncidentesService::class)->materializar($request->fresh())['zona'];

    $this->assertSame('ZPR 3', $zona['nome']);
    $this->assertSame('enderecamento_maior_porcao', $zona['fonte']);
    $this->assertSame(['ZPR 3', 'ZEIS'], array_column($zona['porcoes'], 'zona'));
    $this->assertSame(78.0, $zona['porcoes'][0]['percentual']);
}

public function test_sem_poligono_do_enderecamento_a_zona_e_do_ponto(): void
{
    $this->wfsComTrecho(null);

    $zona = app(CamadasIncidentesService::class)->materializar($this->processo())['zona'];

    $this->assertSame('ZCMe 1/01', $zona['nome']);
    $this->assertSame('ponto', $zona['fonte']);
    $this->assertSame([], $zona['porcoes']);
    $this->assertNull($zona['maior_porcao']);
}

public function test_maior_porcao_indisponivel_mostra_a_zona_do_ponto_com_o_motivo(): void
{
    $this->wfsComTrecho(null);
    $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)->para(self::LOTE, ZonaMaiorPorcao::indisponivel('GeoServer fora.')));
    $request = $this->processo();
    $request->forceFill(['address_polygon_geojson' => self::LOTE])->save();

    $zona = app(CamadasIncidentesService::class)->materializar($request->fresh())['zona'];

    $this->assertSame('ZCMe 1/01', $zona['nome']);
    $this->assertSame('ponto', $zona['fonte']);
    $this->assertSame('indisponivel', $zona['maior_porcao']['status']);
    $this->assertSame('GeoServer fora.', $zona['maior_porcao']['motivo']);
}
```

Imports: `App\Services\Geo\ZonaMaiorPorcao`, `App\Services\Geo\ZonaMaiorPorcaoResolver`, `Tests\Support\Geo\FakeZonaMaiorPorcaoResolver`.

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Geo/CamadasIncidentesServiceTest.php`
Esperado: FAIL — chave `fonte` ausente.

- [ ] **Passo 3: Implementar**

Injetar `private ZonaProcessoService $zonaProcesso` no construtor. Em `identificar()`:

```php
if ($ponto === null) {
    $inventario['zona'] = $this->zona($request, null);

    return $inventario;
}

$territorio = $this->territorio->identify($ponto['lat'], $ponto['lng']);
// ...
$inventario['zona'] = $this->zona($request, $territorio->zona);
```

Método novo (e remover o uso de `dimensao()` para a zona; `dimensao()` segue para o bairro):

```php
/**
 * Zona do inventário: a da maior porção do endereçamento quando identificada,
 * senão a do ponto, com as porções e o motivo da maior porção quando houve
 * polígono para calcular.
 *
 * @param  array<string, mixed>|null  $zonaPonto
 * @return array{status: string, nome: ?string, fonte: ?string, porcoes: list<array{zona: string, area_m2: float, percentual: float}>, maior_porcao: array{status: string, motivo: ?string, empate: list<string>}|null}|null
 */
private function zona(ViabilityRequest $request, ?array $zonaPonto): ?array
{
    $porArea = $this->zonaProcesso->resolver($request);

    if ($porArea?->identificado()) {
        return [
            'status' => 'identificado',
            'nome' => $porArea->codigo,
            'fonte' => ZonaFonte::EnderecamentoMaiorPorcao->value,
            'porcoes' => $porArea->porcoes,
            'maior_porcao' => ['status' => $porArea->status, 'motivo' => null, 'empate' => []],
        ];
    }

    if ($zonaPonto === null && $porArea === null) {
        return null;
    }

    $ponto = $zonaPonto === null ? ['status' => 'indisponivel', 'nome' => null] : $this->dimensao($zonaPonto);

    return $ponto + [
        'fonte' => $ponto['status'] === 'identificado' ? ZonaFonte::Ponto->value : null,
        'porcoes' => $porArea?->porcoes ?? [],
        'maior_porcao' => $porArea === null ? null : [
            'status' => $porArea->status,
            'motivo' => $porArea->motivo,
            'empate' => $porArea->empate,
        ],
    ];
}
```

Import `App\Enums\ZonaFonte`. Atualizar o docblock da classe: a zona vem da maior porção quando há polígono do endereçamento.

- [ ] **Passo 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Geo/CamadasIncidentesServiceTest.php tests/Feature/Expresso/FluxoExpressoTerritorialTest.php`
Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/CamadasIncidentesService.php tests/Feature/Geo/CamadasIncidentesServiceTest.php
git commit -m "feat: inventário de camadas mostra a fonte da zona e as porções do lote"
```

---

### Tarefa 9: Empate e divergência no fluxo expresso

**Arquivos:**
- Alterar: `app/Enums/TipoGatilho.php` (caso `ZonaEmpate`)
- Alterar: `app/Services/Geo/LocalizacaoDivergenciaService.php` (`avaliarEnderecamento()`)
- Alterar: `app/Services/Decisao/DecisionTextCatalog.php`, `database/seeders/DecisionTextSeeder.php` (chave `localizacao.motivo.divergencia_zona_enderecamento`)
- Alterar: `app/Services/Expresso/FluxoExpressoService.php` (construtor ~linha 71; `decidirSobLock()` ~linhas 217–248; `capturarQueda()` ~linha 480)
- Teste: `tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php`

**Interfaces:**
- Consome: `ZonaProcessoService::materializar()`, `motivoEmpate()` (Tarefa 6); `ZonaMaiorPorcaoResolver` (Tarefa 5).
- Produz:
  - `TipoGatilho::ZonaEmpate = 'zona_empate'`, label `'Zona com porções iguais'`.
  - `LocalizacaoDivergenciaService::avaliarEnderecamento(ViabilityRequest $request, ?ZonaMaiorPorcao $zonaEnderecamento): ?string` — motivo ou null. Auditoria `event: 'zona-divergencia-enderecamento'`, propriedades `viability_request_id`, `resultado` (`coincide|diverge|nao_verificada`), `motivo`, `zona_desenho`, `zona_enderecamento`.

Ordem no `decidirSobLock()` (decisão 9): `materializar` da zona antes do `resolver->resolve` (o motor lê a zona gravada) → RT-01 indefere → empate → divergência endereçamento × desenho → RN-005 existente → poligonais → resto.

- [ ] **Passo 1: Escrever os testes que falham**

Base: copiar de `FluxoExpressoLocalizacaoDivergenteTest` o `setUp()`, `protocoladaComCnaes()`, `setupDeferivel()` e `fakeCadastro()` (linhas 40–172). O `setupDeferivel()` deixa o ponto em `ZCN-1` e o Quadro 10 permite `nR1` em `ZCN-1`. Acrescentar:

```php
private const DESENHO = ['type' => 'Polygon', 'coordinates' => [[[-38.5110, -12.9710], [-38.5105, -12.9710], [-38.5105, -12.9705], [-38.5110, -12.9705], [-38.5110, -12.9710]]]];

private const ENDERECAMENTO = ['type' => 'Polygon', 'coordinates' => [[[-38.5111, -12.9711], [-38.5104, -12.9711], [-38.5104, -12.9704], [-38.5111, -12.9704], [-38.5111, -12.9711]]]];

private function zonas(ZonaMaiorPorcao $enderecamento, ?ZonaMaiorPorcao $desenho = null): FakeZonaMaiorPorcaoResolver
{
    $fake = (new FakeZonaMaiorPorcaoResolver)->para(self::ENDERECAMENTO, $enderecamento);

    if ($desenho !== null) {
        $fake->para(self::DESENHO, $desenho);
    }

    $this->app->instance(ZonaMaiorPorcaoResolver::class, $fake);

    return $fake;
}

private function comPoligonos(ViabilityRequest $request): ViabilityRequest
{
    $request->forceFill([
        'property_polygon_geojson' => self::DESENHO,
        'address_polygon_geojson' => self::ENDERECAMENTO,
        'zona_codigo' => null,
    ])->save();

    return $request->fresh();
}
```

Testes:

```php
public function test_ca_zn_04_empate_encaminha_com_as_duas_zonas_e_nao_grava_vencedora(): void
{
    Event::fake([ResultadoEmitido::class]);
    $this->fakeCadastro(-12.9707, -38.5107);
    $request = $this->comPoligonos($this->setupDeferivel());
    $this->zonas(FakeZonaMaiorPorcaoResolver::empate('ZCN-1', 'ZPR 3'));

    $result = app(FluxoExpressoService::class)->decide($request);

    $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
    $this->assertStringContainsString('ZCN-1', (string) $result->reason);
    $this->assertStringContainsString('ZPR 3', (string) $result->reason);
    $this->assertSame(TipoGatilho::ZonaEmpate->value, ExpressoQueda::query()->where('viability_request_id', $request->id)->first()?->tipo_gatilho);
    $this->assertNotSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->fresh()->zona_fonte);
}

public function test_ca_dv_01_desenho_em_outra_zona_encaminha_e_mantem_a_zona_do_enderecamento(): void
{
    Event::fake([ResultadoEmitido::class]);
    $this->fakeCadastro(-12.9707, -38.5107);
    $request = $this->comPoligonos($this->setupDeferivel());
    $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'), FakeZonaMaiorPorcaoResolver::identificado('ZPR 3'));

    $result = app(FluxoExpressoService::class)->decide($request);

    $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
    $this->assertStringContainsString('ZCN-1', (string) $result->reason);
    $this->assertStringContainsString('ZPR 3', (string) $result->reason);
    $this->assertSame('ZCN-1', $request->fresh()->zona_codigo);
    $this->assertSame(TipoGatilho::LocalizacaoDivergente->value, ExpressoQueda::query()->where('viability_request_id', $request->id)->first()?->tipo_gatilho);
}

public function test_ca_dv_02_zonas_iguais_seguem_o_fluxo_e_a_coincidencia_e_auditada(): void
{
    Event::fake([ResultadoEmitido::class]);
    $this->fakeCadastro(-12.9707, -38.5107);
    $request = $this->comPoligonos($this->setupDeferivel());
    $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'), FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'));

    $result = app(FluxoExpressoService::class)->decide($request);

    $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
    $auditoria = Activity::query()->where('event', 'zona-divergencia-enderecamento')->where('subject_id', $request->id)->first();
    $this->assertSame('coincide', $auditoria?->properties['resultado']);
}

public function test_zona_da_maior_porcao_decide_o_quadro_10(): void
{
    Event::fake([ResultadoEmitido::class]);
    $this->fakeCadastro(-12.9707, -38.5107);
    $request = $this->setupDeferivel();
    // Ponto do imóvel em zona sem permissão; a maior porção do lote é ZCN-1.
    $fake = new FakeSpatialRepository;
    $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
    $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZPR 1']]);
    $this->app->instance(SpatialRepository::class, $fake);
    $request->forceFill(['property_polygon_geojson' => null, 'property_point_lat' => -12.9707, 'property_point_lng' => -38.5107, 'address_polygon_geojson' => self::ENDERECAMENTO])->save();
    $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'));

    $result = app(FluxoExpressoService::class)->decide($request->fresh());

    $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
    $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->fresh()->zona_fonte);
    $this->assertSame('enderecamento_maior_porcao', $request->fresh()->camadas_incidentes['zona']['fonte']);
}

public function test_rt01_tem_precedencia_sobre_o_empate(): void
{
    Event::fake([ResultadoEmitido::class]);
    $this->fakeCadastro(-12.9707, -38.5107);
    $request = $this->comPoligonos($this->setupDeferivel('4520006'));
    $request->forceFill(['cod_log' => '212'])->save();
    $this->zonas(FakeZonaMaiorPorcaoResolver::empate('ZCN-1', 'ZPR 3'));

    $result = app(FluxoExpressoService::class)->decide($request->fresh());

    $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);
}

public function test_ca_zn_08_uma_consulta_por_poligono_na_decisao(): void
{
    Event::fake([ResultadoEmitido::class]);
    $this->fakeCadastro(-12.9707, -38.5107);
    $request = $this->comPoligonos($this->setupDeferivel());
    $fake = $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'), FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'));

    app(FluxoExpressoService::class)->decide($request);

    // Endereçamento: materializar + inventário; desenho: divergência. O fake
    // conta chamadas; o memo real fica provado no teste postgis do resolvedor.
    $this->assertLessThanOrEqual(3, $fake->chamadas);
}
```

Imports: `App\Enums\ZonaFonte`, `App\Services\Geo\ZonaMaiorPorcao`, `App\Services\Geo\ZonaMaiorPorcaoResolver`, `Tests\Support\Geo\FakeZonaMaiorPorcaoResolver`, além dos do arquivo de origem.

Se o `setupDeferivel('4520006')` não indeferir pela RT-01 aqui (depende do `TerritorialRuleSeeder` e do CODLOG `212`), seguir exatamente o arranjo de `test_rt01_tem_precedencia_sobre_a_divergencia` do arquivo de origem.

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php`
Esperado: FAIL — `TipoGatilho::ZonaEmpate` inexistente / deferimentos indevidos.

- [ ] **Passo 3: Gatilho e texto**

`TipoGatilho`:

```php
// Art. 18, § 2º, da Lei 9.148/2016: o polígono do endereçamento tem porções
// iguais em duas zonas e a lei não diz qual prevalece.
case ZonaEmpate = 'zona_empate';
```

e no `label()`: `self::ZonaEmpate => 'Zona com porções iguais',`.

`DecisionTextCatalog::defaults()`:

```php
'localizacao.motivo.divergencia_zona_enderecamento' => 'Localização divergente: o polígono desenhado está na zona :zona_desenho e o polígono do endereçamento SEDUR, na zona :zona_enderecamento — segue para análise técnica.',
```

`DecisionTextSeeder`:

```php
'localizacao.motivo.divergencia_zona_enderecamento' => 'Motivo do encaminhamento à análise quando o polígono desenhado e o polígono do endereçamento SEDUR caem em zonas diferentes pela maior porção. Placeholders: :zona_desenho, :zona_enderecamento.',
```

- [ ] **Passo 4: `avaliarEnderecamento()`**

Em `LocalizacaoDivergenciaService`, injetar `private ZonaMaiorPorcaoResolver $maiorPorcao` e acrescentar:

```php
/**
 * Com o polígono desenhado e o do endereçamento, a zona de cada um pela
 * maior porção precisa coincidir. Diferente, a decisão automática é bloqueada;
 * a zona gravada continua a do endereçamento.
 */
public function avaliarEnderecamento(ViabilityRequest $request, ?ZonaMaiorPorcao $zonaEnderecamento): ?string
{
    if (blank($request->property_polygon_geojson) || $zonaEnderecamento === null || ! $zonaEnderecamento->identificado()) {
        return null;
    }

    $zonaDesenho = $this->maiorPorcao->resolver($request->property_polygon_geojson);

    if (! $zonaDesenho->identificado()) {
        $this->auditarEnderecamento($request, 'nao_verificada', $zonaDesenho->motivo, null, $zonaEnderecamento->codigo);

        return null;
    }

    if ($zonaDesenho->codigo === $zonaEnderecamento->codigo) {
        $this->auditarEnderecamento($request, 'coincide', null, $zonaDesenho->codigo, $zonaEnderecamento->codigo);

        return null;
    }

    $motivo = $this->textos->render('localizacao.motivo.divergencia_zona_enderecamento', [
        ':zona_desenho' => (string) $zonaDesenho->codigo,
        ':zona_enderecamento' => (string) $zonaEnderecamento->codigo,
    ]);

    $this->auditarEnderecamento($request, 'diverge', $motivo, $zonaDesenho->codigo, $zonaEnderecamento->codigo);

    return $motivo;
}

private function auditarEnderecamento(ViabilityRequest $request, string $resultado, ?string $motivo, ?string $zonaDesenho, ?string $zonaEnderecamento): void
{
    $this->audit->log(
        logName: 'geo',
        event: 'zona-divergencia-enderecamento',
        description: "Zona do desenho × endereçamento na solicitação #{$request->id}: {$resultado}",
        properties: [
            'viability_request_id' => $request->id,
            'resultado' => $resultado,
            'motivo' => $motivo,
            'zona_desenho' => $zonaDesenho,
            'zona_enderecamento' => $zonaEnderecamento,
        ],
        subject: $request,
        result: $resultado,
    );
}
```

Atualizar o docblock da classe para citar as duas verificações.

- [ ] **Passo 5: Fluxo expresso**

Injetar `private ZonaProcessoService $zonaProcesso` no construtor. Em `decidirSobLock()`, trocar o trecho a partir de `$resolved = $this->resolver->resolve($request);` por:

```php
// Zona pela maior porção do endereçamento (art. 18, § 2º) gravada antes
// do motor, que enquadra com a zona do processo.
$zonaPorArea = $this->zonaProcesso->materializar($request);

// Reexecução FRESCA dos motores — a decisão é autoritativa, não o snapshot.
$resolved = $this->resolver->resolve($request);
```

Depois do `if ($territorial->status === TerritorialRuleVerdict::INDEFERIR) { ... }` e antes da divergência RN-005:

```php
// Empate de porções: a lei não diz qual zona prevalece.
if ($zonaPorArea?->status === ZonaMaiorPorcao::EMPATE) {
    return $this->encaminharAnalise(
        $request,
        $this->zonaProcesso->motivoEmpate($zonaPorArea),
        $actor,
        $resolved,
        gatilho: TipoGatilho::ZonaEmpate,
    );
}

// Desenho do requerente em zona diferente do endereçamento SEDUR.
$divergenciaEnderecamento = $this->localizacaoDivergencia->avaliarEnderecamento($request, $zonaPorArea);

if ($divergenciaEnderecamento !== null) {
    return $this->encaminharAnalise(
        $request,
        $divergenciaEnderecamento,
        $actor,
        $resolved,
        gatilho: TipoGatilho::LocalizacaoDivergente,
    );
}
```

Em `capturarQueda()`, trocar `if ($gatilho === TipoGatilho::LocalizacaoDivergente) {` por:

```php
if ($gatilho === TipoGatilho::LocalizacaoDivergente || $gatilho === TipoGatilho::ZonaEmpate) {
```

Imports: `App\Services\Geo\ZonaProcessoService`, `App\Services\Geo\ZonaMaiorPorcao`.

- [ ] **Passo 6: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php tests/Feature/Expresso/FluxoExpressoLocalizacaoDivergenteTest.php tests/Feature/Expresso/FluxoExpressoTerritorialTest.php tests/Feature/Seeders/DecisionTextSeederTest.php`
Esperado: PASS.

- [ ] **Passo 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/TipoGatilho.php app/Services/Geo/LocalizacaoDivergenciaService.php app/Services/Decisao/DecisionTextCatalog.php database/seeders/DecisionTextSeeder.php app/Services/Expresso/FluxoExpressoService.php tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php
git commit -m "feat: expresso encaminha empate de zona e divergência entre desenho e endereçamento"
```

---

### Tarefa 10: Props da ficha de análise e do detalhe do processo

**Arquivos:**
- Alterar: `app/Http/Controllers/Gestao/AnalysisRecordController.php` (`localizacaoDoImovel()` ~linha 446; `camadasComZonaEVia()` ~linha 487)
- Alterar: `app/Http/Controllers/Gestao/ProcessoController.php` (bloco `'geo'` ~linha 229)
- Teste: `tests/Feature/Analise/FichaUiSmokeTest.php`, `tests/Feature/Analise/ProcessoUiSmokeTest.php` (novos testes)

**Interfaces:**
- Produz: `localizacao.poligono_enderecamento` e `geo.poligono_enderecamento` (`?array` GeoJSON); `localizacao.camadas.zona.fonte` quando a zona é sintetizada de `zona_codigo`.

- [ ] **Passo 1: Escrever os testes que falham**

Em `FichaUiSmokeTest`, seguindo o arranjo de `test_ficha_mostra_as_camadas_do_imovel` (linha 177) para criar o processo e o usuário com `analisar-processos`:

```php
public function test_ca_fc_01_ficha_expoe_o_poligono_do_enderecamento(): void
{
    // Mesmo arranjo de test_ficha_mostra_as_camadas_do_imovel; depois de criar $processo:
    $processo->forceFill(['address_polygon_geojson' => ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]]])->save();

    // GET na mesma rota do teste-modelo:
    $resposta->assertInertia(fn (Assert $page) => $page
        ->where('localizacao.poligono_enderecamento.type', 'Polygon')
        ->where('localizacao.poligono.type', 'Polygon'));
}

public function test_ficha_sem_poligono_do_enderecamento_entrega_nulo(): void
{
    // Mesmo arranjo, sem address_polygon_geojson.
    $resposta->assertInertia(fn (Assert $page) => $page->where('localizacao.poligono_enderecamento', null));
}
```

Em `ProcessoUiSmokeTest`, seguindo o teste que valida `geo.poligono.type` (linha ~127):

```php
public function test_ca_fc_02_detalhe_expoe_o_poligono_do_enderecamento(): void
{
    // Mesmo arranjo do teste-modelo; antes do GET:
    $processo->forceFill(['address_polygon_geojson' => ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]]])->save();

    $resposta->assertInertia(fn (Assert $page) => $page->where('geo.poligono_enderecamento.type', 'Polygon'));
}
```

O executor copia literalmente o corpo (fábricas, usuário, `actingAs`, rota) do teste-modelo indicado e só acrescenta o `forceFill` e as asserções acima.

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php tests/Feature/Analise/ProcessoUiSmokeTest.php --filter=enderecamento`
Esperado: FAIL — prop ausente.

- [ ] **Passo 3: Implementar**

`AnalysisRecordController::localizacaoDoImovel()`: no array devolvido, depois de `'poligono'`:

```php
'poligono_enderecamento' => $request->address_polygon_geojson,
```

e acrescentar `poligono_enderecamento: array<string, mixed>|null,` no `@return` do docblock. Trocar a chamada por `$this->camadasComZonaEVia($request->camadas_incidentes, $zona, $via, $request->zona_fonte?->value)` e, em `camadasComZonaEVia()`, receber `?string $fonteZona` e sintetizar:

```php
$camadas['zona'] = [
    'status' => 'identificado',
    'nome' => $zona,
    'fonte' => $fonteZona,
];
```

`ProcessoController`, bloco `'geo'`, depois de `'poligono'`:

```php
'poligono_enderecamento' => $viabilityRequest->address_polygon_geojson,
```

E ajustar `'ponto'` para continuar null só quando há desenho (sem mudança de regra): manter como está.

- [ ] **Passo 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php tests/Feature/Analise/ProcessoUiSmokeTest.php`
Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/AnalysisRecordController.php app/Http/Controllers/Gestao/ProcessoController.php tests/Feature/Analise/FichaUiSmokeTest.php tests/Feature/Analise/ProcessoUiSmokeTest.php
git commit -m "feat: ficha e detalhe do processo expõem o polígono do endereçamento"
```

---

### Tarefa 11: UI — mapa com os dois polígonos e linha da zona

**Arquivos:**
- Criar: `resources/js/components/geo/geometria.ts` + `resources/js/components/geo/geometria.test.ts`
- Criar: `resources/js/components/geo/camadas-imovel-zona.ts` + `resources/js/components/geo/camadas-imovel-zona.test.ts`
- Alterar: `resources/js/components/geo/map-imovel.tsx` (`MapLayer` ~linha 15; render ~linha 107)
- Alterar: `resources/js/components/geo/camadas-imovel.tsx`
- Alterar: `resources/js/pages/gestao/ficha-analise/show.tsx` (tipo ~linha 104; card “Polígono” ~linhas 1504 e 1629–1660)
- Alterar: `resources/js/pages/gestao/processos/show.tsx` (tipo ~linha 54; centro ~linha 182; mapa ~linha 600)

**Interfaces:**
- Consome: props da Tarefa 10 e `camadas_incidentes.zona` da Tarefa 8.
- Produz:
  - `centroDaGeometria(g: GeoJsonObject | null | undefined): { lat: number; lng: number } | null` — média dos vértices do anel exterior (Polygon) ou do primeiro polígono (MultiPolygon).
  - `textoZona(zona?: ZonaCamada | null): string`.
  - `MapLayer.estilo?: PathOptions`.
  - Constante `ESTILO_ENDERECAMENTO: PathOptions` exportada de `geometria.ts`.

- [ ] **Passo 1: Testes Vitest que falham**

`resources/js/components/geo/camadas-imovel-zona.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { textoZona } from './camadas-imovel-zona';

describe('textoZona', () => {
    it('mostra a fonte e o percentual de cada porção quando há mais de uma', () => {
        expect(
            textoZona({
                status: 'identificado',
                nome: 'ZPR 3',
                fonte: 'enderecamento_maior_porcao',
                porcoes: [
                    { zona: 'ZPR 3', area_m2: 780, percentual: 78 },
                    { zona: 'ZEIS', area_m2: 220, percentual: 22 },
                ],
            }),
        ).toBe('ZPR 3 — maior porção do lote (ZPR 3 78,0% · ZEIS 22,0%)');
    });

    it('omite os percentuais com uma porção só', () => {
        expect(
            textoZona({
                status: 'identificado',
                nome: 'ZPR 3',
                fonte: 'enderecamento_maior_porcao',
                porcoes: [{ zona: 'ZPR 3', area_m2: 1000, percentual: 100 }],
            }),
        ).toBe('ZPR 3 — maior porção do lote');
    });

    it('marca a zona do ponto da inscrição', () => {
        expect(textoZona({ status: 'identificado', nome: 'ZEIS', fonte: 'ponto', porcoes: [] })).toBe('ZEIS — ponto da inscrição');
    });

    it('mantém o texto antigo sem fonte e nos estados degradados', () => {
        expect(textoZona({ status: 'identificado', nome: 'ZCMe 1/01' })).toBe('ZCMe 1/01');
        expect(textoZona({ status: 'indisponivel', nome: null })).toBe('Camada indisponível');
        expect(textoZona({ status: 'nao_encontrado', nome: null })).toBe('Não identificado');
        expect(textoZona(null)).toBe('—');
    });
});
```

`resources/js/components/geo/geometria.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { centroDaGeometria } from './geometria';

const anel = [
    [-38.5, -12.98],
    [-38.498, -12.98],
    [-38.498, -12.978],
    [-38.5, -12.978],
    [-38.5, -12.98],
];

describe('centroDaGeometria', () => {
    it('calcula o centro do anel exterior de um Polygon sem repetir o vértice de fechamento', () => {
        expect(centroDaGeometria({ type: 'Polygon', coordinates: [anel] } as never)).toEqual({ lat: -12.979, lng: -38.499 });
    });

    it('usa o primeiro polígono de um MultiPolygon', () => {
        expect(centroDaGeometria({ type: 'MultiPolygon', coordinates: [[anel]] } as never)).toEqual({ lat: -12.979, lng: -38.499 });
    });

    it('devolve null sem geometria', () => {
        expect(centroDaGeometria(null)).toBeNull();
    });
});
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `npx vitest run resources/js/components/geo`
Esperado: FAIL — módulos inexistentes.

- [ ] **Passo 3: Helpers**

`resources/js/components/geo/camadas-imovel-zona.ts`:

```ts
export interface PorcaoZona {
    zona: string;
    area_m2: number;
    percentual: number;
}

export interface ZonaCamada {
    status: string;
    nome: string | null;
    fonte?: 'ponto' | 'enderecamento_maior_porcao' | null;
    porcoes?: PorcaoZona[];
    maior_porcao?: { status: string; motivo: string | null; empate?: string[] } | null;
}

const ROTULO_FONTE: Record<string, string> = {
    ponto: 'ponto da inscrição',
    enderecamento_maior_porcao: 'maior porção do lote',
};

function percentual(valor: number): string {
    return `${valor.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
}

/**
 * Linha da zona no inventário: zona, regra que a decidiu e, havendo mais de
 * uma porção, o percentual de cada (art. 18, § 2º, da LOUOS).
 */
export function textoZona(zona?: ZonaCamada | null): string {
    if (!zona) {
        return '—';
    }

    if (zona.status !== 'identificado' || !zona.nome) {
        return zona.status === 'indisponivel' ? 'Camada indisponível' : 'Não identificado';
    }

    const fonte = zona.fonte ? ROTULO_FONTE[zona.fonte] : undefined;
    const porcoes = zona.porcoes ?? [];
    const detalhe = porcoes.length > 1 ? ` (${porcoes.map((p) => `${p.zona} ${percentual(p.percentual)}`).join(' · ')})` : '';

    return fonte ? `${zona.nome} — ${fonte}${detalhe}` : `${zona.nome}${detalhe}`;
}
```

`resources/js/components/geo/geometria.ts`:

```ts
import type { GeoJsonObject, MultiPolygon, Polygon } from 'geojson';
import type { PathOptions } from 'leaflet';

/** Contorno do polígono do endereçamento SEDUR, distinto do desenho do requerente. */
export const ESTILO_ENDERECAMENTO: PathOptions = { color: '#f79009', weight: 2, dashArray: '6 4', fillOpacity: 0.08 };

/**
 * Centro aproximado (média dos vértices do anel exterior) para centralizar o
 * mini-mapa. Null quando a geometria não é Polygon/MultiPolygon.
 */
export function centroDaGeometria(geometria: GeoJsonObject | null | undefined): { lat: number; lng: number } | null {
    let anel: number[][] | undefined;

    if (geometria?.type === 'Polygon') {
        anel = (geometria as Polygon).coordinates[0];
    } else if (geometria?.type === 'MultiPolygon') {
        anel = (geometria as MultiPolygon).coordinates[0]?.[0];
    }

    if (!anel || anel.length === 0) {
        return null;
    }

    const vertices = anel.length > 1 && anel[0][0] === anel[anel.length - 1][0] && anel[0][1] === anel[anel.length - 1][1] ? anel.slice(0, -1) : anel;
    const soma = vertices.reduce((acc, [lng, lat]) => ({ lat: acc.lat + lat, lng: acc.lng + lng }), { lat: 0, lng: 0 });

    return { lat: soma.lat / vertices.length, lng: soma.lng / vertices.length };
}
```

Se o `toEqual` falhar por arredondamento de ponto flutuante, trocar no teste por `toBeCloseTo` em `lat` e `lng`.

- [ ] **Passo 4: Rodar e ver passar**

Run: `npx vitest run resources/js/components/geo`
Esperado: PASS (7 testes).

- [ ] **Passo 5: Componentes**

`map-imovel.tsx`: em `MapLayer`, acrescentar `estilo?: PathOptions;` (import `type PathOptions` de `leaflet`) e no render `<GeoJSON key={camada.id} data={camada.geojson} style={camada.estilo} />`.

`camadas-imovel.tsx`: importar `{ textoZona, type ZonaCamada }` de `./camadas-imovel-zona`; trocar o tipo `zona?: { status: string; nome: string | null } | null;` por `zona?: ZonaCamada | null;`; trocar a linha `{ camada: 'Zona', elemento: textoDimensao(camadas.zona) }` por `{ camada: 'Zona', elemento: textoZona(camadas.zona) }`.

`ficha-analise/show.tsx`:
- No tipo da localização (~linha 104): `poligono_enderecamento?: GeoJsonObject | null;`.
- Em ~linha 1504:

```ts
const centro = centroideDoPoligono(localizacao?.poligono) ?? centroDaGeometria(localizacao?.poligono_enderecamento);
const temPoligono = centro !== null && (localizacao?.poligono != null || localizacao?.poligono_enderecamento != null);
```

- No `MapaSection` do card “Polígono”, montar as camadas:

```tsx
camadas={[
    ...(localizacao?.poligono ? [{ id: 'imovel', type: 'imovel', geojson: localizacao.poligono as GeoJsonObject }] : []),
    ...(localizacao?.poligono_enderecamento
        ? [{ id: 'enderecamento', type: 'enderecamento', geojson: localizacao.poligono_enderecamento, estilo: ESTILO_ENDERECAMENTO }]
        : []),
]}
```

- Abaixo do mapa, antes do crédito do OpenStreetMap, quando houver `poligono_enderecamento`:

```tsx
{localizacao?.poligono_enderecamento ? (
    <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">Contorno tracejado: polígono do endereçamento SEDUR.</p>
) : null}
```

- Descrição do card: `"Polígono cadastrado do imóvel e polígono do endereçamento SEDUR."`. Texto do estado vazio: `"Polígono do imóvel não cadastrado neste processo."` permanece.

`processos/show.tsx`:
- Tipo do `geo` (~linha 54): `poligono_enderecamento: Polygon | MultiPolygon | null;` (import `MultiPolygon` de `geojson`).
- Centro (~linha 182): `const centroCalculado = centroide(geo.poligono) ?? centroDaGeometria(geo.poligono_enderecamento) ?? geo.ponto;`.
- Camadas do mapa (~linha 603):

```tsx
camadas={[
    ...(geo.poligono ? [{ id: 'poligono', type: 'poligono', geojson: geo.poligono }] : []),
    ...(geo.poligono_enderecamento
        ? [{ id: 'enderecamento', type: 'enderecamento', geojson: geo.poligono_enderecamento, estilo: ESTILO_ENDERECAMENTO }]
        : []),
]}
```

- Mesma legenda do contorno tracejado abaixo do mapa quando houver `geo.poligono_enderecamento`.

Imports nas páginas: `{ centroDaGeometria, ESTILO_ENDERECAMENTO } from '@/components/geo/geometria'`.

- [ ] **Passo 6: Tipos, lint e build**

Run: `npx tsc --noEmit` (ou o script `types`/`lint` do `package.json`, se existir) — Esperado: sem erros novos.
Run: `npm run build` — Esperado: build ok.
Run: `npx vitest run` — Esperado: PASS.

- [ ] **Passo 7: Conferência visual**

Com `composer run dev` de pé, abrir no navegador interno a ficha de análise e o detalhe de um processo com `address_polygon_geojson` preenchido (via `php artisan tinker --execute '...'` num processo de dev, com aprovação do usuário) e conferir: dois contornos, o do endereçamento tracejado, legenda presente, linha “Zona” com fonte e percentuais. Resetar o viewport ao terminar.

- [ ] **Passo 8: Commit**

```bash
git add resources/js/components/geo/geometria.ts resources/js/components/geo/geometria.test.ts resources/js/components/geo/camadas-imovel-zona.ts resources/js/components/geo/camadas-imovel-zona.test.ts resources/js/components/geo/map-imovel.tsx resources/js/components/geo/camadas-imovel.tsx resources/js/pages/gestao/ficha-analise/show.tsx resources/js/pages/gestao/processos/show.tsx
git commit -m "feat: mapa mostra o polígono do endereçamento e a zona pela maior porção"
```

---

### Tarefa 12: Fonte da zona no rastro da decisão

**Arquivos:**
- Alterar: `app/Services/Expresso/FluxoExpressoService.php` (montagem de `rules_versions` em `emitir()` e em `indeferirTerritorial()`)
- Teste: `tests/Feature/Expresso/ZonaMaiorPorcaoDecisaoTest.php` (arquivo da Tarefa 9)

**Interfaces:**
- Consome: `ViabilityRequest::$zona_fonte` e `$zona_codigo` (Tarefa 6), `camadas_incidentes['zona']` (Tarefa 8).
- Produz: chave `territorio.zona` em `rules_versions` da decisão: `['codigo' => ..., 'fonte' => ..., 'maior_porcao' => bool]`.

Por que: a auditoria já registra o cálculo, mas `rules_versions` é o que permite **reproduzir** a decisão depois que a camada do GeoServer mudar (RN-002 e HU-036 RN-004). Sem isso, uma decisão de hoje fica sem dizer se a zona veio da área ou do ponto.

- [ ] **Passo 1: Escrever o teste que falha**

Acrescentar a `tests/Feature/Expresso/ZonaMaiorPorcaoDecisaoTest.php`:

```php
public function test_a_decisao_registra_a_fonte_da_zona_nas_versoes_de_regra(): void
{
    $processo = $this->processoComZonaPorArea('ZPR 3');

    app(FluxoExpressoService::class)->decidir($processo, null);

    $decisao = $processo->fresh()->decision;

    $this->assertSame([
        'codigo' => 'ZPR 3',
        'fonte' => 'enderecamento_maior_porcao',
        'maior_porcao' => true,
    ], $decisao->rules_versions['territorio']['zona']);
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=test_a_decisao_registra_a_fonte_da_zona_nas_versoes_de_regra`
Esperado: FAIL — chave `territorio` ausente em `rules_versions`.

- [ ] **Passo 3: Implementar**

Em `FluxoExpressoService`, método privado novo:

```php
/**
 * Zona usada na decisão e como ela foi decidida — o que permite reproduzir
 * a decisão depois que a camada mudar (RN-002).
 *
 * @return array<string, array<string, mixed>>
 */
private function versaoDoTerritorio(ViabilityRequest $request): array
{
    if ($request->zona_codigo === null) {
        return [];
    }

    return ['territorio' => ['zona' => [
        'codigo' => $request->zona_codigo,
        'fonte' => $request->zona_fonte?->value,
        'maior_porcao' => $request->zona_fonte === ZonaFonte::EnderecamentoMaiorPorcao,
    ]]];
}
```

Importar `App\Enums\ZonaFonte`. Espalhar na montagem de `rules_versions` das duas decisões:

```php
'rules_versions' => [
    ...$resolved->rules_versions,
    ...$this->versaoDoTerritorio($request),
],
```

Em `indeferirTerritorial()` a chave `territorial` já existe e continua; as duas convivem (`territorial` é a versão da regra RT, `territorio` é a zona).

- [ ] **Passo 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Expresso`
Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Expresso/FluxoExpressoService.php tests/Feature/Expresso/ZonaMaiorPorcaoDecisaoTest.php
git commit -m "feat: decisão registra a zona usada e a fonte dela"
```

---

### Tarefa 13: Registrar a decisão no estado do projeto e no inventário do GeoServer

**Arquivos:**
- Alterar: `.planning/STATE.md`
- Alterar: `docs/integracoes/geoserver-sedur.md` (seção 5.4, parágrafo do `enderecamento:VM_ENDERECAMENTO`, ~linha 189)

Sem teste automatizado: é documentação. Mas é a fonte de verdade do projeto — `STATE.md` vence documento aspiracional, e o inventário do GeoServer é o que diz quais camadas o motor consome de verdade.

- [ ] **Passo 1: Inventário do GeoServer**

Em `docs/integracoes/geoserver-sedur.md`, trocar a linha solta da camada por um parágrafo com o estado real:

```markdown
`enderecamento:VM_ENDERECAMENTO` — `CODLOG`, `NUMERACAO_METRICA`, `COMPLEMENTO`, `SUB_UNIDADE`, `ALVARA`, `PROCESSO`, `ANO_PROCESSO`, `ORIGEM`.

**Em uso pelo motor.** Fonte do polígono do lote: `PoligonoEnderecamentoService` consulta por `CODLOG = <logradouro tributário> AND NUMERACAO_METRICA = <número métrico da certidão SEFAZ>`, com `COMPLEMENTO` desempatando condomínio. Interruptor: `features.geoserver_enderecamento`. O polígono decide a zona pela maior porção (art. 18, § 2º da Lei nº 9.148/2016); sem polígono, a zona segue pelo ponto da inscrição.

Armadilhas medidas (2026-10-02): `CODLOG` + número **não é chave única** (CODLOG 9745, nº 73 devolve 4 polígonos distintos — blocos A, B, C, D); subunidades do mesmo número repetem o mesmo polígono; a camada só cobre endereço licenciado (47.308 feições) e **`NUMERACAO_METRICA` é o número métrico, não o número de porta**.
```

Acrescentar à seção 3 (Armadilhas medidas) o item do WAF:

```markdown
8. **O WAF bloqueia polígono na URL.** `INTERSECTS(GEOMETRY,SRID=4326;POLYGON((...)))` devolve HTTP 500 com página "URL Bloqueada", mesmo com 4 vértices. `POINT` e `BBOX` passam. Interseção de área é feita com `BBOX` + cálculo no PostGIS da aplicação.
```

- [ ] **Passo 2: STATE.md**

Na seção de decisões acumuladas, acrescentar:

```markdown
- **Zona pela maior porção (2026-10-02):** com polígono do endereçamento SEDUR, a zona do processo é a de maior área de interseção (art. 18, § 2º da Lei nº 9.148/2016), gravada em `zona_codigo` com `zona_fonte`. Sem polígono, segue o ponto da inscrição — o sistema NUNCA desenha polígono a partir do ponto e da área do terreno. Chave do endereçamento: CODLOG tributário + número MÉTRICO da certidão SEFAZ (o `address_number` do RUC da JUCEB é número de PORTA e diverge do métrico). Empate de área e divergência entre o desenho e o endereçamento encaminham à análise.
- **Pendência SEDUR/SEFAZ/COGEL:** camada poligonal `SEMAP (Inscrições Geo)` (`MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/18`, 155.259 feições com inscrição) cobriria o endereço sem polígono no endereçamento. BLOQUEADA até autorização formal — proposta enviada em 2026-10-02 (`docs/integracoes/proposta-poligono-por-inscricao-sefaz.md`). Não consumir antes da resposta.
```

- [ ] **Passo 3: Commit**

```bash
git add .planning/STATE.md docs/integracoes/geoserver-sedur.md
git commit -m "docs: registra a zona pela maior porção e a pendência da base de inscrições"
```

---

### Tarefa 14: Verificação final

- [ ] **Passo 1: Testes da feature**

```bash
php artisan test --compact tests/Feature/Geo tests/Feature/Expresso tests/Feature/Analise/FichaUiSmokeTest.php tests/Feature/Analise/ProcessoUiSmokeTest.php tests/Feature/Seeders tests/Feature/Solicitacao/ProtocolarSolicitacaoTest.php tests/Unit/Geo
php artisan test --compact --group=postgis
npx vitest run
```

Esperado: tudo PASS, sem SKIP no grupo postgis (container de teste de pé).

- [ ] **Passo 2: Cobertura dos critérios de aceite**

| CA | Teste |
|---|---|
| CA-PE-01..06 | `PoligonoEnderecamentoProtocoloTest` |
| CA-ZN-01 | `ZonaMaiorPorcaoResolverPostgisTest`, `ZonaProcessoServiceTest` |
| CA-ZN-02 | `CamadasIncidentesServiceTest::test_ca_zn_02_...` |
| CA-ZN-03 | `ZonaMaiorPorcaoResolverPostgisTest`, `ZonaProcessoServiceTest` |
| CA-ZN-04 | `ZonaMaiorPorcaoResolverPostgisTest`, `ZonaProcessoServiceTest`, `FluxoExpressoZonaMaiorPorcaoTest` |
| CA-ZN-05, 06 | `ZonaMaiorPorcaoResolverPostgisTest` |
| CA-ZN-07 | `ZonaMaiorPorcaoResolverTest` |
| CA-ZN-08 | `ZonaMaiorPorcaoResolverPostgisTest`, `FluxoExpressoZonaMaiorPorcaoTest` |
| CA-DV-01, 02 | `FluxoExpressoZonaMaiorPorcaoTest` |
| CA-FC-01, 02 | `FichaUiSmokeTest`, `ProcessoUiSmokeTest` |
| CA-FC-03 | `camadas-imovel-zona.test.ts` |
| CA-AU-01 | `ZonaProcessoServiceTest::test_ca_au_01_...` |

- [ ] **Passo 3: Suíte inteira**

Perguntar ao usuário se roda `php artisan test --compact` completo; rodar se ele quiser e reportar a saída real.

- [ ] **Passo 4: Pint final**

Run: `vendor/bin/pint --dirty --format agent` — Esperado: nada a corrigir.

## Riscos de execução

- **Zonas sobrepostas.** Se camadas de zona se sobrepõem no GeoServer (ex.: ZEIS desenhada por cima de uma ZPR), as duas podem somar 100% do lote e cair em empate. O fluxo manda à análise, que é o lado seguro; se aparecer com frequência na homologação, tratar numa spec própria (precedência entre camadas), não neste plano.
- **Eixos do BBOX.** Confirmados no Passo 0 da Tarefa 5. Ordem errada devolve `FeatureCollection` vazia, o que resulta em `sem_intersecao` e zona pelo ponto, nunca zona errada.
- **Processos protocolados antes do deploy** ficam sem polígono do endereçamento e com `zona_fonte` nula até a próxima decisão (que grava `ponto`). Não há backfill neste plano.
