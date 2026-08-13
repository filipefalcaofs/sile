<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Models\User;
use App\Services\Geo\SpatialRepository;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Backend do mapa territorial da gestão (HU-030/HU-036/HU-037): a página lista
 * as camadas com seus status (zona/lote explícitos como pendentes) e o limiar
 * de sobreposição; os endpoints identificar e validar-localizacao operam a
 * lógica real e são auditados; tudo protegido pela permissão consultar-territorio
 * (PADRÃO CROSS-GUARD do 04-03 — gate no middleware permission:, FormRequest
 * authorize()=true). A página React (gestao/territorio/index) foi entregue no
 * 04-07, então o carregamento verifica também o ->component().
 */
class TerritoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function seedCamadas(): void
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'bairro-2024',
            'feature_count' => 1,
        ]);
        GeoLayer::factory()->pendenteFonte()->create([
            'type' => GeoLayerType::Zona,
            'version' => 'zona-pendente',
        ]);
        GeoLayer::factory()->pendenteFonte()->create([
            'type' => GeoLayerType::Lote,
            'version' => 'lote-pendente',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function poligono(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.50, -12.97],
                [-38.49, -12.97],
                [-38.49, -12.96],
                [-38.50, -12.96],
                [-38.50, -12.97],
            ]],
        ];
    }

    public function test_sem_permissao_consultar_territorio_recebe_403_auditado(): void
    {
        // Acessa a gestão mas NÃO tem consultar-territorio: o gate específico
        // barra e audita (CA-04).
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/territorio')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_analista_carrega_a_pagina_com_camadas_e_limiar(): void
    {
        $this->seedCamadas();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/territorio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/territorio/index')
                ->has('camadas')
                ->where('sobreposicaoMinima', 50)
                ->has('geocodingEnabled')
                // Zona e lote comunicados como pendentes de fonte (HU-036).
                ->where('camadas', fn (Collection $camadas) => $camadas->firstWhere('type', 'zona')['status'] === 'pendente_fonte'
                    && $camadas->firstWhere('type', 'lote')['status'] === 'pendente_fonte')
            );
    }

    public function test_identificar_retorna_o_resultado_territorial_e_e_auditado(): void
    {
        // Fake do repositório espacial: identify roda sem PostGIS (consumidor
        // testado em memória, precedente 04-05). O SQL real fica no @group postgis.
        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, [
            'id' => 1,
            'properties' => ['NOME_BAIRRO' => 'Pelourinho'],
        ]);
        $this->instance(SpatialRepository::class, $fake);

        $this->seedCamadas();

        $this->actingAs($this->analista(), 'gestao')
            ->postJson('/gestao/territorio/identificar', ['lat' => -12.97, 'lng' => -38.51])
            ->assertOk()
            ->assertJsonStructure(['bairro', 'via', 'zona', 'lote', 'restricoes'])
            ->assertJsonPath('bairro.status', 'identificado')
            ->assertJsonPath('zona.status', 'indisponivel')
            ->assertJsonPath('lote.status', 'indisponivel');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'identificacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_identificar_valida_as_coordenadas(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->postJson('/gestao/territorio/identificar', ['lat' => 200, 'lng' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_validar_localizacao_comunica_lote_indisponivel_e_audita(): void
    {
        $this->seedCamadas();

        $this->actingAs($this->analista(), 'gestao')
            ->postJson('/gestao/territorio/validar-localizacao', ['polygon' => $this->poligono()])
            ->assertOk()
            ->assertJsonPath('status', 'indisponivel')
            ->assertJsonPath('limiar', 50)
            ->assertJsonPath('alerta', false);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'validacao-localizacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_validar_localizacao_exige_poligono_geojson(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->postJson('/gestao/territorio/validar-localizacao', ['polygon' => ['type' => 'Point']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['polygon.coordinates']);
    }

    public function test_visitante_nao_acessa_o_territorio(): void
    {
        $this->postJson('/gestao/territorio/identificar', ['lat' => -12.97, 'lng' => -38.51])
            ->assertStatus(401);
    }
}
