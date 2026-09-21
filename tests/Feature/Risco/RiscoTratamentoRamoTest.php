<?php

namespace Tests\Feature\Risco;

use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class RiscoTratamentoRamoTest extends TestCase
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

    public function test_risco_e_fluxo_vem_do_ramo_nao_do_cnae_achatado(): void
    {
        $result = app(RiscoClassificationService::class)->classify(new RiscoInput(
            cnaeCode: '0111-3/01',
            areaUtilizada: 800.0,
            respostasTratamento: [11 => false],
        ));

        $this->assertSame('baixo', $result->municipal['nivel']);
        $this->assertSame('expresso', $result->encaminhamento['fluxo']);
        $this->assertSame('1.01', $result->encaminhamento['tll']);
        $this->assertSame('planilha-20-08-26', $result->versoes()['risco_tratamento']);
    }

    public function test_versao_municipal_nao_exibe_a_planilha_de_tratamento_como_decreto(): void
    {
        // Relatório SEDUR 21/09 (item 08): a tela rotulava "Decreto" com a
        // versão da planilha de tratamento. A dimensão municipal guarda a
        // versão do decreto; a da planilha fica em risco_tratamento.
        $municipal = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-41758-2026',
            'rules_version' => 'decreto-41758-2026',
        ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '0111301',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $result = app(RiscoClassificationService::class)->classify(new RiscoInput(
            cnaeCode: '0111-3/01',
            areaUtilizada: 800.0,
            respostasTratamento: [11 => false],
        ));

        $this->assertSame('decreto-41758-2026', $result->municipal['versao_regras']);
        $this->assertSame('decreto-41758-2026', $result->versoes()['municipal']);
        $this->assertSame('planilha-20-08-26', $result->versoes()['risco_tratamento']);
    }

    public function test_motor_de_risco_nao_instancia_o_enquadramento_louos(): void
    {
        $tipos = array_map(
            static fn (\ReflectionParameter $param): ?string => $param->getType() instanceof \ReflectionNamedType
                ? $param->getType()->getName()
                : null,
            (new ReflectionClass(RiscoClassificationService::class))->getConstructor()?->getParameters() ?? [],
        );

        $this->assertNotContains(LouosEnquadramentoService::class, $tipos);
    }
}
