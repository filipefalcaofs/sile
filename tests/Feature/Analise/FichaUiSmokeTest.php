<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\StandardText;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Smoke do contrato página↔props da ficha de análise (HU-135): com
 * analisar-processos, a rota gestao.processos.ficha.show renderiza o componente
 * Inertia gestao/ficha-analise/show com as props essenciais que a tela consome
 * (ficha com revisão/per_cnae, textos-padrão ativos, debounce do autosave,
 * processo e a localização real do imóvel para o mini-mapa). As asserções de
 * comportamento (autosave/finalizar/diff/precedentes) já estão em 10-06/10-09;
 * aqui garante-se que a página casa com o backend.
 */
class FichaUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_ficha_renderiza_componente_inertia_com_as_props_essenciais(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000123',
            'protocoled_at' => now(),
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
            'property_polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-38.50, -12.97],
                    [-38.49, -12.97],
                    [-38.49, -12.96],
                    [-38.50, -12.96],
                    [-38.50, -12.97],
                ]],
            ],
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        StandardText::factory()->create([
            'category' => 'deferimento',
            'content' => 'Parecer favorável com fundamentação na LOUOS.',
            'active' => true,
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('ficha.revision', 1)
                ->where('ficha.status', 'rascunho')
                ->where('ficha.editavel', true)
                ->has('ficha.per_cnae')
                ->has('textosPadrao', 1)
                ->has('autosaveDebounceMs')
                ->where('processo.id', $processo->id)
                ->where('processo.protocol_number', 'VIA-'.now()->year.'-000123')
                ->has('localizacao')
                ->where('localizacao.endereco', 'Rua das Flores, 100, Centro')
                ->where('localizacao.logradouro', 'Rua das Flores')
                ->where('localizacao.poligono.type', 'Polygon')
                ->has('cadastroImobiliario')
                ->has('dadosTvl'));
    }

    public function test_ficha_sem_poligono_entrega_localizacao_nula_sem_inventar(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000124',
            'protocoled_at' => now(),
            'property_polygon_geojson' => null,
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('localizacao.poligono', null));
    }

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
}
