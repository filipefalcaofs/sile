<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Varredura somente leitura das decisões expressas emitidas antes da 0.64.0:
 * aponta deferimentos que o motor corrigido não daria (Quadro 11-A com CNLU,
 * observação (a)/(b) do Quadro 10 fora da allow-list) e conta as citações
 * legais antigas gravadas na fundamentação.
 */
class AuditarDesviosExpressoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string CNLU = 'Objeto de análise particularizada pela CNLU';

    private int $sequencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TerritorialRuleSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, mixed>  $quadro11a
     * @param  array<string, mixed>  $atributosDecisao
     * @param  array<string, mixed>  $atributosSolicitacao
     */
    private function decisaoExpressa(
        array $quadro10 = ['status' => 'identificado', 'permissao' => 'permitido', 'condicionante_ref' => null],
        array $quadro11a = ['status' => 'identificado', 'classe_via' => 'VL', 'condicoes' => ['Sim']],
        array $atributosDecisao = [],
        array $atributosSolicitacao = [],
    ): ViabilityDecision {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->sequencia, 6, '0', STR_PAD_LEFT),
        ]);

        if ($atributosSolicitacao !== []) {
            $solicitacao->forceFill($atributosSolicitacao)->save();
        }

        return ViabilityDecision::factory()->create([
            'viability_request_id' => $solicitacao->id,
            'tvl_product_number' => 'TVL-2026-'.str_pad((string) $this->sequencia, 6, '0', STR_PAD_LEFT),
            'decision_trace' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'origem' => 'motor',
                    'passos' => [
                        ['passo' => 'louos.quadro10', 'resultado_parcial' => $quadro10],
                        ['passo' => 'louos.quadro11a', 'resultado_parcial' => $quadro11a],
                    ],
                ],
            ],
            ...$atributosDecisao,
        ]);
    }

    private function saida(): string
    {
        $this->assertSame(0, Artisan::call('expresso:auditar-desvios-064'));

        return Artisan::output();
    }

    public function test_aponta_deferimento_com_quadro_11a_encaminhado_a_cnlu(): void
    {
        $decisao = $this->decisaoExpressa(
            quadro11a: ['status' => 'identificado', 'classe_via' => 'VC I', 'condicoes' => [self::CNLU]],
        );

        $saida = $this->saida();

        $this->assertStringContainsString($decisao->viabilityRequest->protocol_number, $saida);
        $this->assertStringContainsString('Quadro 11-A encaminha à CNLU', $saida);
    }

    public function test_aponta_observacao_a_fora_da_allow_list_como_provavel_erro(): void
    {
        $decisao = $this->decisaoExpressa(
            quadro10: [
                'status' => 'identificado',
                'permissao' => 'permitido_condicionado',
                'condicionante_ref' => 'Observação (a) do Quadro 10 da Lei nº 9.148/2016',
            ],
            atributosSolicitacao: ['cod_log' => '9999', 'bairro_oficial' => 'Pituba'],
        );

        $saida = $this->saida();

        $this->assertStringContainsString($decisao->viabilityRequest->protocol_number, $saida);
        $this->assertStringContainsString('fora da allow-list', $saida);
    }

    public function test_observacao_sem_codlog_nem_bairro_pede_conferencia_manual(): void
    {
        $decisao = $this->decisaoExpressa(
            quadro10: [
                'status' => 'identificado',
                'permissao' => 'permitido_condicionado',
                'condicionante_ref' => 'Observação (b) do Quadro 10 da Lei nº 9.148/2016',
            ],
            atributosSolicitacao: ['cod_log' => null, 'bairro_oficial' => null],
        );

        $saida = $this->saida();

        $this->assertStringContainsString($decisao->viabilityRequest->protocol_number, $saida);
        $this->assertStringContainsString('conferir à mão', $saida);
    }

    public function test_observacao_dentro_da_allow_list_nao_e_apontada(): void
    {
        $decisao = $this->decisaoExpressa(
            quadro10: [
                'status' => 'identificado',
                'permissao' => 'permitido_condicionado',
                'condicionante_ref' => 'Observação (a) do Quadro 10 da Lei nº 9.148/2016',
            ],
            atributosSolicitacao: ['cod_log' => '578', 'bairro_oficial' => null],
        );

        $this->artisan('expresso:auditar-desvios-064')
            ->doesntExpectOutputToContain($decisao->viabilityRequest->protocol_number)
            ->expectsOutputToContain('Nenhuma decisão expressa com desvio')
            ->assertExitCode(0);
    }

    public function test_ignora_decisao_normal_indeferida_e_da_analise_tecnica(): void
    {
        $normal = $this->decisaoExpressa();
        $indeferida = $this->decisaoExpressa(
            quadro11a: ['status' => 'identificado', 'classe_via' => 'VC I', 'condicoes' => [self::CNLU]],
            atributosDecisao: ['outcome' => DecisionOutcome::Indeferida, 'reason' => 'Uso vedado.', 'tvl_product_number' => null],
        );
        $analise = $this->decisaoExpressa(
            quadro11a: ['status' => 'identificado', 'classe_via' => 'VC I', 'condicoes' => [self::CNLU]],
            atributosDecisao: ['flow' => 'analise_tecnica'],
        );

        $this->artisan('expresso:auditar-desvios-064')
            ->doesntExpectOutputToContain($normal->viabilityRequest->protocol_number)
            ->doesntExpectOutputToContain($indeferida->viabilityRequest->protocol_number)
            ->doesntExpectOutputToContain($analise->viabilityRequest->protocol_number)
            ->assertExitCode(0);
    }

    public function test_conta_decisao_sem_rastro(): void
    {
        $this->decisaoExpressa(atributosDecisao: ['decision_trace' => null]);

        $this->artisan('expresso:auditar-desvios-064')
            ->expectsOutputToContain('Sem rastro do motor: 1')
            ->assertExitCode(0);
    }

    public function test_conta_citacoes_antigas_na_fundamentacao(): void
    {
        $this->decisaoExpressa(atributosDecisao: ['fundamentacao' => [
            'Lei nº 9.148/2016 — Quadro 10',
            'Decreto Municipal nº 41.758/2026',
        ]]);
        $this->decisaoExpressa(atributosDecisao: ['fundamentacao' => [
            'art. 116, I, e Quadro 10 do Anexo 01 da Lei nº 9.148/2016',
            'Decreto Municipal nº 32.636/2020, com os Anexos na redação do Decreto Municipal nº 41.758/2026',
        ]]);

        $this->artisan('expresso:auditar-desvios-064')
            ->expectsOutputToContain('Lei nº 9.148/2016 — Quadro 10: 1')
            ->expectsOutputToContain('Decreto Municipal nº 41.758/2026 sem o Decreto 32.636/2020: 1')
            ->assertExitCode(0);
    }

    public function test_grava_csv_e_nao_altera_registros(): void
    {
        $decisao = $this->decisaoExpressa(
            quadro11a: ['status' => 'identificado', 'classe_via' => 'VC I', 'condicoes' => [self::CNLU]],
        );
        $antes = $decisao->fresh()->getAttributes();
        $caminho = sys_get_temp_dir().'/auditoria-desvios-'.uniqid().'.csv';

        $this->artisan('expresso:auditar-desvios-064', ['--csv' => $caminho])
            ->assertExitCode(0);

        $this->assertFileExists($caminho);
        $conteudo = (string) file_get_contents($caminho);
        $this->assertStringContainsString($decisao->viabilityRequest->protocol_number, $conteudo);
        $this->assertStringContainsString('Quadro 11-A encaminha à CNLU', $conteudo);
        $this->assertSame($antes, $decisao->fresh()->getAttributes());

        unlink($caminho);
    }
}
