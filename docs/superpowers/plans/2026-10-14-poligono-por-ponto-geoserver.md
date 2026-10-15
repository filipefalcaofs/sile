# Polígono do lote pelo ponto no GeoServer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Quando o endereçamento por CODLOG + número e o SEMAP não acham o polígono do lote, buscá-lo pelo ponto do imóvel no GeoServer, antes da decisão, só nos caminhos assíncronos.

**Architecture:** O `GeoServerEnderecamentoClient` ganha `consultarPorPonto` (CQL `INTERSECTS(GEOMETRY,SRID=4326;POINT(lng lat))`, timeout próprio de 45 s). O `PoligonoEnderecamentoService` ganha a terceira fonte, exposta por `aplicarPorPonto()` e por `aplicar($request, true)`. Só `DecidirFluxoExpressoJob` e o protocolo da declaração REGIN (ambos em fila, timeout de 120 s) ligam a consulta; os caminhos HTTP síncronos seguem como hoje. Uma coluna nova `address_polygon_source` registra a fonte.

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, `Http::fake`, Eloquent enum casts, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-14-poligono-por-ponto-geoserver-design.md`

## Global Constraints

- Testes em PHPUnit (nunca Pest). Rodar o teste alterado a cada mudança: `php artisan test --compact --filter=<Classe>`.
- Pint só nos arquivos tocados: `vendor/bin/pint --format agent <arquivos>`. NÃO rodar `--dirty`: a árvore tem alterações não commitadas de outro trabalho.
- Commits em português no padrão do repositório (`feat:`, `fix:`, `test:`), sem coautoria nem rodapé de ferramenta. Fazer `git add` só dos arquivos listados na tarefa.
- NÃO fazer `git add` em `app/Services/Regin/ReginProcessoProtocolador.php` nem em `tests/Feature/Regin/ReginProcessoProtocoladorTest.php`: já têm alterações não commitadas do usuário. A mudança de uma linha da Tarefa 4 fica no working tree e é avisada no relatório final.
- Nova ordem de fontes do polígono: chave (CODLOG + número) → SEMAP → ponto. O ponto só roda se o chamador pedir (`consultarPorPonto`) ou via `aplicarPorPonto()`.
- Resultado da consulta por ponto: exatamente 1 geometria distinta grava; mais de 1 é `ambiguo` e não grava; vazio, erro HTTP, JSON fora do contrato ou falha de conexão não gravam nem apagam polígono existente.
- Ponto sempre em (longitude, latitude), com 8 casas e ponto decimal (`%.8F`), prefixo `SRID=4326;` obrigatório: sem ele o GeoServer devolve 0 resultados.
- Timeout da consulta por ponto: 45 s (`viabiliza.integrations.geoserver.enderecamento.timeout_ponto`), sem retry interno. Os 8 s gerais seguem para as demais consultas.
- Fora de escopo: SEMAP (token), ponto no mapa da ficha, trava por zona ambígua, camada `parcelamento_lotes`.
- O MCP `laravel-boost` (`search-docs`) falhou ao conectar nesta sessão; nenhuma API nova do framework é usada neste plano (só `Http`, `Schema`, casts e Mockery já presentes no repositório).

## Review Focus

Entradas e falhas que a spec implica mas nenhuma tarefa de comportamento principal cobre, da mais provável para a menos:

1. Subunidades com geometria idêntica no mesmo ponto devem contar como UM polígono e gravar (não ficar ambíguo). Teste na Tarefa 2.
2. Feição `MultiPolygon` única deve ser aceita e gravada como o `Polygon`. Teste na Tarefa 2.
3. Falha ou HTTP 500 na consulta por ponto dentro do job não pode impedir a decisão: `decide()` roda mesmo assim, com o polígono vazio. Teste na Tarefa 4.
4. Reexecução do job (nova tentativa) com o polígono já gravado não reconsulta o GeoServer. Teste na Tarefa 3 (`aplicarPorPonto` com polígono presente) e na Tarefa 4.
5. Formato das coordenadas na URL: ordem (lng lat), 8 casas, ponto decimal, independentemente do locale. Teste na Tarefa 2 (asserção sobre a URL decodificada).
6. A declaração REGIN (`protocolarDeclaracao`) não tem teste dedicado: não existe fixture dessa rota e o arquivo de teste vizinho tem alterações não commitadas. A cobertura é indireta (Tarefa 3 testa `aplicar($request, true)`); a mudança de uma linha é revisada à mão.

---

### Task 1: Fonte do polígono (enum, coluna, cast) gravada pela chave e pelo SEMAP

**Files:**
- Create: `app/Enums/PoligonoFonte.php`
- Create: `database/migrations/2026_10_14_120000_add_address_polygon_source_to_viability_requests_table.php`
- Modify: `app/Models/ViabilityRequest.php` (import do enum + cast em `casts()`)
- Modify: `app/Services/Geo/PoligonoEnderecamentoService.php` (`gravar` recebe a fonte)
- Test: `tests/Feature/Geo/PoligonoFonteTest.php`

**Interfaces:**
- Produces: `App\Enums\PoligonoFonte` com os casos `Chave = 'chave'`, `Semap = 'semap'`, `Ponto = 'ponto'` e `label(): string`.
- Produces: atributo `ViabilityRequest::$address_polygon_source` (`?PoligonoFonte`).
- Produces: `PoligonoEnderecamentoService::gravar(ViabilityRequest $request, EnderecamentoHit $hit, PoligonoFonte $fonte, bool $atributosDoLote = false): void` (privado).

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Feature/Geo/PoligonoFonteTest.php`:

