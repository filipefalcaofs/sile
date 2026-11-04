<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
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
    /**
     * @param  array<string, mixed>|null  $territorio  Território gravado no snapshot da pré-análise.
     */
    private function opcoesDoCnae(string $risco, array $extras = [], ?float $area = 100.0, ?array $territorio = null): array
    {
        $versao = $this->seedTratamentoPlanilha();
        TratamentoEnquadramento::query()
            ->where('rule_version_id', $versao->id)
            ->where('cnae', '4712-1/00')
            ->update(['risco' => $risco, ...$extras]);

        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000321',
            'protocoled_at' => now(),
            'used_area_m2' => $area,
        ]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [['cnae' => '4712100', 'cnae_formatado' => '4712-1/00', 'status_escolhido' => null]],
            'engine_snapshot' => $territorio === null ? null : [
                'por_cnae' => [['cnae' => '4712100', 'consulta' => ['territorio' => $territorio]]],
            ],
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

    public function test_opcao_traz_o_subgrupo_e_as_faixas_da_planilha(): void
    {
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO', ['subcategoria' => 'nR1-08', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12']);

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertSame('nR1-08', $opcao['subgrupo']);
            $this->assertSame([['codigo' => 'nR2-12', 'limite' => 'acima de 1.250 m²']], $opcao['subgrupo_faixas']);
        }
    }

    public function test_opcao_traz_o_subgrupo_que_a_area_do_processo_alcanca(): void
    {
        $faixas = ['subcategoria' => 'nR1-08', 'grupo' => 'nR1', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12', 'enquadramento3' => null, 'acima_m2' => null];
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO', $faixas, 100);

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertSame('nR1-08', $opcao['subgrupo_area']);
            $this->assertSame('nR1', $opcao['grupo_area']);
            $this->assertSame('baixo', $opcao['risco_nivel']);
        }
    }

    public function test_area_acima_do_limite_troca_o_subgrupo_e_eleva_o_risco(): void
    {
        $faixas = ['subcategoria' => 'nR1-08', 'grupo' => 'nR1', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12', 'enquadramento3' => null, 'acima_m2' => null];
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO', $faixas, 2000);

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertSame('nR2-12', $opcao['subgrupo_area']);
            $this->assertSame('nR2', $opcao['grupo_area']);
            $this->assertSame('medio', $opcao['risco_nivel']);
            $this->assertSame('Médio Risco', $opcao['risco_label']);
        }
    }

    public function test_area_na_terceira_faixa_vira_alto_risco(): void
    {
        $faixas = ['subcategoria' => 'nR1-08', 'grupo' => 'nR1', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12', 'enquadramento3' => 'nR3-04', 'acima_m2' => 5000];
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO', $faixas, 6000);

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertSame('nR3-04', $opcao['subgrupo_area']);
            $this->assertSame('nR3', $opcao['grupo_area']);
            $this->assertSame('alto', $opcao['risco_nivel']);
            $this->assertSame('analise', $opcao['fluxo']);
        }
    }

    public function test_sem_area_e_com_faixa_o_subgrupo_fica_em_aberto(): void
    {
        $faixas = ['subcategoria' => 'nR1-08', 'grupo' => 'nR1', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12'];
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO', $faixas, null);

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertNull($opcao['subgrupo_area']);
            $this->assertSame('nR1-08', $opcao['subgrupo']);
        }
    }

    private function permissaoNaZona(string $zona, string $grupo, Quadro10Permissao $permissao): void
    {
        $versao = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $versao->id,
            'zona' => $zona,
            'grupo_uso' => $grupo,
            'subgrupo' => '',
            'permissao' => $permissao,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function territorioNaZona(string $zona): array
    {
        return [
            'bairro' => ['status' => 'indisponivel'],
            'via' => ['status' => 'indisponivel', 'motivo' => 'Via não identificada.'],
            'zona' => ['status' => 'identificado', 'nome' => $zona],
            'lote' => ['status' => 'indisponivel'],
            'restricoes' => ['status' => 'indisponivel'],
        ];
    }

    public function test_cada_opcao_traz_o_quadro_10_do_seu_enquadramento_na_zona(): void
    {
        $this->permissaoNaZona('ZR-1', 'nR1', Quadro10Permissao::Permitido);
        $this->permissaoNaZona('ZR-1', 'nR2', Quadro10Permissao::Proibido);
        $faixas = ['subcategoria' => 'nR1-08', 'grupo' => 'nR1', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12', 'enquadramento3' => null, 'acima_m2' => null];

        $pequena = $this->opcoesDoCnae('BAIXO RISCO', $faixas, 100, $this->territorioNaZona('ZR-1'));
        $this->assertNotEmpty($pequena);
        foreach ($pequena as $opcao) {
            $this->assertSame('quadro10', $opcao['quadros'][0]['key']);
            $this->assertSame('permitido', $opcao['quadros'][0]['estado']);
            $this->assertStringContainsString('NR1-08 na zona ZR-1', $opcao['quadros'][0]['detalhe']);
            $this->assertSame('quadro11a', $opcao['quadros'][1]['key']);
            $this->assertSame('pendente', $opcao['quadros'][1]['estado']);
        }
    }

    public function test_area_que_muda_o_grupo_muda_a_permissao_do_quadro_10(): void
    {
        $this->permissaoNaZona('ZR-1', 'nR1', Quadro10Permissao::Permitido);
        $this->permissaoNaZona('ZR-1', 'nR2', Quadro10Permissao::Proibido);
        $faixas = ['subcategoria' => 'nR1-08', 'grupo' => 'nR1', 'ate_m2' => 1250, 'enquadramento2' => 'nR2-12', 'enquadramento3' => null, 'acima_m2' => null];

        $grande = $this->opcoesDoCnae('BAIXO RISCO', $faixas, 2000, $this->territorioNaZona('ZR-1'));
        $this->assertNotEmpty($grande);
        foreach ($grande as $opcao) {
            $this->assertSame('nao_permitido', $opcao['quadros'][0]['estado']);
        }
    }

    public function test_sem_territorio_gravado_a_opcao_nao_traz_quadros(): void
    {
        $opcoes = $this->opcoesDoCnae('BAIXO RISCO');

        $this->assertNotEmpty($opcoes);
        foreach ($opcoes as $opcao) {
            $this->assertNull($opcao['quadros']);
        }
    }

    public function test_ficha_informa_o_enquadramento_que_o_motor_sugeriu(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000322',
            'protocoled_at' => now(),
        ]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [['cnae' => '4712100', 'cnae_formatado' => '4712-1/00', 'status_escolhido' => null, 'codigo_louos' => '9.9.9', 'codigo_tll' => '7.07']],
            'engine_snapshot' => [
                'por_cnae' => [[
                    'cnae' => '4712100',
                    'consulta' => [
                        'enquadramento' => [
                            'enquadramento' => ['status' => 'identificado', 'grupo' => 'nR1', 'subgrupo' => 'nR1-08', 'codigo_louos' => '1.2.3'],
                        ],
                        'risco' => ['encaminhamento' => ['tll' => '2.02']],
                    ],
                ]],
            ],
        ]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.per_cnae.0.codigo_louos', '9.9.9')
                ->where('ficha.per_cnae.0.enquadramento_motor', [
                    'codigo_louos' => '1.2.3',
                    'codigo_tll' => '2.02',
                    'grupo' => 'nR1',
                    'subgrupo' => 'nR1-08',
                ]));
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
