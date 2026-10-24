<?php

namespace Tests\Feature\Geo;

use App\Enums\PoligonoFonte;
use App\Enums\ZonaFonte;
use App\Models\GeoServerLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Camadas do mapa do processo que justificam a zona e a via da decisão. Só
 * leitura: o teste confere o que vai ao mapa, nunca a regra de decisão.
 */
class MapaDecisaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOTE = [
        'type' => 'Polygon',
        'coordinates' => [[[-38.5000, -12.9800], [-38.4995, -12.9800], [-38.4995, -12.9795], [-38.5000, -12.9795], [-38.5000, -12.9800]]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['viabiliza.features.geoserver_zona' => true]);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function processo(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'address_polygon_geojson' => self::LOTE,
            'zona_codigo' => 'ZPR 3',
            'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
            'cod_log' => '2631',
        ]);
    }

    /**
     * @param  array<string, mixed>  $geometria
     * @param  array<string, mixed>  $propriedades
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private function colecao(array $geometria, array $propriedades): array
    {
        return ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => $geometria, 'properties' => $propriedades]]];
    }

    public function test_devolve_lote_zona_linha_central_e_via_da_decisao(): void
    {
        GeoServerLayer::factory()->create(['workspace' => 'louos_zpr3', 'type_name' => 'VM_L_Z_USO_ZPR_3', 'ordem' => 1]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zona_uso_zclmu', 'type_name' => 'VM_L_Z_USO_ZCLMU', 'ordem' => 2]);

        Http::fake([
            '*VM_L_Z_USO_ZPR_3*' => Http::response($this->colecao(self::LOTE, ['ZONA' => 'ZPR 3'])),
            '*VM_L_Z_USO_ZCLMU*' => Http::response($this->colecao(['type' => 'LineString', 'coordinates' => [[-38.501, -12.9812], [-38.499, -12.9812]]], ['NOME' => 'Av. ACM'])),
            '*VM_LOGRADOURO*' => Http::response(['type' => 'FeatureCollection', 'features' => [
                ['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[-38.501, -12.9805], [-38.499, -12.9805]]], 'properties' => ['CODLOG' => 2631, 'NOME_LOGRADOURO' => 'Av. ACM']],
                ['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[-38.4997, -12.979], [-38.4997, -12.981]]], 'properties' => ['CODLOG' => 9999, 'NOME_LOGRADOURO' => 'Rua Vizinha']],
            ]]),
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao(['type' => 'LineString', 'coordinates' => [[-38.501, -12.9805], [-38.499, -12.9805]]], ['CODLOG' => 2631, 'CLASSIFICACAO_VIARIA' => 'ARTERIAL I'])),
        ]);

        $processo = $this->processo();

        $resposta = $this->actingAs($this->analista(), 'gestao')
            ->getJson(route('gestao.processos.mapa-decisao', $processo))
            ->assertOk()
            ->assertJsonPath('zona.codigo', 'ZPR 3')
            ->assertJsonPath('zona.fonte', 'Maior porção do lote')
            ->assertJsonPath('via.cod_log', '2631')
            ->assertJsonPath('enderecamento.type', 'Polygon')
            ->assertJsonPath('indisponiveis', []);

        $this->assertSame('ZPR 3', $resposta->json('zonas.0.codigo'));
        $this->assertTrue($resposta->json('zonas.0.da_decisao'));
        $this->assertSame('ZCLMu', $resposta->json('linhas.0.tipo'));
        $this->assertSame('Av. ACM', $resposta->json('linhas.0.nome'));

        $vias = collect($resposta->json('vias'));
        $this->assertCount(2, $vias);
        $this->assertTrue($vias->first()['da_decisao']);
        $this->assertSame('2631', $vias->first()['cod_log']);
        $this->assertSame('ARTERIAL I', $vias->first()['classe']);
        $this->assertFalse($vias->last()['da_decisao']);
    }

    public function test_camada_sem_resposta_fica_indisponivel_sem_derrubar_o_mapa(): void
    {
        GeoServerLayer::factory()->create(['workspace' => 'louos_zpr3', 'type_name' => 'VM_L_Z_USO_ZPR_3']);

        Http::fake([
            '*VM_L_Z_USO_ZPR_3*' => Http::response('bloqueado', 403),
            '*' => Http::response(['type' => 'FeatureCollection', 'features' => []]),
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson(route('gestao.processos.mapa-decisao', $this->processo()))
            ->assertOk()
            ->assertJsonPath('indisponiveis.0', 'louos_zpr3:VM_L_Z_USO_ZPR_3')
            ->assertJsonPath('zonas', []);
    }

    public function test_processo_sem_geometria_nao_consulta_o_geoserver(): void
    {
        Http::fake();

        $processo = ViabilityRequest::factory()->create(['address_polygon_geojson' => null, 'property_polygon_geojson' => null]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson(route('gestao.processos.mapa-decisao', $processo))
            ->assertOk()
            ->assertJsonPath('lote', null)
            ->assertJsonPath('zonas', []);

        Http::assertNothingSent();
    }

    public function test_localizacao_nao_confirmada_vem_com_distancia_logradouro_e_lote_do_ponto(): void
    {
        Http::fake();
        $processo = ViabilityRequest::factory()->create(['address_polygon_geojson' => null, 'property_polygon_geojson' => null]);
        $processo->forceFill([
            'address_polygon_geojson' => self::LOTE,
            'address_polygon_source' => PoligonoFonte::Ponto,
            'localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 405.85, 'logradouro' => 'Avenida Heitor Dias'],
        ])->save();
        config(['viabiliza.features.geoserver_zona' => false]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson(route('gestao.processos.mapa-decisao', $processo))
            ->assertOk()
            ->assertJsonPath('localizacao.distancia_m', 406)
            ->assertJsonPath('localizacao.logradouro', 'Avenida Heitor Dias')
            ->assertJsonPath('localizacao.lote_do_ponto', true);
    }

    public function test_localizacao_confirmada_ou_sem_conferencia_vem_nula(): void
    {
        Http::fake();
        $semConferencia = ViabilityRequest::factory()->create(['address_polygon_geojson' => null, 'property_polygon_geojson' => null]);
        $confirmada = ViabilityRequest::factory()->create(['address_polygon_geojson' => null, 'property_polygon_geojson' => null]);
        $confirmada->forceFill(['localizacao_conferencia' => ['confirmacao' => 'confirmado', 'distancia_m' => 12.0]])->save();

        foreach ([$semConferencia, $confirmada] as $processo) {
            $this->actingAs($this->analista(), 'gestao')
                ->getJson(route('gestao.processos.mapa-decisao', $processo))
                ->assertOk()
                ->assertJsonPath('localizacao', null);
        }
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->getJson(route('gestao.processos.mapa-decisao', $this->processo()))
            ->assertForbidden();
    }
}