```php
<?php

namespace Tests\Feature\Geo;

use App\Enums\PoligonoFonte;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O processo guarda de qual fonte veio o polígono do lote: endereçamento por
 * CODLOG + número (chave), cadastro municipal (SEMAP) ou o ponto do imóvel.
 */
class PoligonoFonteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.geoserver_enderecamento' => true,
            'viabiliza.features.arcgis_semap' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function colecao(bool $comFeicao): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $comFeicao ? [[
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [-38.516331, -12.977852],
                        [-38.516291, -12.977746],
                        [-38.516453, -12.977688],
                        [-38.516492, -12.977795],
                        [-38.516331, -12.977852],
                    ]],
                ],
                'properties' => [
                    'INSCRICAO' => '344672',
                    'CODLOG' => '212',
                    'NUMERACAO_METRICA' => '70',
                    'COMPLEMENTO' => null,
                    'CAT_DE_USO' => 'COMERCIAL',
                    'PADRAOCONS' => 'MÉDIO',
                ],
            ]] : [],
        ];
    }

    public function test_poligono_da_chave_grava_a_fonte_chave(): void
    {
        Http::fake(['*geoserver.sedur*' => Http::response($this->colecao(true))]);

        $request = ViabilityRequest::factory()->draft()->create([
            'cod_log' => '212',
            'address_number' => '70',
        ]);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $this->assertSame(PoligonoFonte::Chave, $request->fresh()->address_polygon_source);
    }

    public function test_poligono_do_semap_grava_a_fonte_semap(): void
    {
        Http::fake([
            '*geoserver.sedur*' => Http::response($this->colecao(false)),
            '*geo.salvador*' => Http::response($this->colecao(true)),
        ]);

        $request = ViabilityRequest::factory()->draft()->create([
            'cod_log' => '212',
            'property_registration' => '344672',
        ]);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $this->assertSame(PoligonoFonte::Semap, $request->fresh()->address_polygon_source);
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --compact --filter=PoligonoFonteTest`
Expected: FAIL (a coluna `address_polygon_source` e a classe `PoligonoFonte` não existem).

- [ ] **Step 3: Criar o enum**

Criar `app/Enums/PoligonoFonte.php`:

```php
<?php

namespace App\Enums;

/**
 * Fonte que forneceu o polígono do lote gravado em address_polygon_geojson.
 */
enum PoligonoFonte: string
{
    case Chave = 'chave';
    case Semap = 'semap';
    case Ponto = 'ponto';

    public function label(): string
    {
        return match ($this) {
            self::Chave => 'Endereçamento por CODLOG e número',
            self::Semap => 'Cadastro municipal (SEMAP)',
            self::Ponto => 'Endereçamento pelo ponto do imóvel',
        };
    }
}
```

- [ ] **Step 4: Criar a migration**

Criar `database/migrations/2026_10_14_120000_add_address_polygon_source_to_viability_requests_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fonte do polígono do lote (chave, semap ou ponto). Escrita pelo sistema
     * junto com address_polygon_geojson, nunca pelo requerente.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('address_polygon_source', 20)->nullable()->after('address_polygon_consulted_at');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('address_polygon_source');
        });
    }
};
```

- [ ] **Step 5: Adicionar o cast no modelo**

Em `app/Models/ViabilityRequest.php`, junto dos demais `use App\Enums\...`, acrescentar:

```php
use App\Enums\PoligonoFonte;
```

e em `casts()`, logo depois da linha `'address_polygon_consulted_at' => 'datetime',`:

```php
            'address_polygon_source' => PoligonoFonte::class,
```

- [ ] **Step 6: Gravar a fonte em `PoligonoEnderecamentoService`**

Em `app/Services/Geo/PoligonoEnderecamentoService.php`, acrescentar o import:

```php
use App\Enums\PoligonoFonte;
```

Trocar a chamada da chave:

```php
            if ($hit->encontrado()) {
                $this->gravar($request, $hit, PoligonoFonte::Chave);

                return true;
            }
```

Trocar a chamada do SEMAP:

```php
        $this->gravar($request, $semap, PoligonoFonte::Semap, atributosDoLote: true);
```

Trocar a assinatura e o início de `gravar`:

```php
    private function gravar(ViabilityRequest $request, EnderecamentoHit $hit, PoligonoFonte $fonte, bool $atributosDoLote = false): void
    {
        $dados = [
            'address_polygon_geojson' => $hit->geometria,
            'address_polygon_consulted_at' => now(),
            'address_polygon_source' => $fonte,
        ];
```

- [ ] **Step 7: Rodar o teste novo e os vizinhos**

Run: `php artisan test --compact --filter=PoligonoFonteTest`
Expected: PASS (2 testes).

Run: `php artisan test --compact tests/Feature/Geo/PoligonoLoteFallbackSemapTest.php tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php`
Expected: PASS (nenhuma regressão).

- [ ] **Step 8: Pint e commit**

```bash
vendor/bin/pint --format agent app/Enums/PoligonoFonte.php database/migrations/2026_10_14_120000_add_address_polygon_source_to_viability_requests_table.php app/Models/ViabilityRequest.php app/Services/Geo/PoligonoEnderecamentoService.php tests/Feature/Geo/PoligonoFonteTest.php
git add app/Enums/PoligonoFonte.php database/migrations/2026_10_14_120000_add_address_polygon_source_to_viability_requests_table.php app/Models/ViabilityRequest.php app/Services/Geo/PoligonoEnderecamentoService.php tests/Feature/Geo/PoligonoFonteTest.php
git commit -m "feat: registra a fonte do polígono do lote (chave, SEMAP ou ponto)"
```

---

### Task 2: Consulta por ponto no cliente do endereçamento

**Files:**
- Modify: `app/Services/Geo/GeoServerEnderecamentoClient.php` (arquivo inteiro, ver Step 3)
- Modify: `config/viabiliza.php` (bloco `geoserver.enderecamento`)
- Test: `tests/Feature/Geo/GeoServerEnderecamentoClientTest.php` (acrescentar testes)

**Interfaces:**
- Consumes: `EnderecamentoHit` (inalterado).
- Produces: `GeoServerEnderecamentoClient::consultarPorPonto(float $lat, float $lng): EnderecamentoHit`. Status possíveis: `encontrado`, `ambiguo`, `nao_encontrado`, `indisponivel`, `desligado`.

- [ ] **Step 1: Escrever os testes que falham**

Em `tests/Feature/Geo/GeoServerEnderecamentoClientTest.php`, acrescentar o import `use Illuminate\Http\Client\ConnectionException;` e, ao final da classe, estes testes:

