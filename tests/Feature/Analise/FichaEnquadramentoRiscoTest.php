<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\TratamentoEnquadramento;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Cada opção de enquadramento (LOUOS × TLL) da ficha informa o risco da
 * atividade e o encaminhamento dele, para a tela atualizar os dois quando o
 * analista troca o enquadramento selecionado.
 */
class FichaEnquadramentoRiscoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function opcoesDoCnae(string $risco): array
    {
        $versao = $this->seedTratamentoPlanilha();
        TratamentoEnquadramento::query()
            ->where('rule_version_id', $versao->id)
            ->where('cnae', '4712-1/00')
            ->update(['risco' => $risco]);

        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000321',
            'protocoled_at' => now(),
        ]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [['cnae' => '4712100', 'cnae_formatado' => '4712-1/00', 'status_escolhido' => null]],
        ]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $opcoes = [];

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$opcoes): void {
                $opcoes = $page->toArray()['props']['enquadramentoOpcoes']['4712100'];
            });

        return $opcoes;
    }

    public function test_opcao_de_baixo_risco_segue_o_encaminhamento_do_mapa_de_risco(): void
    {
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO');

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertSame('baixo', $opcao['risco_nivel']);
            $this->assertSame('Baixo Risco', $opcao['risco_label']);
            $this->assertSame('expresso', $opcao['fluxo']);
        }
    }

    public function test_opcao_de_alto_risco_vai_para_analise(): void
    {
        $opcoes = $this->opcoesDoCnae('ALTO RISCO');

        foreach ($opcoes as $opcao) {
            $this->assertSame('alto', $opcao['risco_nivel']);
            $this->assertSame('Alto Risco', $opcao['risco_label']);
            $this->assertSame('analise', $opcao['fluxo']);
        }
    }

    public function test_opcao_de_medio_risco_tem_rotulo_proprio(): void
    {
        $opcoes = $this->opcoesDoCnae('MÉDIO RISCO');

        foreach ($opcoes as $opcao) {
            $this->assertSame('medio', $opcao['risco_nivel']);
            $this->assertSame('Médio Risco', $opcao['risco_label']);
        }
    }

    public function test_cnlu_nao_e_classificado_como_baixo_e_vai_para_analise(): void
    {
        $opcoes = $this->opcoesDoCnae('CNLU');

        foreach ($opcoes as $opcao) {
            $this->assertNull($opcao['risco_nivel']);
            $this->assertNull($opcao['risco_label']);
            $this->assertSame('analise', $opcao['fluxo']);
        }
    }
}
