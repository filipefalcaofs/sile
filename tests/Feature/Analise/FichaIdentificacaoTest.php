<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A ficha de análise identifica o processo (BAP e serviço), descreve cada CNAE
 * ao lado do código e mostra o tipo do imóvel junto da localização.
 */
class FichaIdentificacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function abrirFicha(ViabilityRequest $processo): TestResponse
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        return $this->actingAs($analista, 'gestao')->get("/gestao/processos/{$processo->id}/ficha");
    }

    private function processo(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000777',
            'protocoled_at' => now(),
            'external_reference' => 'BAP2601234567',
            'tipo_imovel' => 'Edifício comercial',
        ]);
        ViabilityRequestEvento::factory()->create([
            'viability_request_id' => $processo->id,
            'codigo' => '101',
            'nome' => 'Alteração de atividades econômicas',
        ]);

        return $processo;
    }

    public function test_processo_da_ficha_traz_o_bap_e_o_servico(): void
    {
        $processo = $this->processo();
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);

        $this->abrirFicha($processo)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processo.bap', 'BAP2601234567')
                ->where('processo.servicos', ['Alteração de atividades econômicas']));
    }

    public function test_servico_cai_no_tipo_interno_quando_nao_ha_evento_da_juceb(): void
    {
        $processo = $this->processo();
        $processo->eventos()->delete();
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);

        $this->abrirFicha($processo)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processo.servicos', fn ($servicos): bool => count($servicos) <= 1));
    }

    public function test_cada_cnae_da_ficha_traz_a_descricao(): void
    {
        $processo = $this->processo();
        Cnae::factory()->create(['code' => '8211300', 'description' => 'Serviços combinados de escritório e apoio administrativo']);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [['cnae' => '8211300', 'cnae_formatado' => '8211-3/00', 'status_escolhido' => null]],
        ]);

        $this->abrirFicha($processo)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.per_cnae.0.descricao', 'Serviços combinados de escritório e apoio administrativo'));
    }

    public function test_cnae_sem_cadastro_fica_sem_descricao_e_nao_quebra(): void
    {
        $processo = $this->processo();
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'per_cnae' => [['cnae' => '9999999', 'cnae_formatado' => '9999-9/99', 'status_escolhido' => null]],
        ]);

        $this->abrirFicha($processo)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('ficha.per_cnae.0.descricao', null));
    }

    public function test_localizacao_traz_o_tipo_do_imovel(): void
    {
        $processo = $this->processo();
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);

        $this->abrirFicha($processo)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('localizacao.tipo_imovel', 'Edifício comercial'));
    }
}