```php
    public function test_consulta_por_ponto_devolve_o_poligono_unico_e_envia_o_cql_certo(): void
    {
        $this->responder([['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)]]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);

        Http::assertSent(function (Request $request): bool {
            $url = urldecode($request->url());

            return str_contains($url, 'typeName=enderecamento:VM_ENDERECAMENTO')
                && str_contains($url, 'CQL_FILTER=INTERSECTS(GEOMETRY,SRID=4326;POINT(-38.50000000 -12.98000000))');
        });
    }

    public function test_consulta_por_ponto_com_dois_poligonos_distintos_e_ambigua(): void
    {
        $this->responder([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'SALA 2', 'geometria' => $this->quadrado(-38.6, -12.9)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::AMBIGUO, $hit->status);
        $this->assertSame(2, $hit->candidatas);
        $this->assertNull($hit->geometria);
        $this->assertStringContainsString('no ponto informado', (string) $hit->motivo);
    }

    public function test_consulta_por_ponto_com_subunidades_de_geometria_identica_conta_como_uma(): void
    {
        $this->responder([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'SALA 2', 'geometria' => $this->quadrado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
    }

    public function test_consulta_por_ponto_aceita_multipolygon_unico(): void
    {
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [$this->quadrado(-38.5, -12.98)['coordinates']]];
        $this->responder([['complemento' => null, 'geometria' => $multi]]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame('MultiPolygon', $hit->geometria['type']);
    }

    public function test_consulta_por_ponto_sem_feicao_nao_encontra(): void
    {
        $this->responder([]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->geometria);
    }

    public function test_consulta_por_ponto_com_http_500_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertStringContainsString('500', (string) $hit->motivo);
    }

    public function test_consulta_por_ponto_com_falha_de_conexao_e_indisponivel(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('timeout');
        });

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
    }

    public function test_consulta_por_ponto_com_json_fora_do_contrato_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response(['erro' => 'x'])]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
    }

    public function test_consulta_por_ponto_desligada_nao_chama_o_geoserver(): void
    {
        $this->ligar(false);
        Http::fake();

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_timeout_da_consulta_por_ponto_e_de_45_segundos(): void
    {
        $this->assertSame(45, config('viabiliza.integrations.geoserver.enderecamento.timeout_ponto'));
    }
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --compact --filter=GeoServerEnderecamentoClientTest`
Expected: FAIL nos 10 testes novos (`consultarPorPonto` não existe; config ausente). Os testes antigos seguem passando.

- [ ] **Step 3: Implementar no cliente**

Substituir o conteúdo de `app/Services/Geo/GeoServerEnderecamentoClient.php` por:

```php
<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Polígono do endereço licenciado na camada enderecamento:VM_ENDERECAMENTO do
 * GeoServer SEDUR. Dois modos de busca:
 *
 * - por CODLOG + NUMERACAO_METRICA (rápido, ~0,2 s): a mesma chave pode ter
 *   várias feições, o complemento desempata e geometrias idênticas
 *   (subunidades) contam como uma;
 * - pelo ponto do imóvel (INTERSECTS, ~30 s medidos): só um polígono distinto
 *   no ponto vale. Sobrando mais de um, o resultado é ambíguo.
 *
 * Em ambos, sobrando mais de uma geometria distinta, nenhuma é escolhida.
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

    /**
     * Lote que contém o ponto. A consulta espacial é lenta no servidor
     * (~30 s), por isso tem timeout próprio e só deve rodar em fila.
     */
    public function consultarPorPonto(float $lat, float $lng): EnderecamentoHit
    {
        $chave = sprintf('ponto|%.8F|%.8F', $lat, $lng);

        return $this->memo[$chave] ??= $this->consultarPontoNoGeoServer($lat, $lng);
    }

    private function consultarNoGeoServer(string $codLog, string $numero, string $complemento): EnderecamentoHit
    {
        if (! Settings::enabled('geoserver_enderecamento')) {
            return new EnderecamentoHit(EnderecamentoHit::DESLIGADO, motivo: 'Consulta do endereçamento no GeoServer SEDUR desligada.');
        }

        if (! ctype_digit($codLog) || ! ctype_digit($numero)) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO, motivo: 'CODLOG ou número métrico não numérico.');
        }

        return $this->buscar(
            $this->url($codLog, $numero),
            (int) config('viabiliza.integrations.geoserver.timeout', 8),
            $complemento,
        );
    }

    private function consultarPontoNoGeoServer(float $lat, float $lng): EnderecamentoHit
    {
        if (! Settings::enabled('geoserver_enderecamento')) {
            return new EnderecamentoHit(EnderecamentoHit::DESLIGADO, motivo: 'Consulta do endereçamento no GeoServer SEDUR desligada.');
        }

        return $this->buscar(
            $this->urlPorPonto($lat, $lng),
            (int) config('viabiliza.integrations.geoserver.enderecamento.timeout_ponto', 45),
            '',
            porPonto: true,
        );
    }

    private function buscar(string $url, int $timeout, string $complemento, bool $porPonto = false): EnderecamentoHit
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout($timeout)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url);
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

        return $this->escolher($this->poligonais($payload['features'] ?? []), $complemento, $porPonto);
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
    private function escolher(array $feicoes, string $complemento, bool $porPonto = false): EnderecamentoHit
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
                motivo: count($distintas).($porPonto
                    ? ' polígonos distintos no ponto informado.'
                    : ' polígonos distintos para o mesmo CODLOG e número, sem complemento que desempate.'),
            );
        }

        $unica = array_values($distintas)[0];

        return new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, geometria: $unica['geometry'], propriedades: $unica['properties'], candidatas: 1);
    }

    private function url(string $codLog, string $numero): string
    {
        return $this->wfsUrl(sprintf('CODLOG=%s AND NUMERACAO_METRICA=%s', $codLog, $numero));
    }

    private function urlPorPonto(float $lat, float $lng): string
    {
        return $this->wfsUrl(sprintf('INTERSECTS(GEOMETRY,SRID=4326;POINT(%.8F %.8F))', $lng, $lat));
    }

    private function wfsUrl(string $cqlFilter): string
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
            'CQL_FILTER' => $cqlFilter,
        ]);
    }
}
```

- [ ] **Step 4: Acrescentar o timeout na config**

Em `config/viabiliza.php`, no bloco `geoserver` → `'enderecamento' => [...]`, deixar:

