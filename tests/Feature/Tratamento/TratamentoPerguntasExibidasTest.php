<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TratamentoPerguntasExibidasTest extends TestCase
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function porNumero(string $cnae): array
    {
        $porNumero = [];

        foreach (app(TratamentoRamoResolver::class)->perguntasExibidas($cnae) as $pergunta) {
            $porNumero[$pergunta['numero']] = $pergunta;
        }

        return $porNumero;
    }

    public function test_pergunta_sim_nao_traz_o_tipo(): void
    {
        $this->assertSame('sim_nao', $this->porNumero('0111-3/01')[11]['tipo']);
    }

    public function test_pergunta_de_opcoes_traz_tipo_e_opcoes(): void
    {
        $p14 = $this->porNumero('5223-1/00')[14];

        $this->assertSame('opcao_unica', $p14['tipo']);
        $this->assertCount(3, $p14['opcoes']);
    }

    public function test_pergunta_de_letras_da_regra_53(): void
    {
        $p27 = $this->porNumero('4682-6/00')[27];

        $this->assertSame('opcao_unica', $p27['tipo']);
        $this->assertSame(['A', 'B'], array_column($p27['opcoes'], 'rotulo'));
    }
}
