<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
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
        app(PlanilhaTratamentoImporter::class)->complementar($versao, database_path('data/regras-20-08-26/planilha-regras.xlsx'));
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

    public function test_pergunta_dependente_traz_de_qual_resposta_ela_depende(): void
    {
        $perguntas = $this->porNumero('4682-6/00');

        $this->assertSame(['pergunta' => 25, 'sim' => true], $perguntas[26]['depende']);
        $this->assertSame(['pergunta' => 26, 'sim' => false], $perguntas[29]['depende']);
        $this->assertNull($perguntas[25]['depende']);
    }

    public function test_pergunta_1_traz_as_opcoes_da_subdivisao_do_cnae(): void
    {
        $p1 = $this->porNumero('9609-2/99')[1];

        $this->assertSame('multipla', $p1['tipo']);
        $this->assertCount(17, $p1['opcoes']);
        $this->assertSame('1', $p1['opcoes'][0]['rotulo']);
        $this->assertSame('Atividades de astrólogos, videntes e similares', $p1['opcoes'][0]['texto']);
        $this->assertSame('Serviços pessoais por telefone, disque sexo, disque amizade e similares', $p1['opcoes'][16]['texto']);
    }

    public function test_pergunta_1_de_organizacao_religiosa_e_de_opcao_unica(): void
    {
        $p1 = $this->porNumero('9491-0/00')[1];

        $this->assertSame('opcao_unica', $p1['tipo']);
        $this->assertSame(['1', '2', '3', '4'], array_column($p1['opcoes'], 'rotulo'));
        $this->assertSame('Culto religioso', $p1['opcoes'][0]['texto']);
    }

    public function test_cnae_sem_pergunta_1_segue_com_a_pergunta_sim_nao(): void
    {
        $perguntas = $this->porNumero('0111-3/01');

        $this->assertArrayNotHasKey(1, $perguntas);
        $this->assertSame('sim_nao', $perguntas[11]['tipo']);
    }
}