```php
            'enderecamento' => [
                'type_name' => 'enderecamento:VM_ENDERECAMENTO',
                // Consulta espacial por ponto: ~30 s medidos no servidor real.
                // Sem retry interno; o job de decisão já tem tentativas (120 s).
                'timeout_ponto' => 45,
            ],
```

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --compact --filter=GeoServerEnderecamentoClientTest`
Expected: PASS (testes antigos e os 10 novos).

Run: `php artisan test --compact --filter=GeoServerEnderecamentoToggleTest`
Expected: PASS.

- [ ] **Step 6: Pint e commit**

```bash
vendor/bin/pint --format agent app/Services/Geo/GeoServerEnderecamentoClient.php config/viabiliza.php tests/Feature/Geo/GeoServerEnderecamentoClientTest.php
git add app/Services/Geo/GeoServerEnderecamentoClient.php config/viabiliza.php tests/Feature/Geo/GeoServerEnderecamentoClientTest.php
git commit -m "feat: consulta o lote do endereçamento pelo ponto do imóvel no GeoServer"
```

---

### Task 3: Terceira fonte no serviço do polígono

**Files:**
- Modify: `app/Services/Geo/PoligonoEnderecamentoService.php`
- Test: `tests/Feature/Geo/PoligonoPorPontoTest.php`

**Interfaces:**
- Consumes: `GeoServerEnderecamentoClient::consultarPorPonto(float $lat, float $lng): EnderecamentoHit` (Tarefa 2); `PoligonoFonte::Ponto` e `gravar(..., PoligonoFonte $fonte, ...)` (Tarefa 1); `ViabilityRequest::pontoDoImovel(): ?array{lat: float, lng: float}`.
- Produces: `PoligonoEnderecamentoService::aplicar(ViabilityRequest $request, bool $consultarPorPonto = false): bool`.
- Produces: `PoligonoEnderecamentoService::aplicarPorPonto(ViabilityRequest $request): bool`. Retorna `true` só quando gravou.

- [ ] **Step 1: Escrever os testes que falham**

Criar `tests/Feature/Geo/PoligonoPorPontoTest.php`:

```php
<?php

namespace Tests\Feature\Geo;

use App\Enums\PoligonoFonte;
use App\Models\Activity;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Terceira fonte do polígono do lote: o endereçamento do GeoServer pelo ponto
 * do imóvel, só quando a chave e o SEMAP não acham e o chamador pede.
 */
class PoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.geoserver_enderecamento' => true,
            'viabiliza.features.arcgis_semap' => true,
        ]);
    }

    /**
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function quadrado(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.0005, $lat], [$lng + 0.0005, $lat + 0.0005], [$lng, $lat + 0.0005], [$lng, $lat],
        ]]];
    }

    /**
     * @param  array<string, mixed>  ...$geometrias
     * @return array<string, mixed>
     */
    private function colecao(array ...$geometrias): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => array_map(fn (array $geometria): array => [
                'type' => 'Feature',
                'geometry' => $geometria,
                'properties' => ['CODLOG' => 24574, 'NUMERACAO_METRICA' => 70, 'COMPLEMENTO' => null],
            ], $geometrias),
        ];
    }

    /**
     * SEMAP sempre vazio; a chave e o ponto respondem o que o teste informar.
     *
     * @param  array<string, mixed>  $porPonto
     * @param  array<string, mixed>|null  $porChave
     */
    private function fakeGeoServer(array $porPonto, ?array $porChave = null): void
    {
        Http::fake(function (Request $request) use ($porPonto, $porChave) {
            if (str_contains($request->url(), 'arcgis')) {
                return Http::response($this->colecao());
            }

            $ehPonto = str_contains(urldecode($request->url()), 'INTERSECTS');

            return Http::response($ehPonto ? $porPonto : ($porChave ?? $this->colecao()));
        });
    }

    private function consultasPorPonto(): int
    {
        return Http::recorded(
            fn (Request $request): bool => str_contains(urldecode($request->url()), 'INTERSECTS'),
        )->count();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create($atributos + [
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.5,
            'property_registration' => '344672',
        ]);
    }

    private function auditou(string $status): bool
    {
        return Activity::query()->where('event', 'enderecamento-poligono')
            ->get()
            ->contains(fn (Activity $a): bool => ($a->properties['fonte'] ?? null) === 'ponto'
                && ($a->properties['status'] ?? null) === $status);
    }

    public function test_sem_poligono_na_chave_nem_no_semap_o_ponto_grava_o_lote(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $request->refresh();

        $this->assertSame('Polygon', $request->address_polygon_geojson['type']);
        $this->assertSame(PoligonoFonte::Ponto, $request->address_polygon_source);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertTrue($this->auditou('encontrado'));
    }

    public function test_sem_o_parametro_o_ponto_nao_e_consultado(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $this->assertSame(0, $this->consultasPorPonto());
        $this->assertNull($request->fresh()->address_polygon_geojson);
    }

    public function test_dois_poligonos_distintos_no_ponto_sao_ambiguos_e_nao_gravam(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98), $this->quadrado(-38.6, -12.9)));
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $request->refresh();

        $this->assertNull($request->address_polygon_geojson);
        $this->assertNull($request->address_polygon_source);
        $this->assertTrue($this->auditou('ambiguo'));
    }

    public function test_resposta_vazia_no_ponto_nao_grava(): void
    {
        $this->fakeGeoServer($this->colecao());
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertTrue($this->auditou('nao_encontrado'));
    }

    public function test_http_500_no_ponto_nao_grava(): void
    {
        Http::fake(function (Request $request) {
            return str_contains(urldecode($request->url()), 'INTERSECTS')
                ? Http::response('erro', 500)
                : Http::response($this->colecao());
        });
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertTrue($this->auditou('indisponivel'));
    }

    public function test_chave_que_acha_o_lote_dispensa_o_ponto(): void
    {
        $this->fakeGeoServer($this->colecao(), $this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo(['cod_log' => '24574', 'address_number' => '70']);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertSame(PoligonoFonte::Chave, $request->fresh()->address_polygon_source);
        $this->assertSame(0, $this->consultasPorPonto());
    }

    public function test_aplicar_por_ponto_nao_consulta_com_poligono_ja_gravado(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.6, -12.9)));
        $existente = $this->quadrado(-38.5, -12.98);
        $request = $this->processo(['address_polygon_geojson' => $existente]);

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(0, $this->consultasPorPonto());
        $this->assertSame($existente, $request->fresh()->address_polygon_geojson);
    }

    public function test_aplicar_por_ponto_sem_ponto_nao_consulta(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo(['property_point_lat' => null, 'property_point_lng' => null]);

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(0, $this->consultasPorPonto());
    }

    public function test_aplicar_por_ponto_grava_a_fonte_ponto(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(PoligonoFonte::Ponto, $request->fresh()->address_polygon_source);
    }

    public function test_interruptor_desligado_nao_consulta_o_ponto(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => false]);
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertSame(0, $this->consultasPorPonto());
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --compact --filter=PoligonoPorPontoTest`
Expected: FAIL (`aplicar` não aceita o segundo argumento nomeado; `aplicarPorPonto` não existe).

- [ ] **Step 3: Implementar no serviço**

Em `app/Services/Geo/PoligonoEnderecamentoService.php`:

a) Atualizar o docblock da classe para listar a terceira fonte. Trocar o bloco que descreve as fontes por:

```php
/**
 * Grava no processo a geometria do lote, em cadeia de fontes oficiais:
 *
 * 1. Endereçamento SEDUR, pelo CODLOG do logradouro tributário, número e
 *    complemento — só cobre endereço licenciado;
 * 2. Cadastro municipal (SEMAP), pela inscrição imobiliária — cobre o que o
 *    endereçamento não tem, inclusive a chave ambígua por bloco;
 * 3. Endereçamento SEDUR pelo ponto do imóvel — consulta espacial lenta
 *    (~30 s), só quando o chamador pede (`consultarPorPonto`), em fila.
 *
 * Nenhuma das três com polígono único não grava nada, e cada tentativa fica na
 * auditoria com a fonte. Desenho do requerente não entra aqui.
 */
