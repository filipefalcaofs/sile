<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Resposta da SEDUR de 07/10/2026: enquadramento sinalizado como CNLU só vai
 * para a análise quando a zona permite o uso; se o Quadro 10 proíbe, o veto da
 * zona prevalece e o uso é indeferido.
 */
class LouosCnluQuadro10Test extends TestCase
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
    }

    private function permissao(string $zona, Quadro10Permissao $permissao): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => $zona,
            'grupo_uso' => 'nRa',
            'subgrupo' => '',
            'permissao' => $permissao,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);
    }

    private function territorio(?string $zona): TerritoryResult
    {
        $vazia = ['status' => 'nao_encontrado', 'nome' => null, 'propriedades' => null, 'motivo' => null, 'versao_camada' => null];

        return new TerritoryResult(
            bairro: $vazia,
            via: $vazia + ['distancia_m' => null],
            zona: $zona === null
                ? $vazia
                : ['status' => 'identificado', 'nome' => $zona, 'propriedades' => ['NOME' => $zona], 'motivo' => null, 'versao_camada' => 'zoneamento-louos-2026'],
            lote: $vazia,
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    private function enquadrar(?string $zona): EnquadramentoResult
    {
        return app(LouosEnquadramentoService::class)->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '0111-3/01',
            $this->territorio($zona),
            [11 => true],
        ));
    }

    public function test_zona_que_proibe_indefere_mesmo_com_sinalizacao_cnlu(): void
    {
        $this->permissao('ZR-1', Quadro10Permissao::Proibido);

        $result = $this->enquadrar('ZR-1');

        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $result->resultado());
    }

    public function test_zona_que_permite_manda_para_a_analise_da_cnlu(): void
    {
        $this->permissao('ZR-1', Quadro10Permissao::Permitido);

        $result = $this->enquadrar('ZR-1');

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertStringContainsStringIgnoringCase('cnlu', (string) $result->consolidado['motivo']);
    }

    public function test_sem_zona_identificada_manda_para_a_analise(): void
    {
        $result = $this->enquadrar(null);

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
    }
}
