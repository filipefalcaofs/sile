<?php

namespace Tests\Feature\TreinoIa;

use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportarTesteCegoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $campos = new PropertyCadastroCampos(...array_merge(PropertyCadastroCampos::vazios()->toArray(), [
                    'area_terreno_m2' => '36',
                    'sub_unidade' => $inscricao === '200' ? '0421' : null,
                    'contribuinte' => 'Nome que não pode sair',
                    'cpf_cnpj' => '00000000000',
                ]));

                return new PropertyRegistryResult(latitude: -12.99, longitude: -38.46, inscricao: $inscricao, cadastro: $campos);
            }
        });
    }

    public function test_exporta_entrada_sem_poligono_e_referencia_separada(): void
    {
        $processo = $this->processo('100');

        $this->artisan('treino-ia:exportar-teste-cego', ['--semente' => 7, '--quantidade' => 5])->assertSuccessful();

        $entrada = json_decode(Storage::disk('local')->get('treino-ia/cego/entrada.json'), true);
        $referencia = json_decode(Storage::disk('local')->get('treino-ia/cego/referencia.json'), true);
        $this->assertCount(1, $entrada);
        $this->assertSame((string) $processo->id, (string) $entrada[0]['id']);
        $this->assertSame([-12.99, -38.46], $entrada[0]['ponto']);
        $this->assertArrayNotHasKey('poligono', $entrada[0]);
        $this->assertStringNotContainsString('coordinates', Storage::disk('local')->get('treino-ia/cego/entrada.json'));
        $this->assertSame($this->lote(), $referencia[(string) $processo->id]);
        $this->assertEquals(36.0, $entrada[0]['area_cadastro']);
    }

    public function test_nao_grava_dado_pessoal_do_cadastro(): void
    {
        $this->processo('100');

        $this->artisan('treino-ia:exportar-teste-cego', ['--semente' => 7])->assertSuccessful();

        $texto = Storage::disk('local')->get('treino-ia/cego/entrada.json');
        $this->assertStringNotContainsString('Nome que não pode sair', $texto);
        $this->assertStringNotContainsString('00000000000', $texto);
    }

    public function test_mesma_semente_mesma_amostra(): void
    {
        foreach (range(1, 8) as $i) {
            $this->processo((string) (100 + $i));
        }

        $this->artisan('treino-ia:exportar-teste-cego', ['--semente' => 3, '--quantidade' => 4])->assertSuccessful();
        $primeira = array_column(json_decode(Storage::disk('local')->get('treino-ia/cego/entrada.json'), true), 'id');
        $this->artisan('treino-ia:exportar-teste-cego', ['--semente' => 3, '--quantidade' => 4])->assertSuccessful();
        $segunda = array_column(json_decode(Storage::disk('local')->get('treino-ia/cego/entrada.json'), true), 'id');

        $this->assertCount(4, $primeira);
        $this->assertSame($primeira, $segunda);
    }

    public function test_marca_unidade_pela_sub_unidade_ou_pelo_complemento(): void
    {
        $porSubUnidade = $this->processo('200');
        $porComplemento = $this->processo('300', 'LOTES 1 E 2 EDIF:MAXCENTER ;SALA:0421');
        $casa = $this->processo('400');

        $this->artisan('treino-ia:exportar-teste-cego', ['--semente' => 1])->assertSuccessful();

        $unidade = collect(json_decode(Storage::disk('local')->get('treino-ia/cego/entrada.json'), true))->pluck('unidade', 'id');
        $this->assertTrue($unidade[(string) $porSubUnidade->id]);
        $this->assertTrue($unidade[(string) $porComplemento->id]);
        $this->assertFalse($unidade[(string) $casa->id]);
    }

    public function test_ignora_processo_sem_lote_do_codlog_ou_com_desenho(): void
    {
        $this->processo('100', fonte: PoligonoFonte::Overture);
        $desenhado = $this->processo('101');
        $desenhado->forceFill(['property_polygon_geojson' => $this->lote()])->save();

        $this->artisan('treino-ia:exportar-teste-cego', ['--semente' => 1])->assertSuccessful();

        $this->assertSame([], json_decode(Storage::disk('local')->get('treino-ia/cego/entrada.json'), true));
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(): array
    {
        return ['type' => 'MultiPolygon', 'coordinates' => [[[
            [-38.4601, -12.9901], [-38.4599, -12.9901], [-38.4599, -12.9899], [-38.4601, -12.9899], [-38.4601, -12.9901],
        ]]]];
    }

    private function processo(string $inscricao, ?string $complemento = null, PoligonoFonte $fonte = PoligonoFonte::Chave): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);
        $processo->forceFill([
            'protocol_number' => 'VIA-'.$processo->id,
            'property_registration' => $inscricao,
            'cod_log' => '2631',
            'address_number' => '846',
            'address_complement' => $complemento,
            'address_polygon_geojson' => $this->lote(),
            'address_polygon_source' => $fonte,
            'property_point_lat' => -12.99,
            'property_point_lng' => -38.46,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return $processo->fresh();
    }
}