```

b) Trocar a assinatura de `aplicar`:

```php
    public function aplicar(ViabilityRequest $request, bool $consultarPorPonto = false): bool
```

c) Trocar o desfecho do SEMAP sem polígono:

```php
        if (! $semap->encontrado()) {
            return $consultarPorPonto && $this->aplicarPorPonto($request);
        }
```

d) Inserir, antes de `private function texto(mixed $valor): ?string`:

```php
    /**
     * Terceira fonte: o lote que contém o ponto do imóvel. Não consulta quando
     * o processo já tem polígono ou não tem ponto, então repetir a chamada (nova
     * tentativa do job) não repete a consulta lenta.
     */
    public function aplicarPorPonto(ViabilityRequest $request): bool
    {
        $ponto = $request->pontoDoImovel();

        if ($ponto === null || ! $this->precisaConsultarPorPonto($request)) {
            return false;
        }

        $hit = $this->cliente->consultarPorPonto($ponto['lat'], $ponto['lng']);

        $this->auditarPonto($request, $ponto, $hit);

        if (! $hit->encontrado()) {
            return false;
        }

        $this->gravar($request, $hit, PoligonoFonte::Ponto);

        return true;
    }

    /**
     * A consulta por ponto faz sentido: falta o polígono, há ponto e o
     * endereçamento está ligado. O reprocesso usa a mesma regra para decidir
     * se agenda a decisão em fila.
     */
    public function precisaConsultarPorPonto(ViabilityRequest $request): bool
    {
        $semPoligono = ! is_array($request->address_polygon_geojson) || $request->address_polygon_geojson === [];

        return $semPoligono
            && $request->pontoDoImovel() !== null
            && Settings::enabled('geoserver_enderecamento');
    }

```

Acrescentar também o import `use App\Support\Settings;` no topo do serviço.

e) Inserir, depois do método `auditar(...)` existente (antes do `}` final da classe):

```php

    /**
     * @param  array{lat: float, lng: float}  $ponto
     */
    private function auditarPonto(ViabilityRequest $request, array $ponto, EnderecamentoHit $hit): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'enderecamento-poligono',
            description: "Polígono do lote em ponto ({$hit->status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'fonte' => 'ponto',
                'lat' => $ponto['lat'],
                'lng' => $ponto['lng'],
                'status' => $hit->status,
                'candidatas' => $hit->candidatas,
                'motivo' => $hit->motivo,
            ],
            subject: $request,
            result: $hit->status,
        );
    }
```

- [ ] **Step 4: Rodar os testes**

Run: `php artisan test --compact --filter=PoligonoPorPontoTest`
Expected: PASS (10 testes).

Run: `php artisan test --compact tests/Feature/Geo`
Expected: PASS (sem regressão nas demais classes de Geo).

- [ ] **Step 5: Pint e commit**

```bash
vendor/bin/pint --format agent app/Services/Geo/PoligonoEnderecamentoService.php tests/Feature/Geo/PoligonoPorPontoTest.php
git add app/Services/Geo/PoligonoEnderecamentoService.php tests/Feature/Geo/PoligonoPorPontoTest.php
git commit -m "feat: terceira fonte do polígono do lote pelo ponto do imóvel"
```

---

### Task 4: Ligar a consulta por ponto só nos caminhos assíncronos

**Files:**
- Modify: `app/Jobs/DecidirFluxoExpressoJob.php`
- Modify: `app/Services/Regin/ReginProcessoProtocolador.php` (uma linha; NÃO commitar, ver Global Constraints)
- Test: `tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php`
- Test: `tests/Feature/Geo/PoligonoPorPontoCaminhosTest.php`

**Interfaces:**
- Consumes: `PoligonoEnderecamentoService::aplicarPorPonto(ViabilityRequest): bool` e `aplicar(ViabilityRequest, bool $consultarPorPonto = false): bool` (Tarefa 3).
- Produces: `DecidirFluxoExpressoJob::handle(FluxoExpressoService $service, ?ProtocolarSolicitacaoService $protocolo = null, ?PoligonoEnderecamentoService $poligono = null): void`.

- [ ] **Step 1: Escrever os testes do job (falham)**

Criar `tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php`:

```php
<?php

