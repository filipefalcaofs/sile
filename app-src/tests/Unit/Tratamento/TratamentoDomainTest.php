<?php

namespace Tests\Unit\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TratamentoDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_risco_tratamento_e_dominio_sensivel(): void
    {
        $this->assertTrue(RuleDomain::RiscoTratamento->isSensitive());
        $this->assertSame('risco_tratamento', RuleDomain::RiscoTratamento->value);
        $this->assertSame('Planilha de regras de tratamento (20.08.26)', RuleDomain::RiscoTratamento->label());
    }

    public function test_modelos_treatment_persistem_na_versao(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        $pergunta = TratamentoPergunta::query()->create([
            'rule_version_id' => $versao->id,
            'numero' => 11,
            'texto' => 'A atividade será desenvolvida no local?',
        ]);

        $regra = TratamentoRegra::query()->create([
            'rule_version_id' => $versao->id,
            'numero' => 5,
            'tratamento' => 'Fluxo Expresso — P11 Não e área ≤ 1250',
        ]);

        $enquadramento = TratamentoEnquadramento::query()->create([
            'rule_version_id' => $versao->id,
            'cnae' => '0111-3/01',
            'codigo_louos' => '07.12.13',
            'subcategoria' => 'nR1-12',
            'grupo' => 'nR1',
            'risco' => 'baixo',
        ]);

        $binding = TratamentoCnaeBinding::query()->create([
            'rule_version_id' => $versao->id,
            'cnae' => '0111-3/01',
            'regra' => 5,
            'codigo_louos' => '07.12.13',
            'perguntas' => [11],
        ]);

        $this->assertTrue($pergunta->exists);
        $this->assertTrue($regra->exists);
        $this->assertTrue($enquadramento->exists);
        $this->assertTrue($binding->exists);
        $this->assertSame([11], $binding->perguntas);
    }
}
