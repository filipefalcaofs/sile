<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Regra geral da SEDUR (07/10/2026): quando o sistema não consegue enquadrar,
 * encaminha para análise; nunca indefere automaticamente. Vale mesmo com a
 * zona proibindo todos os grupos.
 */
class LouosNaoEnquadraNuncaIndefereTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);
        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));

        $quadro10 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10',
            'rules_version' => 'lei-9148-2016-quadro10',
        ]);

        foreach (['nR1', 'nR2', 'nR3', 'nR4', 'nRa', 'ID3'] as $grupo) {
            LouosQuadro10Permissao::factory()->create([
                'rule_version_id' => $quadro10->id,
                'zona' => 'ZR-1',
                'grupo_uso' => $grupo,
                'subgrupo' => '',
                'permissao' => Quadro10Permissao::Proibido,
                'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
            ]);
        }
    }

    private function territorio(): TerritoryResult
    {
        $vazia = ['status' => 'nao_encontrado', 'nome' => null, 'propriedades' => null, 'motivo' => null, 'versao_camada' => null];

        return new TerritoryResult(
            bairro: $vazia,
            via: $vazia + ['distancia_m' => null],
            zona: ['status' => 'identificado', 'nome' => 'ZR-1', 'propriedades' => ['NOME' => 'ZR-1'], 'motivo' => null, 'versao_camada' => 'zoneamento-louos-2026'],
            lote: $vazia,
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    /**
     * @param  array<int, bool>  $respostas
     */
    private function resultado(string $cnae, ?float $area, array $respostas): string
    {
        return app(LouosEnquadramentoService::class)->enquadrar(
            EnquadramentoInput::paraConsulta($area ?? 0.0, $cnae, $this->territorio(), $respostas),
        )->resultado();
    }

    public function test_pergunta_sem_resposta_vai_para_analise(): void
    {
        $this->assertSame(ResultadoViabilidade::Pendente->value, $this->resultado('4712-1/00', 100.0, []));
    }

    public function test_cnae_sem_regra_na_planilha_vai_para_analise(): void
    {
        $this->assertSame(ResultadoViabilidade::Pendente->value, $this->resultado('0000-0/00', 100.0, []));
    }

    public function test_cnae_inexistente_vai_para_analise_mesmo_com_respostas(): void
    {
        $this->assertSame(ResultadoViabilidade::Pendente->value, $this->resultado('9999-9/99', 100.0, [11 => true]));
    }
}