namespace Tests\Feature\Expresso;

use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DecisionResult;
use App\Services\Expresso\FluxoExpressoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * O job de decisão busca o polígono do lote pelo ponto ANTES de decidir, para a
 * zona sair pela maior porção. Falha da consulta não impede a decisão.
 */
class DecidirFluxoExpressoJobPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.features.geoserver_enderecamento' => true]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function protocolada(array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create($atributos + [
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.5,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function colecaoComUmLote(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                    [-38.5, -12.98], [-38.4995, -12.98], [-38.4995, -12.9795], [-38.5, -12.9795], [-38.5, -12.98],
                ]]],
                'properties' => ['CODLOG' => 24574, 'NUMERACAO_METRICA' => 70, 'COMPLEMENTO' => null],
            ]],
        ];
    }

    private function consultasPorPonto(): int
    {
        return Http::recorded(
            fn (Request $request): bool => str_contains(urldecode($request->url()), 'INTERSECTS'),
        )->count();
    }

    public function test_job_grava_o_poligono_pelo_ponto_antes_de_decidir(): void
    {
        Http::fake(['*' => Http::response($this->colecaoComUmLote())]);
        $request = $this->protocolada();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturnUsing(function (ViabilityRequest $r): DecisionResult {
            $this->assertSame('Polygon', $r->fresh()->address_polygon_geojson['type']);

            return DecisionResult::paraAnalise('teste');
        });

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertSame(1, $this->consultasPorPonto());
    }

    public function test_falha_na_consulta_por_ponto_nao_impede_a_decisao(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $request = $this->protocolada();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertNull($request->fresh()->address_polygon_geojson);
    }

    public function test_job_nao_consulta_quando_o_poligono_ja_esta_gravado(): void
    {
        Http::fake(['*' => Http::response($this->colecaoComUmLote())]);
        $existente = ['type' => 'Polygon', 'coordinates' => [[
            [-38.6, -12.9], [-38.5995, -12.9], [-38.5995, -12.8995], [-38.6, -12.8995], [-38.6, -12.9],
        ]]];
        $request = $this->protocolada(['address_polygon_geojson' => $existente]);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertSame(0, $this->consultasPorPonto());
        $this->assertSame($existente, $request->fresh()->address_polygon_geojson);
    }
}
```

- [ ] **Step 2: Escrever o teste do caminho síncrono (falha só se o parâmetro vazar)**

Criar `tests/Feature/Geo/PoligonoPorPontoCaminhosTest.php`:

```php
<?php

namespace Tests\Feature\Geo;

use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * O protocolo do portal é síncrono (HTTP): não pode pedir a consulta lenta por
 * ponto. Quem a pede é o job de decisão, depois.
 */
class PoligonoPorPontoCaminhosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_completar_territorio_do_protocolo_nao_pede_a_consulta_por_ponto(): void
    {
        Http::fake();

        $chamadas = [];
        $poligono = Mockery::mock(PoligonoEnderecamentoService::class);
        $poligono->shouldReceive('aplicar')->andReturnUsing(
            function (ViabilityRequest $request, bool $consultarPorPonto = false) use (&$chamadas): bool {
                $chamadas[] = $consultarPorPonto;

                return false;
            },
        );
        $poligono->shouldNotReceive('aplicarPorPonto');
        $this->app->instance(PoligonoEnderecamentoService::class, $poligono);

        $request = ViabilityRequest::factory()->protocoled()->create([
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.5,
            'zona_codigo' => 'ZCMu 2',
            'bairro_oficial' => 'Centro',
            'cod_log' => '24574',
        ]);

        app(ProtocolarSolicitacaoService::class)->completarTerritorio($request);

        $this->assertSame([false], $chamadas);
    }
}
```

- [ ] **Step 3: Rodar e confirmar o estado**

Run: `php artisan test --compact --filter=DecidirFluxoExpressoJobPoligonoPorPontoTest`
Expected: FAIL em `test_job_grava_o_poligono_pelo_ponto_antes_de_decidir` (o job ainda não consulta); os outros dois passam por acaso.

Run: `php artisan test --compact --filter=PoligonoPorPontoCaminhosTest`
Expected: PASS (já é o comportamento atual; o teste protege contra vazamento do parâmetro). Se falhar por consulta de rede não coberta pelo `Http::fake()` ou por outra dependência de `completarTerritorio`, ajustar só o setup do teste (atributos do processo), nunca o comportamento.

- [ ] **Step 4: Ligar a consulta no job de decisão**

Em `app/Jobs/DecidirFluxoExpressoJob.php`, acrescentar o import:

```php
use App\Services\Geo\PoligonoEnderecamentoService;
```

Trocar a assinatura de `handle`:

```php
    public function handle(FluxoExpressoService $service, ?ProtocolarSolicitacaoService $protocolo = null, ?PoligonoEnderecamentoService $poligono = null): void
```

Trocar a última linha do corpo de `handle`:

```php
        // O polígono do lote vem ANTES da decisão: é ele que zoneia pela maior
        // porção. Só aqui (fila, 120 s) a consulta espacial lenta é aceitável; o
        // protocolo síncrono não a pede. Falha não impede a decisão.
        ($poligono ?? app(PoligonoEnderecamentoService::class))->aplicarPorPonto($request);

        $service->decide($request);
```

- [ ] **Step 5: Ligar a consulta no protocolo da declaração REGIN**

Em `app/Services/Regin/ReginProcessoProtocolador.php`, no método `protocolarDeclaracao`, trocar:

```php
        $this->poligonoLote->aplicar($solicitacao->fresh() ?? $solicitacao);
```

por:

```php
        $this->poligonoLote->aplicar($solicitacao->fresh() ?? $solicitacao, consultarPorPonto: true);
```

e acrescentar ao comentário logo acima, depois de "...só vale se a cadeia oficial não achar.":

```php
        // Sem polígono pela chave nem pelo SEMAP, busca o lote pelo ponto: este
        // protocolo roda no job do REGIN (120 s), onde a espera é aceitável.
```

Este arquivo tem alterações não commitadas do usuário: não rodar Pint nele, não fazer `git add`.

- [ ] **Step 6: Rodar os testes**

Run: `php artisan test --compact --filter=DecidirFluxoExpressoJobPoligonoPorPontoTest`
Expected: PASS (3 testes).

Run: `php artisan test --compact tests/Feature/Expresso/DecidirFluxoExpressoJobTest.php tests/Feature/Expresso/DecidirFluxoExpressoJobLocalizacaoTest.php tests/Feature/Regin`
Expected: PASS (sem regressão no job existente nem no REGIN).

- [ ] **Step 7: Pint e commit (sem o arquivo do REGIN)**

```bash
vendor/bin/pint --format agent app/Jobs/DecidirFluxoExpressoJob.php tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php tests/Feature/Geo/PoligonoPorPontoCaminhosTest.php
git add app/Jobs/DecidirFluxoExpressoJob.php tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php tests/Feature/Geo/PoligonoPorPontoCaminhosTest.php
git commit -m "feat: o job de decisão busca o polígono pelo ponto antes de decidir"
```

- [ ] **Step 8: Verificação final**

Run: `php artisan test --compact tests/Feature/Geo tests/Feature/Expresso tests/Feature/Regin`
Expected: PASS. Registrar no relatório que a mudança de uma linha em `ReginProcessoProtocolador.php` ficou no working tree, sem commit, por já haver alterações não commitadas do usuário no arquivo.

---

### Task 5: Reprocesso agenda a decisão quando falta o polígono

**Files:**
- Modify: `app/Jobs/DecidirFluxoExpressoJob.php` (construtor, guarda, ator)
- Modify: `app/Services/Motor/ReprocessarEtapasService.php`
- Test: `tests/Feature/Motor/ReprocessarPoligonoPorPontoTest.php`
- Test: `tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php` (acrescentar 2 testes)

**Interfaces:**
- Consumes: `PoligonoEnderecamentoService::precisaConsultarPorPonto(ViabilityRequest): bool` (Tarefa 3); `FluxoExpressoService::podeReavaliar(ViabilityRequest): bool` (existente).
- Produces: `new DecidirFluxoExpressoJob(int $viabilityRequestId, bool $reavaliacao = false, ?int $atorId = null)`, com propriedades públicas readonly de mesmo nome.

- [ ] **Step 1: Testes do job em modo reavaliação (falham)**

Acrescentar em `tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php` os imports `use App\Enums\AnalysisStatus;`, `use App\Enums\ViabilityRequestStatus;`, `use App\Models\User;` e os testes:

```php
    public function test_reavaliacao_decide_processo_em_analise_sem_analista_com_o_ator(): void
    {
        Http::fake(['*' => Http::response($this->colecaoComUmLote())]);
        $ator = User::factory()->create();
        $request = $this->protocolada([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
        ]);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('podeReavaliar')->once()->andReturnTrue();
        $service->shouldReceive('decide')->once()
            ->with(Mockery::type(ViabilityRequest::class), Mockery::on(fn (?User $u): bool => $u?->id === $ator->id))
            ->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id, reavaliacao: true, atorId: $ator->id))->handle($service);

        $this->assertSame('Polygon', $request->fresh()->address_polygon_geojson['type']);
    }

    public function test_sem_reavaliacao_processo_em_analise_continua_sem_efeito(): void
    {
        Http::fake();
        $request = $this->protocolada([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
        ]);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldNotReceive('decide');

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        Http::assertNothingSent();
    }
```

Run: `php artisan test --compact --filter=DecidirFluxoExpressoJobPoligonoPorPontoTest`
Expected: FAIL em `test_reavaliacao_decide_processo_em_analise_sem_analista_com_o_ator` (construtor sem `reavaliacao`).

- [ ] **Step 2: Implementar no job**

Em `app/Jobs/DecidirFluxoExpressoJob.php`, acrescentar `use App\Models\User;` e trocar a assinatura do construtor:

```php
    public function __construct(
        public readonly int $viabilityRequestId,
        public readonly bool $reavaliacao = false,
        public readonly ?int $atorId = null,
    ) {
```

Trocar a guarda do início de `handle`:

```php
        $request = ViabilityRequest::query()->find($this->viabilityRequestId);

        // Guard idempotente: a solicitação foi removida ou já saiu de protocolada
        // (decidida/encaminhada por outra passada) → no-op. Na reavaliação pedida
        // pelo reprocesso vale a mesma regra do reprocesso: protocolada, ou em
        // análise sem analista e sem decisão. O serviço re-checa sob o lock.
        if ($request === null) {
            return;
        }

        if ($this->reavaliacao ? ! $service->podeReavaliar($request) : $request->status !== ViabilityRequestStatus::Protocolada) {
            return;
        }
```

e a chamada final:

```php
        $service->decide($request, $this->atorId !== null ? User::query()->find($this->atorId) : null);
```

Run: `php artisan test --compact --filter=DecidirFluxoExpressoJob`
Expected: PASS (testes novos e os existentes de `DecidirFluxoExpressoJobTest` e `DecidirFluxoExpressoJobLocalizacaoTest`).

- [ ] **Step 3: Testes do reprocesso (falham)**

Criar `tests/Feature/Motor/ReprocessarPoligonoPorPontoTest.php`:

```php
<?php

namespace Tests\Feature\Motor;

use App\Enums\AnalysisStatus;
use App\Enums\EtapaMotor;
use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestStatus;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use App\Services\Geo\SpatialRepository;
use App\Services\Motor\ReprocessarEtapasService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Reprocesso sem polígono não decide pelo ponto na hora: agenda o job de
 * decisão, que busca o lote pelo ponto (consulta lenta) antes de decidir.
 */
class ReprocessarPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);

        Http::fake(['*' => Http::response(['type' => 'FeatureCollection', 'features' => []])]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processoEmAnaliseComPonto(array $atributos = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create($atributos + [
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.9738,
            'property_point_lng' => -38.4803,
            'zona_codigo' => 'ZCN-1',
            'bairro_oficial' => 'Comércio',
            'cod_log' => '958',
            'used_area_m2' => 40,
        ]);
        $processo->cnaes()->attach(Cnae::factory()->create()->id, ['is_primary' => true]);

        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::Zona,
        ]);

        return $processo;
    }

    public function test_sem_poligono_e_com_ponto_agenda_a_decisao_com_o_ator(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => true]);
        $ator = User::factory()->create();
        $processo = $this->processoEmAnaliseComPonto();

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo, $ator);

        Queue::assertPushed(
            DecidirFluxoExpressoJob::class,
            fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $processo->id
                && $job->reavaliacao
                && $job->atorId === $ator->id,
        );
        $this->assertStringContainsString('polígono do lote', $resultado->mensagem);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_com_poligono_ja_gravado_reavalia_na_hora_sem_agendar(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => true]);
        $processo = $this->processoEmAnaliseComPonto([
            'address_polygon_geojson' => ['type' => 'Polygon', 'coordinates' => [[
                [-38.4803, -12.9738], [-38.4798, -12.9738], [-38.4798, -12.9733], [-38.4803, -12.9733], [-38.4803, -12.9738],
            ]]],
        ]);

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
        $this->assertSame('Etapas reprocessadas e veredito reavaliado.', $resultado->mensagem);
    }

    public function test_interruptor_desligado_reavalia_na_hora_sem_agendar(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => false]);
        $processo = $this->processoEmAnaliseComPonto();

        app(ReprocessarEtapasService::class)->reprocessar($processo);

        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }
}
```

Run: `php artisan test --compact --filter=ReprocessarPoligonoPorPontoTest`
Expected: FAIL em `test_sem_poligono_e_com_ponto_agenda_a_decisao_com_o_ator` (nada é agendado). Os outros dois passam. Se algum falhar por setup (etapa, território), ajustar só o setup do teste.

- [ ] **Step 4: Implementar no reprocesso**

Em `app/Services/Motor/ReprocessarEtapasService.php`, acrescentar os imports `use App\Jobs\DecidirFluxoExpressoJob;` e `use App\Services\Geo\PoligonoEnderecamentoService;`, e o parâmetro no construtor:

```php
        private PoligonoEnderecamentoService $poligono,
```

Trocar o bloco que completa o território e reavalia:

```php
        $decisaoAgendada = false;

        if ($paradaEm === null) {
            $this->protocolo->completarTerritorio($request);

            // Sem polígono e com ponto, a decisão espera a busca do lote pelo
            // ponto (consulta lenta): vai para a fila em vez de decidir agora
            // pelo ponto. Com polígono, segue síncrona como sempre.
            if ($this->poligono->precisaConsultarPorPonto($request) && $this->expresso->podeReavaliar($request)) {
                DecidirFluxoExpressoJob::dispatch($request->id, reavaliacao: true, atorId: $actor?->id);
                $decisaoAgendada = true;
            } else {
                $this->expresso->reavaliar($request, $actor);
            }
        }
```

Trocar o cálculo da mensagem:

```php
        $mensagem = match (true) {
            $paradaEm !== null => "Serviço de {$paradaEm->rotulo()} continua indisponível. Nova tentativa agendada.",
            $decisaoAgendada => 'Etapas reprocessadas. O polígono do lote está sendo buscado pelo ponto; o veredito sai em seguida.',
            default => 'Etapas reprocessadas e veredito reavaliado.',
        };
```

E nas `properties` da auditoria `reprocesso`, depois de `'status_do_processo' => ...`, acrescentar:

```php
                'decisao_agendada' => $decisaoAgendada,
```

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --compact --filter=ReprocessarPoligonoPorPontoTest`
Expected: PASS (3 testes).

Run: `php artisan test --compact tests/Feature/Motor tests/Feature/Expresso`
Expected: PASS (sem regressão em `ReprocessarEtapasServiceTest`, endpoint, comando e expresso).

- [ ] **Step 6: Pint e commit**

```bash
vendor/bin/pint --format agent app/Jobs/DecidirFluxoExpressoJob.php app/Services/Motor/ReprocessarEtapasService.php tests/Feature/Motor/ReprocessarPoligonoPorPontoTest.php tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php
git add app/Jobs/DecidirFluxoExpressoJob.php app/Services/Motor/ReprocessarEtapasService.php tests/Feature/Motor/ReprocessarPoligonoPorPontoTest.php tests/Feature/Expresso/DecidirFluxoExpressoJobPoligonoPorPontoTest.php
git commit -m "feat: reprocesso sem polígono agenda a decisão para buscar o lote pelo ponto"
```

---

## Self-Review

**Spec coverage**
- Ordem de fontes chave → SEMAP → ponto: Tarefas 2 e 3 (`aplicar(..., true)`).
- `aplicar($r, bool)` e `aplicarPorPonto`: Tarefa 3. Tabela de caminhos: Tarefa 4 (job e declaração REGIN ligam; portal, renovação, reprocesso, simulação e `garantirPoligonoRegin` não são tocados, e `PoligonoPorPontoCaminhosTest` fixa o portal).
- Regra do resultado (único grava; ambíguo, vazio, erro, timeout não gravam; polígono presente não consulta): testes das Tarefas 2, 3 e 4.
- Timeout de 45 s sem retry interno e CQL com `SRID=4326;POINT(lng lat)`: Tarefa 2.
- Auditoria com `fonte = ponto`: Tarefa 3. Coluna `address_polygon_source` nas três fontes: Tarefas 1 e 3.
- Interruptor `geoserver_enderecamento`: Tarefa 2 (cliente) e Tarefa 3 (serviço).
- Lacuna conhecida: a declaração REGIN não tem teste dedicado (declarada em Review Focus 6).

**Placeholders:** nenhum "TBD". O único ponto condicional é o ajuste de setup do `PoligonoPorPontoCaminhosTest` (Step 3 da Tarefa 4), limitado ao setup do teste e declarado.

**Consistência de tipos:** `PoligonoFonte` (Tarefa 1) usado em `gravar` (Tarefas 1 e 3); `consultarPorPonto(float $lat, float $lng)` (Tarefa 2) chamado com `$ponto['lat'], $ponto['lng']` (Tarefa 3); `aplicarPorPonto` e `aplicar(..., consultarPorPonto:)` (Tarefa 3) usados na Tarefa 4 com os mesmos nomes.
